import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";

export const metadata = { title: "Privacy Policy · NarLit" };

export default function PrivacyPage() {
  return (
    <div className="hm-shell">
      <PublicNav />

      <main style={{ padding: "60px 24px" }}>
        <div style={{ maxWidth: 780, margin: "0 auto" }}>
          <p style={{ color: "var(--muted)", fontSize: "0.85rem", marginBottom: 4 }}>Last updated: January 1, 2026</p>
          <h1 style={{ fontSize: "2.4rem", margin: "0 0 24px" }}>Privacy Policy</h1>

          <Section title="Overview">
            NarLit takes privacy seriously. This policy explains what we collect, why, and what rights you have.
            If you have questions, email{" "}
            <a href="mailto:privacy@narlit.com" className="su-link">privacy@narlit.com</a>.
          </Section>

          <Section title="What we collect">
            <ul>
              <li><strong>Account data</strong>: name, email, phone, hashed password.</li>
              <li><strong>Payment data</strong>: handled by Stripe; we store only the last 4 digits of your card and a Stripe customer ID.</li>
              <li><strong>Reading data</strong>: articles you open, read percentage, reading time, device type, and country.</li>
              <li><strong>Technical data</strong>: IP address, user agent, and session logs.</li>
            </ul>
          </Section>

          <Section title="Why we collect it">
            <ul>
              <li>To operate your account and process payments.</li>
              <li>To calculate donations owed to nonprofits based on your completed reads.</li>
              <li>To personalize your experience (recommendations, streaks, achievements).</li>
              <li>To detect fraud and abuse.</li>
              <li>To comply with legal obligations.</li>
            </ul>
          </Section>

          <Section title="Who we share it with">
            <ul>
              <li><strong>Stripe</strong>: for payment processing and Stripe Connect payouts.</li>
              <li><strong>Nonprofits</strong>: they see aggregate read counts on their own articles, not personal data.</li>
              <li><strong>Service providers</strong>: transactional email, hosting, and error monitoring (bound by contract).</li>
              <li><strong>Legal</strong>: when required by law or to protect our rights.</li>
            </ul>
            We never sell your personal data.
          </Section>

          <Section title="Cookies">
            We use essential cookies to keep you signed in and to remember your preferences.
            See the{" "}
            <a href="/cookies" className="su-link">Cookie Policy</a> for details.
          </Section>

          <Section title="Your rights">
            Depending on your jurisdiction, you have the right to access, correct, delete, or export your data. You
            can do so directly from the{" "}
            <a href="/account" className="su-link">Account settings page</a>, or email{" "}
            <a href="mailto:privacy@narlit.com" className="su-link">privacy@narlit.com</a>.
          </Section>

          <Section title="Data retention">
            We keep your data for as long as your account is active. When you delete your account, personal data is
            erased within 30 days, except where retention is required by law (e.g., tax records).
          </Section>

          <Section title="Data security">
            Data is encrypted in transit (TLS) and at rest. Passwords are hashed with modern algorithms. We limit
            employee access to production data on a need-to-know basis.
          </Section>

          <Section title="Children">
            NarLit is not intended for users under 13. We do not knowingly collect personal data from children.
          </Section>

          <Section title="International transfers">
            Your data may be processed in the United States. We rely on Standard Contractual Clauses for cross-border
            transfers where applicable.
          </Section>

          <Section title="Changes to this policy">
            We&apos;ll notify you of material changes at least 14 days in advance by email.
          </Section>
        </div>
      </main>

      <PublicFooter />
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section style={{ marginBottom: 28 }}>
      <h2 style={{ fontSize: "1.15rem", marginBottom: 10 }}>{title}</h2>
      <div style={{ color: "var(--muted)", lineHeight: 1.75, fontSize: "0.95rem" }}>{children}</div>
    </section>
  );
}
