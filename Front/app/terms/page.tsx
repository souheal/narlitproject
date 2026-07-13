import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";

export const metadata = { title: "Terms of Service · NarLit" };

export default function TermsPage() {
  return (
    <div className="hm-shell">
      <PublicNav />

      <main style={{ padding: "60px 24px" }}>
        <div style={{ maxWidth: 780, margin: "0 auto" }}>
          <p style={{ color: "var(--muted)", fontSize: "0.85rem", marginBottom: 4 }}>Last updated: January 1, 2026</p>
          <h1 style={{ fontSize: "2.4rem", margin: "0 0 24px" }}>Terms of Service</h1>

          <Section title="1. Acceptance of terms">
            By creating an account or using NarLit, you agree to be bound by these Terms of Service and our{" "}
            <a href="/privacy" className="su-link">Privacy Policy</a>. If you do not agree, do not use the service.
          </Section>

          <Section title="2. Eligibility">
            You must be at least 13 years old (or the minimum digital-consent age in your jurisdiction) to use NarLit.
            Organization accounts require an authorized representative acting on behalf of a registered nonprofit.
          </Section>

          <Section title="3. Accounts">
            You are responsible for maintaining the confidentiality of your login credentials and for all activity
            under your account. You agree to notify us immediately if you suspect unauthorized use.
          </Section>

          <Section title="4. Subscriptions & billing">
            NarLit offers monthly and yearly subscriptions billed through Stripe. Subscriptions renew automatically
            until canceled. You may cancel at any time through the Subscription page; access continues until the end
            of the current billing period. Refunds are provided where required by law.
          </Section>

          <Section title="5. Donation split">
            A portion of your subscription is directed to nonprofits based on the articles you complete reading. The
            current split is disclosed on the platform and may be adjusted with advance notice.
          </Section>

          <Section title="6. Content">
            Articles on NarLit are published by verified 501(c)(3) nonprofits. NarLit does not guarantee the accuracy
            of any article. Nonprofits retain ownership of their content and grant NarLit a license to display it on
            the platform.
          </Section>

          <Section title="7. Acceptable use">
            You agree not to: (a) attempt to circumvent the read-tracking system to inflate donations, (b) scrape or
            resell content, (c) attempt unauthorized access to any systems, or (d) impersonate any person or entity.
          </Section>

          <Section title="8. Nonprofit accounts">
            Nonprofits must maintain valid 501(c)(3) status. Payouts require verified Stripe Connect onboarding.
            NarLit may withhold payouts pending fraud review or if the nonprofit&apos;s status lapses.
          </Section>

          <Section title="9. Termination">
            We may suspend or terminate your account for violation of these terms. You may delete your account at any
            time from the{" "}
            <a href="/account" className="su-link">Account settings page</a>.
          </Section>

          <Section title="10. Disclaimers">
            NarLit is provided &quot;as is&quot; without warranties of any kind. To the maximum extent permitted by law,
            NarLit disclaims all warranties, express or implied.
          </Section>

          <Section title="11. Limitation of liability">
            NarLit&apos;s aggregate liability for any claim shall not exceed the amount you paid in the twelve months
            preceding the claim.
          </Section>

          <Section title="12. Changes to these terms">
            We may update these terms from time to time. Material changes will be announced by email at least 14 days
            before taking effect. Continued use after changes constitutes acceptance.
          </Section>

          <Section title="13. Contact">
            Questions about these terms? Email us at{" "}
            <a href="mailto:legal@narlit.com" className="su-link">legal@narlit.com</a>.
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
