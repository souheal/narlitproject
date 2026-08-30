"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";

interface OrgBreakdown {
  organization_public_id: string;
  organization_name: string;
  reads: number;
  amount_donated: string;
  first_supported_at: string | null;
}

interface MonthlyPoint {
  month: string;
  reads: number;
  donated: number;
}

interface Transaction {
  public_id: string;
  organization_name: string;
  article_title: string;
  amount: string;
  created_at: string;
}

interface User {
  full_name: string;
  email: string;
}

interface ImpactSummary {
  totals: {
    donated_all_time: string;
    donated_this_month: string;
    articles_read: number;
    unique_articles: number;
    organizations_supported: number;
    reading_seconds: number;
    day_streak: number;
    longest_streak: number;
  };
  monthly: MonthlyPoint[];
  organizations: OrgBreakdown[];
  recent_transactions: Transaction[];
  currency: string;
}

export default function ImpactPage() {
  const [user, setUser] = useState<User | null>(null);
  const [data, setData] = useState<ImpactSummary | null>(null);
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  async function load() {
    setLoading(true);
    try {
      const [meRes, impactRes] = await Promise.all([
        apiFetch("/auth/me"),
        apiFetch("/member/impact"),
      ]);
      const meData = await meRes.json();
      const impactData = await impactRes.json();
      setUser(meData.data?.user ?? meData.data ?? null);
      if (impactRes.ok) setData(impactData.data?.impact ?? null);
      else setError(impactData?.message ?? "Failed to load impact.");
    } catch {
      setError("Failed to load impact data.");
    }
    setLoading(false);
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      setChecking(false);
      load();
    });
  }, []);

  if (checking) {
    return (
      <BrandLoader />
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";
  const totals = data?.totals;
  const monthly = data?.monthly ?? [];
  const orgs = data?.organizations ?? [];
  const transactions = data?.recent_transactions ?? [];
  const maxMonthly = Math.max(1, ...monthly.map((m) => m.donated));
  const hours = totals ? Math.floor(totals.reading_seconds / 3600) : 0;
  const mins = totals ? Math.floor((totals.reading_seconds % 3600) / 60) : 0;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />

      <main className="hm-main">
        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        <section className="hm-welcome">
          <div className="hm-welcome-inner">
            <div>
              <p className="hm-welcome-kicker">Your journey</p>
              <h1 className="hm-welcome-title">
                You&apos;ve donated{" "}
                <span className="hm-welcome-name">${totals?.donated_all_time ?? "0.00"}</span>
              </h1>
              <p className="hm-welcome-sub">
                Across {totals?.organizations_supported ?? 0} nonprofits, through {totals?.articles_read ?? 0} completed reads.
              </p>
            </div>
            <a href="/impact/share" className="hm-article-btn" style={{ alignSelf: "center" }}>
              Share my impact →
            </a>
          </div>
        </section>

        <section className="hm-section">
          <h2 className="hm-section-title">Lifetime stats</h2>
          {loading ? (
            <div className="hm-stats-grid" aria-busy="true" aria-label="Loading stats">
              {Array.from({ length: 5 }).map((_, i) => (
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
              <div className="hm-stat-value">${totals?.donated_all_time ?? "0.00"}</div>
              <div className="hm-stat-label">Total Donated</div>
              <div className="hm-stat-hint">${totals?.donated_this_month ?? "0.00"} this month</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-teal">📖</div>
              <div className="hm-stat-value">{totals?.articles_read ?? 0}</div>
              <div className="hm-stat-label">Articles Read</div>
              <div className="hm-stat-hint">{totals?.unique_articles ?? 0} unique</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-purple">🤝</div>
              <div className="hm-stat-value">{totals?.organizations_supported ?? 0}</div>
              <div className="hm-stat-label">Nonprofits Supported</div>
              <div className="hm-stat-hint">Across all time</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-orange">⏱️</div>
              <div className="hm-stat-value">{hours}h {mins}m</div>
              <div className="hm-stat-label">Time Reading</div>
              <div className="hm-stat-hint">All-time</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-orange">🔥</div>
              <div className="hm-stat-value">{totals?.day_streak ?? 0}</div>
              <div className="hm-stat-label">Current Streak</div>
              <div className="hm-stat-hint">Longest: {totals?.longest_streak ?? 0} days</div>
            </div>
          </div>
          )}
        </section>

        <div className="hm-content-grid">
          <section className="hm-section">
            <h2 className="hm-section-title">Monthly impact</h2>
            <div className="hm-panel">
              {monthly.length === 0 && <p className="hm-empty">No history yet. Read your first article to start.</p>}
              {monthly.length > 0 && (
                <div style={{ display: "flex", alignItems: "flex-end", gap: 8, height: 200 }}>
                  {monthly.map((m) => (
                    <div key={m.month} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
                      <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>${m.donated.toFixed(2)}</div>
                      <div
                        title={`${m.month}: $${m.donated.toFixed(2)} · ${m.reads} reads`}
                        style={{
                          width: "100%",
                          height: `${Math.round((m.donated / maxMonthly) * 100)}%`,
                          minHeight: 4,
                          background: "linear-gradient(180deg, var(--orange), var(--teal))",
                          borderRadius: 6,
                        }}
                      />
                      <span style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{m.month.slice(-3)}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <h2 className="hm-section-title" style={{ marginTop: 32 }}>Nonprofits you support</h2>
            {loading && (
              <div className="hm-articles" aria-busy="true" aria-label="Loading nonprofits">
                {Array.from({ length: 3 }).map((_, i) => (
                  <article key={i} className="hm-article-card">
                    <div className="hm-article-top">
                      <Skeleton width={140} height={12} />
                      <Skeleton width={70} height={12} />
                    </div>
                    <div style={{ marginTop: 10, marginBottom: 10 }}>
                      <Skeleton height={20} width="60%" />
                    </div>
                    <div className="hm-article-footer">
                      <Skeleton width={180} height={12} />
                      <Skeleton width={80} height={28} radius={8} />
                    </div>
                  </article>
                ))}
              </div>
            )}
            <div className="hm-articles">
              {!loading && orgs.length === 0 && <p className="hm-empty">No nonprofits supported yet.</p>}
              {!loading && orgs.map((o) => (
                <article key={o.organization_public_id} className="hm-article-card">
                  <div className="hm-article-top">
                    <span className="hm-article-org">{o.organization_name}</span>
                    <span className="hm-article-cat">{o.reads} reads</span>
                  </div>
                  <h3 className="hm-article-title" style={{ fontSize: "1.2rem" }}>
                    ${o.amount_donated} donated
                  </h3>
                  <div className="hm-article-footer">
                    <span className="hm-article-time">
                      {o.first_supported_at ? `Supporting since ${new Date(o.first_supported_at).toLocaleDateString()}` : "Recent"}
                    </span>
                    <a href={`/organizations/${o.organization_public_id}`} className="hm-article-btn">
                      Visit →
                    </a>
                  </div>
                </article>
              ))}
            </div>
          </section>

          <div className="hm-side">
            <section className="hm-panel">
              <h2 className="hm-panel-title">Recent contributions</h2>
              <p className="hm-panel-sub">Last 10 completed reads that funded a nonprofit</p>
              {loading && (
                <div style={{ display: "flex", flexDirection: "column", gap: 12, marginTop: 12 }} aria-busy="true" aria-label="Loading contributions">
                  {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} style={{ paddingBottom: 10, borderBottom: "1px solid var(--line)" }}>
                      <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                        <Skeleton width="55%" height={12} />
                        <Skeleton width={50} height={12} />
                      </div>
                      <div style={{ marginTop: 4 }}>
                        <Skeleton width="80%" height={10} />
                      </div>
                      <div style={{ marginTop: 4 }}>
                        <Skeleton width={70} height={10} />
                      </div>
                    </div>
                  ))}
                </div>
              )}
              <div style={{ display: "flex", flexDirection: "column", gap: 12, marginTop: 12 }}>
                {!loading && transactions.length === 0 && <p className="hm-empty">No contributions yet.</p>}
                {!loading && transactions.slice(0, 10).map((t) => (
                  <div key={t.public_id} style={{ paddingBottom: 10, borderBottom: "1px solid var(--line)" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.8rem" }}>
                      <strong>{t.organization_name}</strong>
                      <span style={{ color: "var(--teal)" }}>+${t.amount}</span>
                    </div>
                    <div style={{ fontSize: "0.72rem", color: "var(--muted)", marginTop: 2 }}>
                      {t.article_title}
                    </div>
                    <div style={{ fontSize: "0.7rem", color: "var(--muted)", marginTop: 2 }}>
                      {new Date(t.created_at).toLocaleDateString()}
                    </div>
                  </div>
                ))}
              </div>
            </section>

            <section className="hm-panel">
              <h2 className="hm-panel-title">Keep going</h2>
              <p className="hm-panel-sub">Simple ways to grow your impact</p>
              <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 12 }}>
                <a href="/explore" className="hm-article-btn" style={{ textAlign: "center" }}>
                  🔍 Explore new stories
                </a>
                <a href="/achievements" className="hm-pager-btn" style={{ textAlign: "center", textDecoration: "none" }}>
                  🏆 View achievements
                </a>
                <a href="/organizations" className="hm-pager-btn" style={{ textAlign: "center", textDecoration: "none" }}>
                  🏢 Discover nonprofits
                </a>
              </div>
            </section>
          </div>
        </div>
      </main>
    </div>
  );
}
