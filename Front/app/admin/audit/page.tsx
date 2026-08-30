"use client";

import React, { useEffect, useState } from "react";
import { adminFetch } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";

interface AuditEntry {
  id: number;
  public_id: string;
  actor: { name: string; email: string; role: string } | null;
  action: string;
  entity_type: string | null;
  entity_id: string | null;
  target_type: string | null;
  target_label: string | null;
  ip_address: string | null;
  user_agent: string | null;
  device_summary: string | null;
  status: string;
  metadata: Record<string, unknown> | null;
  target_admin_path: string | null;
  created_at: string;
}

function rewriteTargetPath(path: string | null): string | null {
  if (!path) return null;
  const match = path.match(/^\/admin\/(users|articles|organizations|subscriptions|payouts)\/([^/?#]+)$/);
  if (!match) return path;
  const [, section, id] = match;
  return `/admin/${section}?open=${encodeURIComponent(id)}`;
}

const ACTION_COLORS: Record<string, string> = {
  create: "var(--teal)",
  update: "#7c5cbf",
  delete: "#e53935",
  approve: "#4caf50",
  reject: "#e53935",
  login: "var(--muted)",
  logout: "var(--muted)",
  suspend: "var(--orange)",
  activate: "#4caf50",
};

function actionColor(action: string): string {
  for (const key of Object.keys(ACTION_COLORS)) {
    if (action.toLowerCase().includes(key)) return ACTION_COLORS[key];
  }
  return "var(--muted)";
}

export default function AdminAuditPage() {
  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [filterActor, setFilterActor] = useState("");
  const [filterAction, setFilterAction] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [expanded, setExpanded] = useState<string | null>(null);
  const [details, setDetails] = useState<AuditEntry | null>(null);
  const [loadingDetails, setLoadingDetails] = useState(false);
  const [exporting, setExporting] = useState(false);

  function buildQuery(): string {
    const params = new URLSearchParams();
    if (filterActor) params.set("actor", filterActor);
    if (filterAction) params.set("action", filterAction);
    return params.toString();
  }

  async function fetchEntries(page = 1) {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "25", page: String(page) });
      if (filterActor) params.set("actor", filterActor);
      if (filterAction) params.set("action", filterAction);
      const res = await adminFetch(`/admin/audit?${params.toString()}`);
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to load audit log.");
      } else {
        const payload = data.data?.entries;
        setEntries(payload?.data ?? []);
        setMeta({
          current_page: payload?.current_page ?? page,
          last_page: payload?.last_page ?? 1,
          total: payload?.total ?? 0,
        });
      }
    } catch {
      setError("Failed to load audit log.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { fetchEntries(1); }, []);

  function applyFilters(e: React.FormEvent) {
    e.preventDefault();
    fetchEntries(1);
  }

  async function openDetails(entry: AuditEntry) {
    setDetails(entry);
    setLoadingDetails(true);
    try {
      const res = await adminFetch(`/admin/audit-logs/${entry.id}`);
      const data = await res.json();
      if (res.ok) {
        setDetails(data.data?.audit_log ?? entry);
      }
    } catch { /* keep list-level data */ }
    setLoadingDetails(false);
  }

  async function exportCsv() {
    setExporting(true);
    setError(""); setFeedback("");
    try {
      const query = buildQuery();
      const res = await adminFetch(`/admin/audit-logs/export${query ? `?${query}` : ""}`);
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setError(data?.message ?? "Failed to export.");
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `admin-audit-logs-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      setFeedback("Export downloaded.");
    } catch {
      setError("Failed to export.");
    } finally {
      setExporting(false);
    }
  }

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Audit log</h2>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <span style={{ color: "var(--muted)", fontSize: "0.9rem" }}>
            {meta.total.toLocaleString()} events
          </span>
          <button className="admin-btn" onClick={exportCsv} disabled={exporting || loading}>
            {exporting ? "Exporting…" : "Export CSV"}
          </button>
        </div>
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}

      <form onSubmit={applyFilters} style={{ display: "flex", gap: 8, marginBottom: 16 }}>
        <input
          type="text"
          value={filterActor}
          onChange={(e) => setFilterActor(e.target.value)}
          placeholder="Filter by actor email…"
          style={{
            flex: 1,
            padding: "10px 14px",
            border: "1px solid var(--line)",
            borderRadius: 10,
            background: "var(--panel)",
            color: "var(--foreground)",
            fontSize: "0.9rem",
          }}
        />
        <input
          type="text"
          value={filterAction}
          onChange={(e) => setFilterAction(e.target.value)}
          placeholder="Filter by action (e.g. approve)…"
          style={{
            flex: 1,
            padding: "10px 14px",
            border: "1px solid var(--line)",
            borderRadius: 10,
            background: "var(--panel)",
            color: "var(--foreground)",
            fontSize: "0.9rem",
          }}
        />
        <button type="submit" className="admin-btn admin-btn-approve">Apply</button>
      </form>

      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      <div className="admin-table-wrap">
        {loading && (
          <table className="admin-table" aria-busy="true" aria-label="Loading audit log">
            <thead>
              <tr>
                <th>Time</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Target</th>
                <th>IP</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {Array.from({ length: 6 }).map((_, i) => (
                <tr key={i}>
                  <td><Skeleton width={120} height={12} /></td>
                  <td><SkeletonText lines={2} widths={["70%", "80%"]} /></td>
                  <td><Skeleton width={60} height={20} radius={6} /></td>
                  <td><SkeletonText lines={2} widths={["70%", "40%"]} /></td>
                  <td><Skeleton width={90} height={12} /></td>
                  <td>
                    <div style={{ display: "flex", gap: 4 }}>
                      <Skeleton width={70} height={28} radius={8} />
                      <Skeleton width={50} height={28} radius={8} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {!loading && entries.length === 0 && <p className="admin-empty">No events.</p>}
        {!loading && entries.length > 0 && (
          <table className="admin-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Target</th>
                <th>IP</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {entries.map((e) => (
                <React.Fragment key={e.public_id}>
                  <tr>
                    <td style={{ fontSize: "0.75rem", whiteSpace: "nowrap" }}>
                      {new Date(e.created_at).toLocaleString()}
                    </td>
                    <td>
                      {e.actor ? (
                        <>
                          <div style={{ fontWeight: 600 }}>{e.actor.name}</div>
                          <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{e.actor.email} · {e.actor.role}</div>
                        </>
                      ) : (
                        <span style={{ color: "var(--muted)" }}>System</span>
                      )}
                    </td>
                    <td>
                      <span
                        className="admin-badge"
                        style={{ background: `${actionColor(e.action)}20`, color: actionColor(e.action) }}
                      >
                        {e.action}
                      </span>
                    </td>
                    <td>
                      {e.target_label ? (
                        <>
                          <div>{e.target_label}</div>
                          {e.target_type && <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{e.target_type}</div>}
                        </>
                      ) : "—"}
                    </td>
                    <td style={{ fontFamily: "monospace", fontSize: "0.75rem" }}>{e.ip_address ?? "—"}</td>
                    <td style={{ whiteSpace: "nowrap" }}>
                      {e.metadata && Object.keys(e.metadata).length > 0 && (
                        <button
                          className="admin-btn"
                          onClick={() => setExpanded(expanded === e.public_id ? null : e.public_id)}
                        >
                          {expanded === e.public_id ? "Hide" : "Metadata"}
                        </button>
                      )}
                      <button
                        className="admin-btn"
                        style={{ marginLeft: 4 }}
                        onClick={() => openDetails(e)}
                      >
                        View
                      </button>
                    </td>
                  </tr>
                  {expanded === e.public_id && (
                    <tr>
                      <td colSpan={6} style={{ background: "var(--panel)" }}>
                        <pre style={{ margin: 0, fontSize: "0.75rem", overflow: "auto", maxHeight: 200 }}>
                          {JSON.stringify(e.metadata, null, 2)}
                        </pre>
                        {e.user_agent && (
                          <div style={{ fontSize: "0.7rem", color: "var(--muted)", marginTop: 8 }}>
                            UA: {e.user_agent}
                          </div>
                        )}
                      </td>
                    </tr>
                  )}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        )}

        {meta.last_page > 1 && (
          <div style={{ display: "flex", justifyContent: "center", gap: 16, marginTop: 16 }}>
            <button className="admin-btn" disabled={meta.current_page <= 1 || loading} onClick={() => fetchEntries(meta.current_page - 1)}>
              ← Prev
            </button>
            <span style={{ alignSelf: "center", fontSize: "0.85rem", color: "var(--muted)" }}>
              Page {meta.current_page} of {meta.last_page}
            </span>
            <button className="admin-btn" disabled={meta.current_page >= meta.last_page || loading} onClick={() => fetchEntries(meta.current_page + 1)}>
              Next →
            </button>
          </div>
        )}
      </div>

      {details && (
        <div className="admin-modal-overlay" onClick={() => setDetails(null)}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 640 }}>
            <h3 style={{ marginTop: 0 }}>Audit event #{details.id}</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.8rem", margin: 0 }}>
              {new Date(details.created_at).toLocaleString()}
            </p>

            {loadingDetails && (
              <div aria-busy="true" aria-label="Loading audit details" style={{ marginTop: 12 }}>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 }}>
                  {Array.from({ length: 8 }).map((_, i) => (
                    <Skeleton key={i} width="80%" height={14} />
                  ))}
                </div>
                <Skeleton height={120} radius={8} />
              </div>
            )}

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, margin: "16px 0" }}>
              <div><strong>Action:</strong> {details.action}</div>
              <div><strong>Status:</strong> {details.status}</div>
              <div><strong>Actor:</strong> {details.actor?.name ?? "System"}</div>
              <div><strong>Email:</strong> {details.actor?.email ?? "—"}</div>
              <div><strong>Entity type:</strong> {details.entity_type ?? "—"}</div>
              <div><strong>Entity id:</strong> {details.entity_id ?? "—"}</div>
              <div><strong>IP:</strong> <span style={{ fontFamily: "monospace" }}>{details.ip_address ?? "—"}</span></div>
              <div><strong>Device:</strong> {details.device_summary ?? "—"}</div>
            </div>

            {details.user_agent && (
              <div style={{ fontSize: "0.75rem", color: "var(--muted)", wordBreak: "break-all", marginBottom: 12 }}>
                <strong>UA:</strong> {details.user_agent}
              </div>
            )}

            {details.metadata && Object.keys(details.metadata).length > 0 && (
              <>
                <div style={{ fontWeight: 700, marginBottom: 6 }}>Metadata</div>
                <pre style={{ margin: 0, padding: 12, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8, fontSize: "0.75rem", maxHeight: 240, overflow: "auto" }}>
                  {JSON.stringify(details.metadata, null, 2)}
                </pre>
              </>
            )}

            <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
              {(() => {
                const target = rewriteTargetPath(details.target_admin_path);
                return target ? <a className="admin-btn" href={target}>Open target</a> : null;
              })()}
              <button className="admin-btn" onClick={() => setDetails(null)}>Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
