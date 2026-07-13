export default function PublicFooter() {
  const year = new Date().getFullYear();
  return (
    <footer
      style={{
        borderTop: "1px solid var(--line)",
        padding: "40px 24px",
        marginTop: 60,
        background: "var(--panel)",
      }}
    >
      <div style={{ maxWidth: 1100, margin: "0 auto", display: "grid", gridTemplateColumns: "1.5fr 1fr 1fr 1fr", gap: 32 }}>
        <div>
          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span style={{ fontWeight: 900 }}>NarLit</span>
          </div>
          <p style={{ fontSize: "0.85rem", color: "var(--muted)", lineHeight: 1.6, margin: 0, maxWidth: 300 }}>
            Read stories that matter. Every completed article funds the nonprofit that wrote it.
          </p>
        </div>

        <div>
          <h4 style={{ fontSize: "0.85rem", textTransform: "uppercase", letterSpacing: "0.05em", color: "var(--muted)", marginBottom: 12 }}>
            Product
          </h4>
          <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 8 }}>
            <li><a href="/#how-it-works" className="su-link">How it works</a></li>
            <li><a href="/signup" className="su-link">Sign up</a></li>
            <li><a href="/signup/organizer" className="su-link">For nonprofits</a></li>
          </ul>
        </div>

        <div>
          <h4 style={{ fontSize: "0.85rem", textTransform: "uppercase", letterSpacing: "0.05em", color: "var(--muted)", marginBottom: 12 }}>
            Company
          </h4>
          <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 8 }}>
            <li><a href="/about" className="su-link">About</a></li>
            <li><a href="/contact" className="su-link">Contact</a></li>
          </ul>
        </div>

        <div>
          <h4 style={{ fontSize: "0.85rem", textTransform: "uppercase", letterSpacing: "0.05em", color: "var(--muted)", marginBottom: 12 }}>
            Legal
          </h4>
          <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 8 }}>
            <li><a href="/terms" className="su-link">Terms of Service</a></li>
            <li><a href="/privacy" className="su-link">Privacy Policy</a></li>
            <li><a href="/cookies" className="su-link">Cookie Policy</a></li>
          </ul>
        </div>
      </div>

      <div
        style={{
          maxWidth: 1100,
          margin: "32px auto 0",
          paddingTop: 20,
          borderTop: "1px solid var(--line)",
          display: "flex",
          justifyContent: "space-between",
          fontSize: "0.8rem",
          color: "var(--muted)",
          flexWrap: "wrap",
          gap: 12,
        }}
      >
        <span>© {year} NarLit. All rights reserved.</span>
        <span>support@narlit.com</span>
      </div>
    </footer>
  );
}
