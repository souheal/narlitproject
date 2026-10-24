"use client";

<<<<<<< HEAD
import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { BrandLoader } from "@/components/BrandLoader";
import OrgNav from "@/components/OrgNav";
import ArticleEditor, { OrgArticleDetail } from "@/components/ArticleEditor";
import DeleteArticleDialog from "@/components/DeleteArticleDialog";
import BackLink from "@/components/BackLink";

function formatDate(iso: string | null): string {
  return iso ? new Date(iso).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) : "—";
}

/** The status shown to the organization; a draft an admin sent back reads as "Changes requested". */
function statusLabel(a: OrgArticleDetail): string {
  if (a.status === "draft" && a.rejection_reason) return "Changes requested";
  return (
    {
      draft: "Draft",
      pending_review: "Pending approval",
      rejected: "Not approved",
      published: "Published",
    } as Record<string, string>
  )[a.status] ?? a.status;
}

export default function OrgArticlePage() {
  const params = useParams<{ publicId: string }>();
  const publicId = params?.publicId;

  const [checking, setChecking] = useState(true);
  const [article, setArticle] = useState<OrgArticleDetail | null>(null);
  const [loadError, setLoadError] = useState("");
  const [success, setSuccess] = useState("");
  const [confirmDelete, setConfirmDelete] = useState(false);
  // Remounts the editor after a save so its fields reflect the saved article.
  const [version, setVersion] = useState(0);

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      try {
        const res = await apiFetch(`/organization/articles/${publicId}`);
        const data = await res.json();
        if (!res.ok) {
          setLoadError(res.status === 404 ? "This article doesn't exist or was deleted." : data?.message ?? "Failed to load article.");
        } else {
          setArticle(data.data.article);
        }
      } catch {
        setLoadError("Failed to load article.");
      }
      setChecking(false);
    });
  }, [publicId]);

  function handleSaved(saved: OrgArticleDetail, message: string) {
    setArticle(saved);
    setVersion((v) => v + 1);
    setSuccess(message);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  if (checking) {
    return <BrandLoader />;
  }

=======
import {
  ChangeEvent,
  FormEvent,
  use,
  useEffect,
  useRef,
  useState,
  useTransition,
} from "react";
import { apiFetch, requireRole } from "@/lib/api";
import { BrandLoader } from "@/components/BrandLoader";
import OrgNav from "@/components/OrgNav";

const CATEGORIES = [
  "Civil Rights",
  "Food Security",
  "Healthcare",
  "Education",
  "Environment",
  "Housing",
  "Women & Children",
  "Refugees",
  "Other",
];

const COVER_IMAGE_MAX_BYTES = 4 * 1024 * 1024;
const COVER_IMAGE_ACCEPT = "image/jpeg,image/png,image/webp,image/gif";

const STATUS_LABEL: Record<string, string> = {
  draft: "Draft",
  pending_review: "Pending review",
  approved: "Approved",
  rejected: "Rejected",
  published: "Published",
  archived: "Archived",
};

interface ArticleDetail {
  public_id: string;
  title: string;
  slug: string;
  excerpt: string | null;
  content: string | null;
  category: string | null;
  status: string;
  rejection_reason: string | null;
  published_at: string | null;
  created_at: string | null;
  read_time_minutes: number | null;
  total_reads: number;
  total_unique_reads: number;
  total_reading_seconds: number;
  total_points_generated: number;
  cover_image_url: string | null;
}

export default function OrgArticleDetailPage({
  params,
}: {
  params: Promise<{ publicId: string }>;
}) {
  const { publicId } = use(params);

  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [article, setArticle] = useState<ArticleDetail | null>(null);

  const [title, setTitle] = useState("");
  const [category, setCategory] = useState(CATEGORIES[0]);
  const [excerpt, setExcerpt] = useState("");
  const [body, setBody] = useState("");
  const [readTime, setReadTime] = useState<number>(5);
  const [status, setStatus] = useState<"draft" | "pending_review">("draft");

  const [coverImage, setCoverImage] = useState<File | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(null);
  const [removeCoverFlag, setRemoveCoverFlag] = useState(false);
  const coverInputRef = useRef<HTMLInputElement | null>(null);

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [isPending, startTransition] = useTransition();
  const [isDeleting, startDeleteTransition] = useTransition();
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  useEffect(() => {
    requireRole(["organization"]).then((user) => {
      if (!user) return;
      setChecking(false);
      void loadArticle();
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function loadArticle() {
    setLoading(true);
    setError("");
    try {
      const res = await apiFetch(`/organization/articles/${publicId}`);
      const payload = await res.json();
      if (!res.ok) {
        setError(payload?.message ?? "Article not found.");
        setArticle(null);
      } else {
        const a: ArticleDetail = payload.data?.article;
        setArticle(a);
        setTitle(a.title ?? "");
        setCategory(a.category ?? CATEGORIES[0]);
        setExcerpt(a.excerpt ?? "");
        setBody(a.content ?? "");
        setReadTime(a.read_time_minutes ?? 5);
        setStatus(
          a.status === "pending_review" || a.status === "draft"
            ? (a.status as "draft" | "pending_review")
            : "draft"
        );
      }
    } catch {
      setError("Failed to load article.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (!coverImage) {
      setCoverPreview(null);
      return;
    }
    const url = URL.createObjectURL(coverImage);
    setCoverPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [coverImage]);

  function handleCoverChange(event: ChangeEvent<HTMLInputElement>) {
    setError("");
    const file = event.target.files?.[0] ?? null;
    if (!file) {
      setCoverImage(null);
      return;
    }
    if (file.size > COVER_IMAGE_MAX_BYTES) {
      setError("Cover image must be 4 MB or smaller.");
      event.target.value = "";
      setCoverImage(null);
      return;
    }
    if (!COVER_IMAGE_ACCEPT.split(",").includes(file.type)) {
      setError("Cover image must be JPG, PNG, WEBP, or GIF.");
      event.target.value = "";
      setCoverImage(null);
      return;
    }
    setCoverImage(file);
    setRemoveCoverFlag(false);
  }

  function clearCoverSelection() {
    setCoverImage(null);
    if (coverInputRef.current) {
      coverInputRef.current.value = "";
    }
  }

  function markRemoveCurrentCover() {
    setRemoveCoverFlag(true);
    clearCoverSelection();
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!article) return;
    setError("");
    setSuccess("");

    startTransition(async () => {
      try {
        const formData = new FormData();
        formData.append("title", title);
        formData.append("category", category);
        formData.append("excerpt", excerpt);
        formData.append("body", body);
        formData.append("read_time_minutes", String(readTime));
        formData.append("status", status);
        if (coverImage) {
          formData.append("cover_image", coverImage);
        } else if (removeCoverFlag) {
          formData.append("remove_cover_image", "1");
        }

        const res = await apiFetch(`/organization/articles/${publicId}`, {
          method: "POST",
          body: formData,
        });
        const payload = await res.json();
        if (!res.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Failed to update article."
          );
        }
        const updated: ArticleDetail = payload.data?.article;
        setArticle(updated);
        setRemoveCoverFlag(false);
        clearCoverSelection();
        setSuccess("Changes saved.");
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  function handleDelete() {
    if (!article) return;
    setError("");
    startDeleteTransition(async () => {
      try {
        const res = await apiFetch(`/organization/articles/${publicId}`, {
          method: "DELETE",
        });
        if (!res.ok) {
          const payload = await res.json().catch(() => null);
          throw new Error(payload?.message ?? "Failed to delete article.");
        }
        window.location.href = "/organization/articles";
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
        setShowDeleteConfirm(false);
      }
    });
  }

  if (checking || loading) {
    return <BrandLoader />;
  }

  if (!article) {
    return (
      <div className="hm-shell" suppressHydrationWarning>
        <OrgNav active="articles" />
        <main className="hm-main">
          <section className="hm-section" style={{ maxWidth: 720, margin: "0 auto", textAlign: "center" }}>
            <h2 className="hm-section-title">Article unavailable</h2>
            <p className="hm-empty">{error || "This article does not exist."}</p>
            <a href="/organization/articles" className="hm-article-btn" style={{ display: "inline-block", marginTop: 16 }}>
              ← Back to articles
            </a>
          </section>
        </main>
      </div>
    );
  }

  const currentCoverUrl = removeCoverFlag ? null : article.cover_image_url;
  const previewUrl = coverPreview ?? currentCoverUrl;

>>>>>>> 9f279c46a8cdc1bb00b7c8644cb2bac1744a578f
  return (
    <div className="hm-shell" suppressHydrationWarning>
      <OrgNav active="articles" />

      <main className="hm-main">
<<<<<<< HEAD
        <div className="oa-wrap">
          <BackLink href="/organization/articles" className="su-link">← All articles</BackLink>

          {loadError && <p className="narlit-feedback narlit-feedback-error">{loadError}</p>}

          {article && (
            <>
              <header className="oa-header">
                <div className="oa-header-row">
                  <div>
                    <div className="hm-article-top">
                      <span className="hm-article-org">{statusLabel(article)}</span>
                      {article.category && <span className="hm-article-cat">{article.category}</span>}
                    </div>
                    <h1 className="oa-title">{article.title}</h1>
                  </div>
                  <button type="button" className="hm-pager-btn oa-delete-btn" onClick={() => setConfirmDelete(true)}>
                    Delete
                  </button>
                </div>
                <p className="oa-meta">
                  Created {formatDate(article.created_at)}
                  {article.updated_at && <> · Last updated {formatDate(article.updated_at)}</>}
                  {article.resubmission_count > 0 && <> · Resubmitted {article.resubmission_count}×</>}
                </p>
              </header>

              <StatusBanner article={article} />

              {success && <p className="narlit-feedback narlit-feedback-success">{success}</p>}

              {article.status === "published" && (
                <section className="oa-stats">
                  <div className="hm-panel oa-stat">
                    <span className="oa-stat-value">{article.total_reads}</span>
                    <span className="oa-stat-label">Reads</span>
                  </div>
                  <div className="hm-panel oa-stat">
                    <span className="oa-stat-value">{article.total_unique_reads}</span>
                    <span className="oa-stat-label">Unique readers</span>
                  </div>
                  <div className="hm-panel oa-stat">
                    <span className="oa-stat-value">{article.total_points_generated}</span>
                    <span className="oa-stat-label">Impact points</span>
                  </div>
                </section>
              )}

              <ArticleEditor
                key={version}
                article={article}
                approvalRequired={article.approval_required}
                onSaved={handleSaved}
              />
            </>
          )}
        </div>
      </main>

      {article && confirmDelete && (
        <DeleteArticleDialog
          article={article}
          onClose={() => setConfirmDelete(false)}
          onDeleted={() => { window.location.replace("/organization/articles?deleted=1"); }}
        />
      )}
=======
        <section className="hm-section" style={{ maxWidth: 820, margin: "0 auto" }}>
          <div className="hm-section-header">
            <h2 className="hm-section-title">Manage article</h2>
            <a href="/organization/articles" className="su-link">← Back</a>
          </div>

          <div style={{ display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap", marginBottom: 16 }}>
            <span className="hm-article-cat">
              Status: {STATUS_LABEL[article.status] ?? article.status}
            </span>
            {article.published_at && (
              <span className="hm-article-time">
                Published {new Date(article.published_at).toLocaleDateString()}
              </span>
            )}
          </div>

          {article.status === "rejected" && article.rejection_reason && (
            <div
              className="narlit-feedback narlit-feedback-error"
              style={{ whiteSpace: "pre-wrap", marginBottom: 16 }}
            >
              <strong>Rejected:</strong> {article.rejection_reason}
            </div>
          )}

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))",
              gap: 12,
              marginBottom: 20,
            }}
          >
            <StatTile label="Total reads" value={article.total_reads} />
            <StatTile label="Unique reads" value={article.total_unique_reads} />
            <StatTile label="Reading time" value={`${Math.round(article.total_reading_seconds / 60)}m`} />
            <StatTile label="Points generated" value={article.total_points_generated} />
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}
          {success && <p className="narlit-feedback narlit-feedback-success">{success}</p>}

          <form className="login-form" onSubmit={handleSubmit}>
            <label className="narlit-field">
              <span>Title</span>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                maxLength={180}
                required
              />
            </label>

            <label className="narlit-field">
              <span>Category</span>
              <select value={category} onChange={(e) => setCategory(e.target.value)}>
                {CATEGORIES.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            </label>

            <label className="narlit-field">
              <span>Excerpt</span>
              <textarea
                value={excerpt}
                onChange={(e) => setExcerpt(e.target.value)}
                rows={3}
                maxLength={280}
              />
            </label>

            <label className="narlit-field">
              <span>Body</span>
              <textarea
                value={body}
                onChange={(e) => setBody(e.target.value)}
                rows={14}
                required
              />
            </label>

            <div className="narlit-field">
              <span>Cover image</span>
              <input
                ref={coverInputRef}
                type="file"
                accept={COVER_IMAGE_ACCEPT}
                onChange={handleCoverChange}
              />
              <small style={{ color: "var(--muted)", fontSize: "0.8rem", marginTop: 4 }}>
                JPG, PNG, WEBP, or GIF · up to 4 MB.
              </small>
              {previewUrl && (
                <div style={{ marginTop: 10 }}>
                  <img
                    src={previewUrl}
                    alt="Cover preview"
                    style={{
                      width: "100%",
                      maxHeight: 240,
                      objectFit: "cover",
                      borderRadius: 10,
                      border: "1px solid var(--line)",
                    }}
                  />
                  <div style={{ marginTop: 8, display: "flex", gap: 8, flexWrap: "wrap" }}>
                    {coverImage && (
                      <button
                        type="button"
                        className="hm-pager-btn"
                        onClick={clearCoverSelection}
                        style={{ padding: "6px 10px" }}
                      >
                        Cancel new upload
                      </button>
                    )}
                    {!coverImage && article.cover_image_url && !removeCoverFlag && (
                      <button
                        type="button"
                        className="hm-pager-btn"
                        onClick={markRemoveCurrentCover}
                        style={{ padding: "6px 10px" }}
                      >
                        Remove current cover
                      </button>
                    )}
                  </div>
                </div>
              )}
              {removeCoverFlag && (
                <small style={{ color: "var(--muted)", fontSize: "0.8rem", marginTop: 8 }}>
                  Cover will be removed when you save.
                  <button
                    type="button"
                    className="su-link"
                    onClick={() => setRemoveCoverFlag(false)}
                    style={{ marginLeft: 8, background: "none", border: 0, padding: 0, cursor: "pointer" }}
                  >
                    Undo
                  </button>
                </small>
              )}
            </div>

            <label className="narlit-field">
              <span>Estimated read time (minutes)</span>
              <input
                type="number"
                min={1}
                max={60}
                value={readTime}
                onChange={(e) =>
                  setReadTime(Math.max(1, Math.min(60, Number(e.target.value) || 1)))
                }
              />
            </label>

            <label className="narlit-field">
              <span>Save as</span>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value as "draft" | "pending_review")}
              >
                <option value="pending_review">
                  {article.status === "rejected" ? "Resubmit for review" : "Submit for review"}
                </option>
                <option value="draft">Draft (save without submitting)</option>
              </select>
            </label>

            <div style={{ display: "flex", gap: 12, flexWrap: "wrap" }}>
              <button
                type="submit"
                className="narlit-button narlit-button-primary"
                disabled={isPending || isDeleting}
              >
                {isPending ? "Saving…" : "Save changes"}
              </button>
              <button
                type="button"
                className="hm-pager-btn"
                onClick={() => setShowDeleteConfirm(true)}
                disabled={isPending || isDeleting}
                style={{ color: "#b91c1c" }}
              >
                Delete article
              </button>
            </div>
          </form>

          {showDeleteConfirm && (
            <div
              role="dialog"
              aria-modal="true"
              style={{
                position: "fixed",
                inset: 0,
                background: "rgba(0,0,0,0.4)",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                padding: 20,
                zIndex: 50,
              }}
              onClick={() => !isDeleting && setShowDeleteConfirm(false)}
            >
              <div
                onClick={(e) => e.stopPropagation()}
                style={{
                  background: "var(--background)",
                  padding: 24,
                  borderRadius: 14,
                  maxWidth: 420,
                  width: "100%",
                  border: "1px solid var(--line)",
                }}
              >
                <h3 style={{ marginTop: 0 }}>Delete this article?</h3>
                <p style={{ color: "var(--muted)" }}>
                  This will remove the article from your library. The action can only be reversed by an admin.
                </p>
                <div style={{ display: "flex", gap: 10, justifyContent: "flex-end", marginTop: 16 }}>
                  <button
                    type="button"
                    className="hm-pager-btn"
                    onClick={() => setShowDeleteConfirm(false)}
                    disabled={isDeleting}
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    className="narlit-button narlit-button-primary"
                    onClick={handleDelete}
                    disabled={isDeleting}
                    style={{ background: "#b91c1c", borderColor: "#b91c1c" }}
                  >
                    {isDeleting ? "Deleting…" : "Yes, delete"}
                  </button>
                </div>
              </div>
            </div>
          )}
        </section>
      </main>
>>>>>>> 9f279c46a8cdc1bb00b7c8644cb2bac1744a578f
    </div>
  );
}

<<<<<<< HEAD
function StatusBanner({ article }: { article: OrgArticleDetail }) {
  if (article.status === "pending_review") {
    return (
      <div className="oa-banner oa-banner-info">
        <strong>Waiting for approval.</strong> The NarLit team is reviewing this article. You&apos;ll get a
        notification as soon as it&apos;s approved or sent back.
      </div>
    );
  }

  if (article.status === "published") {
    return (
      <div className="oa-banner oa-banner-success">
        <span>
          <strong>Live.</strong> Readers can see this article since {formatDate(article.published_at)}.
        </span>
        <a href={`/articles/${article.public_id}`} className="su-link">View as a reader →</a>
      </div>
    );
  }

  if (article.status === "rejected") {
    return (
      <div className="oa-banner oa-banner-warn">
        <strong>Not approved.</strong> {article.rejection_reason ?? "No reason was given."}
        <div className="oa-banner-hint">Revise the article below and publish it again if the issue can be fixed.</div>
      </div>
    );
  }

  if (article.status === "draft" && article.rejection_reason) {
    return (
      <div className="oa-banner oa-banner-warn">
        <strong>Changes requested.</strong> {article.rejection_reason}
        <div className="oa-banner-hint">Make the changes below, then publish it again.</div>
      </div>
    );
  }

  return (
    <div className="oa-banner oa-banner-info">
      <strong>Draft.</strong> Only your organization can see this. Publish it when it&apos;s ready.
=======
function StatTile({ label, value }: { label: string; value: number | string }) {
  return (
    <div
      style={{
        padding: 12,
        border: "1px solid var(--line)",
        borderRadius: 10,
        background: "var(--panel)",
      }}
    >
      <div style={{ fontSize: "0.75rem", color: "var(--muted)", textTransform: "uppercase", letterSpacing: "0.05em" }}>
        {label}
      </div>
      <div style={{ fontSize: "1.4rem", fontWeight: 700, marginTop: 4 }}>{value}</div>
>>>>>>> 9f279c46a8cdc1bb00b7c8644cb2bac1744a578f
    </div>
  );
}
