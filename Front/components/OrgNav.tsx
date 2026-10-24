"use client";

import { useEffect, useRef, useState } from "react";
import { clearToken, saveRole } from "@/lib/auth";
import { apiFetch } from "@/lib/api";
import { BellIcon } from "@/components/icons";
import { safeHref } from "@/lib/safeUrl";

type Section = "overview" | "articles" | "payouts";

interface NotifItem {
  public_id: string;
  title: string;
  body: string | null;
  action_url: string | null;
  icon: string | null;
  read_at: string | null;
  created_at: string;
}

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

function timeAgo(iso: string): string {
  const m = Math.floor((Date.now() - new Date(iso).getTime()) / 60_000);
  if (m < 1) return "just now";
  if (m < 60) return `${m}m`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h`;
  const d = Math.floor(h / 24);
  return d < 7 ? `${d}d` : new Date(iso).toLocaleDateString();
}

/** Top bar shared by every organization page. */
export default function OrgNav({ active, name, email, initials }: Props) {
  const [open, setOpen] = useState<null | "account" | "notif">(null);
  const [unread, setUnread] = useState(0);
  const [notifs, setNotifs] = useState<NotifItem[] | null>(null);
  const [me, setMe] = useState<{ name?: string; email?: string }>({});
  const rootRef = useRef<HTMLDivElement>(null);

  // Covers sessions that logged in before the role cookie existed
  useEffect(() => { saveRole("organization"); }, []);

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
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(null);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(null);
    }
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onClick);
      document.removeEventListener("keydown", onKey);
    };
  }, []);

  // Admin decisions on articles (approved / rejected / changes requested) arrive
  // as notifications; poll the unread count like the member nav does.
  useEffect(() => {
    let mounted = true;
    async function fetchCount() {
      try {
        const res = await apiFetch("/member/notifications/unread-count");
        if (!res.ok) return;
        const data = await res.json();
        if (mounted) setUnread(data.data?.count ?? 0);
      } catch { /* ignore */ }
    }
    fetchCount();
    const id = window.setInterval(fetchCount, 60_000);
    return () => { mounted = false; window.clearInterval(id); };
  }, []);

  useEffect(() => {
    if (open !== "notif") return;
    apiFetch("/member/notifications?filter=all")
      .then((r) => r.json())
      .then((d) => {
        const list: NotifItem[] = d.data?.notifications?.data ?? [];
        setNotifs(list.slice(0, 8));
      })
      .catch(() => setNotifs([]));
  }, [open]);

  async function markAllRead() {
    try {
      await apiFetch("/member/notifications/read-all", { method: "POST" });
      setNotifs((prev) => prev?.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })) ?? prev);
      setUnread(0);
    } catch { /* ignore */ }
  }

  function markOneRead(n: NotifItem) {
    if (n.read_at) return;
    // keepalive lets the request finish while the link navigates away
    apiFetch(`/member/notifications/${n.public_id}/read`, { method: "POST", keepalive: true }).catch(() => {});
    setUnread((c) => Math.max(0, c - 1));
  }

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
          <div style={{ position: "relative" }}>
            <button
              type="button"
              className={`hm-icon-btn${open === "notif" ? " hm-icon-btn-active" : ""}`}
              onClick={() => setOpen(open === "notif" ? null : "notif")}
              aria-label={`Notifications${unread > 0 ? ` (${unread} unread)` : ""}`}
              aria-expanded={open === "notif"}
            >
              <BellIcon />
              {unread > 0 && <span className="hm-icon-badge">{unread > 99 ? "99+" : unread}</span>}
            </button>
            {open === "notif" && (
              <div className="hm-panel-dropdown hm-notif-dropdown">
                <div className="hm-panel-dropdown-header">
                  <h3 className="hm-panel-dropdown-title">Notifications</h3>
                  {unread > 0 && (
                    <button type="button" className="su-link" onClick={markAllRead} style={{ background: "none", border: 0, cursor: "pointer", fontSize: "0.75rem", fontFamily: "inherit" }}>
                      Mark all read
                    </button>
                  )}
                </div>
                <div className="hm-notif-list">
                  {notifs === null && <div className="hm-search-empty">Loading…</div>}
                  {notifs?.length === 0 && <div className="hm-search-empty">No notifications yet.</div>}
                  {notifs?.map((n) => (
                    <a
                      key={n.public_id}
                      href={safeHref(n.action_url, "/organization/articles")}
                      onClick={() => markOneRead(n)}
                      className={`hm-notif-item${!n.read_at ? " hm-notif-item-unread" : ""}`}
                    >
                      <div className="hm-notif-item-icon">{n.icon ?? "🔔"}</div>
                      <div className="hm-notif-item-body">
                        <p className="hm-notif-item-title">{n.title}</p>
                        {n.body && <p className="hm-notif-item-desc">{n.body}</p>}
                        <p className="hm-notif-item-time">{timeAgo(n.created_at)}</p>
                      </div>
                      {!n.read_at && <span className="hm-notif-item-dot" />}
                    </a>
                  ))}
                </div>
              </div>
            )}
          </div>
          <button
            type="button"
            className="hm-nav-avatar"
            onClick={() => setOpen(open === "account" ? null : "account")}
            aria-label="Account menu"
            aria-expanded={open === "account"}
            style={{ border: 0 }}
          >
            {badge}
          </button>
          {open === "account" && (
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
