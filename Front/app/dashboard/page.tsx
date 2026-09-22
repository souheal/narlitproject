"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import ImpactScene from "@/components/ImpactScene";

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
  const [loading, setLoading] = useState(true);

  async function loadDashboard() {
    setLoading(true);
    try {
      const res = await apiFetch("/member/dashboard");
      const data = await res.json();
      setDashboard(data.data?.dashboard ?? null);
    } catch {
      /* ignore — page falls back to empty state */
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      setChecking(false);
      apiFetch("/auth/me")
        .then((r) => r.json())
        .then((d) => setUser(d.data?.user ?? d.data ?? null))
        .catch(() => {});
      loadDashboard();
    });
  }, []);

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
      <div className="hm-shell" suppressHydrationWarning>
        <MemberNav initials="…" />
        <main className="hm-main" aria-busy="true" aria-label="Loading dashboard">
          <section className="hm-welcome">
            <div className="hm-welcome-inner">
              <div style={{ flex: 1 }}>
                <Skeleton width={110} height={12} />
                <div style={{ marginTop: 10 }}>
                  <Skeleton width="65%" height={28} />
                </div>
                <div style={{ marginTop: 10 }}>
                  <Skeleton width="85%" height={14} />
                </div>
              </div>
              <Skeleton width={120} height={32} radius={999} />
            </div>
          </section>
          <section className="hm-section">
            <Skeleton width={140} height={20} />
            <div className="hm-stats-grid" style={{ marginTop: 16 }}>
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="hm-stat-card">
                  <Skeleton width={40} height={40} radius={12} />
                  <div style={{ marginTop: 10 }}>
                    <Skeleton width="55%" height={22} />
                  </div>
                  <div style={{ marginTop: 8 }}>
                    <Skeleton width="70%" height={12} />
                  </div>
                </div>
              ))}
            </div>
          </section>
        </main>
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
      <MemberNav initials={initials} name={displayName} email={user?.email} />

      <main className="hm-main">

        {/* ── Welcome Banner ── */}
        <section className="hm-welcome hm-welcome-hero">
          <div className="hm-welcome-scene">
            <ImpactScene />
          </div>
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
          {loading ? (
            <div className="hm-stats-grid" aria-busy="true" aria-label="Loading impact stats">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="hm-stat-card">
                  <Skeleton width={40} height={40} radius={12} />
                  <div style={{ marginTop: 10 }}>
                    <Skeleton width="55%" height={22} />
                  </div>
                  <div style={{ marginTop: 8 }}>
                    <Skeleton width="70%" height={12} />
                  </div>
                  <div style={{ marginTop: 6 }}>
                    <Skeleton width="50%" height={10} />
                  </div>
                </div>
              ))}
            </div>
          ) : (
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
          )}
        </section>

        {/* ── Articles + Impact Split ── */}
        <div className="hm-content-grid">

          {/* Featured Articles */}
          <section className="hm-section">
            <div className="hm-section-header">
              <h2 className="hm-section-title">Stories to Read</h2>
              <a href="/articles" className="hm-see-all">See all →</a>
            </div>
            {loading && (
              <div className="hm-articles" aria-busy="true" aria-label="Loading stories">
                {Array.from({ length: 3 }).map((_, i) => (
                  <article key={i} className="hm-article-card">
                    <div className="hm-article-top">
                      <Skeleton width={90} height={12} />
                      <Skeleton width={70} height={12} />
                    </div>
                    <div style={{ marginTop: 10 }}>
                      <Skeleton height={22} width="85%" />
                    </div>
                    <div style={{ marginTop: 10, marginBottom: 12 }}>
                      <SkeletonText lines={2} />
                    </div>
                    <div className="hm-article-footer">
                      <Skeleton width={70} height={12} />
                      <Skeleton width={90} height={32} radius={8} />
                    </div>
                  </article>
                ))}
              </div>
            )}
            <div className="hm-articles">
              {!loading && stories.length === 0 && (
                <p className="hm-empty">No stories available right now — check back soon.</p>
              )}
              {!loading && stories.map((a) => (
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
                    <a href={`/articles/${a.public_id}`} className="hm-article-btn">
                      {a.cta_label}
                    </a>
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
              {loading && (
                <div style={{ display: "flex", flexDirection: "column", gap: 12, marginTop: 12 }} aria-busy="true" aria-label="Loading breakdown">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <div key={i} style={{ display: "flex", alignItems: "center", gap: 10 }}>
                      <Skeleton width={80} height={14} />
                      <div style={{ flex: 1 }}>
                        <Skeleton width="100%" height={10} radius={5} />
                      </div>
                      <Skeleton width={36} height={14} />
                    </div>
                  ))}
                </div>
              )}
              {!loading && (
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
              )}
              {!loading && (
              <div className="hm-rule-note">
                ${nonprofitAmount} of your ${subscriptionAmount} supports nonprofits based on reading engagement.
              </div>
              )}
            </section>

            {/* Funding breakdown */}
            <section className="hm-panel">
              <h2 className="hm-panel-title">Your Funding Breakdown</h2>
              <p className="hm-panel-sub">Based on articles read this month</p>
              {loading && (
                <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 12 }} aria-busy="true" aria-label="Loading funding">
                  {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} style={{ display: "flex", alignItems: "center", gap: 10 }}>
                      <Skeleton width={110} height={14} />
                      <div style={{ flex: 1 }}>
                        <Skeleton width="100%" height={10} radius={5} />
                      </div>
                      <Skeleton width={30} height={14} />
                    </div>
                  ))}
                </div>
              )}
              {!loading && (
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
              )}
            </section>

          </div>
        </div>
      </main>
    </div>
  );
}
