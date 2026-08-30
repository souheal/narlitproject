"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useSearchParams } from "next/navigation";
import { adminFetch } from "@/lib/api";
import { ConfirmDialog } from "@/app/admin/_components/ConfirmDialog";
import { Skeleton, SkeletonText } from "@/components/Skeleton";

type Status = "pending_review" | "published" | "rejected" | "archived" | "draft";

const VALID_STATUSES: Status[] = ["pending_review", "published", "rejected", "archived", "draft"];

interface AdminArticle {
  public_id: string;
  title: string;
  excerpt?: string | null;
  body?: string | null;
  category: string | null;
  status: Status | string;
  is_featured: boolean;
  read_time_minutes: number | null;
  organization: { public_id: string | null; name: string | null };
  total_reads: number;
  total_unique_reads: number;
  total_points_generated: number;
  published_at: string | null;
  submitted_at: string | null;
  rejection_reason?: string | null;
}

interface AdminArticleDetail {
  public_id: string;
  title: string;
  excerpt: string | null;
  content: string | null;
  body: string | null;
  category: string | null;
  status: string;
  rejection_reason: string | null;
  organization: {
    public_id: string | null;
    name: string | null;
    website: string | null;
    verification_status: string | null;
    author: { public_id: string | null; name: string | null; email: string | null };
  };
  images: string[];
  metadata: Record<string, unknown>;
  read_statistics: {
    total_reads: number;
    total_unique_reads: number;
    total_reading_seconds: number;
    total_points_generated: number;
    recorded_read_events: number;
  };
  dates: {
    submitted_at: string | null;
    updated_at: string | null;
    published_at: string | null;
    featured_at: string | null;
    archived_at: string | null;
  };
  submission_history: { action: string; admin_name: string; reason: string | null; from_status: string | null; to_status: string | null; created_at: string }[];
  moderation_history: { action: string; admin_name: string; reason: string | null; from_status: string | null; to_status: string | null; created_at: string }[];
}

const STATUS_TABS: Status[] = ["pending_review", "published", "rejected", "archived", "draft"];

export default function AdminArticlesPage() {
  const searchParams = useSearchParams();
  const initialTab = useMemo<Status>(() => {
    const raw = searchParams?.get("status");
    return raw && (VALID_STATUSES as string[]).includes(raw) ? (raw as Status) : "pending_review";
  }, [searchParams]);
  const openId = searchParams?.get("open") ?? null;
  const autoOpenedRef = useRef<string | null>(null);

  const [articles, setArticles] = useState<AdminArticle[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [tab, setTab] = useState<Status>(initialTab);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [reviewing, setReviewing] = useState<AdminArticle | null>(null);
  const [detail, setDetail] = useState<AdminArticleDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [rejectReason, setRejectReason] = useState("");
  const [editMode, setEditMode] = useState(false);
  const [editForm, setEditForm] = useState({
    title: "",
    excerpt: "",
    content: "",
    category: "",
    edit_note: "",
  });
  const [deleting, setDeleting] = useState<AdminArticle | null>(null);
  const [deleteReason, setDeleteReason] = useState("");
  const [deleteConfirm, setDeleteConfirm] = useState("");
  const [archiveTarget, setArchiveTarget] = useState<AdminArticle | null>(null);
  const [publishTarget, setPublishTarget] = useState<AdminArticle | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<AdminArticle | null>(null);
  const [isPending, startTransition] = useTransition();

  async function openReviewById(publicId: string) {
    try {
      const res = await adminFetch(`/admin/articles/${publicId}`);
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to load article."); return; }
      const d = data.data?.article;
      if (!d) return;
      const stub: AdminArticle = {
        public_id: d.public_id,
        title: d.title,
        excerpt: d.excerpt,
        body: d.body ?? d.content,
        category: d.category,
        status: d.status,
        is_featured: !!d.dates?.featured_at,
        read_time_minutes: null,
        organization: { public_id: d.organization?.public_id ?? null, name: d.organization?.name ?? null },
        total_reads: d.read_statistics?.total_reads ?? 0,
        total_unique_reads: d.read_statistics?.total_unique_reads ?? 0,
        total_points_generated: d.read_statistics?.total_points_generated ?? 0,
        published_at: d.dates?.published_at ?? null,
        submitted_at: d.dates?.submitted_at ?? null,
        rejection_reason: d.rejection_reason,
      };
      setReviewing(stub);
      setDetail(d);
      setEditForm({
        title: d.title ?? "",
        excerpt: d.excerpt ?? "",
        content: d.content ?? d.body ?? "",
        category: d.category ?? "",
        edit_note: "",
      });
    } catch {
      setError("Failed to load article.");
    }
  }

  async function openReview(a: AdminArticle, startInEdit = false) {
    setReviewing(a);
    setDetail(null);
    setEditMode(startInEdit);
    setDetailLoading(true);
    try {
      const res = await adminFetch(`/admin/articles/${a.public_id}`);
      const data = await res.json();
      if (res.ok) {
        const d = data.data?.article ?? null;
        setDetail(d);
        if (d) {
          setEditForm({
            title: d.title ?? "",
            excerpt: d.excerpt ?? "",
            content: d.content ?? d.body ?? "",
            category: d.category ?? "",
            edit_note: "",
          });
        }
      }
    } catch {
      /* keep list-level fallback */
    }
    setDetailLoading(false);
  }

  function closeReview() {
    setReviewing(null);
    setDetail(null);
    setRejectReason("");
    setEditMode(false);
  }

  function saveEdit(a: AdminArticle) {
    if (editForm.title.trim().length < 3) {
      setError("Title must be at least 3 characters.");
      return;
    }
    if (editForm.content.trim().length < 20) {
      setError("Content must be at least 20 characters.");
      return;
    }
    setFeedback(""); setError("");
    const payload: Record<string, string | null> = {
      title: editForm.title.trim(),
      excerpt: editForm.excerpt.trim() || null,
      content: editForm.content,
      category: editForm.category.trim() || null,
    };
    if (editForm.edit_note.trim()) payload.edit_note = editForm.edit_note.trim();
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to save article."); return; }
      setFeedback("Article updated.");
      setDetail(data.data?.article ?? null);
      setEditMode(false);
      fetchArticles(meta.current_page);
    });
  }

  function openDelete(a: AdminArticle) {
    setDeleting(a);
    setDeleteReason("");
    setDeleteConfirm("");
    setError("");
  }

  function closeDelete() {
    setDeleting(null);
    setDeleteReason("");
    setDeleteConfirm("");
  }

  function confirmDelete() {
    if (!deleting) return;
    if (deleteReason.trim().length < 10) {
      setError("Reason must be at least 10 characters.");
      return;
    }
    if (deleteConfirm !== "DELETE") {
      setError("Type DELETE exactly to confirm.");
      return;
    }
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${deleting.public_id}`, {
        method: "DELETE",
        body: JSON.stringify({ reason: deleteReason.trim(), confirm: "DELETE" }),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to delete article."); return; }
      setFeedback("Article deleted permanently.");
      closeDelete();
      if (reviewing?.public_id === deleting.public_id) closeReview();
      fetchArticles(meta.current_page);
    });
  }

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

  useEffect(() => {
    if (openId && autoOpenedRef.current !== openId) {
      autoOpenedRef.current = openId;
      openReviewById(openId);
    }
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [openId]);

  function approve(a: AdminArticle) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/approve`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to approve."); return; }
      setFeedback("Article approved and published.");
      closeReview();
      fetchArticles(meta.current_page);
    });
  }

  function reject(a: AdminArticle) {
    if (rejectReason.trim().length < 10) {
      setError("Rejection reason must be at least 10 characters.");
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
      closeReview();
      fetchArticles(meta.current_page);
    });
  }

  function toggleFeatured(a: AdminArticle) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/feature`, {
        method: a.is_featured ? "DELETE" : "POST",
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed."); return; }
      setFeedback(a.is_featured ? "Article unfeatured." : "Article featured.");
      fetchArticles(meta.current_page);
    });
  }

  function archive(a: AdminArticle) {
    setArchiveTarget(a);
  }

  function confirmArchive() {
    if (!archiveTarget) return;
    const a = archiveTarget;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/archive`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to archive."); return; }
      setFeedback("Article archived.");
      setArchiveTarget(null);
      fetchArticles(meta.current_page);
    });
  }

  function requestChanges(a: AdminArticle) {
    if (rejectReason.trim().length < 10) {
      setError("Please describe the changes needed (at least 10 characters).");
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
      closeReview();
      fetchArticles(meta.current_page);
    });
  }

  function publish(a: AdminArticle) {
    setPublishTarget(a);
  }

  function confirmPublish() {
    if (!publishTarget) return;
    const a = publishTarget;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/publish`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to publish."); return; }
      setFeedback("Article published.");
      setPublishTarget(null);
      closeReview();
      fetchArticles(meta.current_page);
    });
  }

  function restore(a: AdminArticle) {
    setRestoreTarget(a);
  }

  function confirmRestore() {
    if (!restoreTarget) return;
    const a = restoreTarget;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/articles/${a.public_id}/restore`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to restore."); return; }
      setFeedback("Article restored.");
      setRestoreTarget(null);
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
        {loading && (
          <table className="admin-table" aria-busy="true" aria-label="Loading articles">
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
              {Array.from({ length: 6 }).map((_, i) => (
                <tr key={i}>
                  <td><SkeletonText lines={2} widths={["80%", "40%"]} /></td>
                  <td><Skeleton width="70%" height={14} /></td>
                  <td><Skeleton width={70} height={14} /></td>
                  <td><SkeletonText lines={2} widths={["40%", "60%"]} /></td>
                  <td><Skeleton width={80} height={14} /></td>
                  <td><Skeleton width={80} height={20} radius={6} /></td>
                  <td>
                    <div className="admin-actions">
                      <Skeleton width={60} height={28} radius={8} />
                      <Skeleton width={50} height={28} radius={8} />
                      <Skeleton width={70} height={28} radius={8} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
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
                      <button className="admin-btn" onClick={() => openReview(a)}>Review</button>
                      <button className="admin-btn" onClick={() => openReview(a, true)}>Edit</button>
                      {a.status === "pending_review" && (
                        <>
                          <button className="admin-btn admin-btn-approve" onClick={() => approve(a)} disabled={isPending}>
                            Approve
                          </button>
                          <button className="admin-btn admin-btn-reject" onClick={() => openReview(a)}>
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
                      <button className="admin-btn admin-btn-reject" onClick={() => openDelete(a)} disabled={isPending} title="Delete permanently">
                        Delete
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
        <div className="admin-modal-overlay" onClick={closeReview}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 720 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 12 }}>
              <div style={{ flex: 1 }}>
                <h3 style={{ marginTop: 0, marginBottom: 4 }}>{detail?.title ?? reviewing.title}</h3>
                <p style={{ color: "var(--muted)", fontSize: "0.85rem", margin: 0 }}>
                  {(detail?.organization.name ?? reviewing.organization.name) ?? "—"} · {(detail?.category ?? reviewing.category) ?? "Uncategorized"} · {reviewing.read_time_minutes ?? "?"} min read
                </p>
                {detail?.organization.author?.email && (
                  <p style={{ color: "var(--muted)", fontSize: "0.75rem", margin: "4px 0 0" }}>
                    Author: {detail.organization.author.name} · {detail.organization.author.email}
                  </p>
                )}
              </div>
              {!detailLoading && detail && (
                <div className="admin-tabs" style={{ marginTop: 0 }}>
                  <button className={`admin-tab ${!editMode ? "admin-tab-active" : ""}`} onClick={() => setEditMode(false)}>
                    Review
                  </button>
                  <button className={`admin-tab ${editMode ? "admin-tab-active" : ""}`} onClick={() => setEditMode(true)}>
                    Edit
                  </button>
                </div>
              )}
            </div>

            {detailLoading && (
              <div aria-busy="true" aria-label="Loading article" style={{ marginTop: 16 }}>
                <Skeleton height={140} radius={8} />
                <div style={{ marginTop: 12, display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 8 }}>
                  {Array.from({ length: 4 }).map((_, i) => (
                    <Skeleton key={i} height={14} />
                  ))}
                </div>
                <div style={{ marginTop: 12 }}>
                  <SkeletonText lines={4} />
                </div>
              </div>
            )}

            {!detailLoading && detail && editMode && (
              <div style={{ display: "flex", flexDirection: "column", gap: 12, marginTop: 16 }}>
                <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.82rem" }}>
                  <span style={{ fontWeight: 700 }}>Title</span>
                  <input
                    type="text"
                    value={editForm.title}
                    onChange={(e) => setEditForm({ ...editForm, title: e.target.value })}
                    style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "inherit", fontSize: "0.95rem" }}
                  />
                </label>
                <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.82rem" }}>
                  <span style={{ fontWeight: 700 }}>Category</span>
                  <input
                    type="text"
                    value={editForm.category}
                    placeholder="Uncategorized"
                    onChange={(e) => setEditForm({ ...editForm, category: e.target.value })}
                    style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "inherit" }}
                  />
                </label>
                <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.82rem" }}>
                  <span style={{ fontWeight: 700 }}>Excerpt</span>
                  <textarea
                    value={editForm.excerpt}
                    onChange={(e) => setEditForm({ ...editForm, excerpt: e.target.value })}
                    rows={2}
                    style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "inherit", resize: "vertical" }}
                  />
                </label>
                <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.82rem" }}>
                  <span style={{ fontWeight: 700 }}>Content</span>
                  <textarea
                    value={editForm.content}
                    onChange={(e) => setEditForm({ ...editForm, content: e.target.value })}
                    rows={12}
                    style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "inherit", fontSize: "0.9rem", lineHeight: 1.55, resize: "vertical" }}
                  />
                </label>
                <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.82rem" }}>
                  <span style={{ fontWeight: 700 }}>Edit note <span style={{ color: "var(--muted)", fontWeight: 400 }}>(optional — recorded in audit log)</span></span>
                  <input
                    type="text"
                    value={editForm.edit_note}
                    onChange={(e) => setEditForm({ ...editForm, edit_note: e.target.value })}
                    placeholder="e.g. Fixed typo in the second paragraph"
                    style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "inherit" }}
                  />
                </label>
                <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 8, flexWrap: "wrap" }}>
                  <button className="admin-btn" onClick={() => setEditMode(false)}>Cancel edit</button>
                  <button className="admin-btn admin-btn-approve" onClick={() => saveEdit(reviewing)} disabled={isPending}>
                    {isPending ? "Saving…" : "Save changes"}
                  </button>
                </div>
              </div>
            )}

            {!editMode && (
              <>


            {detail?.rejection_reason && (
              <div style={{ marginTop: 12, padding: 12, background: "rgba(200,50,50,0.08)", border: "1px solid rgba(200,50,50,0.3)", borderRadius: 8 }}>
                <strong>Previous rejection:</strong> {detail.rejection_reason}
              </div>
            )}

            <div style={{ margin: "16px 0", padding: 12, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8 }}>
              {detail?.excerpt && (
                <div style={{ fontStyle: "italic", color: "var(--muted)", marginBottom: 8 }}>{detail.excerpt}</div>
              )}
              <div style={{ whiteSpace: "pre-wrap", maxHeight: 300, overflow: "auto", fontSize: "0.9rem", lineHeight: 1.6 }}>
                {detail?.content ?? detail?.body ?? (detailLoading ? "" : "(no body)")}
              </div>
            </div>

            {detail && detail.images.length > 0 && (
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 16 }}>
                {detail.images.slice(0, 6).map((src, i) => (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img key={i} src={src} alt="" style={{ width: 96, height: 72, objectFit: "cover", borderRadius: 6, border: "1px solid var(--line)" }} />
                ))}
              </div>
            )}

            {detail && (
              <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 8, marginBottom: 12, fontSize: "0.75rem" }}>
                <div><strong>Reads:</strong> {detail.read_statistics.total_reads}</div>
                <div><strong>Unique:</strong> {detail.read_statistics.total_unique_reads}</div>
                <div><strong>Seconds:</strong> {detail.read_statistics.total_reading_seconds}</div>
                <div><strong>Points:</strong> {detail.read_statistics.total_points_generated}</div>
              </div>
            )}

            {detail && detail.moderation_history.length > 0 && (
              <div style={{ marginBottom: 12 }}>
                <div style={{ fontWeight: 700, marginBottom: 6 }}>Moderation history</div>
                <ul style={{ margin: 0, paddingLeft: 20, fontSize: "0.75rem", maxHeight: 120, overflow: "auto" }}>
                  {detail.moderation_history.map((log, i) => (
                    <li key={i}>
                      <strong>{log.action}</strong> by {log.admin_name} · {new Date(log.created_at).toLocaleString()}
                      {log.reason && <> — {log.reason}</>}
                    </li>
                  ))}
                </ul>
              </div>
            )}

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
                  <button className="admin-btn" onClick={closeReview}>Cancel</button>
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
                <button className="admin-btn" onClick={closeReview}>Close</button>
                <button className="admin-btn admin-btn-approve" onClick={() => publish(reviewing)} disabled={isPending}>
                  Publish
                </button>
              </div>
            )}

            {reviewing.status === "archived" && (
              <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
                <button className="admin-btn" onClick={closeReview}>Close</button>
                <button className="admin-btn admin-btn-approve" onClick={() => restore(reviewing)} disabled={isPending}>
                  Restore
                </button>
              </div>
            )}

            {(reviewing.status !== "pending_review" && reviewing.status !== "draft" && reviewing.status !== "archived") && (
              <div style={{ display: "flex", gap: 8, marginTop: 16, justifyContent: "flex-end" }}>
                <button className="admin-btn" onClick={closeReview}>Close</button>
              </div>
            )}
              </>
            )}
          </div>
        </div>
      )}

      {deleting && (
        <div className="admin-modal-overlay" onClick={closeDelete}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 480 }}>
            <h3 style={{ marginTop: 0, color: "#c62828" }}>⚠️ Delete article permanently</h3>
            <p style={{ fontSize: "0.9rem", lineHeight: 1.5 }}>
              You are about to <strong>permanently delete</strong> the article{" "}
              <strong>&ldquo;{deleting.title}&rdquo;</strong>. This cannot be undone —
              use <strong>Archive</strong> instead if you want to hide it temporarily.
            </p>

            <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.85rem", marginTop: 12 }}>
              <span style={{ fontWeight: 700 }}>Reason (required, logged in audit)</span>
              <textarea
                value={deleteReason}
                onChange={(e) => setDeleteReason(e.target.value)}
                rows={3}
                placeholder="e.g. Duplicate of another article; author requested removal…"
                style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "inherit", resize: "vertical" }}
              />
            </label>

            <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.85rem", marginTop: 12 }}>
              <span style={{ fontWeight: 700 }}>Type <code style={{ background: "rgba(200,50,50,0.1)", padding: "2px 6px", borderRadius: 4 }}>DELETE</code> to confirm</span>
              <input
                type="text"
                value={deleteConfirm}
                onChange={(e) => setDeleteConfirm(e.target.value)}
                placeholder="DELETE"
                style={{ padding: 10, border: "1px solid var(--line)", borderRadius: 8, background: "var(--panel)", color: "var(--foreground)", fontFamily: "monospace", fontSize: "0.95rem" }}
              />
            </label>

            {error && <p className="narlit-feedback narlit-feedback-error" style={{ marginTop: 12 }}>{error}</p>}

            <div style={{ display: "flex", gap: 8, justifyContent: "flex-end", marginTop: 16, flexWrap: "wrap" }}>
              <button className="admin-btn" onClick={closeDelete}>Cancel</button>
              <button
                className="admin-btn admin-btn-reject"
                onClick={confirmDelete}
                disabled={isPending || deleteReason.trim().length < 10 || deleteConfirm !== "DELETE"}
              >
                {isPending ? "Deleting…" : "Delete permanently"}
              </button>
            </div>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={archiveTarget !== null}
        title="Archive article?"
        variant="danger"
        confirmLabel="Archive"
        loading={isPending}
        message={
          archiveTarget ? (
            <>
              Archive <strong>&ldquo;{archiveTarget.title}&rdquo;</strong>? Members will
              no longer see it, but the article can be restored later from the
              Archived tab.
            </>
          ) : null
        }
        onConfirm={confirmArchive}
        onCancel={() => setArchiveTarget(null)}
      />

      <ConfirmDialog
        open={publishTarget !== null}
        title="Publish article?"
        variant="primary"
        confirmLabel="Publish now"
        loading={isPending}
        message={
          publishTarget ? (
            <>
              Publish <strong>&ldquo;{publishTarget.title}&rdquo;</strong> now? It will
              become visible to all members immediately.
            </>
          ) : null
        }
        onConfirm={confirmPublish}
        onCancel={() => setPublishTarget(null)}
      />

      <ConfirmDialog
        open={restoreTarget !== null}
        title="Restore article?"
        variant="primary"
        confirmLabel="Restore"
        loading={isPending}
        message={
          restoreTarget ? (
            <>
              Restore <strong>&ldquo;{restoreTarget.title}&rdquo;</strong>? It will
              become visible to members again with its previous status.
            </>
          ) : null
        }
        onConfirm={confirmRestore}
        onCancel={() => setRestoreTarget(null)}
      />
    </div>
  );
}
