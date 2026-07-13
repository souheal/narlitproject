"use client";

import { useEffect, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface OrgProfile {
  organization_name: string;
  email: string;
  verification_status: string;
  irs_verified: boolean;
  initials: string;
}

interface Stat {
  label: string;
  value: string | number;
  hint?: string;
}

interface RecentArticle {
  public_id: string;
  title: string;
  status: string;
  total_reads: number;
  total_unique_reads: number;
  published_at: string | null;
}

interface OrgDashboard {
  organization: OrgProfile;
  stats: {
    total_articles: number;
    published_articles: number;
    pending_articles: number;
    total_reads_this_month: number;
    unique_readers_this_month: number;
    earned_this_month: string;
    earned_all_time: string;
    pending_payout: string;
  };
  recent_articles: RecentArticle[];
  monthly_reads: { month: string; reads: number }[];
}

const STATUS_BADGE: Record<string, string> = {
  draft: "Draft",
  pending_review: "Pending review",
  approved: "Approved",
  rejected: "Rejected",
  published: "Published",
  archived: "Archived",
};

export default function OrgDashboardPage() {
  const [data, setData] = useState<OrgDashboard | null>(null);
  const [checking, setChecking] = useState(true);
  const [navOpen, setNavOpen] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      try {
        const res = await apiFetch("/organization/dashboard");
        const payload = await res.json();
        if (!res.ok) {
          setError(payload?.message ?? "Failed to load dashboard.");
        } else {
          setData(payload.data?.dashboard ?? null);
        }
      } catch {
        setError("Failed to load dashboard.");
      }
      setChecking(false);
    });
  }, []);

  async function handleLogout() {
    try { await apiFetch("/auth/logout", { method: "POST" }); } catch { /* noop */ }
    clearToken();
    window.location.href = "/login";
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
      </div>
    );
  }

  const org = data?.organization;
  const s = data?.stats;
  const recent = data?.recent_articles ?? [];
  const monthly = data?.monthly_reads ?? [];
  const maxMonthly = Math.max(1, ...monthly.map((m) => m.reads));

  const stats: Stat[] = [
    { label: "Earned This Month", value: `$${s?.earned_this_month ?? "0.00"}`, hint: `$${s?.earned_all_time ?? "0.00"} all time` },
    { label: "Reads This Month", value: s?.total_reads_this_month ?? 0, hint: `${s?.unique_readers_this_month ?? 0} unique readers` },
    { label: "Published Articles", value: s?.published_articles ?? 0, hint: `${s?.pending_articles ?? 0} pending review` },
    { label: "Pending Payout", value: `$${s?.pending_payout ?? "0.00"}`, hint: "Paid monthly" },
  ];

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <nav className="hm-nav">
        <div className="hm-nav-inner">
          <a href="/organization/dashboard" className="hm-nav-brand">
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span className="hm-nav-wordmark">NarLit · Org</span>
          </a>
          <div className="hm-nav-links">
            <a href="/organization/dashboard" className="hm-nav-link hm-nav-link-active">Overview</a>
            <a href="/organization/articles" className="hm-nav-link">Articles</a>
            <a href="/organization/payouts" className="hm-nav-link">Payouts</a>
          </div>
          <div className="hm-nav-user">
            <div className="hm-nav-avatar" onClick={() => setNavOpen(!navOpen)}>
              {org?.initials ?? "OR"}
            </div>
            {navOpen && (
              <div className="hm-nav-dropdown">
                <div className="hm-nav-dd-name">{org?.organization_name ?? "Organization"}</div>
                <div className="hm-nav-dd-email">{org?.email ?? ""}</div>
                <div className="hm-nav-dd-divider" />
                <a className="hm-nav-dd-item" href="/profile">Profile</a>
                <button className="hm-nav-dd-item" onClick={handleLogout}>Sign out</button>
              </div>
            )}
          </div>
        </div>
      </nav>

      <main className="hm-main">
        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        <section className="hm-welcome">
          <div className="hm-welcome-inner">
            <div>
              <p className="hm-welcome-kicker">Welcome back</p>
              <h1 className="hm-welcome-title">
                <span className="hm-welcome-name">{org?.organization_name ?? "Your organization"}</span>
              </h1>
              <p className="hm-welcome-sub">
                {org?.verification_status === "approved"
                  ? "You're approved — publish stories and start earning."
                  : org?.verification_status === "pending"
                    ? "Your organization is under review. You can still draft articles."
                    : "Complete your verification to start publishing."}
              </p>
            </div>
            <div className="hm-welcome-badge">
              <span className="hm-badge-dot" />
              {org?.verification_status === "approved" ? "Approved" : (STATUS_BADGE[org?.verification_status ?? ""] ?? "Pending")}
            </div>
          </div>
        </section>

        <section className="hm-section">
          <h2 className="hm-section-title">Overview</h2>
          <div className="hm-stats-grid">
            {stats.map((stat, idx) => (
              <div key={idx} className="hm-stat-card">
                <div className={`hm-stat-icon hm-stat-icon-${["orange", "teal", "purple", "orange"][idx]}`}>
                  {["$", "📖", "📝", "💰"][idx]}
                </div>
                <div className="hm-stat-value">{stat.value}</div>
                <div className="hm-stat-label">{stat.label}</div>
                {stat.hint && <div className="hm-stat-hint">{stat.hint}</div>}
              </div>
            ))}
          </div>
        </section>

        <div className="hm-content-grid">
          <section className="hm-section">
            <div className="hm-section-header">
              <h2 className="hm-section-title">Recent Articles</h2>
              <a href="/organization/articles" className="hm-see-all">See all →</a>
            </div>
            <div className="hm-articles">
              {recent.length === 0 && (
                <p className="hm-empty">
                  No articles yet.{" "}
                  <a href="/organization/articles/new" className="su-link">Submit your first one</a>.
                </p>
              )}
              {recent.map((a) => (
                <article key={a.public_id} className="hm-article-card">
                  <div className="hm-article-top">
                    <span className="hm-article-org">{STATUS_BADGE[a.status] ?? a.status}</span>
                    {a.published_at && (
                      <span className="hm-article-cat">
                        {new Date(a.published_at).toLocaleDateString()}
                      </span>
                    )}
                  </div>
                  <h3 className="hm-article-title">{a.title}</h3>
                  <div className="hm-article-footer">
                    <span className="hm-article-time">
                      {a.total_reads} reads · {a.total_unique_reads} unique
                    </span>
                    <a href={`/organization/articles/${a.public_id}`} className="hm-article-btn">
                      Manage →
                    </a>
                  </div>
                </article>
              ))}
            </div>
          </section>

          <div className="hm-side">
            <section className="hm-panel">
              <h2 className="hm-panel-title">Monthly Reads</h2>
              <p className="hm-panel-sub">Last 6 months of engagement</p>
              <div className="hm-impact-list">
                {monthly.length === 0 && <p className="hm-empty">No data yet.</p>}
                {monthly.map((m) => (
                  <div key={m.month} className="hm-impact-row">
                    <div className="hm-impact-info">
                      <span className="hm-impact-org">{m.month}</span>
                      <span className="hm-impact-reads">{m.reads} reads</span>
                    </div>
                    <div className="hm-impact-bar-wrap">
                      <div
                        className="hm-impact-bar"
                        style={{ width: `${Math.round((m.reads / maxMonthly) * 100)}%`, background: "var(--teal)" }}
                      />
                    </div>
                    <span className="hm-impact-pct">{Math.round((m.reads / maxMonthly) * 100)}%</span>
                  </div>
                ))}
              </div>
            </section>

            <section className="hm-panel">
              <h2 className="hm-panel-title">Quick actions</h2>
              <p className="hm-panel-sub">Manage your presence on NarLit</p>
              <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 12 }}>
                <a href="/organization/articles/new" className="hm-article-btn" style={{ textAlign: "center" }}>
                  + Submit new article
                </a>
                <a href="/organization/payouts" className="hm-pager-btn" style={{ textAlign: "center", textDecoration: "none" }}>
                  View payouts
                </a>
                <a href="/profile" className="hm-pager-btn" style={{ textAlign: "center", textDecoration: "none" }}>
                  Edit organization profile
                </a>
              </div>
            </section>
          </div>
        </div>
      </main>
    </div>
  );
}
