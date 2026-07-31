"use client";

import { useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

type Status = "pending_review" | "published" | "rejected" | "archived" | "draft";

interface AdminArticle {
  public_id: string;
  title: string;
  excerpt: string | null;
  body: string | null;
  category: string | null;
  status: Status | string;
  is_featured: boolean;
  read_time_minutes: number | null;
  organization: { public_id: string | null; name: string | null };
  total_reads: number;
  total_unique_reads: number;
  total_points_generated: number;
  published_at: string | null;
  created_at: string;
  submitted_at: string | null;
  rejection_reason: string | null;
}

const STATUS_TABS: Status[] = ["pending_review", "published", "rejected", "archived", "draft"];

export default function AdminArticlesPage() {
  const [articles, setArticles] = useState<AdminArticle[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [tab, setTab] = useState<Status>("pending_review");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [reviewing, setReviewing] = useState<AdminArticle | null>(null);
  const [rejectReason, setRejectReason] = useState("");
  const [isPending, startTransition] = useTransition();

  async function fetchArticles(page = 1) {
    setLoading(true);
    setError("");
    try {
      const res = await adminFetch(`/admin/articles?status=${tab}&per_page=15&page=${page}`);
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to load articles.");
      } else {
        const payload = data.data?.articles;
        setArticles(payload?.data ?? []);
        setMeta({
          current_page: payload?.current_page ?? page,
          last_page: payload?.last_page ?? 1,
          total: payload?.total ?? 0,
        });
      }
    } catch {
      setError("Failed to load articles.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { fetchArticles(1); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [tab]);

  function approve(a: AdminArticle) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/approve`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to approve."); return; }
      setFeedback("Article approved and published.");
      setReviewing(null);
      fetchArticles(meta.current_page);
    });
  }

  function reject(a: AdminArticle) {
    if (!rejectReason.trim()) {
      setError("Please provide a rejection reason.");
      return;
    }
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/reject`, {
        method: "POST",
        body: JSON.stringify({ reason: rejectReason }),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to reject."); return; }
      setFeedback("Article rejected.");
      setReviewing(null);
      setRejectReason("");
      fetchArticles(meta.current_page);
    });
  }

  function toggleFeatured(a: AdminArticle) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/feature`, {
        method: "POST",
        body: JSON.stringify({ featured: !a.is_featured }),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed."); return; }
      setFeedback(a.is_featured ? "Article unfeatured." : "Article featured.");
      fetchArticles(meta.current_page);
    });
  }

  function archive(a: AdminArticle) {
    if (!confirm("Archive this article? Members will no longer see it.")) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/archive`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to archive."); return; }
      setFeedback("Article archived.");
      fetchArticles(meta.current_page);
    });
  }

  function requestChanges(a: AdminArticle) {
    if (!rejectReason.trim()) {
      setError("Please describe the changes needed.");
      return;
    }
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/request-changes`, {
        method: "POST",
        body: JSON.stringify({ reason: rejectReason }),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to request changes."); return; }
      setFeedback("Changes requested. Author has been notified.");
      setReviewing(null);
      setRejectReason("");
      fetchArticles(meta.current_page);
    });
  }

  function publish(a: AdminArticle) {
    if (!confirm("Publish this article now?")) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/publish`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to publish."); return; }
      setFeedback("Article published.");
      setReviewing(null);
      fetchArticles(meta.current_page);
    });
  }

  function restore(a: AdminArticle) {
    if (!confirm("Restore this article? It will become visible to members again.")) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/restore`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to restore."); return; }
      setFeedback("Article restored.");
      fetchArticles(meta.current_page);
    });
  }

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Articles</h2>
        <span style={{ color: "var(--muted)", fontSize: "0.9rem" }}>
          {meta.total.toLocaleString()} in {tab.replaceAll("_", " ")}
        </span>
      </div>

      <div className="admin-tabs">
        {STATUS_TABS.map((s) => (
          <button
            key={s}
            className={`admin-tab ${tab === s ? "admin-tab-active" : ""}`}
            onClick={() => setTab(s)}
          >
            {s.replaceAll("_", " ").replace(/\b\w/g, (c) => c.toUpperCase())}
          </button>
        ))}
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      <div className="admin-table-wrap">
        {loading && <p className="admin-empty">Loading…</p>}
        {!loading && articles.length === 0 && <p className="admin-empty">No articles here.</p>}
        {!loading && articles.length > 0 && (
          <table className="admin-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Organization</th>
                <th>Category</th>
                <th>Reads</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {articles.map((a) => (
                <tr key={a.public_id}>
                  <td>
                    <div style={{ fontWeight: 700 }}>
                      {a.title}
                      {a.is_featured && <span style={{ marginLeft: 6 }}>⭐</span>}
                    </div>
                    {a.category && <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>{a.category}</div>}
                  </td>
                  <td>{a.organization.name ?? "—"}</td>
                  <td>{a.category ?? "—"}</td>
                  <td>
                    {a.total_reads}
                    <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>{a.total_unique_reads} unique</div>
                  </td>
                  <td>{a.submitted_at ? new Date(a.submitted_at).toLocaleDateString() : "—"}</td>
                  <td>
                    <span className={`admin-badge ${a.status === "published" ? "admin-badge-success" : a.status === "rejected" ? "admin-badge-rejected" : "admin-badge-pending"}`}>
                      {String(a.status).replaceAll("_", " ")}
                    </span>
                  </td>
                  <td>
                    <div className="admin-actions">
                      <button className="admin-btn" onClick={() => setReviewing(a)}>Review</button>
                      {a.status === "pending_review" && (
                        <>
                          <button className="admin-btn admin-btn-approve" onClick={() => approve(a)} disabled={isPending}>
                            Approve
                          </button>
                          <button className="admin-btn admin-btn-reject" onClick={() => setReviewing(a)}>
                            Reject
                          </button>
                        </>
                      )}
                      {a.status === "published" && (
                        <>
                          <button className="admin-btn" onClick={() => toggleFeatured(a)} disabled={isPending}>
                            {a.is_featured ? "Unfeature" : "Feature"}
                          </button>
                          <button className="admin-btn admin-btn-reject" onClick={() => archive(a)} disabled={isPending}>
                            Archive
                          </button>
                        </>
                      )}
                      {a.status === "draft" && (
                        <button className="admin-btn admin-btn-approve" onClick={() => publish(a)} disabled={isPending}>
                          Publish
                        </button>
                      )}
                      {a.status === "archived" && (
                        <button className="admin-btn admin-btn-approve" onClick={() => restore(a)} disabled={isPending}>
                          Restore
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {meta.last_page > 1 && (
          <div style={{ display: "flex", justifyContent: "center", gap: 16, marginTop: 16 }}>
            <button className="admin-btn" disabled={meta.current_page <= 1 || loading} onClick={() => fetchArticles(meta.current_page - 1)}>
              ← Prev
            </button>
            <span style={{ alignSelf: "center", fontSize: "0.85rem", color: "var(--muted)" }}>
              Page {meta.current_page} of {meta.last_page}
            </span>
            <button className="admin-btn" disabled={meta.current_page >= meta.last_page || loading} onClick={() => fetchArticles(meta.current_page + 1)}>
              Next →
            </button>
          </div>
        )}
      </div>

      {reviewing && (
        <div className="admin-modal-overlay" onClick={() => { setReviewing(null); setRejectReason(""); }}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 720 }}>
            <h3 style={{ marginTop: 0 }}>{reviewing.title}</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem", margin: 0 }}>
              {reviewing.organization.name} · {reviewing.category ?? "Uncategorized"} · {reviewing.read_time_minutes ?? "?"} min read
            </p>

            {reviewing.rejection_reason && (
              <div style={{ marginTop: 12, padding: 12, background: "rgba(200,50,50,0.08)", border: "1px solid rgba(200,50,50,0.3)", borderRadius: 8 }}>
                <strong>Previous rejection:</strong> {reviewing.rejection_reason}
              </div>
            )}

            <div style={{ margin: "16px 0", padding: 12, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8 }}>
              <div style={{ fontStyle: "italic", color: "var(--muted)", marginBottom: 8 }}>{reviewing.excerpt}</div>
              <div style={{ whiteSpace: "pre-wrap", maxHeight: 300, overflow: "auto", fontSize: "0.9rem", lineHeight: 1.6 }}>
                {reviewing.body ?? "(no body)"}
              </div>
            </div>

            {reviewing.status === "pending_review" && (
              <>
                <label style={{ display: "block", marginBottom: 8, fontSize: "0.85rem" }}>
                  Reason (required for Reject and Request changes):
                </label>
                <textarea
                  value={rejectReason}
                  onChange={(e) => setRejectReason(e.target.value)}
                  rows={3}
                  placeholder="e.g. Missing sources, off-brand tone, factual errors…"
                  style={{
                    width: "100%",
                    padding: 10,
                    border: "1px solid var(--line)",
                    borderRadius: 8,
                    background: "var(--panel)",
                    color: "var(--foreground)",
                    fontFamily: "inherit",
                  }}
                />
                <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end", flexWrap: "wrap" }}>
                  <button className="admin-btn" onClick={() => { setReviewing(null); setRejectReason(""); }}>Cancel</button>
                  <button className="admin-btn" onClick={() => requestChanges(reviewing)} disabled={isPending}>
                    Request changes
                  </button>
                  <button className="admin-btn admin-btn-reject" onClick={() => reject(reviewing)} disabled={isPending}>
                    Reject
                  </button>
                  <button className="admin-btn admin-btn-approve" onClick={() => approve(reviewing)} disabled={isPending}>
                    Approve & Publish
                  </button>
                </div>
              </>
            )}

            {reviewing.status === "draft" && (
              <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
                <button className="admin-btn" onClick={() => setReviewing(null)}>Close</button>
                <button className="admin-btn admin-btn-approve" onClick={() => publish(reviewing)} disabled={isPending}>
                  Publish
                </button>
              </div>
            )}

            {reviewing.status === "archived" && (
              <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
                <button className="admin-btn" onClick={() => setReviewing(null)}>Close</button>
                <button className="admin-btn admin-btn-approve" onClick={() => restore(reviewing)} disabled={isPending}>
                  Restore
                </button>
              </div>
            )}

            {(reviewing.status !== "pending_review" && reviewing.status !== "draft" && reviewing.status !== "archived") && (
              <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
                <button className="admin-btn" onClick={() => setReviewing(null)}>Close</button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
