"use client";

import { useEffect, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface User {
  full_name: string;
  email: string;
}

interface Story {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  organization: { public_id: string | null; name: string | null };
  read_time_minutes: number | null;
  is_read: boolean;
  cta_label: string;
}

interface FundingRow {
  organization_public_id: string | null;
  organization_name: string | null;
  reads: number;
  percent: number;
}

interface SubscriptionSlice {
  percent: number;
  amount: string;
}

interface Dashboard {
  member: {
    name: string | null;
    initials: string;
    subscription_status: string;
    subscription_active: boolean;
  };
  impact: {
    donated_this_month: string;
    donated_delta_from_last_month: string;
    articles_read: number;
    articles_read_label: string;
    nonprofits_funded_this_month: number;
    day_streak: number;
  };
  stories: Story[];
  subscription_breakdown: {
    subscription_amount: string;
    currency: string;
    nonprofits: SubscriptionSlice;
    operations: SubscriptionSlice;
    growth: SubscriptionSlice;
  };
  funding_breakdown: FundingRow[];
}

const FUNDING_COLORS = ["var(--orange)", "var(--teal)", "#7c5cbf", "#e0b84a", "#4a8fb3"];

export default function DashboardPage() {
  const [user, setUser] = useState<User | null>(null);
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [checking, setChecking] = useState(true);
  const [navOpen, setNavOpen] = useState(false);
  const [markingId, setMarkingId] = useState<string | null>(null);

  async function loadDashboard() {
    try {
      const res = await apiFetch("/member/dashboard");
      const data = await res.json();
      setDashboard(data.data?.dashboard ?? null);
    } catch {
      /* ignore — page falls back to empty state */
    }
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      await Promise.all([
        (async () => {
          try {
            const res = await apiFetch("/auth/me");
            const data = await res.json();
            setUser(data.data?.user ?? data.data ?? null);
          } catch {
            /* ignore — name just won't show */
          }
        })(),
        loadDashboard(),
      ]);
      setChecking(false);
    });
  }, []);

  async function handleLogout() {
    try { await apiFetch("/auth/logout", { method: "POST" }); } catch { /* noop */ }
    clearToken();
    window.location.href = "/login";
  }

  async function handleReadStory(story: Story) {
    if (markingId) return;
    setMarkingId(story.public_id);
    try {
      await apiFetch(`/member/articles/${story.public_id}/read`, {
        method: "POST",
        body: JSON.stringify({ read_percent: 100 }),
      });
      await loadDashboard();
    } catch {
      /* ignore — button just won't update */
    } finally {
      setMarkingId(null);
    }
  }

  const firstName =
    dashboard?.member?.name?.split(" ")[0] ??
    user?.full_name?.split(" ")[0] ??
    "there";

  const initials =
    dashboard?.member?.initials ??
    (user?.full_name
      ? user.full_name.split(" ").map((w) => w[0]).slice(0, 2).join("").toUpperCase()
      : "NL");

  const displayName = user?.full_name ?? dashboard?.member?.name ?? "Member";
  const isActive = dashboard?.member?.subscription_active ?? false;

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
      </div>
    );
  }

  const impact = dashboard?.impact;
  const stories = dashboard?.stories ?? [];
  const breakdown = dashboard?.subscription_breakdown;
  const funding = dashboard?.funding_breakdown ?? [];
  const subscriptionAmount = breakdown?.subscription_amount ?? "0.00";
  const nonprofitAmount = breakdown?.nonprofits?.amount ?? "0.00";

  return (
    <div className="hm-shell" suppressHydrationWarning>
      {/* ── Navbar ── */}
      <nav className="hm-nav">
        <div className="hm-nav-inner">
          {/* Brand */}
          <a href="/dashboard" className="hm-nav-brand">
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span className="hm-nav-wordmark">NarLit</span>
          </a>

          {/* Desktop links */}
          <div className="hm-nav-links">
            <a href="/dashboard" className="hm-nav-link hm-nav-link-active">Home</a>
            <a href="/articles" className="hm-nav-link">Explore</a>
            <a href="#" className="hm-nav-link">My Impact</a>
          </div>

          {/* User menu */}
          <div className="hm-nav-user">
            <div className="hm-nav-avatar" onClick={() => setNavOpen(!navOpen)}>{initials}</div>
            {navOpen && (
              <div className="hm-nav-dropdown">
                <div className="hm-nav-dd-name">{displayName}</div>
                <div className="hm-nav-dd-email">{user?.email ?? ""}</div>
                <div className="hm-nav-dd-divider" />
                <button className="hm-nav-dd-item" onClick={handleLogout}>Sign out</button>
              </div>
            )}
          </div>
        </div>
      </nav>

      {/* ── Main ── */}
      <main className="hm-main">

        {/* ── Welcome Banner ── */}
        <section className="hm-welcome">
          <div className="hm-welcome-inner">
            <div>
              <p className="hm-welcome-kicker">Welcome back</p>
              <h1 className="hm-welcome-title">
                Good to see you,{" "}
                <span className="hm-welcome-name">{firstName}.</span>
              </h1>
              <p className="hm-welcome-sub">
                {isActive
                  ? "Your subscription is active — every article you read makes a difference."
                  : "Activate your subscription to start making an impact."}
              </p>
            </div>
            <div className="hm-welcome-badge">
              <span className="hm-badge-dot" />
              {isActive ? "Active Member" : "Inactive"}
            </div>
          </div>
        </section>

        {/* ── Impact Stats ── */}
        <section className="hm-section">
          <h2 className="hm-section-title">Your Impact</h2>
          <div className="hm-stats-grid">
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-orange">$</div>
              <div className="hm-stat-value">${impact?.donated_this_month ?? "0.00"}</div>
              <div className="hm-stat-label">Donated This Month</div>
              <div className="hm-stat-hint">
                {impact ? `${impact.donated_delta_from_last_month.startsWith("-") ? "" : "+"}$${impact.donated_delta_from_last_month} from last month` : "—"}
              </div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-teal">📖</div>
              <div className="hm-stat-value">{impact?.articles_read ?? 0}</div>
              <div className="hm-stat-label">Articles Read</div>
              <div className="hm-stat-hint">{impact?.articles_read_label ?? "All time"}</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-purple">🤝</div>
              <div className="hm-stat-value">{impact?.nonprofits_funded_this_month ?? 0}</div>
              <div className="hm-stat-label">Nonprofits Funded</div>
              <div className="hm-stat-hint">This month</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-orange">🔥</div>
              <div className="hm-stat-value">{impact?.day_streak ?? 0}</div>
              <div className="hm-stat-label">Day Streak</div>
              <div className="hm-stat-hint">Keep it going!</div>
            </div>
          </div>
        </section>

        {/* ── Articles + Impact Split ── */}
        <div className="hm-content-grid">

          {/* Featured Articles */}
          <section className="hm-section">
            <div className="hm-section-header">
              <h2 className="hm-section-title">Stories to Read</h2>
              <a href="/articles" className="hm-see-all">See all →</a>
            </div>
            <div className="hm-articles">
              {stories.length === 0 && (
                <p className="hm-empty">No stories available right now — check back soon.</p>
              )}
              {stories.map((a) => (
                <article key={a.public_id} className={`hm-article-card${a.is_read ? " hm-article-read" : ""}`}>
                  <div className="hm-article-top">
                    <span className="hm-article-org">{a.organization.name ?? "NarLit"}</span>
                    {a.category && <span className="hm-article-cat">{a.category}</span>}
                    {a.is_read && <span className="hm-article-done">✓ Read</span>}
                  </div>
                  <h3 className="hm-article-title">{a.title}</h3>
                  <p className="hm-article-excerpt">{a.excerpt}</p>
                  <div className="hm-article-footer">
                    <span className="hm-article-time">
                      {a.read_time_minutes ? `${a.read_time_minutes} min read` : "Quick read"}
                    </span>
                    <button
                      type="button"
                      className="hm-article-btn"
                      onClick={() => handleReadStory(a)}
                      disabled={markingId === a.public_id}
                    >
                      {markingId === a.public_id ? "Saving…" : a.cta_label}
                    </button>
                  </div>
                </article>
              ))}
            </div>
          </section>

          {/* Right column */}
          <div className="hm-side">

            {/* Where your money goes */}
            <section className="hm-panel">
              <h2 className="hm-panel-title">Where Your ${subscriptionAmount} Goes</h2>
              <p className="hm-panel-sub">This month's breakdown</p>
              <div className="hm-rule-bars">
                <div className="hm-rule-row">
                  <span className="hm-rule-label">Nonprofits</span>
                  <div className="hm-rule-track">
                    <div
                      className="hm-rule-fill hm-rule-fill-orange"
                      style={{ width: `${breakdown?.nonprofits.percent ?? 0}%` }}
                    />
                  </div>
                  <span className="hm-rule-pct">{breakdown?.nonprofits.percent ?? 0}%</span>
                </div>
                <div className="hm-rule-row">
                  <span className="hm-rule-label">Operations</span>
                  <div className="hm-rule-track">
                    <div
                      className="hm-rule-fill hm-rule-fill-teal"
                      style={{ width: `${breakdown?.operations.percent ?? 0}%` }}
                    />
                  </div>
                  <span className="hm-rule-pct">{breakdown?.operations.percent ?? 0}%</span>
                </div>
                <div className="hm-rule-row">
                  <span className="hm-rule-label">Growth</span>
                  <div className="hm-rule-track">
                    <div
                      className="hm-rule-fill hm-rule-fill-purple"
                      style={{ width: `${breakdown?.growth.percent ?? 0}%` }}
                    />
                  </div>
                  <span className="hm-rule-pct">{breakdown?.growth.percent ?? 0}%</span>
                </div>
              </div>
              <div className="hm-rule-note">
                ${nonprofitAmount} of your ${subscriptionAmount} supports nonprofits based on reading engagement.
              </div>
            </section>

            {/* Funding breakdown */}
            <section className="hm-panel">
              <h2 className="hm-panel-title">Your Funding Breakdown</h2>
              <p className="hm-panel-sub">Based on articles read this month</p>
              <div className="hm-impact-list">
                {funding.length === 0 && (
                  <p className="hm-empty">Read your first story to start funding nonprofits.</p>
                )}
                {funding.map((item, idx) => (
                  <div key={item.organization_public_id ?? idx} className="hm-impact-row">
                    <div className="hm-impact-info">
                      <span className="hm-impact-org">{item.organization_name ?? "Unknown"}</span>
                      <span className="hm-impact-reads">{item.reads} reads</span>
                    </div>
                    <div className="hm-impact-bar-wrap">
                      <div
                        className="hm-impact-bar"
                        style={{
                          width: `${item.percent}%`,
                          background: FUNDING_COLORS[idx % FUNDING_COLORS.length],
                        }}
                      />
                    </div>
                    <span className="hm-impact-pct">{item.percent}%</span>
                  </div>
                ))}
              </div>
            </section>

          </div>
        </div>
      </main>
    </div>
  );
}
