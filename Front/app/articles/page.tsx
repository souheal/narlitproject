"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";

interface User { full_name: string; email: string }

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
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<User | null>(null);

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
      setChecking(false);
      apiFetch("/auth/me")
        .then((r) => r.json())
        .then((d) => setUser(d.data?.user ?? d.data ?? null))
        .catch(() => {});
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

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />

      <main className="hm-main">
        <section className="hm-section">
          <div className="hm-section-header">
            <h2 className="hm-section-title">All Stories</h2>
            {meta && <span className="hm-article-time">{meta.total} total</span>}
          </div>

          {loading && articles.length === 0 && (
            <div className="hm-articles" aria-busy="true" aria-label="Loading stories">
              {Array.from({ length: 6 }).map((_, i) => (
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
                    <Skeleton width={70} height={12} />
                    <Skeleton width={90} height={32} radius={8} />
                  </div>
                </article>
              ))}
            </div>
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
                  <a href={`/articles/${a.public_id}`} className="hm-article-btn">
                    {a.cta_label}
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
