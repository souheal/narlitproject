"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { adminFetch } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";

interface Overview {
  users: { total: number; active: number; new_this_month: number; new_last_month: number };
  subscriptions: { active: number; mrr: string; arr: string; canceled_this_month: number; churn_rate: number };
  organizations: { total: number; pending: number; approved: number; rejected: number };
  articles: { total: number; published: number; pending_review: number; rejected: number };
  reads: { this_month: number; last_month: number; all_time: number };
  donations: { paid_out_all_time: string; paid_out_this_month: string; pending_payout: string };
  currency: string;
  activity: { label: string; timestamp: string; kind: string }[];
  charts: {
    users_by_day: { date: string; count: number }[];
    reads_by_day: { date: string; count: number }[];
    revenue_by_month: { month: string; amount: number }[];
  };
}

function pctChange(current: number, previous: number): string {
  if (previous === 0) return current > 0 ? "+∞%" : "0%";
  const change = ((current - previous) / previous) * 100;
  const sign = change >= 0 ? "+" : "";
  return `${sign}${change.toFixed(1)}%`;
}

function Sparkline({ points, color = "var(--teal)" }: { points: number[]; color?: string }) {
  if (points.length === 0) return null;
  const max = Math.max(1, ...points);
  const min = Math.min(0, ...points);
  const range = max - min || 1;
  const w = 100;
  const h = 28;
  const step = w / (points.length - 1 || 1);
  const path = points
    .map((v, i) => `${i === 0 ? "M" : "L"} ${(i * step).toFixed(2)},${(h - ((v - min) / range) * h).toFixed(2)}`)
    .join(" ");
  return (
    <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{ width: "100%", height: 32 }}>
      <path d={path} fill="none" stroke={color} strokeWidth="1.6" />
    </svg>
  );
}

function QueueRow({
  icon,
  tone,
  label,
  hint,
  count,
  href,
  action,
  subtle,
}: {
  icon: string;
  tone: "orange" | "teal" | "muted";
  label: string;
  hint: string;
  count: number | string;
  href: string;
  action: string;
  subtle?: boolean;
}) {
  return (
    <li className="admin-queue-row">
      <span className={`admin-queue-icon admin-queue-icon-${tone}`}>{icon}</span>
      <div className="admin-queue-info">
        <span className="admin-queue-label">{label}</span>
        <span className="admin-queue-hint">{hint}</span>
      </div>
      <span className={`admin-queue-count admin-queue-count-${tone}`}>{count}</span>
      <Link href={href} className={`admin-queue-action${subtle ? " admin-queue-action-subtle" : ""}`}>
        {action} <span aria-hidden>→</span>
      </Link>
    </li>
  );
}

function formatKind(kind: string): string {
  return kind.replace(/_/g, " ").replace(/\./g, " · ");
}

function formatTime(iso: string): string {
  const then = new Date(iso).getTime();
  const now = Date.now();
  const diff = Math.floor((now - then) / 1000);
  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
  return new Date(iso).toLocaleDateString();
}

function activityStyle(kind: string): { icon: string; tone: "orange" | "teal" | "muted" } {
  const k = kind.toLowerCase();
  if (k.includes("subscription")) return { icon: "💳", tone: "orange" };
  if (k.includes("payout") || k.includes("donation")) return { icon: "💰", tone: "orange" };
  if (k.includes("article")) return { icon: "📰", tone: "teal" };
  if (k.includes("organization") || k.includes("org")) return { icon: "🏢", tone: "teal" };
  if (k.includes("user")) return { icon: "👤", tone: "teal" };
  if (k.includes("audit")) return { icon: "📋", tone: "muted" };
  return { icon: "⚡", tone: "muted" };
}

function Bars({ points, color = "var(--orange)" }: { points: { label: string; value: number }[]; color?: string }) {
  const max = Math.max(1, ...points.map((p) => p.value));
  return (
    <div style={{ display: "flex", alignItems: "flex-end", gap: 6, height: 120 }}>
      {points.map((p) => (
        <div key={p.label} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
          <div
            style={{
              width: "100%",
              height: `${Math.round((p.value / max) * 100)}%`,
              minHeight: 2,
              background: color,
              borderRadius: 4,
              opacity: 0.85,
            }}
            title={`${p.label}: ${p.value}`}
          />
          <span style={{ fontSize: "0.68rem", color: "var(--muted)" }}>{p.label}</span>
        </div>
      ))}
    </div>
  );
}

export default function AdminDashboardPage() {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    (async () => {
      try {
        const res = await adminFetch("/admin/overview");
        const data = await res.json();
        if (!res.ok) {
          setError(data?.message ?? "Failed to load overview.");
        } else {
          setOverview(data.data?.overview ?? null);
        }
      } catch {
        setError("Failed to load overview.");
      }
      setLoading(false);
    })();
  }, []);

  if (loading) {
    return (
      <div suppressHydrationWarning aria-busy="true" aria-label="Loading dashboard">
        <div className="admin-page-header">
          <h2 className="admin-page-title">Platform Overview</h2>
          <Skeleton width={50} height={22} radius={12} />
        </div>

        <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="admin-stat-card" style={{ cursor: "default" }}>
              <Skeleton width={130} height={12} />
              <div style={{ marginTop: 10 }}>
                <Skeleton width="60%" height={30} />
              </div>
              <div style={{ marginTop: 8 }}>
                <Skeleton width="80%" height={12} />
              </div>
            </div>
          ))}
        </div>

        <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="admin-stat-card" style={{ cursor: "default", gridColumn: i === 2 ? "span 2" : undefined }}>
              <Skeleton width={120} height={12} />
              <div style={{ marginTop: 10 }}>
                <Skeleton width="50%" height={26} />
              </div>
              <div style={{ marginTop: 12 }}>
                <Skeleton height={i === 2 ? 120 : 32} />
              </div>
            </div>
          ))}
        </div>

        <div className="admin-dash-two-col">
          <section className="admin-panel">
            <header className="admin-panel-header">
              <div>
                <Skeleton width={140} height={16} />
                <div style={{ marginTop: 6 }}><Skeleton width={200} height={12} /></div>
              </div>
              <Skeleton width={40} height={26} radius={10} />
            </header>
            <ul className="admin-queue-list">
              {Array.from({ length: 4 }).map((_, i) => (
                <li key={i} className="admin-queue-row">
                  <Skeleton width={32} height={32} radius={8} />
                  <div className="admin-queue-info" style={{ flex: 1 }}>
                    <SkeletonText lines={2} widths={["55%", "75%"]} />
                  </div>
                  <Skeleton width={40} height={20} radius={6} />
                  <Skeleton width={80} height={28} radius={8} />
                </li>
              ))}
            </ul>
          </section>

          <section className="admin-panel">
            <header className="admin-panel-header">
              <div>
                <Skeleton width={140} height={16} />
                <div style={{ marginTop: 6 }}><Skeleton width={200} height={12} /></div>
              </div>
              <Skeleton width={40} height={26} radius={10} />
            </header>
            <ul className="admin-activity-list">
              {Array.from({ length: 6 }).map((_, i) => (
                <li key={i} className="admin-activity-item">
                  <Skeleton width={28} height={28} radius="50%" />
                  <div className="admin-activity-body" style={{ flex: 1 }}>
                    <SkeletonText lines={2} widths={["70%", "40%"]} />
                  </div>
                  <Skeleton width={50} height={12} />
                </li>
              ))}
            </ul>
          </section>
        </div>
      </div>
    );
  }

  if (error || !overview) {
    return (
      <div suppressHydrationWarning>
        <h2 className="admin-page-title">Dashboard</h2>
        <p className="admin-empty">{error || "No data available."}</p>
      </div>
    );
  }

  const usersTrend = overview.charts.users_by_day.map((d) => d.count);
  const readsTrend = overview.charts.reads_by_day.map((d) => d.count);
  const revenueBars = overview.charts.revenue_by_month.map((m) => ({ label: m.month.slice(-3), value: m.amount }));
  const userChange = pctChange(overview.users.new_this_month, overview.users.new_last_month);
  const readChange = pctChange(overview.reads.this_month, overview.reads.last_month);

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Platform Overview</h2>
        <span className="admin-badge admin-badge-success">Live</span>
      </div>

      {/* Top KPI Cards */}
      <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
        <div className="admin-stat-card" style={{ cursor: "default" }}>
          <span className="admin-stat-label">Active Subscribers</span>
          <span className="admin-stat-icon">👥</span>
          <span style={{ fontSize: "1.9rem", fontWeight: 900 }}>{overview.subscriptions.active.toLocaleString()}</span>
          <span className="admin-stat-hint">{overview.users.total.toLocaleString()} total users</span>
        </div>

        <div className="admin-stat-card" style={{ cursor: "default" }}>
          <span className="admin-stat-label">Monthly Recurring Revenue</span>
          <span className="admin-stat-icon">💵</span>
          <span style={{ fontSize: "1.9rem", fontWeight: 900 }}>${overview.subscriptions.mrr}</span>
          <span className="admin-stat-hint">ARR ${overview.subscriptions.arr} · Churn {overview.subscriptions.churn_rate.toFixed(1)}%</span>
        </div>

        <div className="admin-stat-card" style={{ cursor: "default" }}>
          <span className="admin-stat-label">Reads This Month</span>
          <span className="admin-stat-icon">📖</span>
          <span style={{ fontSize: "1.9rem", fontWeight: 900 }}>{overview.reads.this_month.toLocaleString()}</span>
          <span className="admin-stat-hint">{readChange} vs last month</span>
        </div>

        <div className="admin-stat-card" style={{ cursor: "default" }}>
          <span className="admin-stat-label">Donations Paid Out</span>
          <span className="admin-stat-icon">💰</span>
          <span style={{ fontSize: "1.9rem", fontWeight: 900 }}>${overview.donations.paid_out_all_time}</span>
          <span className="admin-stat-hint">${overview.donations.pending_payout} pending</span>
        </div>
      </div>

      {/* Row 2: Signups + reads trend + revenue */}
      <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
        <div className="admin-stat-card" style={{ cursor: "default" }}>
          <span className="admin-stat-label">New signups (30d)</span>
          <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{overview.users.new_this_month}</span>
          <span className="admin-stat-hint">{userChange} vs last month</span>
          <Sparkline points={usersTrend} color="var(--orange)" />
        </div>

        <div className="admin-stat-card" style={{ cursor: "default" }}>
          <span className="admin-stat-label">Reads (30d)</span>
          <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{overview.reads.this_month.toLocaleString()}</span>
          <span className="admin-stat-hint">All-time {overview.reads.all_time.toLocaleString()}</span>
          <Sparkline points={readsTrend} color="var(--teal)" />
        </div>

        <div className="admin-stat-card" style={{ cursor: "default", gridColumn: "span 2" }}>
          <span className="admin-stat-label">Revenue by month</span>
          <span className="admin-stat-hint">Last 6 months · USD</span>
          <div style={{ marginTop: 12 }}>
            <Bars points={revenueBars} color="var(--orange)" />
          </div>
        </div>
      </div>

      {/* Queues + Activity */}
      <div className="admin-dash-two-col">
        <section className="admin-panel">
          <header className="admin-panel-header">
            <div>
              <h3 className="admin-panel-title">Review queues</h3>
              <p className="admin-panel-sub">Items awaiting your attention</p>
            </div>
            <span className="admin-panel-count">{
              overview.organizations.pending +
              overview.articles.pending_review +
              overview.subscriptions.canceled_this_month
            }</span>
          </header>

          <ul className="admin-queue-list">
            <QueueRow
              icon="🏢"
              tone="orange"
              label="Pending organizations"
              hint="Awaiting approval"
              count={overview.organizations.pending}
              href="/admin/organizations?status=pending"
              action="Review"
            />
            <QueueRow
              icon="📰"
              tone="teal"
              label="Articles awaiting review"
              hint="Editorial queue"
              count={overview.articles.pending_review}
              href="/admin/articles?status=pending_review"
              action="Review"
            />
            <QueueRow
              icon="💰"
              tone="orange"
              label="Payouts pending release"
              hint="Ready to disburse"
              count={`$${overview.donations.pending_payout}`}
              href="/admin/payouts"
              action="Manage"
            />
            <QueueRow
              icon="↩️"
              tone="muted"
              label="Canceled this month"
              hint="Subscription drop-offs"
              count={overview.subscriptions.canceled_this_month}
              href="/admin/subscriptions?status=canceled"
              action="View"
              subtle
            />
          </ul>
        </section>

        <section className="admin-panel">
          <header className="admin-panel-header">
            <div>
              <h3 className="admin-panel-title">Recent activity</h3>
              <p className="admin-panel-sub">Latest events across the platform</p>
            </div>
            <span className="admin-panel-count admin-panel-count-teal">{overview.activity.length}</span>
          </header>

          {overview.activity.length === 0 ? (
            <p className="admin-empty">No recent activity.</p>
          ) : (
            <ul className="admin-activity-list">
              {overview.activity.slice(0, 8).map((a, i) => {
                const { icon, tone } = activityStyle(a.kind);
                return (
                  <li key={i} className="admin-activity-item">
                    <span className={`admin-activity-icon admin-activity-icon-${tone}`}>{icon}</span>
                    <div className="admin-activity-body">
                      <span className="admin-activity-label">{a.label}</span>
                      <span className="admin-activity-kind">{formatKind(a.kind)}</span>
                    </div>
                    <span className="admin-activity-time">{formatTime(a.timestamp)}</span>
                  </li>
                );
              })}
            </ul>
          )}

          <Link href="/admin/audit" className="admin-panel-footer-link">
            View full audit log →
          </Link>
        </section>
      </div>

      {/* Bottom breakdown */}
      <div className="admin-stats-grid" style={{ marginTop: 20 }}>
        <Link href="/admin/organizations" className="admin-stat-card">
          <span className="admin-stat-label">Organizations</span>
          <span className="admin-stat-icon">🏢</span>
          <span style={{ fontSize: "1.4rem", fontWeight: 900 }}>{overview.organizations.total}</span>
          <span className="admin-stat-hint">
            {overview.organizations.approved} approved · {overview.organizations.pending} pending
          </span>
        </Link>
        <Link href="/admin/articles" className="admin-stat-card">
          <span className="admin-stat-label">Articles</span>
          <span className="admin-stat-icon">📰</span>
          <span style={{ fontSize: "1.4rem", fontWeight: 900 }}>{overview.articles.total}</span>
          <span className="admin-stat-hint">
            {overview.articles.published} published · {overview.articles.pending_review} pending
          </span>
        </Link>
        <Link href="/admin/users" className="admin-stat-card">
          <span className="admin-stat-label">Users</span>
          <span className="admin-stat-icon">👤</span>
          <span style={{ fontSize: "1.4rem", fontWeight: 900 }}>{overview.users.total}</span>
          <span className="admin-stat-hint">{overview.users.active} active accounts</span>
        </Link>
        <Link href="/admin/analytics" className="admin-stat-card">
          <span className="admin-stat-label">Analytics</span>
          <span className="admin-stat-icon">📊</span>
          <span style={{ fontSize: "1.4rem", fontWeight: 900 }}>Explore</span>
          <span className="admin-stat-hint">Cohorts, funnels, retention</span>
        </Link>
      </div>
    </div>
  );
}
