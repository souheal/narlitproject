"use client";

import { use, useEffect, useRef, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface RelatedArticle {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  organization: { public_id: string | null; name: string | null };
  read_time_minutes: number | null;
  is_read: boolean;
}

interface ArticleDetail {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  body: string | null;
  organization: { public_id: string | null; name: string | null };
  read_time_minutes: number | null;
  published_at: string | null;
  is_read: boolean;
  is_bookmarked: boolean;
  requires_subscription: boolean;
  has_access: boolean;
  related: RelatedArticle[];
}

interface User { full_name: string; email: string }

export default function ArticleDetailPage({
  params,
}: {
  params: Promise<{ publicId: string }>;
}) {
  const { publicId } = use(params);
  const [user, setUser] = useState<User | null>(null);
  const [article, setArticle] = useState<ArticleDetail | null>(null);
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");
  const [readingSeconds, setReadingSeconds] = useState(0);
  const [markedRead, setMarkedRead] = useState(false);
  const [saving, setSaving] = useState(false);
  const [bookmarking, setBookmarking] = useState(false);
  const startedAt = useRef<number>(Date.now());
  const bodyRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = `/login?next=/articles/${publicId}`;
        return;
      }
      try {
        const meRes = await apiFetch("/auth/me");
        const meData = await meRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
      } catch { /* ignore */ }
      try {
        const res = await apiFetch(`/articles/${publicId}`);
        const payload = await res.json();
        if (!res.ok) {
          setError(payload?.message ?? "Article not found.");
        } else {
          setArticle(payload.data?.article ?? null);
        }
      } catch {
        setError("Failed to load article.");
      }
      setChecking(false);
      startedAt.current = Date.now();
    });
  }, [publicId]);

  useEffect(() => {
    if (!article || !article.has_access || markedRead) return;
    const interval = setInterval(() => {
      setReadingSeconds(Math.floor((Date.now() - startedAt.current) / 1000));
    }, 1000);
    return () => clearInterval(interval);
  }, [article, markedRead]);

  async function markRead(readPercent: number) {
    if (!article || markedRead || saving) return;
    setSaving(true);
    try {
      const res = await apiFetch(`/member/articles/${article.public_id}/read`, {
        method: "POST",
        body: JSON.stringify({
          read_percent: readPercent,
          reading_seconds: readingSeconds,
        }),
      });
      if (res.ok) {
        setMarkedRead(true);
        setArticle({ ...article, is_read: true });
      }
    } catch {
      /* ignore */
    } finally {
      setSaving(false);
    }
  }

  useEffect(() => {
    if (!article || !article.has_access || markedRead) return;
    function onScroll() {
      if (!bodyRef.current) return;
      const rect = bodyRef.current.getBoundingClientRect();
      const total = bodyRef.current.scrollHeight;
      const visibleBottom = window.innerHeight - rect.top;
      const percent = Math.max(0, Math.min(100, Math.round((visibleBottom / total) * 100)));
      if (percent >= 80 && !markedRead) {
        markRead(percent);
      }
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, [article, markedRead]);

  async function toggleBookmark() {
    if (!article || bookmarking) return;
    setBookmarking(true);
    try {
      if (article.is_bookmarked) {
        await apiFetch(`/member/bookmarks/${article.public_id}`, { method: "DELETE" });
      } else {
        await apiFetch(`/member/bookmarks/${article.public_id}`, { method: "POST" });
      }
      setArticle({ ...article, is_bookmarked: !article.is_bookmarked });
    } catch { /* ignore */ }
    setBookmarking(false);
  }

  async function shareArticle() {
    if (!article) return;
    const url = window.location.href;
    if (navigator.share) {
      try { await navigator.share({ title: article.title, text: article.excerpt ?? undefined, url }); }
      catch { /* user canceled */ }
    } else {
      try { await navigator.clipboard.writeText(url); alert("Link copied to clipboard"); } catch { /* ignore */ }
    }
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
      </div>
    );
  }

  if (error || !article) {
    return (
      <div className="hm-shell" suppressHydrationWarning>
        <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
        <main className="hm-main">
          <section className="hm-section" style={{ maxWidth: 720, margin: "0 auto", textAlign: "center" }}>
            <h2 className="hm-section-title">Article unavailable</h2>
            <p className="hm-empty">{error || "This article does not exist."}</p>
            <a href="/articles" className="hm-article-btn" style={{ display: "inline-block", marginTop: 16 }}>
              ← Back to stories
            </a>
          </section>
        </main>
      </div>
    );
  }

  const locked = article.requires_subscription && !article.has_access;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />

      <main className="hm-main">
        <article className="hm-section" style={{ maxWidth: 760, margin: "0 auto" }}>
          <a href="/articles" className="su-link" style={{ fontSize: "0.85rem" }}>← Back to stories</a>

          <div className="hm-article-top" style={{ marginTop: 16 }}>
            <span className="hm-article-org">{article.organization.name ?? "NarLit"}</span>
            {article.category && <span className="hm-article-cat">{article.category}</span>}
            {article.is_read && <span className="hm-article-done">✓ Read</span>}
          </div>

          <h1 className="hm-section-title" style={{ fontSize: "2rem", lineHeight: 1.2, marginTop: 12 }}>
            {article.title}
          </h1>

          <p className="hm-article-excerpt" style={{ fontSize: "1.05rem", marginTop: 8 }}>
            {article.excerpt}
          </p>

          <div className="hm-article-footer" style={{ marginTop: 16, marginBottom: 24, flexWrap: "wrap", gap: 8 }}>
            <span className="hm-article-time">
              {article.read_time_minutes ? `${article.read_time_minutes} min read` : "Quick read"}
              {article.published_at && ` · ${new Date(article.published_at).toLocaleDateString()}`}
            </span>
            <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
              <button
                type="button"
                className="hm-pager-btn"
                onClick={toggleBookmark}
                disabled={bookmarking}
                aria-label={article.is_bookmarked ? "Remove bookmark" : "Bookmark article"}
                style={{ padding: "8px 12px" }}
              >
                {article.is_bookmarked ? "🔖 Saved" : "🔖 Save"}
              </button>
              <button
                type="button"
                className="hm-pager-btn"
                onClick={shareArticle}
                aria-label="Share article"
                style={{ padding: "8px 12px" }}
              >
                🔗 Share
              </button>
              {article.has_access && !markedRead && !article.is_read && (
                <button
                  type="button"
                  className="hm-article-btn"
                  onClick={() => markRead(100)}
                  disabled={saving}
                >
                  {saving ? "Saving…" : "Mark as read"}
                </button>
              )}
            </div>
          </div>

          {locked ? (
            <div className="hm-panel" style={{ textAlign: "center", padding: 40 }}>
              <div style={{ fontSize: "2.5rem", marginBottom: 12 }}>🔒</div>
              <h2 className="hm-panel-title">Members-only story</h2>
              <p className="hm-panel-sub" style={{ maxWidth: 460, margin: "8px auto 20px" }}>
                This full article is available to active NarLit subscribers. Your subscription funds
                the nonprofit behind this story every time you finish reading.
              </p>
              <a href="/signup" className="hm-article-btn" style={{ display: "inline-block" }}>
                Start your subscription →
              </a>
            </div>
          ) : (
            <div
              ref={bodyRef}
              className="hm-article-body"
              style={{
                fontSize: "1.05rem",
                lineHeight: 1.75,
                color: "var(--foreground)",
                whiteSpace: "pre-wrap",
              }}
            >
              {article.body ?? article.excerpt}
            </div>
          )}

          {article.organization.public_id && (
            <div style={{ marginTop: 32, padding: 20, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 14 }}>
              <p style={{ margin: 0, fontSize: "0.8rem", color: "var(--muted)", textTransform: "uppercase", letterSpacing: "0.05em" }}>
                Published by
              </p>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: 8 }}>
                <div>
                  <h3 style={{ margin: 0, fontSize: "1.05rem" }}>{article.organization.name}</h3>
                  <p style={{ margin: "4px 0 0", fontSize: "0.85rem", color: "var(--muted)" }}>
                    Learn more about this nonprofit.
                  </p>
                </div>
                <a href={`/organizations/${article.organization.public_id}`} className="hm-article-btn">
                  Visit →
                </a>
              </div>
            </div>
          )}

          {article.related && article.related.length > 0 && (
            <section style={{ marginTop: 40 }}>
              <h2 className="hm-section-title" style={{ fontSize: "1.2rem" }}>Related stories</h2>
              <div className="hm-articles" style={{ marginTop: 12 }}>
                {article.related.slice(0, 3).map((r) => (
                  <article key={r.public_id} className={`hm-article-card${r.is_read ? " hm-article-read" : ""}`}>
                    <div className="hm-article-top">
                      <span className="hm-article-org">{r.organization.name ?? "NarLit"}</span>
                      {r.category && <span className="hm-article-cat">{r.category}</span>}
                      {r.is_read && <span className="hm-article-done">✓ Read</span>}
                    </div>
                    <h3 className="hm-article-title">{r.title}</h3>
                    <p className="hm-article-excerpt">{r.excerpt}</p>
                    <div className="hm-article-footer">
                      <span className="hm-article-time">
                        {r.read_time_minutes ? `${r.read_time_minutes} min` : "Quick read"}
                      </span>
                      <a href={`/articles/${r.public_id}`} className="hm-article-btn">Read →</a>
                    </div>
                  </article>
                ))}
              </div>
            </section>
          )}
        </article>
      </main>
    </div>
  );
}
