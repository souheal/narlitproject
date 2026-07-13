import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";

export const metadata = { title: "Cookie Policy · NarLit" };

export default function CookiesPage() {
  return (
    <div className="hm-shell">
      <PublicNav />

      <main style={{ padding: "60px 24px" }}>
        <div style={{ maxWidth: 780, margin: "0 auto" }}>
          <p style={{ color: "var(--muted)", fontSize: "0.85rem", marginBottom: 4 }}>Last updated: January 1, 2026</p>
          <h1 style={{ fontSize: "2.4rem", margin: "0 0 24px" }}>Cookie Policy</h1>

          <p style={{ color: "var(--muted)", lineHeight: 1.7, fontSize: "0.95rem" }}>
            NarLit uses a small number of cookies to keep the site working. Here&apos;s exactly what we set and why.
          </p>

          <h2 style={{ fontSize: "1.15rem", marginTop: 28, marginBottom: 10 }}>Essential cookies</h2>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "0.9rem", marginTop: 8 }}>
            <thead>
              <tr style={{ textAlign: "left", borderBottom: "1px solid var(--line)" }}>
                <th style={{ padding: "10px 0" }}>Name</th>
                <th>Purpose</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              <Row name="auth_token" purpose="Keeps you signed in as a member" duration="30 days" />
              <Row name="admin_token" purpose="Keeps admins signed in" duration="30 days" />
              <Row name="cookie_consent" purpose="Remembers whether you dismissed the cookie banner" duration="1 year" />
            </tbody>
          </table>

          <h2 style={{ fontSize: "1.15rem", marginTop: 32, marginBottom: 10 }}>What we don&apos;t use</h2>
          <p style={{ color: "var(--muted)", lineHeight: 1.7, fontSize: "0.95rem" }}>
            We do not currently use advertising, cross-site tracking, or third-party analytics cookies. If that ever
            changes, we&apos;ll update this page and give you a choice.
          </p>

          <h2 style={{ fontSize: "1.15rem", marginTop: 32, marginBottom: 10 }}>Managing cookies</h2>
          <p style={{ color: "var(--muted)", lineHeight: 1.7, fontSize: "0.95rem" }}>
            You can clear cookies at any time through your browser settings. Removing the auth cookie will sign you
            out, but nothing else will break.
          </p>
        </div>
      </main>

      <PublicFooter />
    </div>
  );
}

function Row({ name, purpose, duration }: { name: string; purpose: string; duration: string }) {
  return (
    <tr style={{ borderBottom: "1px solid var(--line)" }}>
      <td style={{ padding: "12px 0", fontFamily: "monospace", fontSize: "0.85rem" }}>{name}</td>
      <td style={{ color: "var(--muted)" }}>{purpose}</td>
      <td style={{ color: "var(--muted)" }}>{duration}</td>
    </tr>
  );
}
