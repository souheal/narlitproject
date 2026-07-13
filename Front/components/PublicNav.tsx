"use client";

import { useEffect, useState } from "react";

export default function PublicNav() {
  const [loggedIn, setLoggedIn] = useState(false);

  useEffect(() => {
    setLoggedIn(document.cookie.includes("auth_token="));
  }, []);

  return (
    <nav className="hm-nav">
      <div className="hm-nav-inner">
        <a href="/" className="hm-nav-brand">
          <span className="hm-nav-mark">
            <span className="hm-nm-orange" />
            <span className="hm-nm-teal" />
          </span>
          <span className="hm-nav-wordmark">NarLit</span>
        </a>

        <div className="hm-nav-links">
          <a href="/about" className="hm-nav-link">About</a>
          <a href="/contact" className="hm-nav-link">Contact</a>
        </div>

        <div className="hm-nav-user" style={{ display: "flex", gap: 8 }}>
          {loggedIn ? (
            <a href="/dashboard" className="hm-article-btn">
              Go to dashboard →
            </a>
          ) : (
            <>
              <a href="/login" className="hm-nav-link">Sign in</a>
              <a href="/signup" className="hm-article-btn">Get started</a>
            </>
          )}
        </div>
      </div>
    </nav>
  );
}
