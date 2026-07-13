"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { adminFetch } from "@/lib/api";

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
      <div>
        <h2 className="admin-page-title">Dashboard</h2>
        <p className="admin-empty">Loading overview…</p>
      </div>
    );
  }

  if (error || !overview) {
    return (
      <div>
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
      <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr", gap: 20 }}>
        <div className="admin-table-wrap">
          <h3 style={{ margin: "0 0 12px", fontSize: "1rem", fontWeight: 700 }}>Review queues</h3>
          <table className="admin-table">
            <thead>
              <tr>
                <th>Queue</th>
                <th>Count</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Pending organizations</td>
                <td><strong>{overview.organizations.pending}</strong></td>
                <td>
                  <Link href="/admin/organizations?status=pending" className="admin-btn admin-btn-approve">
                    Review →
                  </Link>
                </td>
              </tr>
              <tr>
                <td>Articles awaiting review</td>
                <td><strong>{overview.articles.pending_review}</strong></td>
                <td>
                  <Link href="/admin/articles?status=pending_review" className="admin-btn admin-btn-approve">
                    Review →
                  </Link>
                </td>
              </tr>
              <tr>
                <td>Payouts pending release</td>
                <td><strong>${overview.donations.pending_payout}</strong></td>
                <td>
                  <Link href="/admin/payouts" className="admin-btn admin-btn-approve">
                    Manage →
                  </Link>
                </td>
              </tr>
              <tr>
                <td>Canceled this month</td>
                <td><strong>{overview.subscriptions.canceled_this_month}</strong></td>
                <td>
                  <Link href="/admin/subscriptions?status=canceled" className="admin-btn">
                    View
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div className="admin-table-wrap">
          <h3 style={{ margin: "0 0 12px", fontSize: "1rem", fontWeight: 700 }}>Recent activity</h3>
          {overview.activity.length === 0 && <p className="admin-empty">No recent activity.</p>}
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {overview.activity.slice(0, 8).map((a, i) => (
              <div key={i} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "8px 0", borderBottom: "1px solid var(--line)" }}>
                <div>
                  <span style={{ fontSize: "0.85rem", fontWeight: 600 }}>{a.label}</span>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>{a.kind}</div>
                </div>
                <span style={{ fontSize: "0.72rem", color: "var(--muted)" }}>
                  {new Date(a.timestamp).toLocaleString()}
                </span>
              </div>
            ))}
          </div>
          <Link href="/admin/audit" className="admin-btn" style={{ marginTop: 12, display: "inline-block" }}>
            View full audit log →
          </Link>
        </div>
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
