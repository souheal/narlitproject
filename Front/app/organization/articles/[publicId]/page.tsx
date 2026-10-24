"use client";

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

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <OrgNav active="articles" />

      <main className="hm-main">
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
    </div>
  );
}

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
    </div>
  );
}
