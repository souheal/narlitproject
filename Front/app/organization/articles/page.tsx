"use client";

import { useEffect, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";

interface OrgArticle {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  status: string;
  published_at: string | null;
  read_time_minutes: number | null;
  total_reads: number;
  total_unique_reads: number;
  total_points_generated: number;
}

interface Pagination {
  current_page: number;
  last_page: number;
  total: number;
}

const STATUS_LABEL: Record<string, string> = {
  draft: "Draft",
  pending_review: "Pending review",
  approved: "Approved",
  rejected: "Rejected",
  published: "Published",
  archived: "Archived",
};

export default function OrgArticlesPage() {
  const [articles, setArticles] = useState<OrgArticle[]>([]);
  const [meta, setMeta] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  async function loadArticles(target: number) {
    setLoading(true);
    setError("");
    try {
      const res = await apiFetch(`/organization/articles?per_page=10&page=${target}`);
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to load articles.");
        return;
      }
      const payload = data.data?.articles;
      setArticles(payload?.data ?? []);
      setMeta({
        current_page: payload?.current_page ?? target,
        last_page: payload?.last_page ?? 1,
        total: payload?.total ?? 0,
      });
    } catch {
      setError("Failed to load articles.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    validateSession().then((valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      setChecking(false);
      loadArticles(1);
    });
  }, []);

  function goToPage(target: number) {
    if (!meta) return;
    if (target < 1 || target > meta.last_page) return;
    setPage(target);
    loadArticles(target);
  }

  if (checking) {
    return (
      <BrandLoader />
    );
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <nav className="hm-nav">
        <div className="hm-nav-inner">
          <a href="/organization/dashboard" className="hm-nav-brand">
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span className="hm-nav-wordmark">NarLit · Org</span>
          </a>
          <div className="hm-nav-links">
            <a href="/organization/dashboard" className="hm-nav-link">Overview</a>
            <a href="/organization/articles" className="hm-nav-link hm-nav-link-active">Articles</a>
            <a href="/organization/payouts" className="hm-nav-link">Payouts</a>
          </div>
          <div className="hm-nav-user" />
        </div>
      </nav>

      <main className="hm-main">
        <section className="hm-section">
          <div className="hm-section-header">
            <h2 className="hm-section-title">Your Articles</h2>
            <a href="/organization/articles/new" className="hm-article-btn" style={{ display: "inline-block" }}>
              + New article
            </a>
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          {loading && articles.length === 0 && (
            <div className="hm-articles" aria-busy="true" aria-label="Loading articles">
              {Array.from({ length: 5 }).map((_, i) => (
                <article key={i} className="hm-article-card">
                  <div className="hm-article-top">
                    <Skeleton width={90} height={12} />
                    <Skeleton width={70} height={12} />
                  </div>
                  <div style={{ marginTop: 10 }}>
                    <Skeleton height={22} width="85%" />
                  </div>
                  <div style={{ marginTop: 10, marginBottom: 12 }}>
                    <SkeletonText lines={2} />
                  </div>
                  <div className="hm-article-footer">
                    <Skeleton width={140} height={12} />
                    <Skeleton width={90} height={32} radius={8} />
                  </div>
                </article>
              ))}
            </div>
          )}
          {!loading && articles.length === 0 && !error && (
            <p className="hm-empty">
              You haven&apos;t submitted any articles yet.{" "}
              <a href="/organization/articles/new" className="su-link">Submit your first one</a>.
            </p>
          )}

          <div className="hm-articles">
            {articles.map((a) => (
              <article key={a.public_id} className="hm-article-card">
                <div className="hm-article-top">
                  <span className="hm-article-org">{STATUS_LABEL[a.status] ?? a.status}</span>
                  {a.category && <span className="hm-article-cat">{a.category}</span>}
                </div>
                <h3 className="hm-article-title">{a.title}</h3>
                <p className="hm-article-excerpt">{a.excerpt}</p>
                <div className="hm-article-footer">
                  <span className="hm-article-time">
                    {a.total_reads} reads · {a.total_unique_reads} unique
                  </span>
                  <a href={`/organization/articles/${a.public_id}`} className="hm-article-btn">
                    Manage →
                  </a>
                </div>
              </article>
            ))}
          </div>

          {meta && meta.last_page > 1 && (
            <div className="hm-pager">
              <button
                type="button"
                className="hm-pager-btn"
                onClick={() => goToPage(page - 1)}
                disabled={page <= 1 || loading}
              >
                ← Prev
              </button>
              <span className="hm-pager-info">
                Page {meta.current_page} of {meta.last_page}
              </span>
              <button
                type="button"
                className="hm-pager-btn"
                onClick={() => goToPage(page + 1)}
                disabled={page >= meta.last_page || loading}
              >
                Next →
              </button>
            </div>
          )}
        </section>
      </main>
    </div>
  );
}
