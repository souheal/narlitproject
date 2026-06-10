# NarLit Frontend

Minimal Next.js frontend for the NarLit backend flow.

## Included pages

- `/` landing page
- `/signup` register -> verify OTP -> Stripe checkout handoff
- `/billing/success` payment success page
- `/billing/cancel` payment cancel page

## Run locally

1. Copy `.env.local.example` to `.env.local`
2. Make sure this value points to your Laravel API:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

3. Install dependencies:

```bash
npm install
```

4. Start development server:

```bash
npm run dev
```

5. Open:

```text
http://localhost:3000/signup
```
