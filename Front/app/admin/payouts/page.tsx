"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useSearchParams } from "next/navigation";
import { adminFetch } from "@/lib/api";
import { ConfirmDialog } from "@/app/admin/_components/ConfirmDialog";
import { PromptDialog } from "@/app/admin/_components/PromptDialog";
import { Skeleton, SkeletonText } from "@/components/Skeleton";

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

interface AdminActionLog {
  action: string;
  admin_name: string;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

export default function AdminPayoutsPage() {
  const searchParams = useSearchParams();
  const openId = searchParams?.get("open") ?? null;
  const autoOpenedRef = useRef<string | null>(null);

  const [batches, setBatches] = useState<Batch[]>([]);
  const [summary, setSummary] = useState<PayoutSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [openBatch, setOpenBatch] = useState<Batch | null>(null);
  const [items, setItems] = useState<Item[]>([]);
  const [history, setHistory] = useState<AdminActionLog[]>([]);
  const [itemsLoading, setItemsLoading] = useState(false);
  const [isPending, startTransition] = useTransition();

  const [generatePromptOpen, setGeneratePromptOpen] = useState(false);
  const [executeBatchTarget, setExecuteBatchTarget] = useState<Batch | null>(null);
  const [cancelBatchTarget, setCancelBatchTarget] = useState<Batch | null>(null);

  const defaultMonth = useMemo(() => {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().slice(0, 7);
  }, []);

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
      if (batchRes.ok) {
        const paginated = batchData.data?.payout_batches ?? batchData.data?.batches;
        setBatches(paginated?.data ?? paginated ?? []);
      } else {
        setError(batchData?.message ?? "Failed to load payouts.");
      }
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
    setHistory([]);
    try {
      const res = await adminFetch(`/admin/payouts/${b.public_id}`);
      const data = await res.json();
      if (res.ok) {
        const detail = data.data?.payout_batch;
        setItems(detail?.items ?? []);
        setHistory(detail?.admin_action_history ?? []);
      } else {
        const itemsRes = await adminFetch(`/admin/payouts/${b.public_id}/items`);
        const itemsData = await itemsRes.json();
        if (itemsRes.ok) setItems(itemsData.data?.items?.data ?? itemsData.data?.items ?? []);
      }
    } catch { /* ignore */ }
    setItemsLoading(false);
  }

  useEffect(() => { fetchAll(); }, []);

  useEffect(() => {
    if (!openId || autoOpenedRef.current === openId) return;
    autoOpenedRef.current = openId;
    (async () => {
      try {
        const res = await adminFetch(`/admin/payouts/${openId}`);
        const data = await res.json();
        if (!res.ok) { setError(data?.message ?? "Failed to load payout batch."); return; }
        const detail = data.data?.payout_batch;
        if (!detail) return;
        const stub: Batch = {
          public_id: detail.public_id,
          period_start: detail.period_start,
          period_end: detail.period_end,
          status: detail.status,
          total_amount: detail.total_amount,
          total_organizations: detail.total_organizations ?? (detail.items?.length ?? 0),
          total_items: detail.total_items ?? (detail.items?.length ?? 0),
          currency: detail.currency,
          created_at: detail.created_at,
          executed_at: detail.executed_at,
        };
        setOpenBatch(stub);
        setItems(detail.items ?? []);
        setHistory(detail.admin_action_history ?? []);
      } catch {
        setError("Failed to load payout batch.");
      }
    })();
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [openId]);

  function submitGenerateBatch(month: string) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch("/admin/payouts/generate", {
        method: "POST",
        body: JSON.stringify({ month }),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to generate."); return; }
      setFeedback("New payout batch generated.");
      setGeneratePromptOpen(false);
      fetchAll();
    });
  }

  function confirmExecuteBatch() {
    if (!executeBatchTarget) return;
    const b = executeBatchTarget;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/payouts/${b.public_id}/execute`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to execute."); return; }
      setFeedback("Payout batch executed. Stripe transfers initiated.");
      setExecuteBatchTarget(null);
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

  function confirmCancelBatch() {
    if (!cancelBatchTarget) return;
    const b = cancelBatchTarget;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/payouts/${b.public_id}/cancel`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to cancel."); return; }
      setFeedback("Payout batch canceled.");
      setCancelBatchTarget(null);
      fetchAll();
      if (openBatch?.public_id === b.public_id) setOpenBatch(null);
    });
  }

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Payouts</h2>
        <button className="admin-btn admin-btn-approve" onClick={() => setGeneratePromptOpen(true)} disabled={isPending}>
          + Generate new batch
        </button>
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      {!summary && loading && (
        <div className="admin-stats-grid" style={{ marginBottom: 20 }} aria-busy="true" aria-label="Loading payout summary">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="admin-stat-card" style={{ cursor: "default" }}>
              <Skeleton width={130} height={12} />
              <div style={{ marginTop: 10 }}><Skeleton width="60%" height={26} /></div>
              <div style={{ marginTop: 8 }}><Skeleton width="80%" height={12} /></div>
            </div>
          ))}
        </div>
      )}

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

        {loading && (
          <ul className="admin-batch-list" aria-busy="true" aria-label="Loading payout batches">
            {Array.from({ length: 4 }).map((_, i) => (
              <li key={i} className="admin-batch-row">
                <Skeleton width={36} height={36} radius={8} />
                <div className="admin-batch-main" style={{ flex: 1 }}>
                  <SkeletonText lines={2} widths={["60%", "40%"]} />
                </div>
                <div className="admin-batch-stats">
                  <Skeleton width={80} height={20} />
                  <div style={{ marginTop: 4 }}><Skeleton width={100} height={12} /></div>
                </div>
                <Skeleton width={70} height={20} radius={6} />
                <div className="admin-batch-actions">
                  <Skeleton width={70} height={28} radius={8} />
                </div>
              </li>
            ))}
          </ul>
        )}
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
                      <>
                        <button className="admin-btn admin-btn-approve" onClick={() => setExecuteBatchTarget(b)} disabled={isPending}>
                          Execute
                        </button>
                        <button className="admin-btn admin-btn-reject" onClick={() => setCancelBatchTarget(b)} disabled={isPending}>
                          Cancel
                        </button>
                      </>
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

            {itemsLoading && (
              <div style={{ maxHeight: 420, overflow: "auto", marginTop: 12 }} aria-busy="true" aria-label="Loading batch items">
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
                    {Array.from({ length: 4 }).map((_, i) => (
                      <tr key={i}>
                        <td><Skeleton width="70%" height={14} /></td>
                        <td><Skeleton width={60} height={14} /></td>
                        <td><Skeleton width={30} height={14} /></td>
                        <td><Skeleton width={60} height={20} radius={6} /></td>
                        <td><Skeleton width={120} height={12} /></td>
                        <td><Skeleton width={56} height={28} radius={8} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
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

            {history.length > 0 && (
              <>
                <h4 style={{ marginTop: 20, marginBottom: 8 }}>Admin activity</h4>
                <ul style={{ margin: 0, paddingLeft: 20, fontSize: "0.8rem", maxHeight: 160, overflow: "auto" }}>
                  {history.map((log, i) => (
                    <li key={i}>
                      <strong>{log.action}</strong> by {log.admin_name} · {new Date(log.created_at).toLocaleString()}
                    </li>
                  ))}
                </ul>
              </>
            )}

            <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
              {openBatch.status === "pending" && (
                <button className="admin-btn admin-btn-reject" onClick={() => setCancelBatchTarget(openBatch)} disabled={isPending}>
                  Cancel batch
                </button>
              )}
              <button className="admin-btn" onClick={() => setOpenBatch(null)}>Close</button>
            </div>
          </div>
        </div>
      )}

      <PromptDialog
        open={generatePromptOpen}
        title="Generate payout batch"
        description={
          <>
            Choose the month to run payouts for. Only completed reads in that month
            will be included. Defaults to the previous full month.
          </>
        }
        label="Month"
        hint="Format: YYYY-MM"
        placeholder="2026-07"
        defaultValue={defaultMonth}
        inputType="month"
        pattern={/^\d{4}-(0[1-9]|1[0-2])$/}
        patternError="Please pick a valid month (YYYY-MM)."
        confirmLabel="Generate batch"
        variant="primary"
        loading={isPending}
        onSubmit={submitGenerateBatch}
        onCancel={() => setGeneratePromptOpen(false)}
      />

      <ConfirmDialog
        open={executeBatchTarget !== null}
        title="Execute payout batch?"
        variant="danger"
        confirmLabel="Send transfers"
        loading={isPending}
        message={
          executeBatchTarget ? (
            <>
              You are about to send <strong>${executeBatchTarget.total_amount}</strong>{" "}
              to <strong>{executeBatchTarget.total_organizations}</strong> organizations
              via Stripe Connect for the period{" "}
              <strong>
                {new Date(executeBatchTarget.period_start).toLocaleDateString()}
                {" — "}
                {new Date(executeBatchTarget.period_end).toLocaleDateString()}
              </strong>
              .<br /><br />
              <span style={{ color: "#c62828" }}>This cannot be undone.</span>
            </>
          ) : null
        }
        onConfirm={confirmExecuteBatch}
        onCancel={() => setExecuteBatchTarget(null)}
      />

      <ConfirmDialog
        open={cancelBatchTarget !== null}
        title="Cancel payout batch?"
        variant="danger"
        confirmLabel="Cancel batch"
        cancelLabel="Keep it"
        loading={isPending}
        message={
          cancelBatchTarget ? (
            <>
              Cancel the batch for{" "}
              <strong>
                {new Date(cancelBatchTarget.period_start).toLocaleDateString()}
                {" — "}
                {new Date(cancelBatchTarget.period_end).toLocaleDateString()}
              </strong>
              ? Pending items will not be sent.
            </>
          ) : null
        }
        onConfirm={confirmCancelBatch}
        onCancel={() => setCancelBatchTarget(null)}
      />
    </div>
  );
}
