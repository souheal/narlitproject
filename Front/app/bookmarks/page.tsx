"use client";

import { useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

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
  const [error, setError] = useState("");
  const [isPending, startTransition] = useTransition();

  async function load() {
    try {
      const res = await apiFetch("/member/bookmarks");
      const data = await res.json();
      if (res.ok) setBookmarks(data.data?.bookmarks?.data ?? data.data?.bookmarks ?? []);
      else setError(data?.message ?? "Failed to load bookmarks.");
    } catch { setError("Failed to load bookmarks."); }
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const meRes = await apiFetch("/auth/me");
        const meData = await meRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
      } catch { /* ignore */ }
      await load();
      setChecking(false);
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
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" /><span className="hm-loading-dot" /><span className="hm-loading-dot" />
      </div>
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

          {bookmarks.length === 0 && (
            <p className="hm-empty">
              You haven&apos;t saved anything yet. Tap the bookmark icon on any article to save it for later.
            </p>
          )}

          <div className="hm-articles">
            {bookmarks.map((b) => (
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
