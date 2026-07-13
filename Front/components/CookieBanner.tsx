"use client";

import { useEffect, useState } from "react";

const COOKIE_NAME = "cookie_consent";
const MAX_AGE = 60 * 60 * 24 * 365; // one year

export default function CookieBanner() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const consented = document.cookie.split(";").some((c) => c.trim().startsWith(`${COOKIE_NAME}=`));
    if (!consented) setVisible(true);
  }, []);

  function accept() {
    document.cookie = `${COOKIE_NAME}=1; path=/; max-age=${MAX_AGE}; SameSite=Lax`;
    setVisible(false);
  }

  if (!visible) return null;

  return (
    <div
      style={{
        position: "fixed",
        bottom: 16,
        left: 16,
        right: 16,
        zIndex: 60,
        background: "var(--panel)",
        border: "1px solid var(--line)",
        borderRadius: 16,
        padding: 20,
        display: "flex",
        gap: 20,
        alignItems: "center",
        justifyContent: "space-between",
        flexWrap: "wrap",
        boxShadow: "0 10px 40px rgba(0,0,0,0.15)",
        maxWidth: 900,
        margin: "0 auto",
      }}
      role="dialog"
      aria-label="Cookie notice"
    >
      <div style={{ flex: 1, minWidth: 240 }}>
        <div style={{ fontWeight: 800, marginBottom: 4 }}>We use essential cookies</div>
        <div style={{ fontSize: "0.85rem", color: "var(--muted)", lineHeight: 1.5 }}>
          NarLit only uses cookies to keep you signed in and remember your preferences.{" "}
          <a href="/cookies" className="su-link">Learn more</a>.
        </div>
      </div>
      <div style={{ display: "flex", gap: 8 }}>
        <button className="hm-article-btn" onClick={accept}>Got it</button>
      </div>
    </div>
  );
}
