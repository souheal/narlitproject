"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
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

interface CategorySection {
  category: string;
  icon: string;
  articles: Article[];
}

interface ExplorePayload {
  featured: Article[];
  trending: Article[];
  for_you: Article[];
  by_category: CategorySection[];
  new_this_week: Article[];
}

interface User { full_name: string; email: string }

function ArticleCard({ a }: { a: Article }) {
  return (
    <article className={`hm-article-card${a.is_read ? " hm-article-read" : ""}`}>
      <div className="hm-article-top">
        <span className="hm-article-org">{a.organization.name ?? "NarLit"}</span>
        {a.category && <span className="hm-article-cat">{a.category}</span>}
        {a.is_read && <span className="hm-article-done">✓ Read</span>}
      </div>
      <h3 className="hm-article-title">{a.title}</h3>
      <p className="hm-article-excerpt">{a.excerpt}</p>
      <div className="hm-article-footer">
        <span className="hm-article-time">
          {a.read_time_minutes ? `${a.read_time_minutes} min` : "Quick read"}
        </span>
        <a href={`/articles/${a.public_id}`} className="hm-article-btn">{a.cta_label}</a>
      </div>
    </article>
  );
}

function HorizontalRow({ title, articles, seeAllHref }: { title: string; articles: Article[]; seeAllHref?: string }) {
  if (articles.length === 0) return null;
  return (
    <section className="hm-section">
      <div className="hm-section-header">
        <h2 className="hm-section-title">{title}</h2>
        {seeAllHref && <a href={seeAllHref} className="hm-see-all">See all →</a>}
      </div>
      <div
        style={{
          display: "grid",
          gridAutoColumns: "minmax(280px, 320px)",
          gridAutoFlow: "column",
          gap: 14,
          overflowX: "auto",
          padding: "4px 0 12px",
        }}
      >
        {articles.map((a) => <ArticleCard key={a.public_id} a={a} />)}
      </div>
    </section>
  );
}

export default function ExplorePage() {
  const [user, setUser] = useState<User | null>(null);
  const [data, setData] = useState<ExplorePayload | null>(null);
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const [meRes, exRes] = await Promise.all([apiFetch("/auth/me"), apiFetch("/member/explore")]);
        const meData = await meRes.json();
        const exData = await exRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
        if (exRes.ok) setData(exData.data?.explore ?? null);
        else setError(exData?.message ?? "Failed to load explore.");
      } catch { setError("Failed to load explore."); }
      setChecking(false);
    });
  }, []);

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" /><span className="hm-loading-dot" /><span className="hm-loading-dot" />
      </div>
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";
  const hero = data?.featured?.[0];

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        {hero && (
          <section
            className="hm-welcome"
            style={{
              background: "linear-gradient(135deg, rgba(255,138,71,0.25), rgba(17,182,200,0.25))",
              cursor: "pointer",
            }}
            onClick={() => (window.location.href = `/articles/${hero.public_id}`)}
          >
            <div className="hm-welcome-inner">
              <div>
                <p className="hm-welcome-kicker">⭐ Featured story</p>
                <h1 className="hm-welcome-title" style={{ fontSize: "1.7rem" }}>{hero.title}</h1>
                <p className="hm-welcome-sub">{hero.excerpt}</p>
                <div style={{ marginTop: 12, fontSize: "0.85rem", color: "var(--muted)" }}>
                  {hero.organization.name} · {hero.category} · {hero.read_time_minutes ?? "?"} min
                </div>
              </div>
              <a
                href={`/articles/${hero.public_id}`}
                className="hm-article-btn"
                style={{ alignSelf: "center" }}
                onClick={(e) => e.stopPropagation()}
              >
                {hero.cta_label} →
              </a>
            </div>
          </section>
        )}

        <HorizontalRow title="🔥 Trending this week" articles={data?.trending ?? []} seeAllHref="/articles" />
        <HorizontalRow title="✨ For you" articles={data?.for_you ?? []} />
        <HorizontalRow title="🆕 New this week" articles={data?.new_this_week ?? []} />

        {(data?.by_category ?? []).map((sec) => (
          <HorizontalRow
            key={sec.category}
            title={`${sec.icon} ${sec.category}`}
            articles={sec.articles}
            seeAllHref={`/articles?category=${encodeURIComponent(sec.category)}`}
          />
        ))}

        {(!data || (data.featured.length === 0 && data.trending.length === 0)) && (
          <p className="hm-empty">Nothing to explore yet — check back soon.</p>
        )}
      </main>
    </div>
  );
}
