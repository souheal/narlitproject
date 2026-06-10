import Link from "next/link";

export default function BillingCancelPage() {
  return (
    <main className="state-shell">
      <section className="state-card">
        <div className="state-pill state-pill-cancel">Payment canceled</div>
        <h1>Checkout was canceled.</h1>
        <p>
          The account is still registered and email-verified. You can return to the payment step
          and try the Stripe checkout again.
        </p>
        <div className="state-actions">
          <Link href="/signup" className="landing-button landing-button-primary">
            Return to signup
          </Link>
        </div>
      </section>
    </main>
  );
}
