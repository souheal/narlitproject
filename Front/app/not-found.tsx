import Link from "next/link";

export default function NotFound() {
  return (
    <div
      style={{
        minHeight: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: 24,
        textAlign: "center",
      }}
    >
      <div style={{ maxWidth: 480 }}>
        <div style={{ fontSize: "5rem", fontWeight: 900, background: "linear-gradient(90deg, var(--orange), var(--teal))", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>
          404
        </div>
        <h1 style={{ fontSize: "1.6rem", marginTop: 12 }}>Page not found</h1>
        <p style={{ color: "var(--muted)", marginTop: 8, lineHeight: 1.6 }}>
          The page you&apos;re looking for doesn&apos;t exist, or it was moved.
        </p>
        <div style={{ display: "flex", gap: 12, justifyContent: "center", marginTop: 24, flexWrap: "wrap" }}>
          <Link href="/" className="hm-article-btn">Back home</Link>
          <Link href="/contact" className="hm-pager-btn" style={{ textDecoration: "none" }}>
            Report a broken link
          </Link>
        </div>
      </div>
    </div>
  );
}
