"use client";

import { useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

type Status = "active" | "canceled" | "past_due" | "trialing" | "all";

interface Subscription {
  public_id: string;
  subscriber: { public_id: string | null; name: string | null; email: string | null };
  plan: string;
  amount: string;
  currency: string;
  status: string;
  stripe_subscription_id: string | null;
  started_at: string | null;
  renews_or_expires_at: string | null;
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

interface PaymentRow {
  public_id: string;
  amount: string;
  net_amount: string;
  currency: string;
  status: string;
  paid_at: string | null;
  refunded_at: string | null;
  stripe_links?: { payment: string | null; invoice: string | null };
}

interface SubscriptionDetail {
  subscription: Subscription;
  payments: PaymentRow[];
  admin_action_history: { action: string; admin_name: string; metadata: unknown; created_at: string }[];
}

interface PeriodSummary {
  period: { start: string; end: string; previous_start: string; previous_end: string };
  kpis: {
    mrr: string;
    arr: string;
    churn_rate: number;
    arpu: string;
    estimated_ltv: string;
    active_subscriptions: number;
    past_due_subscriptions: number;
    canceled_subscriptions: number;
  };
}

function planIcon(plan: string): string {
  const p = plan.toLowerCase();
  if (p.includes("year")) return "🗓️";
  if (p.includes("month")) return "📅";
  if (p.includes("life")) return "♾️";
  if (p.includes("trial")) return "🎁";
  return "💠";
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
  const [detail, setDetail] = useState<SubscriptionDetail | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [summary, setSummary] = useState<PeriodSummary | null>(null);
  const [summaryOpen, setSummaryOpen] = useState(false);
  const [summaryDays, setSummaryDays] = useState<30 | 90 | 365>(90);

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
    if (!confirm(`Cancel ${s.subscriber.email ?? "this user"}'s ${s.plan} subscription?`)) return;
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
    if (!confirm(`Issue full refund for ${s.subscriber.email ?? "this user"}?`)) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/subscriptions/${s.public_id}/refund`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to refund."); return; }
      setFeedback("Refund issued via Stripe.");
    });
  }

  async function openDetail(s: Subscription) {
    setDetail({ subscription: s, payments: [], admin_action_history: [] });
    setLoadingDetail(true);
    try {
      const res = await adminFetch(`/admin/subscriptions/${s.public_id}`);
      const data = await res.json();
      if (res.ok) setDetail(data.data?.subscription ?? null);
      else setError(data?.message ?? "Failed to load subscription.");
    } catch {
      setError("Failed to load subscription.");
    }
    setLoadingDetail(false);
  }

  function refundPayment(paymentPublicId: string) {
    if (!confirm("Refund this specific payment via Stripe?")) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/payments/${paymentPublicId}/refund`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to refund payment."); return; }
      setFeedback("Payment refunded.");
      if (detail) openDetail(detail.subscription);
    });
  }

  async function loadSummary(days: 30 | 90 | 365) {
    setSummaryDays(days);
    setSummaryOpen(true);
    try {
      const to = new Date();
      const from = new Date();
      from.setDate(to.getDate() - days);
      const params = new URLSearchParams({
        date_from: from.toISOString().slice(0, 10),
        date_to: to.toISOString().slice(0, 10),
      });
      const res = await adminFetch(`/admin/subscriptions/summary?${params.toString()}`);
      const data = await res.json();
      if (res.ok) setSummary(data.data ?? null);
    } catch { /* ignore */ }
  }

  const maxRevenue = Math.max(1, ...(metrics?.revenue_by_month ?? []).map((r) => r.amount));

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Subscriptions</h2>
        <div style={{ display: "flex", gap: 6 }}>
          <span style={{ alignSelf: "center", fontSize: "0.8rem", color: "var(--muted)" }}>Period summary:</span>
          {([30, 90, 365] as const).map((d) => (
            <button
              key={d}
              className={`admin-btn ${summaryOpen && summaryDays === d ? "admin-btn-approve" : ""}`}
              onClick={() => loadSummary(d)}
            >
              {d === 365 ? "1y" : `${d}d`}
            </button>
          ))}
          {summaryOpen && (
            <button className="admin-btn" onClick={() => setSummaryOpen(false)}>Hide</button>
          )}
        </div>
      </div>

      {summaryOpen && summary && (
        <section className="admin-panel" style={{ marginBottom: 16 }}>
          <header className="admin-panel-header">
            <div>
              <h3 className="admin-panel-title">Period: {summary.period.start} → {summary.period.end}</h3>
              <p className="admin-panel-sub">
                vs previous {summary.period.previous_start} → {summary.period.previous_end}
              </p>
            </div>
          </header>
          <div className="admin-summary-grid">
            {[
              { label: "MRR", value: `$${summary.kpis.mrr}` },
              { label: "ARR", value: `$${summary.kpis.arr}` },
              { label: "ARPU", value: `$${summary.kpis.arpu}` },
              { label: "Est. LTV", value: `$${summary.kpis.estimated_ltv}` },
              { label: "Churn", value: `${summary.kpis.churn_rate}%` },
              { label: "Active", value: summary.kpis.active_subscriptions },
              { label: "Past due", value: summary.kpis.past_due_subscriptions },
              { label: "Canceled", value: summary.kpis.canceled_subscriptions },
            ].map((kpi) => (
              <div key={kpi.label} className="admin-summary-cell">
                <span className="admin-summary-label">{kpi.label}</span>
                <span className="admin-summary-value">{kpi.value}</span>
              </div>
            ))}
          </div>
        </section>
      )}

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

          <div className="admin-dash-two-col" style={{ marginBottom: 20 }}>
            <section className="admin-panel">
              <header className="admin-panel-header">
                <div>
                  <h3 className="admin-panel-title">Revenue by month</h3>
                  <p className="admin-panel-sub">Last 6 months · USD total</p>
                </div>
                <div className="admin-panel-stat">
                  <span className="admin-panel-stat-value">
                    ${metrics.revenue_by_month.reduce((sum, r) => sum + r.amount, 0).toLocaleString(undefined, { maximumFractionDigits: 0 })}
                  </span>
                  <span className="admin-panel-stat-label">6-mo total</span>
                </div>
              </header>

              {metrics.revenue_by_month.every((r) => r.amount === 0) ? (
                <div className="admin-chart-empty">
                  <span className="admin-chart-empty-icon">📈</span>
                  <span className="admin-chart-empty-title">No revenue yet</span>
                  <span className="admin-chart-empty-hint">Monthly revenue will appear here once payments start rolling in.</span>
                </div>
              ) : (
                <div className="admin-revenue-chart">
                  {metrics.revenue_by_month.map((r) => {
                    const heightPct = Math.round((r.amount / maxRevenue) * 100);
                    const isMax = r.amount === maxRevenue && r.amount > 0;
                    return (
                      <div key={r.month} className={`admin-revenue-col${isMax ? " admin-revenue-col-peak" : ""}`}>
                        <span className="admin-revenue-value">
                          ${r.amount >= 1000 ? `${(r.amount / 1000).toFixed(1)}k` : r.amount.toFixed(0)}
                        </span>
                        <div className="admin-revenue-track">
                          <div
                            className="admin-revenue-bar"
                            style={{ height: `${Math.max(heightPct, r.amount > 0 ? 6 : 0)}%` }}
                            title={`${r.month}: $${r.amount.toFixed(2)}`}
                          />
                        </div>
                        <span className="admin-revenue-label">{r.month.slice(-3)}</span>
                      </div>
                    );
                  })}
                </div>
              )}
            </section>

            <section className="admin-panel">
              <header className="admin-panel-header">
                <div>
                  <h3 className="admin-panel-title">Breakdown by plan</h3>
                  <p className="admin-panel-sub">Active subscribers · share of total</p>
                </div>
                <div className="admin-panel-stat">
                  <span className="admin-panel-stat-value">
                    {metrics.plans.reduce((sum, p) => sum + p.count, 0)}
                  </span>
                  <span className="admin-panel-stat-label">subscribers</span>
                </div>
              </header>

              {metrics.plans.length === 0 ? (
                <div className="admin-chart-empty">
                  <span className="admin-chart-empty-icon">📊</span>
                  <span className="admin-chart-empty-title">No active plans</span>
                  <span className="admin-chart-empty-hint">Plan breakdown will appear once subscribers sign up.</span>
                </div>
              ) : (
                <ul className="admin-plan-list">
                  {metrics.plans.map((p, i) => {
                    const totalSubs = metrics.plans.reduce((sum, x) => sum + x.count, 0) || 1;
                    const share = Math.round((p.count / totalSubs) * 100);
                    const icon = planIcon(p.plan);
                    return (
                      <li key={p.plan} className="admin-plan-row">
                        <span className={`admin-plan-badge admin-plan-badge-${i % 2 === 0 ? "orange" : "teal"}`}>{icon}</span>
                        <div className="admin-plan-info">
                          <div className="admin-plan-info-top">
                            <span className="admin-plan-name">{p.plan}</span>
                            <span className="admin-plan-share">{share}%</span>
                          </div>
                          <div className="admin-plan-track">
                            <div className={`admin-plan-fill admin-plan-fill-${i % 2 === 0 ? "orange" : "teal"}`} style={{ width: `${share}%` }} />
                          </div>
                        </div>
                        <div className="admin-plan-stats">
                          <span className="admin-plan-count">{p.count}</span>
                          <span className="admin-plan-mrr">${p.mrr}/mo</span>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}

              {metrics.failed_payments > 0 && (
                <div className="admin-panel-alert">
                  <span>⚠️</span>
                  <span>{metrics.failed_payments} failed payment{metrics.failed_payments === 1 ? "" : "s"} need attention</span>
                </div>
              )}
            </section>
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
                    <div style={{ fontWeight: 700 }}>{s.subscriber.name ?? "—"}</div>
                    <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>{s.subscriber.email ?? ""}</div>
                  </td>
                  <td style={{ textTransform: "capitalize" }}>{s.plan}</td>
                  <td>${s.amount} {s.currency}</td>
                  <td>
                    <span className={`admin-badge ${s.status === "active" ? "admin-badge-success" : s.status === "canceled" ? "admin-badge-rejected" : "admin-badge-pending"}`}>
                      {s.status}
                    </span>
                  </td>
                  <td>{s.started_at ? new Date(s.started_at).toLocaleDateString() : "—"}</td>
                  <td>{s.renews_or_expires_at ? new Date(s.renews_or_expires_at).toLocaleDateString() : "—"}</td>
                  <td>
                    <div className="admin-actions">
                      <button className="admin-btn" onClick={() => openDetail(s)}>View</button>
                      {s.status === "active" && (
                        <button className="admin-btn admin-btn-reject" onClick={() => cancelSub(s)} disabled={isPending}>
                          Cancel
                        </button>
                      )}
                      <button className="admin-btn" onClick={() => refund(s)} disabled={isPending}>
                        Refund latest
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

      {detail && (
        <div className="admin-modal-overlay" onClick={() => setDetail(null)}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 720 }}>
            <h3 style={{ marginTop: 0 }}>
              {detail.subscription.subscriber.name ?? "—"} · {detail.subscription.plan}
            </h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem", margin: 0 }}>
              {detail.subscription.subscriber.email ?? ""} · {detail.subscription.status}
            </p>

            <h4 style={{ marginTop: 20, marginBottom: 8 }}>Payments</h4>
            {loadingDetail && <p className="admin-empty">Loading…</p>}
            {!loadingDetail && detail.payments.length === 0 && <p className="admin-empty">No payments recorded.</p>}
            {!loadingDetail && detail.payments.length > 0 && (
              <table className="admin-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Net</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {detail.payments.map((p) => (
                    <tr key={p.public_id}>
                      <td style={{ fontSize: "0.75rem" }}>
                        {p.paid_at ? new Date(p.paid_at).toLocaleDateString() : "—"}
                      </td>
                      <td>${p.amount} {p.currency}</td>
                      <td>${p.net_amount}</td>
                      <td>
                        {p.refunded_at ? (
                          <span className="admin-badge admin-badge-rejected">refunded</span>
                        ) : (
                          <span className={`admin-badge ${p.status === "succeeded" ? "admin-badge-success" : "admin-badge-pending"}`}>{p.status}</span>
                        )}
                      </td>
                      <td>
                        {!p.refunded_at && p.status === "succeeded" && (
                          <button className="admin-btn admin-btn-reject" onClick={() => refundPayment(p.public_id)} disabled={isPending}>
                            Refund
                          </button>
                        )}
                        {p.stripe_links?.payment && (
                          <a className="admin-btn" style={{ marginLeft: 4 }} href={p.stripe_links.payment} target="_blank" rel="noreferrer">
                            Stripe ↗
                          </a>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {detail.admin_action_history.length > 0 && (
              <>
                <h4 style={{ marginTop: 20, marginBottom: 8 }}>Admin activity</h4>
                <ul style={{ margin: 0, paddingLeft: 20, fontSize: "0.8rem" }}>
                  {detail.admin_action_history.map((log, i) => (
                    <li key={i}>
                      <strong>{log.action}</strong> by {log.admin_name} · {new Date(log.created_at).toLocaleString()}
                    </li>
                  ))}
                </ul>
              </>
            )}

            <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
              <button className="admin-btn" onClick={() => setDetail(null)}>Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
