"use client";

import { useEffect, useRef, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch } from "@/lib/api";

type Section = "overview" | "articles" | "payouts";

interface Props {
  active: Section;
  /** Pages that already load the organization pass it in; others are looked up. */
  name?: string;
  email?: string;
  initials?: string;
}

const LINKS: { key: Section; href: string; label: string }[] = [
  { key: "overview", href: "/organization/dashboard", label: "Overview" },
  { key: "articles", href: "/organization/articles", label: "Articles" },
  { key: "payouts", href: "/organization/payouts", label: "Payouts" },
];

function initialsOf(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]!.toUpperCase())
    .join("");
}

/** Top bar shared by every organization page. */
export default function OrgNav({ active, name, email, initials }: Props) {
  const [open, setOpen] = useState(false);
  const [me, setMe] = useState<{ name?: string; email?: string }>({});
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (name) return;
    apiFetch("/auth/me")
      .then((r) => r.json())
      .then((d) => {
        const u = d.data?.user ?? d.data ?? {};
        setMe({ name: u.full_name, email: u.email });
      })
      .catch(() => {});
  }, [name]);

  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onClick);
      document.removeEventListener("keydown", onKey);
    };
  }, []);

  async function handleLogout() {
    try { await apiFetch("/auth/logout", { method: "POST" }); } catch { /* noop */ }
    clearToken();
    window.location.href = "/login";
  }

  const displayName = name ?? me.name;
  const displayEmail = email ?? me.email;
  const badge = initials ?? (displayName ? initialsOf(displayName) : "OR");

  return (
    <nav className="hm-nav">
      <div className="hm-nav-inner">
        <a href="/organization/dashboard" className="hm-nav-brand">
          <span className="hm-nav-mark">
            <span className="hm-nm-orange" />
            <span className="hm-nm-teal" />
          </span>
          <span className="hm-nav-wordmark hm-nav-wordmark-org"><span className="hm-wm-orange">NarLit</span><span className="hm-wm-teal"> · Org</span></span>
        </a>
        <div className="hm-nav-links">
          {LINKS.map((l) => (
            <a
              key={l.key}
              href={l.href}
              className={`hm-nav-link${l.key === active ? " hm-nav-link-active" : ""}`}
              aria-current={l.key === active ? "page" : undefined}
            >
              {l.label}
            </a>
          ))}
        </div>
        <div className="hm-nav-user" ref={rootRef}>
          <button
            type="button"
            className="hm-nav-avatar"
            onClick={() => setOpen(!open)}
            aria-label="Account menu"
            aria-expanded={open}
            style={{ border: 0 }}
          >
            {badge}
          </button>
          {open && (
            <div className="hm-nav-dropdown">
              <div className="hm-nav-dd-name">{displayName ?? "Organization"}</div>
              {displayEmail && <div className="hm-nav-dd-email">{displayEmail}</div>}
              <div className="hm-nav-dd-divider" />
              <a className="hm-nav-dd-item" href="/profile">Profile</a>
              <button className="hm-nav-dd-item" onClick={handleLogout}>Sign out</button>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
