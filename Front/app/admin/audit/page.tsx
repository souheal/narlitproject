"use client";

import React, { useEffect, useState } from "react";
import { adminFetch } from "@/lib/api";

interface AuditEntry {
  public_id: string;
  actor: { name: string; email: string; role: string } | null;
  action: string;
  target_type: string | null;
  target_label: string | null;
  ip_address: string | null;
  user_agent: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
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
  const [expanded, setExpanded] = useState<string | null>(null);

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

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Audit log</h2>
        <span style={{ color: "var(--muted)", fontSize: "0.9rem" }}>
          {meta.total.toLocaleString()} events
        </span>
      </div>

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
        {loading && <p className="admin-empty">Loading…</p>}
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
                    <td>
                      {e.metadata && Object.keys(e.metadata).length > 0 && (
                        <button
                          className="admin-btn"
                          onClick={() => setExpanded(expanded === e.public_id ? null : e.public_id)}
                        >
                          {expanded === e.public_id ? "Hide" : "Details"}
                        </button>
                      )}
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
    </div>
  );
}
