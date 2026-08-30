"use client";

import { useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";

interface Bookmark {
  public_id: string;
  article: {
    public_id: string;
    title: string;
    excerpt: string | null;
    category: string | null;
    organization: { public_id: string | null; name: string | null };
    read_time_minutes: number | null;
    is_read: boolean;
  };
  created_at: string;
}

interface User { full_name: string; email: string }

export default function BookmarksPage() {
  const [user, setUser] = useState<User | null>(null);
  const [bookmarks, setBookmarks] = useState<Bookmark[]>([]);
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [isPending, startTransition] = useTransition();

  async function load() {
    setLoading(true);
    try {
      const res = await apiFetch("/member/bookmarks");
      const data = await res.json();
      if (res.ok) setBookmarks(data.data?.bookmarks?.data ?? data.data?.bookmarks ?? []);
      else setError(data?.message ?? "Failed to load bookmarks.");
    } catch { setError("Failed to load bookmarks."); }
    setLoading(false);
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      setChecking(false);
      apiFetch("/auth/me")
        .then((r) => r.json())
        .then((d) => setUser(d.data?.user ?? d.data ?? null))
        .catch(() => {});
      load();
    });
  }, []);

  function removeBookmark(b: Bookmark) {
    startTransition(async () => {
      try {
        await apiFetch(`/member/bookmarks/${b.article.public_id}`, { method: "DELETE" });
        setBookmarks((prev) => prev.filter((x) => x.public_id !== b.public_id));
      } catch { /* ignore */ }
    });
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
            <h2 className="hm-section-title">Saved for later</h2>
            <span className="hm-article-time">{bookmarks.length} bookmark{bookmarks.length !== 1 ? "s" : ""}</span>
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          {loading && (
            <div className="hm-articles" aria-busy="true" aria-label="Loading bookmarks">
              {Array.from({ length: 4 }).map((_, i) => (
                <article key={i} className="hm-article-card">
                  <div className="hm-article-top">
                    <Skeleton width={90} height={12} />
                    <Skeleton width={70} height={12} />
                  </div>
                  <div style={{ marginTop: 10 }}><Skeleton height={22} width="80%" /></div>
                  <div style={{ marginTop: 8, marginBottom: 12 }}><SkeletonText lines={2} /></div>
                  <div className="hm-article-footer">
                    <Skeleton width={140} height={12} />
                    <div style={{ display: "flex", gap: 8 }}>
                      <Skeleton width={70} height={28} radius={8} />
                      <Skeleton width={90} height={28} radius={8} />
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}

          {!loading && bookmarks.length === 0 && (
            <p className="hm-empty">
              You haven&apos;t saved anything yet. Tap the bookmark icon on any article to save it for later.
            </p>
          )}

          <div className="hm-articles">
            {!loading && bookmarks.map((b) => (
              <article key={b.public_id} className={`hm-article-card${b.article.is_read ? " hm-article-read" : ""}`}>
                <div className="hm-article-top">
                  <span className="hm-article-org">{b.article.organization.name ?? "NarLit"}</span>
                  {b.article.category && <span className="hm-article-cat">{b.article.category}</span>}
                  {b.article.is_read && <span className="hm-article-done">✓ Read</span>}
                </div>
                <h3 className="hm-article-title">{b.article.title}</h3>
                <p className="hm-article-excerpt">{b.article.excerpt}</p>
                <div className="hm-article-footer">
                  <span className="hm-article-time">
                    Saved {new Date(b.created_at).toLocaleDateString()}
                    {b.article.read_time_minutes ? ` · ${b.article.read_time_minutes} min` : ""}
                  </span>
                  <div style={{ display: "flex", gap: 8 }}>
                    <button
                      type="button"
                      className="hm-pager-btn"
                      onClick={() => removeBookmark(b)}
                      disabled={isPending}
                    >
                      Remove
                    </button>
                    <a href={`/articles/${b.article.public_id}`} className="hm-article-btn">
                      {b.article.is_read ? "Read again" : "Read now"}
                    </a>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}
