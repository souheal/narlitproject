import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";

const STEPS = [
  { icon: "💳", title: "Subscribe", body: "Choose monthly or yearly. Your subscription funds every nonprofit whose stories you read." },
  { icon: "📖", title: "Read stories", body: "Discover journalism written by nonprofits about the causes they know best." },
  { icon: "🤝", title: "Fund their mission", body: "Each completed read redirects part of your subscription to that nonprofit." },
];

const CAUSES = [
  { icon: "⚖️", label: "Civil Rights" },
  { icon: "🍽️", label: "Food Security" },
  { icon: "🏥", label: "Healthcare" },
  { icon: "🎓", label: "Education" },
  { icon: "🌍", label: "Environment" },
  { icon: "🏠", label: "Housing" },
  { icon: "🕊️", label: "Refugees" },
  { icon: "👨‍👩‍👧", label: "Women & Children" },
];

export default function HomePage() {
  return (
    <div className="hm-shell">
      <PublicNav />

      <main>
        {/* Hero */}
        <section
          style={{
            padding: "80px 24px 60px",
            textAlign: "center",
            background: "linear-gradient(135deg, rgba(255,138,71,0.15), rgba(17,182,200,0.15))",
          }}
        >
          <div style={{ maxWidth: 780, margin: "0 auto" }}>
            <p style={{ color: "var(--orange)", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", fontSize: "0.8rem", marginBottom: 12 }}>
              Reading with purpose
            </p>
            <h1 style={{ fontSize: "3rem", lineHeight: 1.15, margin: 0, fontWeight: 900 }}>
              Every story you read{" "}
              <span style={{ color: "var(--orange)" }}>funds</span>{" "}
              <span style={{ color: "var(--teal)" }}>a nonprofit</span>.
            </h1>
            <p style={{ fontSize: "1.15rem", color: "var(--muted)", marginTop: 20, lineHeight: 1.6 }}>
              NarLit lets you subscribe once and support nonprofits every time you finish an article.
              No extra donations — just your reading, turning stories into impact.
            </p>
            <div style={{ display: "flex", justifyContent: "center", gap: 12, marginTop: 32, flexWrap: "wrap" }}>
              <a href="/signup" className="hm-article-btn" style={{ padding: "14px 28px", fontSize: "0.95rem" }}>
                Start reading →
              </a>
              <a href="/signup/organizer" className="hm-pager-btn" style={{ padding: "14px 28px", textDecoration: "none" }}>
                I&apos;m a nonprofit
              </a>
            </div>
          </div>
        </section>

        {/* How it works */}
        <section id="how-it-works" style={{ padding: "60px 24px" }}>
          <div style={{ maxWidth: 1000, margin: "0 auto" }}>
            <h2 style={{ textAlign: "center", fontSize: "2rem", marginBottom: 8 }}>How it works</h2>
            <p style={{ textAlign: "center", color: "var(--muted)", marginBottom: 40 }}>Three steps. That&apos;s it.</p>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))", gap: 20 }}>
              {STEPS.map((s, i) => (
                <div
                  key={s.title}
                  style={{
                    padding: 28,
                    background: "var(--panel)",
                    border: "1px solid var(--line)",
                    borderRadius: 16,
                    textAlign: "center",
                  }}
                >
                  <div style={{ fontSize: "3rem", marginBottom: 12 }}>{s.icon}</div>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)", fontWeight: 800, letterSpacing: "0.1em" }}>
                    STEP {i + 1}
                  </div>
                  <h3 style={{ margin: "6px 0 10px", fontSize: "1.15rem" }}>{s.title}</h3>
                  <p style={{ margin: 0, color: "var(--muted)", fontSize: "0.9rem", lineHeight: 1.55 }}>{s.body}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Impact stats */}
        <section style={{ padding: "60px 24px", background: "var(--panel)" }}>
          <div style={{ maxWidth: 900, margin: "0 auto", textAlign: "center" }}>
            <p style={{ color: "var(--teal)", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", fontSize: "0.8rem" }}>
              Where your subscription goes
            </p>
            <h2 style={{ fontSize: "1.7rem", marginTop: 8 }}>
              About $2.31 of every $7 goes directly to nonprofits.
            </h2>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 20, marginTop: 30 }}>
              <Slice pct={33} color="var(--orange)" label="Nonprofits" />
              <Slice pct={33} color="var(--teal)" label="Operations" />
              <Slice pct={34} color="#7c5cbf" label="Growth" />
            </div>
          </div>
        </section>

        {/* Causes */}
        <section style={{ padding: "60px 24px" }}>
          <div style={{ maxWidth: 1000, margin: "0 auto" }}>
            <h2 style={{ textAlign: "center", fontSize: "2rem" }}>Stories from causes that matter</h2>
            <p style={{ textAlign: "center", color: "var(--muted)", marginBottom: 30 }}>
              NarLit publishes work by vetted nonprofits across the issues shaping our world.
            </p>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: 12 }}>
              {CAUSES.map((c) => (
                <div
                  key={c.label}
                  style={{
                    padding: "20px 12px",
                    background: "var(--panel)",
                    border: "1px solid var(--line)",
                    borderRadius: 14,
                    textAlign: "center",
                  }}
                >
                  <div style={{ fontSize: "1.8rem" }}>{c.icon}</div>
                  <div style={{ fontSize: "0.82rem", fontWeight: 700, marginTop: 6 }}>{c.label}</div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Final CTA */}
        <section
          style={{
            padding: "80px 24px",
            textAlign: "center",
            background: "linear-gradient(135deg, var(--orange), var(--teal))",
            color: "white",
          }}
        >
          <div style={{ maxWidth: 640, margin: "0 auto" }}>
            <h2 style={{ fontSize: "2rem", margin: 0 }}>Reading changes something.</h2>
            <p style={{ marginTop: 12, opacity: 0.9, fontSize: "1.05rem" }}>
              Start today. Cancel anytime. Every article makes a real difference.
            </p>
            <a
              href="/signup"
              style={{
                display: "inline-block",
                marginTop: 24,
                padding: "14px 28px",
                background: "white",
                color: "var(--orange)",
                fontWeight: 800,
                borderRadius: 999,
                textDecoration: "none",
              }}
            >
              Start reading →
            </a>
          </div>
        </section>
      </main>

      <PublicFooter />
    </div>
  );
}

function Slice({ pct, color, label }: { pct: number; color: string; label: string }) {
  return (
    <div>
      <div style={{ height: 10, background: "var(--line)", borderRadius: 5, overflow: "hidden" }}>
        <div style={{ width: `${pct}%`, height: "100%", background: color }} />
      </div>
      <div style={{ marginTop: 10, fontSize: "1.4rem", fontWeight: 900 }}>{pct}%</div>
      <div style={{ fontSize: "0.85rem", color: "var(--muted)" }}>{label}</div>
    </div>
  );
}
