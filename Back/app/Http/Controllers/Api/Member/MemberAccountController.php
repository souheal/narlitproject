<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Services\Member\MemberAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberAccountController extends Controller
{
    use ApiResponse;

    public function __construct(protected MemberAccountService $service)
    {
    }

    public function export(Request $request): JsonResponse
    {
        return $this->success('Data export requested.', $this->service->requestExport($request->user()));
    }

    public function download(Request $request): StreamedResponse
    {
        $payload = $this->service->download($request->user());
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filename = 'narlit-data-' . now()->format('Ymd-His') . '.json';

        return response()->streamDownload(
            function () use ($json): void {
                echo $json;
            },
            $filename,
            [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        return $this->success('Account updated.', $this->service->update($request->user(), $data));
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        return $this->success('Account deleted.', $this->service->delete($request->user(), $data['password']));
    }
}
