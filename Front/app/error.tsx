"use client";

import { useEffect } from "react";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

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
        <div style={{ fontSize: "4rem" }}>💥</div>
        <h1 style={{ fontSize: "1.6rem", marginTop: 12 }}>Something went wrong</h1>
        <p style={{ color: "var(--muted)", marginTop: 8, lineHeight: 1.6 }}>
          An unexpected error occurred. Try again — if it keeps happening, let us know.
        </p>
        {error.digest && (
          <p style={{ marginTop: 8, fontSize: "0.75rem", color: "var(--muted)", fontFamily: "monospace" }}>
            Reference: {error.digest}
          </p>
        )}
        <div style={{ display: "flex", gap: 12, justifyContent: "center", marginTop: 24, flexWrap: "wrap" }}>
          <button className="hm-article-btn" onClick={reset}>Try again</button>
          <a href="/" className="hm-pager-btn" style={{ textDecoration: "none" }}>Back home</a>
          <a href="/contact" className="hm-pager-btn" style={{ textDecoration: "none" }}>Contact support</a>
        </div>
      </div>
    </div>
  );
}
