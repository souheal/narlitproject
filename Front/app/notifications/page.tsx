"use client";

import { useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { safeHref } from "@/lib/safeUrl";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";

interface Notification {
  public_id: string;
  type: string;
  title: string;
  body: string | null;
  action_url: string | null;
  action_label: string | null;
  icon: string | null;
  read_at: string | null;
  created_at: string;
}

interface User { full_name: string; email: string }

const TYPE_ICONS: Record<string, string> = {
  new_article: "📰",
  achievement: "🏆",
  payment: "💳",
  system: "⚙️",
  organization: "🏢",
  milestone: "🎉",
};

export default function NotificationsPage() {
  const [user, setUser] = useState<User | null>(null);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [filter, setFilter] = useState<"all" | "unread">("all");
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [isPending, startTransition] = useTransition();

  async function load() {
    setLoading(true);
    try {
      const res = await apiFetch(`/member/notifications?filter=${filter}`);
      const data = await res.json();
      if (res.ok) setNotifications(data.data?.notifications?.data ?? data.data?.notifications ?? []);
      else setError(data?.message ?? "Failed to load notifications.");
    } catch { setError("Failed to load notifications."); }
    setLoading(false);
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      setChecking(false);
      apiFetch("/auth/me")
        .then((r) => r.json())
        .then((d) => setUser(d.data?.user ?? d.data ?? null))
        .catch(() => {});
      load();
    });
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [filter]);

  function markRead(n: Notification) {
    if (n.read_at) return;
    startTransition(async () => {
      try {
        await apiFetch(`/member/notifications/${n.public_id}/read`, { method: "POST" });
        setNotifications((prev) => prev.map((x) => x.public_id === n.public_id ? { ...x, read_at: new Date().toISOString() } : x));
      } catch { /* ignore */ }
    });
  }

  function markAllRead() {
    startTransition(async () => {
      try {
        await apiFetch("/member/notifications/read-all", { method: "POST" });
        setNotifications((prev) => prev.map((x) => ({ ...x, read_at: x.read_at ?? new Date().toISOString() })));
      } catch { /* ignore */ }
    });
  }

  if (checking) {
    return (
      <BrandLoader />
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";
  const unreadCount = notifications.filter((n) => !n.read_at).length;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 780, margin: "0 auto" }}>
          <div className="hm-section-header">
            <h2 className="hm-section-title">Notifications</h2>
            {unreadCount > 0 && (
              <button type="button" className="hm-pager-btn" onClick={markAllRead} disabled={isPending}>
                Mark all read
              </button>
            )}
          </div>

          <div className="admin-tabs" style={{ marginBottom: 20 }}>
            <button className={`admin-tab ${filter === "all" ? "admin-tab-active" : ""}`} onClick={() => setFilter("all")}>All</button>
            <button className={`admin-tab ${filter === "unread" ? "admin-tab-active" : ""}`} onClick={() => setFilter("unread")}>
              Unread {unreadCount > 0 && `(${unreadCount})`}
            </button>
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          {loading && (
            <div style={{ display: "flex", flexDirection: "column", gap: 8 }} aria-busy="true" aria-label="Loading notifications">
              {Array.from({ length: 5 }).map((_, i) => (
                <div
                  key={i}
                  style={{
                    padding: 16,
                    border: "1px solid var(--line)",
                    borderRadius: 12,
                    background: "var(--panel)",
                    display: "flex",
                    gap: 14,
                    alignItems: "flex-start",
                  }}
                >
                  <Skeleton width={32} height={32} radius={16} />
                  <div style={{ flex: 1 }}>
                    <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                      <Skeleton width="55%" height={14} />
                      <Skeleton width={90} height={10} />
                    </div>
                    <div style={{ marginTop: 8 }}>
                      <SkeletonText lines={2} />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {!loading && notifications.length === 0 && <p className="hm-empty">You&apos;re all caught up! 🎉</p>}

          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {notifications.map((n) => (
              <div
                key={n.public_id}
                onClick={() => markRead(n)}
                style={{
                  padding: 16,
                  border: "1px solid var(--line)",
                  borderRadius: 12,
                  background: n.read_at ? "var(--panel)" : "rgba(255, 138, 71, 0.06)",
                  cursor: n.read_at ? "default" : "pointer",
                  display: "flex",
                  gap: 14,
                  alignItems: "flex-start",
                  transition: "background 0.15s",
                }}
              >
                <div style={{ fontSize: "1.6rem", flexShrink: 0 }}>
                  {n.icon ?? TYPE_ICONS[n.type] ?? "🔔"}
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 8 }}>
                    <h3 style={{ margin: 0, fontSize: "0.95rem" }}>
                      {n.title}
                      {!n.read_at && (
                        <span style={{
                          marginLeft: 8,
                          width: 8, height: 8, borderRadius: 4,
                          background: "var(--orange)",
                          display: "inline-block",
                        }} />
                      )}
                    </h3>
                    <span style={{ fontSize: "0.7rem", color: "var(--muted)", whiteSpace: "nowrap" }}>
                      {new Date(n.created_at).toLocaleString()}
                    </span>
                  </div>
                  {n.body && <p style={{ margin: "4px 0 0", color: "var(--muted)", fontSize: "0.85rem" }}>{n.body}</p>}
                  {n.action_url && (
                    <a
                      href={safeHref(n.action_url)}
                      className="su-link"
                      style={{ fontSize: "0.8rem", marginTop: 8, display: "inline-block" }}
                      onClick={(e) => e.stopPropagation()}
                    >
                      {n.action_label ?? "View"} →
                    </a>
                  )}
                </div>
              </div>
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}
