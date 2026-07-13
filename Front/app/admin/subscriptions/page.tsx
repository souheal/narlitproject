"use client";

import { useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

type Status = "active" | "canceled" | "past_due" | "trialing" | "all";

interface Subscription {
  public_id: string;
  user: { public_id: string; full_name: string; email: string };
  plan: string;
  amount: string;
  currency: string;
  status: string;
  stripe_subscription_id: string | null;
  started_at: string;
  expires_at: string | null;
  canceled_at: string | null;
}

interface Metrics {
  mrr: string;
  arr: string;
  active_count: number;
  canceled_this_month: number;
  new_this_month: number;
  churn_rate: number;
  ltv: string;
  arpu: string;
  currency: string;
  plans: { plan: string; count: number; mrr: string }[];
  revenue_by_month: { month: string; amount: number }[];
  failed_payments: number;
}

export default function AdminSubscriptionsPage() {
  const [metrics, setMetrics] = useState<Metrics | null>(null);
  const [subs, setSubs] = useState<Subscription[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [tab, setTab] = useState<Status>("active");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [isPending, startTransition] = useTransition();

  async function fetchMetrics() {
    try {
      const res = await adminFetch("/admin/subscriptions/metrics");
      const data = await res.json();
      if (res.ok) setMetrics(data.data?.metrics ?? null);
    } catch { /* ignore */ }
  }

  async function fetchSubs(page = 1) {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "15", page: String(page) });
      if (tab !== "all") params.set("status", tab);
      const res = await adminFetch(`/admin/subscriptions?${params.toString()}`);
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to load subscriptions.");
      } else {
        const payload = data.data?.subscriptions;
        setSubs(payload?.data ?? []);
        setMeta({
          current_page: payload?.current_page ?? page,
          last_page: payload?.last_page ?? 1,
          total: payload?.total ?? 0,
        });
      }
    } catch {
      setError("Failed to load subscriptions.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { fetchMetrics(); }, []);
  useEffect(() => { fetchSubs(1); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [tab]);

  function cancelSub(s: Subscription) {
    if (!confirm(`Cancel ${s.user.email}'s ${s.plan} subscription?`)) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/subscriptions/${s.public_id}/cancel`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to cancel."); return; }
      setFeedback("Subscription canceled.");
      fetchSubs(meta.current_page);
      fetchMetrics();
    });
  }

  function refund(s: Subscription) {
    if (!confirm(`Issue full refund for ${s.user.email}?`)) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/subscriptions/${s.public_id}/refund`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to refund."); return; }
      setFeedback("Refund issued via Stripe.");
    });
  }

  const maxRevenue = Math.max(1, ...(metrics?.revenue_by_month ?? []).map((r) => r.amount));

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Subscriptions</h2>
      </div>

      {/* Metrics */}
      {metrics && (
        <>
          <div className="admin-stats-grid" style={{ marginBottom: 16 }}>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">MRR</span>
              <span className="admin-stat-icon">💵</span>
              <span style={{ fontSize: "1.7rem", fontWeight: 900 }}>${metrics.mrr}</span>
              <span className="admin-stat-hint">ARR ${metrics.arr}</span>
            </div>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">Active subscribers</span>
              <span className="admin-stat-icon">👥</span>
              <span style={{ fontSize: "1.7rem", fontWeight: 900 }}>{metrics.active_count}</span>
              <span className="admin-stat-hint">+{metrics.new_this_month} this month</span>
            </div>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">Churn rate</span>
              <span className="admin-stat-icon">📉</span>
              <span style={{ fontSize: "1.7rem", fontWeight: 900 }}>{metrics.churn_rate.toFixed(1)}%</span>
              <span className="admin-stat-hint">{metrics.canceled_this_month} canceled this month</span>
            </div>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">ARPU / LTV</span>
              <span className="admin-stat-icon">🧮</span>
              <span style={{ fontSize: "1.7rem", fontWeight: 900 }}>${metrics.arpu}</span>
              <span className="admin-stat-hint">LTV ${metrics.ltv}</span>
            </div>
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 20 }}>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Revenue by month</h3>
              <div style={{ display: "flex", alignItems: "flex-end", gap: 6, height: 140 }}>
                {metrics.revenue_by_month.map((r) => (
                  <div key={r.month} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
                    <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>${r.amount.toFixed(0)}</div>
                    <div
                      style={{
                        width: "100%",
                        height: `${Math.round((r.amount / maxRevenue) * 100)}%`,
                        minHeight: 4,
                        background: "linear-gradient(180deg, var(--orange), var(--teal))",
                        borderRadius: 4,
                      }}
                    />
                    <span style={{ fontSize: "0.68rem", color: "var(--muted)" }}>{r.month.slice(-3)}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Breakdown by plan</h3>
              <table className="admin-table">
                <thead><tr><th>Plan</th><th>Subs</th><th>MRR</th></tr></thead>
                <tbody>
                  {metrics.plans.map((p) => (
                    <tr key={p.plan}>
                      <td style={{ textTransform: "capitalize" }}>{p.plan}</td>
                      <td>{p.count}</td>
                      <td>${p.mrr}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {metrics.failed_payments > 0 && (
                <div style={{ marginTop: 12, padding: 10, background: "rgba(200,50,50,0.08)", border: "1px solid rgba(200,50,50,0.3)", borderRadius: 8, fontSize: "0.85rem" }}>
                  ⚠️ {metrics.failed_payments} failed payment(s) need attention
                </div>
              )}
            </div>
          </div>
        </>
      )}

      {/* Subscriptions list */}
      <div className="admin-tabs">
        {(["active", "trialing", "past_due", "canceled", "all"] as Status[]).map((s) => (
          <button key={s} className={`admin-tab ${tab === s ? "admin-tab-active" : ""}`} onClick={() => setTab(s)}>
            {s.replaceAll("_", " ").replace(/\b\w/g, (c) => c.toUpperCase())}
          </button>
        ))}
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      <div className="admin-table-wrap">
        {loading && <p className="admin-empty">Loading…</p>}
        {!loading && subs.length === 0 && <p className="admin-empty">No subscriptions.</p>}
        {!loading && subs.length > 0 && (
          <table className="admin-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Started</th>
                <th>Expires</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {subs.map((s) => (
                <tr key={s.public_id}>
                  <td>
                    <div style={{ fontWeight: 700 }}>{s.user.full_name}</div>
                    <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>{s.user.email}</div>
                  </td>
                  <td style={{ textTransform: "capitalize" }}>{s.plan}</td>
                  <td>${s.amount} {s.currency}</td>
                  <td>
                    <span className={`admin-badge ${s.status === "active" ? "admin-badge-success" : s.status === "canceled" ? "admin-badge-rejected" : "admin-badge-pending"}`}>
                      {s.status}
                    </span>
                  </td>
                  <td>{new Date(s.started_at).toLocaleDateString()}</td>
                  <td>{s.expires_at ? new Date(s.expires_at).toLocaleDateString() : "—"}</td>
                  <td>
                    <div className="admin-actions">
                      {s.status === "active" && (
                        <button className="admin-btn admin-btn-reject" onClick={() => cancelSub(s)} disabled={isPending}>
                          Cancel
                        </button>
                      )}
                      <button className="admin-btn" onClick={() => refund(s)} disabled={isPending}>
                        Refund
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {meta.last_page > 1 && (
          <div style={{ display: "flex", justifyContent: "center", gap: 16, marginTop: 16 }}>
            <button className="admin-btn" disabled={meta.current_page <= 1 || loading} onClick={() => fetchSubs(meta.current_page - 1)}>
              ← Prev
            </button>
            <span style={{ alignSelf: "center", fontSize: "0.85rem", color: "var(--muted)" }}>
              Page {meta.current_page} of {meta.last_page}
            </span>
            <button className="admin-btn" disabled={meta.current_page >= meta.last_page || loading} onClick={() => fetchSubs(meta.current_page + 1)}>
              Next →
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
