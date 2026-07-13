import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";

export const metadata = { title: "About · NarLit" };

export default function AboutPage() {
  return (
    <div className="hm-shell">
      <PublicNav />

      <main style={{ padding: "60px 24px" }}>
        <div style={{ maxWidth: 780, margin: "0 auto" }}>
          <p style={{ color: "var(--orange)", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", fontSize: "0.8rem" }}>
            About NarLit
          </p>
          <h1 style={{ fontSize: "2.4rem", margin: "8px 0 20px", lineHeight: 1.2 }}>
            Journalism that pays back the people it writes about.
          </h1>
          <p style={{ fontSize: "1.05rem", color: "var(--muted)", lineHeight: 1.7 }}>
            NarLit was born from a simple observation: nonprofits know their causes better than anyone,
            yet they rarely get paid for the stories they tell. Meanwhile, readers want to support the
            issues they care about but don&apos;t know where their donations actually land.
          </p>
          <p style={{ fontSize: "1.05rem", color: "var(--muted)", lineHeight: 1.7 }}>
            We built a subscription that turns reading into direct funding. When you finish an article
            published by a nonprofit on NarLit, part of your monthly subscription goes to them.
            No guilt-tripping, no side donations — just meaningful reading that adds up.
          </p>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
              gap: 20,
              margin: "40px 0",
              padding: 24,
              background: "var(--panel)",
              border: "1px solid var(--line)",
              borderRadius: 16,
            }}
          >
            <Stat value="~$2.31" label="of every $7 goes to nonprofits" />
            <Stat value="80%" label="read threshold that counts as complete" />
            <Stat value="100%" label="of eligible U.S. nonprofits welcome" />
          </div>

          <h2 style={{ fontSize: "1.5rem", marginTop: 40 }}>What we believe</h2>
          <ul style={{ listStyle: "none", padding: 0, marginTop: 16, display: "flex", flexDirection: "column", gap: 14 }}>
            <Belief
              title="Attention is a currency."
              body="What you read reflects what you care about. NarLit converts that attention into funding, transparently."
            />
            <Belief
              title="Verified nonprofits only."
              body="Every publisher on NarLit is a vetted 501(c)(3). We check IRS status before an organization can accept payouts."
            />
            <Belief
              title="No dark patterns."
              body="Simple pricing. Cancel anytime. Cancel and you're refunded any unearned portion where the law requires."
            />
            <Belief
              title="Numbers you can see."
              body="Your impact dashboard shows exactly which nonprofits you've funded, when, and how much."
            />
          </ul>

          <h2 style={{ fontSize: "1.5rem", marginTop: 40 }}>Who&apos;s behind this</h2>
          <p style={{ fontSize: "1.05rem", color: "var(--muted)", lineHeight: 1.7 }}>
            NarLit is a small, independent team building a sustainable model for civic storytelling.
            We&apos;re based remotely, we ship in the open, and we&apos;re available at{" "}
            <a href="mailto:hello@narlit.com" className="su-link">hello@narlit.com</a>.
          </p>

          <div
            style={{
              marginTop: 48,
              padding: 32,
              background: "linear-gradient(135deg, var(--orange), var(--teal))",
              color: "white",
              borderRadius: 20,
              textAlign: "center",
            }}
          >
            <h2 style={{ margin: 0, fontSize: "1.6rem" }}>Read. Fund. Repeat.</h2>
            <p style={{ opacity: 0.9, marginTop: 10 }}>Try NarLit and see your first contribution within one story.</p>
            <a
              href="/signup"
              style={{
                display: "inline-block",
                marginTop: 20,
                padding: "12px 24px",
                background: "white",
                color: "var(--orange)",
                fontWeight: 800,
                borderRadius: 999,
                textDecoration: "none",
              }}
            >
              Get started →
            </a>
          </div>
        </div>
      </main>

      <PublicFooter />
    </div>
  );
}

function Stat({ value, label }: { value: string; label: string }) {
  return (
    <div style={{ textAlign: "center" }}>
      <div style={{ fontSize: "1.8rem", fontWeight: 900 }}>{value}</div>
      <div style={{ fontSize: "0.82rem", color: "var(--muted)", marginTop: 4 }}>{label}</div>
    </div>
  );
}

function Belief({ title, body }: { title: string; body: string }) {
  return (
    <li>
      <div style={{ fontWeight: 800, marginBottom: 4 }}>{title}</div>
      <div style={{ color: "var(--muted)", fontSize: "0.95rem", lineHeight: 1.6 }}>{body}</div>
    </li>
  );
}
