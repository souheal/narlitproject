import Link from "next/link";

export default function BillingSuccessPage() {
  return (
    <main className="state-shell">
      <section className="state-card">
        <div className="state-pill state-pill-success">Payment confirmed</div>
        <h1>Stripe returned successfully.</h1>
        <p>
          If your webhook reaches the backend correctly, the subscription will be synced and the
          account should become active.
        </p>
        <div className="state-actions">
          <Link href="/signup" className="landing-button landing-button-primary">
            Back to signup flow
          </Link>
        </div>
      </section>
    </main>
  );
}
