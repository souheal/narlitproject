"use client";

import { useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

interface Batch {
  public_id: string;
  period_start: string;
  period_end: string;
  status: string;
  total_amount: string;
  total_organizations: number;
  total_items: number;
  currency: string;
  created_at: string;
  executed_at: string | null;
}

interface Item {
  public_id: string;
  organization: { public_id: string; name: string };
  amount: string;
  currency: string;
  status: string;
  total_reads: number;
  stripe_transfer_id: string | null;
  failure_reason: string | null;
  paid_at: string | null;
}

interface PayoutSummary {
  pending_this_period: string;
  paid_last_period: string;
  next_period_starts: string | null;
  next_period_ends: string | null;
  currency: string;
  organizations_ready: number;
  organizations_missing_stripe: number;
}

function batchTone(status: string): "orange" | "teal" | "muted" | "rejected" {
  const s = status.toLowerCase();
  if (s === "completed" || s === "paid") return "teal";
  if (s === "failed") return "rejected";
  if (s === "pending" || s === "processing") return "orange";
  return "muted";
}

function batchIcon(status: string): string {
  const s = status.toLowerCase();
  if (s === "completed" || s === "paid") return "✅";
  if (s === "failed") return "❌";
  if (s === "processing") return "⚙️";
  if (s === "pending") return "⏳";
  return "📦";
}

export default function AdminPayoutsPage() {
  const [batches, setBatches] = useState<Batch[]>([]);
  const [summary, setSummary] = useState<PayoutSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [openBatch, setOpenBatch] = useState<Batch | null>(null);
  const [items, setItems] = useState<Item[]>([]);
  const [itemsLoading, setItemsLoading] = useState(false);
  const [isPending, startTransition] = useTransition();

  async function fetchAll() {
    setLoading(true);
    setError("");
    try {
      const [sumRes, batchRes] = await Promise.all([
        adminFetch("/admin/payouts/summary"),
        adminFetch("/admin/payouts?per_page=20"),
      ]);
      const sumData = await sumRes.json();
      const batchData = await batchRes.json();
      if (sumRes.ok) setSummary(sumData.data?.summary ?? null);
      if (batchRes.ok) setBatches(batchData.data?.batches?.data ?? batchData.data?.batches ?? []);
      else setError(batchData?.message ?? "Failed to load payouts.");
    } catch {
      setError("Failed to load payouts.");
    } finally {
      setLoading(false);
    }
  }

  async function openBatchDetails(b: Batch) {
    setOpenBatch(b);
    setItemsLoading(true);
    setItems([]);
    try {
      const res = await adminFetch(`/admin/payouts/${b.public_id}/items`);
      const data = await res.json();
      if (res.ok) setItems(data.data?.items?.data ?? data.data?.items ?? []);
    } catch { /* ignore */ }
    setItemsLoading(false);
  }

  useEffect(() => { fetchAll(); }, []);

  function generateBatch() {
    if (!confirm("Generate a new payout batch for the current period? This will lock in all completed reads.")) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch("/admin/payouts/generate", { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to generate."); return; }
      setFeedback("New payout batch generated.");
      fetchAll();
    });
  }

  function executeBatch(b: Batch) {
    if (!confirm(`Send $${b.total_amount} to ${b.total_organizations} organizations via Stripe Connect? This cannot be undone.`)) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/payouts/${b.public_id}/execute`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to execute."); return; }
      setFeedback("Payout batch executed. Stripe transfers initiated.");
      fetchAll();
      if (openBatch?.public_id === b.public_id) openBatchDetails(b);
    });
  }

  function retryItem(item: Item) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/payouts/items/${item.public_id}/retry`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to retry."); return; }
      setFeedback("Payout retry initiated.");
      if (openBatch) openBatchDetails(openBatch);
    });
  }

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Payouts</h2>
        <button className="admin-btn admin-btn-approve" onClick={generateBatch} disabled={isPending}>
          + Generate new batch
        </button>
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      {summary && (
        <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
          <div className="admin-stat-card" style={{ cursor: "default" }}>
            <span className="admin-stat-label">Pending this period</span>
            <span className="admin-stat-icon">⏳</span>
            <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>${summary.pending_this_period}</span>
            <span className="admin-stat-hint">
              {summary.next_period_ends ? `Cycle ends ${new Date(summary.next_period_ends).toLocaleDateString()}` : "—"}
            </span>
          </div>
          <div className="admin-stat-card" style={{ cursor: "default" }}>
            <span className="admin-stat-label">Paid last period</span>
            <span className="admin-stat-icon">✅</span>
            <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>${summary.paid_last_period}</span>
            <span className="admin-stat-hint">{summary.currency}</span>
          </div>
          <div className="admin-stat-card" style={{ cursor: "default" }}>
            <span className="admin-stat-label">Ready to receive</span>
            <span className="admin-stat-icon">🏦</span>
            <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{summary.organizations_ready}</span>
            <span className="admin-stat-hint">organizations with Stripe Connect</span>
          </div>
          <div className="admin-stat-card" style={{ cursor: "default" }}>
            <span className="admin-stat-label">Missing Stripe Connect</span>
            <span className="admin-stat-icon">⚠️</span>
            <span style={{ fontSize: "1.6rem", fontWeight: 900, color: summary.organizations_missing_stripe > 0 ? "var(--orange)" : undefined }}>
              {summary.organizations_missing_stripe}
            </span>
            <span className="admin-stat-hint">won't receive payouts</span>
          </div>
        </div>
      )}

      <section className="admin-panel">
        <header className="admin-panel-header">
          <div>
            <h3 className="admin-panel-title">Payout batches</h3>
            <p className="admin-panel-sub">Monthly revenue distribution to organizations</p>
          </div>
          <div className="admin-panel-stat">
            <span className="admin-panel-stat-value">{batches.length}</span>
            <span className="admin-panel-stat-label">batches</span>
          </div>
        </header>

        {loading && <p className="admin-empty">Loading…</p>}
        {!loading && batches.length === 0 && (
          <div className="admin-chart-empty">
            <span className="admin-chart-empty-icon">💸</span>
            <span className="admin-chart-empty-title">No payout batches yet</span>
            <span className="admin-chart-empty-hint">Generate a batch to distribute this period's earnings to organizations.</span>
          </div>
        )}
        {!loading && batches.length > 0 && (
          <ul className="admin-batch-list">
            {batches.map((b) => {
              const tone = batchTone(b.status);
              return (
                <li key={b.public_id} className="admin-batch-row">
                  <span className={`admin-batch-icon admin-batch-icon-${tone}`}>{batchIcon(b.status)}</span>

                  <div className="admin-batch-main">
                    <div className="admin-batch-period">
                      {new Date(b.period_start).toLocaleDateString(undefined, { month: "short", day: "numeric" })}
                      <span className="admin-batch-arrow">→</span>
                      {new Date(b.period_end).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" })}
                    </div>
                    <div className="admin-batch-meta">
                      <span>Created {new Date(b.created_at).toLocaleDateString()}</span>
                      {b.executed_at && (
                        <>
                          <span className="admin-batch-dot" />
                          <span>Executed {new Date(b.executed_at).toLocaleDateString()}</span>
                        </>
                      )}
                    </div>
                  </div>

                  <div className="admin-batch-stats">
                    <div className="admin-batch-amount">
                      <span className="admin-batch-amount-value">${b.total_amount}</span>
                      <span className="admin-batch-amount-currency">{b.currency}</span>
                    </div>
                    <div className="admin-batch-counts">
                      <span>🏢 {b.total_organizations}</span>
                      <span className="admin-batch-dot" />
                      <span>📄 {b.total_items}</span>
                    </div>
                  </div>

                  <span className={`admin-badge ${b.status === "completed" ? "admin-badge-success" : b.status === "failed" ? "admin-badge-rejected" : "admin-badge-pending"}`}>
                    {b.status}
                  </span>

                  <div className="admin-batch-actions">
                    <button className="admin-btn" onClick={() => openBatchDetails(b)}>Details</button>
                    {b.status === "pending" && (
                      <button className="admin-btn admin-btn-approve" onClick={() => executeBatch(b)} disabled={isPending}>
                        Execute
                      </button>
                    )}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      {openBatch && (
        <div className="admin-modal-overlay" onClick={() => setOpenBatch(null)}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 900 }}>
            <h3 style={{ marginTop: 0 }}>
              Batch · {new Date(openBatch.period_start).toLocaleDateString()} — {new Date(openBatch.period_end).toLocaleDateString()}
            </h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem" }}>
              ${openBatch.total_amount} across {openBatch.total_organizations} organizations · Status: <strong>{openBatch.status}</strong>
            </p>

            {itemsLoading && <p className="admin-empty">Loading items…</p>}
            {!itemsLoading && items.length === 0 && <p className="admin-empty">No items.</p>}
            {!itemsLoading && items.length > 0 && (
              <div style={{ maxHeight: 420, overflow: "auto", marginTop: 12 }}>
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th>Organization</th>
                      <th>Amount</th>
                      <th>Reads</th>
                      <th>Status</th>
                      <th>Transfer ID</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((i) => (
                      <tr key={i.public_id}>
                        <td>{i.organization.name}</td>
                        <td>${i.amount} {i.currency}</td>
                        <td>{i.total_reads}</td>
                        <td>
                          <span className={`admin-badge ${i.status === "paid" ? "admin-badge-success" : i.status === "failed" ? "admin-badge-rejected" : "admin-badge-pending"}`}>
                            {i.status}
                          </span>
                          {i.failure_reason && (
                            <div style={{ fontSize: "0.7rem", color: "var(--muted)", marginTop: 4 }}>
                              {i.failure_reason}
                            </div>
                          )}
                        </td>
                        <td style={{ fontSize: "0.72rem", fontFamily: "monospace" }}>
                          {i.stripe_transfer_id ?? "—"}
                        </td>
                        <td>
                          {i.status === "failed" && (
                            <button className="admin-btn admin-btn-approve" onClick={() => retryItem(i)} disabled={isPending}>
                              Retry
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
              <button className="admin-btn" onClick={() => setOpenBatch(null)}>Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
