"use client";

import { useEffect, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Article {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  organization: { public_id: string | null; name: string | null };
  read_time_minutes: number | null;
  is_read: boolean;
  cta_label: string;
}

interface Pagination {
  current_page: number;
  last_page: number;
  total: number;
}

export default function ArticlesPage() {
  const [articles, setArticles] = useState<Article[]>([]);
  const [meta, setMeta] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(false);
  const [markingId, setMarkingId] = useState<string | null>(null);

  async function loadArticles(targetPage: number) {
    setLoading(true);
    try {
      const res = await apiFetch(`/member/articles?per_page=10&page=${targetPage}`);
      const data = await res.json();
      const payload = data.data?.articles;
      setArticles(payload?.data ?? []);
      setMeta({
        current_page: payload?.current_page ?? targetPage,
        last_page: payload?.last_page ?? 1,
        total: payload?.total ?? 0,
      });
    } catch {
      /* ignore */
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      await loadArticles(1);
      setChecking(false);
    });
  }, []);

  async function handleRead(article: Article) {
    if (markingId) return;
    setMarkingId(article.public_id);
    try {
      await apiFetch(`/member/articles/${article.public_id}/read`, {
        method: "POST",
        body: JSON.stringify({ read_percent: 100 }),
      });
      await loadArticles(page);
    } catch {
      /* ignore */
    } finally {
      setMarkingId(null);
    }
  }

  function goToPage(target: number) {
    if (!meta) return;
    if (target < 1 || target > meta.last_page) return;
    setPage(target);
    loadArticles(target);
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
      </div>
    );
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <nav className="hm-nav">
        <div className="hm-nav-inner">
          <a href="/dashboard" className="hm-nav-brand">
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span className="hm-nav-wordmark">NarLit</span>
          </a>
          <div className="hm-nav-links">
            <a href="/dashboard" className="hm-nav-link">Home</a>
            <a href="/articles" className="hm-nav-link hm-nav-link-active">Explore</a>
            <a href="#" className="hm-nav-link">My Impact</a>
          </div>
          <div className="hm-nav-user" />
        </div>
      </nav>

      <main className="hm-main">
        <section className="hm-section">
          <div className="hm-section-header">
            <h2 className="hm-section-title">All Stories</h2>
            {meta && <span className="hm-article-time">{meta.total} total</span>}
          </div>

          {loading && articles.length === 0 && (
            <p className="hm-empty">Loading stories…</p>
          )}

          {!loading && articles.length === 0 && (
            <p className="hm-empty">No stories available yet.</p>
          )}

          <div className="hm-articles">
            {articles.map((a) => (
              <article key={a.public_id} className={`hm-article-card${a.is_read ? " hm-article-read" : ""}`}>
                <div className="hm-article-top">
                  <span className="hm-article-org">{a.organization.name ?? "NarLit"}</span>
                  {a.category && <span className="hm-article-cat">{a.category}</span>}
                  {a.is_read && <span className="hm-article-done">✓ Read</span>}
                </div>
                <h3 className="hm-article-title">{a.title}</h3>
                <p className="hm-article-excerpt">{a.excerpt}</p>
                <div className="hm-article-footer">
                  <span className="hm-article-time">
                    {a.read_time_minutes ? `${a.read_time_minutes} min read` : "Quick read"}
                  </span>
                  <button
                    type="button"
                    className="hm-article-btn"
                    onClick={() => handleRead(a)}
                    disabled={markingId === a.public_id}
                  >
                    {markingId === a.public_id ? "Saving…" : a.cta_label}
                  </button>
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
