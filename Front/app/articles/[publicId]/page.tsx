"use client";

import { use, useEffect, useRef, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";
import BackLink from "@/components/BackLink";

interface RelatedArticle {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  organization: { public_id: string | null; name: string | null };
  read_time_minutes: number | null;
  is_read: boolean;
  cover_image_url?: string | null;
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
  cover_image_url?: string | null;
  images?: string[];
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
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [readingSeconds, setReadingSeconds] = useState(0);
  const [markedRead, setMarkedRead] = useState(false);
  const [saving, setSaving] = useState(false);
  const [bookmarking, setBookmarking] = useState(false);
  // Full body stays hidden behind a "Read full article" CTA until the reader
  // explicitly opts in. That click is what credits the nonprofit's share.
  const [bodyUnlocked, setBodyUnlocked] = useState(false);
  const startedAt = useRef<number>(Date.now());
  const bodyRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = `/login?next=/articles/${publicId}`;
        return;
      }
      setChecking(false);
      apiFetch("/auth/me")
        .then((r) => r.json())
        .then((d) => setUser(d.data?.user ?? d.data ?? null))
        .catch(() => {});
      setLoading(true);
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
      setLoading(false);
      startedAt.current = Date.now();
    });
  }, [publicId]);

  // Once we know the article, auto-unlock the body if the reader has already
  // paid for it (previous completed read) — no need to make them click again.
  useEffect(() => {
    if (article?.is_read) setBodyUnlocked(true);
  }, [article?.is_read]);

  // Reading-time counter only runs while the body is actually visible.
  useEffect(() => {
    if (!article || !article.has_access || !bodyUnlocked || markedRead) return;
    startedAt.current = Date.now();
    const interval = setInterval(() => {
      setReadingSeconds(Math.floor((Date.now() - startedAt.current) / 1000));
    }, 1000);
    return () => clearInterval(interval);
  }, [article, bodyUnlocked, markedRead]);

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

  /** The reader confirms they want to open the full article. */
  async function unlockAndRead() {
    if (!article || !article.has_access || saving) return;
    setBodyUnlocked(true);
    // Fire-and-forget the credit; the UI opens immediately either way.
    // The backend ignores repeat credits for the same article (see
    // UserImpactDashboardService::recordRead).
    if (!article.is_read) {
      void markRead(100);
    }
  }

  // The read is now triggered explicitly by the unlock CTA, so no scroll tracking.
  // (A scroll-based auto-mark would make it feel like the reader is paying by accident.)

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
      <BrandLoader />
    );
  }

  if (loading) {
    return (
      <div className="hm-shell" suppressHydrationWarning>
        <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
        <main className="hm-main">
          <article className="hm-section" style={{ maxWidth: 760, margin: "0 auto" }} aria-busy="true" aria-label="Loading article">
            <Skeleton width={120} height={12} />
            <div className="hm-article-top" style={{ marginTop: 16 }}>
              <Skeleton width={100} height={12} />
              <Skeleton width={70} height={12} />
            </div>
            <div style={{ marginTop: 16 }}>
              <Skeleton width="90%" height={32} />
            </div>
            <div style={{ marginTop: 12 }}>
              <SkeletonText lines={2} />
            </div>
            <div className="hm-article-footer" style={{ marginTop: 16, marginBottom: 24 }}>
              <Skeleton width={180} height={12} />
              <div style={{ display: "flex", gap: 8 }}>
                <Skeleton width={80} height={32} radius={999} />
                <Skeleton width={80} height={32} radius={999} />
              </div>
            </div>
            <div style={{ marginTop: 20 }}>
              <SkeletonText lines={8} />
            </div>
            <div style={{ marginTop: 20 }}>
              <SkeletonText lines={6} />
            </div>
          </article>
        </main>
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
            <BackLink href="/articles" className="hm-article-btn" style={{ display: "inline-block", marginTop: 16 }}>
              ← Back to stories
            </BackLink>
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
          <BackLink href="/articles" className="su-link" style={{ fontSize: "0.85rem" }}>← Back to stories</BackLink>

          {article.cover_image_url && (
            <img
              src={article.cover_image_url}
              alt=""
              style={{
                width: "100%",
                maxHeight: 420,
                objectFit: "cover",
                borderRadius: 14,
                marginTop: 16,
                border: "1px solid var(--line)",
              }}
            />
          )}

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
            </div>
          </div>

          {article.cover_image_url && (
            // eslint-disable-next-line @next/next/no-img-element
            <img className="ar-cover" src={article.cover_image_url} alt="" />
          )}

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
          ) : !bodyUnlocked ? (
            <div className="hm-panel" style={{ textAlign: "center", padding: 32 }}>
              <p
                className="hm-article-excerpt"
                style={{ fontSize: "1.05rem", lineHeight: 1.65, maxWidth: 540, margin: "0 auto 20px" }}
              >
                {article.excerpt || "Open this story to read it in full."}
              </p>
              <button
                type="button"
                className="hm-article-btn"
                onClick={unlockAndRead}
                disabled={saving}
                style={{ display: "inline-block" }}
              >
                {saving ? "Opening…" : "Read full article →"}
              </button>
              {!article.is_read && (
                <p
                  className="hm-panel-sub"
                  style={{ fontSize: "0.8rem", marginTop: 12, color: "var(--muted)" }}
                >
                  Opening this story credits a small share of your subscription to {article.organization.name ?? "this nonprofit"}.
                </p>
              )}
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

          {!locked && bodyUnlocked && article.images && article.images.length > 0 && (
            <div className="ar-gallery">
              {article.images.map((src) => (
                <a key={src} href={src} target="_blank" rel="noopener noreferrer">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={src} alt="" loading="lazy" />
                </a>
              ))}
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
                    {r.cover_image_url && (
                      <img
                        src={r.cover_image_url}
                        alt=""
                        style={{
                          width: "100%",
                          height: 140,
                          objectFit: "cover",
                          borderRadius: 10,
                          marginBottom: 12,
                        }}
                      />
                    )}
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
