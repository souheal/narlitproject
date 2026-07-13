"use client";

import { useEffect, useRef, useState } from "react";
import { usePathname } from "next/navigation";
import { clearToken } from "@/lib/auth";
import { apiFetch } from "@/lib/api";
import { BellIcon, CheckIcon, CloseIcon, MenuIcon, SearchIcon } from "@/components/icons";

interface Props {
  initials?: string;
  name?: string;
  email?: string;
}

interface SearchArticle {
  public_id: string;
  title: string;
  organization: { name: string | null };
}
interface SearchOrg {
  public_id: string;
  organization_name: string;
  category: string | null;
}
interface SearchResults {
  articles: SearchArticle[];
  organizations: SearchOrg[];
}

interface NotifItem {
  public_id: string;
  type: string;
  title: string;
  body: string | null;
  action_url: string | null;
  icon: string | null;
  read_at: string | null;
  created_at: string;
}

const PRIMARY_LINKS = [
  { href: "/dashboard",      label: "Home" },
  { href: "/explore",        label: "Explore" },
  { href: "/impact",         label: "My Impact" },
  { href: "/organizations",  label: "Nonprofits" },
];

const DROPDOWN_LINKS = [
  { href: "/profile",       label: "Profile",          icon: "👤" },
  { href: "/subscription",  label: "Subscription",     icon: "💳" },
  { href: "/bookmarks",     label: "Bookmarks",        icon: "🔖" },
  { href: "/history",       label: "Reading history",  icon: "📖" },
  { href: "/achievements",  label: "Achievements",     icon: "🏆" },
  { href: "/preferences",   label: "Preferences",      icon: "⚙️" },
  { href: "/account",       label: "Account & data",   icon: "🗄️" },
  { href: "/help",          label: "Help & support",   icon: "❓" },
];

const NOTIF_TYPE_ICONS: Record<string, string> = {
  new_article: "📰",
  achievement: "🏆",
  payment: "💳",
  system: "⚙️",
  organization: "🏢",
  milestone: "🎉",
};

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const s = Math.floor(diff / 1000);
  if (s < 60) return "just now";
  const m = Math.floor(s / 60);
  if (m < 60) return `${m}m`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h`;
  const d = Math.floor(h / 24);
  if (d < 7) return `${d}d`;
  return new Date(iso).toLocaleDateString();
}

export default function MemberNav({ initials, name, email }: Props) {
  const pathname = usePathname();
  const [openMenu, setOpenMenu] = useState<null | "avatar" | "search" | "notif" | "mobile">(null);

  const [unreadCount, setUnreadCount] = useState(0);
  const [notifs, setNotifs] = useState<NotifItem[]>([]);
  const [loadingNotifs, setLoadingNotifs] = useState(false);

  const [searchQuery, setSearchQuery] = useState("");
  const [searchResults, setSearchResults] = useState<SearchResults | null>(null);
  const [searching, setSearching] = useState(false);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchDebounce = useRef<number | undefined>(undefined);

  const rootRef = useRef<HTMLDivElement>(null);

  // Poll unread notification count
  useEffect(() => {
    let mounted = true;
    async function fetchCount() {
      try {
        const res = await apiFetch("/member/notifications/unread-count");
        if (!res.ok) return;
        const data = await res.json();
        if (mounted) setUnreadCount(data.data?.count ?? 0);
      } catch { /* ignore */ }
    }
    fetchCount();
    const id = window.setInterval(fetchCount, 60_000);
    return () => { mounted = false; window.clearInterval(id); };
  }, []);

  // Close menus on outside click
  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) {
        setOpenMenu(null);
      }
    }
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, []);

  // ESC to close, Cmd/Ctrl+K to open search
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") { setOpenMenu(null); return; }
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setOpenMenu("search");
      }
    }
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, []);

  // Focus search input when opened
  useEffect(() => {
    if (openMenu === "search") {
      setTimeout(() => searchInputRef.current?.focus(), 50);
    }
  }, [openMenu]);

  // Load notifications when bell dropdown opens
  useEffect(() => {
    if (openMenu !== "notif") return;
    let mounted = true;
    (async () => {
      setLoadingNotifs(true);
      try {
        const res = await apiFetch("/member/notifications?filter=all");
        const data = await res.json();
        if (res.ok && mounted) {
          const list = data.data?.notifications?.data ?? data.data?.notifications ?? [];
          setNotifs(list.slice(0, 6));
        }
      } catch { /* ignore */ }
      if (mounted) setLoadingNotifs(false);
    })();
    return () => { mounted = false; };
  }, [openMenu]);

  // Debounced search
  useEffect(() => {
    if (openMenu !== "search") return;
    window.clearTimeout(searchDebounce.current);
    if (!searchQuery.trim()) {
      setSearchResults(null);
      return;
    }
    searchDebounce.current = window.setTimeout(async () => {
      setSearching(true);
      try {
        const res = await apiFetch(`/search?q=${encodeURIComponent(searchQuery)}&limit=5`);
        const data = await res.json();
        if (res.ok) {
          const r = data.data?.results ?? { articles: [], organizations: [] };
          setSearchResults({
            articles: (r.articles ?? []).slice(0, 5),
            organizations: (r.organizations ?? []).slice(0, 4),
          });
        }
      } catch { /* ignore */ }
      setSearching(false);
    }, 220);
    return () => window.clearTimeout(searchDebounce.current);
  }, [searchQuery, openMenu]);

  async function markAllRead() {
    try {
      await apiFetch("/member/notifications/read-all", { method: "POST" });
      setNotifs((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
      setUnreadCount(0);
    } catch { /* ignore */ }
  }

  async function markOneRead(n: NotifItem) {
    if (n.read_at) return;
    try {
      await apiFetch(`/member/notifications/${n.public_id}/read`, { method: "POST" });
      setNotifs((prev) => prev.map((x) => x.public_id === n.public_id ? { ...x, read_at: new Date().toISOString() } : x));
      setUnreadCount((c) => Math.max(0, c - 1));
    } catch { /* ignore */ }
  }

  async function handleLogout() {
    try { await apiFetch("/auth/logout", { method: "POST" }); } catch { /* noop */ }
    clearToken();
    window.location.href = "/login";
  }

  function submitSearchToFullPage() {
    if (!searchQuery.trim()) return;
    window.location.href = `/search?q=${encodeURIComponent(searchQuery)}`;
  }

  const isMac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/i.test(navigator.platform ?? "");

  return (
    <nav className="hm-nav">
      <div className="hm-nav-inner" ref={rootRef}>
        {/* Brand */}
        <a href="/dashboard" className="hm-nav-brand">
          <span className="hm-nav-mark">
            <span className="hm-nm-orange" />
            <span className="hm-nm-teal" />
          </span>
          <span className="hm-nav-wordmark">NarLit</span>
        </a>

        {/* Primary links (desktop) */}
        <div className="hm-nav-links">
          {PRIMARY_LINKS.map((l) => {
            const active = pathname === l.href || pathname.startsWith(l.href + "/");
            return (
              <a
                key={l.href}
                href={l.href}
                className={`hm-nav-link${active ? " hm-nav-link-active" : ""}`}
              >
                {l.label}
              </a>
            );
          })}
        </div>

        {/* Right-side actions */}
        <div className="hm-nav-user">
          <div className="hm-nav-actions">
            {/* Search */}
            <div style={{ position: "relative" }}>
              <button
                type="button"
                className={`hm-icon-btn${openMenu === "search" ? " hm-icon-btn-active" : ""}`}
                onClick={() => setOpenMenu(openMenu === "search" ? null : "search")}
                aria-label="Search"
                aria-expanded={openMenu === "search"}
              >
                <SearchIcon />
              </button>
              {openMenu === "search" && (
                <div className="hm-panel-dropdown hm-search-dropdown">
                  <div className="hm-search-input-wrap">
                    <SearchIcon />
                    <input
                      ref={searchInputRef}
                      type="search"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      onKeyDown={(e) => e.key === "Enter" && submitSearchToFullPage()}
                      placeholder="Search stories, nonprofits…"
                      className="hm-search-input"
                    />
                    <span className="hm-search-kbd">{isMac ? "⌘" : "Ctrl"}K</span>
                    <button
                      type="button"
                      className="hm-icon-btn"
                      style={{ width: 28, height: 28 }}
                      onClick={() => setOpenMenu(null)}
                      aria-label="Close search"
                    >
                      <CloseIcon />
                    </button>
                  </div>

                  <div className="hm-search-results">
                    {!searchQuery.trim() && (
                      <div className="hm-search-empty">
                        Search for stories, nonprofits, and categories.
                      </div>
                    )}
                    {searchQuery.trim() && searching && (
                      <div className="hm-search-empty">Searching…</div>
                    )}
                    {searchQuery.trim() && !searching && searchResults && (
                      <>
                        {(searchResults.articles.length + searchResults.organizations.length) === 0 && (
                          <div className="hm-search-empty">No results for &quot;{searchQuery}&quot;</div>
                        )}
                        {searchResults.organizations.length > 0 && (
                          <>
                            <div className="hm-search-section-title">Nonprofits</div>
                            {searchResults.organizations.map((o) => (
                              <a key={o.public_id} href={`/organizations/${o.public_id}`} className="hm-search-item">
                                <div className="hm-search-item-icon">
                                  {o.organization_name.slice(0, 2).toUpperCase()}
                                </div>
                                <div style={{ minWidth: 0, flex: 1 }}>
                                  <p className="hm-search-item-title">{o.organization_name}</p>
                                  {o.category && <p className="hm-search-item-sub">{o.category}</p>}
                                </div>
                              </a>
                            ))}
                          </>
                        )}
                        {searchResults.articles.length > 0 && (
                          <>
                            <div className="hm-search-section-title">Stories</div>
                            {searchResults.articles.map((a) => (
                              <a key={a.public_id} href={`/articles/${a.public_id}`} className="hm-search-item">
                                <div className="hm-search-item-icon" style={{ background: "rgba(17,182,200,0.15)", color: "var(--teal)" }}>
                                  📰
                                </div>
                                <div style={{ minWidth: 0, flex: 1 }}>
                                  <p className="hm-search-item-title">{a.title}</p>
                                  {a.organization.name && <p className="hm-search-item-sub">{a.organization.name}</p>}
                                </div>
                              </a>
                            ))}
                          </>
                        )}
                      </>
                    )}
                  </div>

                  {searchQuery.trim() && (
                    <div className="hm-panel-dropdown-footer">
                      <button
                        type="button"
                        onClick={submitSearchToFullPage}
                        className="hm-nav-dd-item"
                        style={{ textAlign: "center" }}
                      >
                        See all results for &quot;{searchQuery}&quot; →
                      </button>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Notifications */}
            <div style={{ position: "relative" }}>
              <button
                type="button"
                className={`hm-icon-btn${openMenu === "notif" ? " hm-icon-btn-active" : ""}`}
                onClick={() => setOpenMenu(openMenu === "notif" ? null : "notif")}
                aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ""}`}
                aria-expanded={openMenu === "notif"}
              >
                <BellIcon />
                {unreadCount > 0 && (
                  <span className="hm-icon-badge">
                    {unreadCount > 99 ? "99+" : unreadCount}
                  </span>
                )}
              </button>
              {openMenu === "notif" && (
                <div className="hm-panel-dropdown hm-notif-dropdown">
                  <div className="hm-panel-dropdown-header">
                    <h3 className="hm-panel-dropdown-title">Notifications</h3>
                    {unreadCount > 0 && (
                      <button
                        type="button"
                        onClick={markAllRead}
                        style={{
                          background: "transparent",
                          border: 0,
                          color: "var(--teal)",
                          cursor: "pointer",
                          fontSize: "0.75rem",
                          fontWeight: 700,
                          fontFamily: "inherit",
                          display: "inline-flex",
                          gap: 4,
                          alignItems: "center",
                        }}
                      >
                        <CheckIcon /> Mark all read
                      </button>
                    )}
                  </div>

                  <div className="hm-notif-list">
                    {loadingNotifs && <div className="hm-search-empty">Loading…</div>}
                    {!loadingNotifs && notifs.length === 0 && (
                      <div className="hm-search-empty">
                        You&apos;re all caught up 🎉
                      </div>
                    )}
                    {!loadingNotifs && notifs.map((n) => (
                      <a
                        key={n.public_id}
                        href={n.action_url ?? "/notifications"}
                        onClick={() => markOneRead(n)}
                        className={`hm-notif-item${!n.read_at ? " hm-notif-item-unread" : ""}`}
                      >
                        <div className="hm-notif-item-icon">
                          {n.icon ?? NOTIF_TYPE_ICONS[n.type] ?? "🔔"}
                        </div>
                        <div className="hm-notif-item-body">
                          <p className="hm-notif-item-title">{n.title}</p>
                          {n.body && <p className="hm-notif-item-desc">{n.body}</p>}
                          <p className="hm-notif-item-time">{timeAgo(n.created_at)}</p>
                        </div>
                        {!n.read_at && <span className="hm-notif-item-dot" />}
                      </a>
                    ))}
                  </div>

                  <div className="hm-panel-dropdown-footer">
                    <a href="/notifications" className="hm-nav-dd-item" style={{ textAlign: "center" }}>
                      View all notifications →
                    </a>
                  </div>
                </div>
              )}
            </div>

            {/* Mobile menu toggle */}
            <button
              type="button"
              className={`hm-icon-btn hm-nav-mobile-only${openMenu === "mobile" ? " hm-icon-btn-active" : ""}`}
              onClick={() => setOpenMenu(openMenu === "mobile" ? null : "mobile")}
              aria-label="Menu"
            >
              <MenuIcon />
            </button>

            {openMenu === "mobile" && (
              <div className="hm-panel-dropdown" style={{ minWidth: 200 }}>
                {PRIMARY_LINKS.map((l) => (
                  <a key={l.href} href={l.href} className="hm-nav-dd-item">{l.label}</a>
                ))}
              </div>
            )}
          </div>

          {/* Avatar */}
          <button
            type="button"
            className="hm-nav-avatar"
            onClick={() => setOpenMenu(openMenu === "avatar" ? null : "avatar")}
            aria-label="Account menu"
            aria-expanded={openMenu === "avatar"}
            style={{ border: 0 }}
          >
            {initials ?? "NL"}
          </button>
          {openMenu === "avatar" && (
            <div className="hm-nav-dropdown">
              {name && <div className="hm-nav-dd-name">{name}</div>}
              {email && <div className="hm-nav-dd-email">{email}</div>}
              <div className="hm-nav-dd-divider" />
              {DROPDOWN_LINKS.map((l) => (
                <a key={l.href} href={l.href} className="hm-nav-dd-item">
                  <span style={{ marginRight: 8 }}>{l.icon}</span> {l.label}
                </a>
              ))}
              <div className="hm-nav-dd-divider" />
              <button className="hm-nav-dd-item" onClick={handleLogout}>
                <span style={{ marginRight: 8 }}>🚪</span> Sign out
              </button>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
