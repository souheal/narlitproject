"use client";

import { useEffect, useState } from "react";

export default function PublicNav() {
  const [loggedIn, setLoggedIn] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    setLoggedIn(document.cookie.includes("auth_token=") || document.cookie.includes("admin_token="));
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  function closeMobile() {
    setMobileOpen(false);
  }

  function handleHashClick(e: React.MouseEvent<HTMLAnchorElement>, href: string) {
    // Only intercept if we're already on the homepage; otherwise let it navigate normally.
    if (typeof window !== "undefined" && window.location.pathname === "/") {
      e.preventDefault();
      const id = href.replace(/^\/#/, "");
      const target = document.getElementById(id);
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
        history.replaceState(null, "", `#${id}`);
      }
    }
    closeMobile();
  }

  return (
    <nav className={`hm-nav ${scrolled ? "hm-nav-scrolled" : ""}`} suppressHydrationWarning>
      <div className={`hm-nav-inner ${mobileOpen ? "hm-nav-open" : ""}`} suppressHydrationWarning>
        <a href="/" className="hm-nav-brand" onClick={closeMobile}>
          <span className="hm-nav-mark" aria-hidden="true">
            <span className="hm-nm-orange" />
            <span className="hm-nm-teal" />
          </span>
          <span className="hm-nav-wordmark">NarLit</span>
        </a>

        <button
          type="button"
          className="hm-nav-mobile-toggle"
          onClick={() => setMobileOpen(!mobileOpen)}
          aria-label={mobileOpen ? "Close menu" : "Open menu"}
          aria-expanded={mobileOpen}
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            {mobileOpen ? (
              <>
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </>
            ) : (
              <>
                <line x1="4" y1="7" x2="20" y2="7" />
                <line x1="4" y1="12" x2="20" y2="12" />
                <line x1="4" y1="17" x2="20" y2="17" />
              </>
            )}
          </svg>
        </button>

        <div className="hm-nav-links">
          <a href="/#how-it-works" className="hm-nav-link" onClick={(e) => handleHashClick(e, "/#how-it-works")}>
            How it works
          </a>
          <a href="/#pricing" className="hm-nav-link" onClick={(e) => handleHashClick(e, "/#pricing")}>
            Pricing
          </a>
          <a href="/organizations" className="hm-nav-link" onClick={closeMobile}>
            Nonprofits
          </a>
          <a href="/about" className="hm-nav-link" onClick={closeMobile}>
            About
          </a>
          <a href="/contact" className="hm-nav-link" onClick={closeMobile}>
            Contact
          </a>
        </div>

        <div className="hm-nav-auth">
          {loggedIn ? (
            <a href="/dashboard" className="hm-nav-cta" onClick={closeMobile}>
              Go to dashboard →
            </a>
          ) : (
            <>
              <a href="/login" className="hm-nav-signin" onClick={closeMobile}>
                Sign in
              </a>
              <a href="/signup" className="hm-nav-cta" onClick={closeMobile}>
                Get started
              </a>
            </>
          )}
        </div>
      </div>
    </nav>
  );
}
