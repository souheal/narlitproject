<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next, string $operation): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            return response()->json(['message' => 'An idempotency key is required.'], 422);
        }

        if (! Str::isUuid($key)) {
            return response()->json(['message' => 'The idempotency key must be a valid UUID.'], 422);
        }

        $fingerprint = $this->fingerprint($request, $operation, (int) $user->id);
        $pathHash = hash('sha256', $request->path());
        $resource = $this->resourceFromRoute($request);
        $now = now();
        $expiresAt = $now->copy()->addHours((int) config('auth.idempotency_ttl_hours', 24));

        $reservation = DB::transaction(function () use ($key, $user, $operation, $request, $fingerprint, $pathHash, $resource, $now, $expiresAt): array {
            $inserted = DB::table('idempotency_keys')->insertOrIgnore([
                'key' => $key,
                'actor_id' => $user->id,
                'operation' => $operation,
                'request_method' => $request->method(),
                'request_path_hash' => $pathHash,
                'request_fingerprint' => $fingerprint,
                'status' => 'processing',
                'resource_type' => $resource['type'],
                'resource_id' => $resource['id'],
                'locked_at' => $now,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = DB::table('idempotency_keys')
                ->where('actor_id', $user->id)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                return ['action' => 'processing'];
            }

            if ($record->operation !== $operation || $record->request_fingerprint !== $fingerprint) {
                return ['action' => 'conflict'];
            }

            if ($inserted === 0 && $record->status === 'completed') {
                return [
                    'action' => 'replay',
                    'status' => (int) ($record->response_status ?? 200),
                    'body' => $this->decodeStoredBody($record->response_body),
                ];
            }

            if ($inserted === 0 && $record->status === 'processing') {
                return ['action' => 'processing'];
            }

            if ($inserted === 0 && $record->status === 'failed') {
                DB::table('idempotency_keys')->where('id', $record->id)->update([
                    'status' => 'processing',
                    'locked_at' => $now,
                    'failed_at' => null,
                    'updated_at' => $now,
                ]);
            }

            return ['action' => 'execute', 'id' => (int) $record->id];
        }, 3);

        if ($reservation['action'] === 'conflict') {
            return response()->json(['message' => 'This idempotency key was already used for a different request.'], 409);
        }

        if ($reservation['action'] === 'processing') {
            return response()->json(['message' => 'This request is already being processed.'], 409);
        }

        if ($reservation['action'] === 'replay') {
            return response()
                ->json($reservation['body'], $reservation['status'])
                ->header('Idempotent-Replay', 'true');
        }

        $recordId = (int) $reservation['id'];
        $request->attributes->set('idempotency_record_id', $recordId);
        $request->attributes->set('stripe_idempotency_key', $this->stripeKey($operation, $recordId));

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            DB::table('idempotency_keys')->where('id', $recordId)->update([
                'status' => 'failed',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

            throw $exception;
        }

        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 400) {
            DB::table('idempotency_keys')->where('id', $recordId)->update([
                'status' => 'completed',
                'response_status' => $status,
                'response_body' => $this->responseBody($response),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($status >= 500) {
            DB::table('idempotency_keys')->where('id', $recordId)->update([
                'status' => 'failed',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('idempotency_keys')->where('id', $recordId)->delete();
        }

        return $response;
    }

    protected function fingerprint(Request $request, string $operation, int $actorId): string
    {
        $route = $request->route();
        $parameters = $route?->parameters() ?? [];
        ksort($parameters);

        return hash('sha256', json_encode([
            'actor_id' => $actorId,
            'method' => $request->method(),
            'operation' => $operation,
            'route_name' => $route?->getName(),
            'route_action' => $route?->getActionName(),
            'route_parameters' => $parameters,
            'body' => $this->normalizeBody($request->all()),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function normalizeBody(array $body): array
    {
        unset($body['idempotency_key'], $body['Idempotency-Key']);
        ksort($body);

        foreach ($body as $key => $value) {
            if (is_array($value)) {
                $body[$key] = $this->normalizeBody($value);
            }
        }

        return $body;
    }

    protected function resourceFromRoute(Request $request): array
    {
        $parameters = $request->route()?->parameters() ?? [];

        foreach (['publicId', 'id'] as $name) {
            if (array_key_exists($name, $parameters)) {
                return ['type' => $name, 'id' => (string) $parameters[$name]];
            }
        }

        return ['type' => null, 'id' => null];
    }

    protected function responseBody(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            $decoded = json_decode((string) $response->getContent(), true);

            return is_array($decoded) ? $decoded : ['message' => 'Request completed.'];
        }

        if ($response instanceof LaravelResponse) {
            $decoded = json_decode((string) $response->getContent(), true);

            return is_array($decoded) ? $decoded : ['message' => 'Request completed.'];
        }

        return ['message' => 'Request completed.'];
    }

    protected function decodeStoredBody(mixed $body): array
    {
        if (is_array($body)) {
            return $body;
        }

        if (is_string($body)) {
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : ['message' => 'Request completed.'];
        }

        return ['message' => 'Request completed.'];
    }

    protected function stripeKey(string $operation, int $recordId): string
    {
        $safeOperation = str_replace(['admin.', '.'], ['narlit-', '-'], $operation);

        return "{$safeOperation}-{$recordId}";
    }
}
