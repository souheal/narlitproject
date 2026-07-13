"use client";

import { FormEvent, useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface ArticleResult {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  organization: { public_id: string | null; name: string | null };
  is_read: boolean;
}

interface OrgResult {
  public_id: string;
  organization_name: string;
  mission_statement: string | null;
  category: string | null;
  logo_url: string | null;
}

interface SearchResults {
  articles: ArticleResult[];
  organizations: OrgResult[];
  suggestions: string[];
}

interface User { full_name: string; email: string }

export default function SearchPage() {
  const [user, setUser] = useState<User | null>(null);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<SearchResults | null>(null);
  const [tab, setTab] = useState<"all" | "articles" | "organizations">("all");
  const [checking, setChecking] = useState(true);
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const meRes = await apiFetch("/auth/me");
        const meData = await meRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
      } catch { /* ignore */ }
      const urlParams = new URLSearchParams(window.location.search);
      const q = urlParams.get("q");
      if (q) {
        setQuery(q);
        await runSearch(q);
      }
      setChecking(false);
    });
  }, []);

  async function runSearch(q: string) {
    if (!q.trim()) return;
    setSearching(true);
    setError("");
    try {
      const res = await apiFetch(`/search?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      if (res.ok) setResults(data.data?.results ?? null);
      else setError(data?.message ?? "Search failed.");
    } catch { setError("Search failed."); }
    setSearching(false);
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!query.trim()) return;
    const url = new URL(window.location.href);
    url.searchParams.set("q", query);
    window.history.replaceState({}, "", url.toString());
    runSearch(query);
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" /><span className="hm-loading-dot" /><span className="hm-loading-dot" />
      </div>
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";
  const showArticles = tab === "all" || tab === "articles";
  const showOrgs = tab === "all" || tab === "organizations";
  const totalResults = (results?.articles.length ?? 0) + (results?.organizations.length ?? 0);

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 900, margin: "0 auto" }}>
          <h2 className="hm-section-title">Search</h2>

          <form onSubmit={handleSubmit} style={{ marginTop: 16 }}>
            <input
              type="search"
              autoFocus
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search stories, nonprofits, categories…"
              style={{
                width: "100%",
                padding: "16px 20px",
                border: "1px solid var(--line)",
                borderRadius: 14,
                background: "var(--panel)",
                color: "var(--foreground)",
                fontSize: "1.05rem",
              }}
            />
          </form>

          {error && <p className="narlit-feedback narlit-feedback-error" style={{ marginTop: 16 }}>{error}</p>}
          {searching && <p className="hm-empty" style={{ marginTop: 20 }}>Searching…</p>}

          {results && !searching && (
            <>
              <p style={{ marginTop: 20, color: "var(--muted)", fontSize: "0.9rem" }}>
                {totalResults} result{totalResults !== 1 ? "s" : ""} for &quot;{query}&quot;
              </p>

              {totalResults > 0 && (
                <div className="admin-tabs" style={{ marginTop: 16 }}>
                  <button className={`admin-tab ${tab === "all" ? "admin-tab-active" : ""}`} onClick={() => setTab("all")}>
                    All ({totalResults})
                  </button>
                  <button className={`admin-tab ${tab === "articles" ? "admin-tab-active" : ""}`} onClick={() => setTab("articles")}>
                    Stories ({results.articles.length})
                  </button>
                  <button className={`admin-tab ${tab === "organizations" ? "admin-tab-active" : ""}`} onClick={() => setTab("organizations")}>
                    Nonprofits ({results.organizations.length})
                  </button>
                </div>
              )}

              {totalResults === 0 && (
                <div style={{ textAlign: "center", padding: 40 }}>
                  <div style={{ fontSize: "3rem" }}>🔍</div>
                  <p className="hm-empty">No results found.</p>
                  {results.suggestions.length > 0 && (
                    <div style={{ marginTop: 16 }}>
                      <p style={{ fontSize: "0.85rem", color: "var(--muted)" }}>Try:</p>
                      {results.suggestions.map((s) => (
                        <button
                          key={s}
                          className="hm-pager-btn"
                          style={{ margin: 4 }}
                          onClick={() => { setQuery(s); runSearch(s); }}
                        >
                          {s}
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {showOrgs && results.organizations.length > 0 && (
                <div style={{ marginTop: 24 }}>
                  <h3 style={{ fontSize: "0.9rem", color: "var(--muted)", textTransform: "uppercase", letterSpacing: "0.05em", marginBottom: 12 }}>
                    Nonprofits
                  </h3>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))", gap: 12 }}>
                    {results.organizations.map((o) => (
                      <a
                        key={o.public_id}
                        href={`/organizations/${o.public_id}`}
                        className="hm-panel"
                        style={{ textDecoration: "none", color: "inherit", padding: 16, display: "flex", gap: 12 }}
                      >
                        <div style={{
                          width: 40, height: 40, borderRadius: 8,
                          background: "linear-gradient(135deg, var(--orange), var(--teal))",
                          display: "flex", alignItems: "center", justifyContent: "center",
                          color: "white", fontWeight: 800, fontSize: "0.9rem",
                          flexShrink: 0,
                        }}>
                          {o.organization_name.slice(0, 2).toUpperCase()}
                        </div>
                        <div>
                          <h4 style={{ margin: 0, fontSize: "0.95rem" }}>{o.organization_name}</h4>
                          {o.category && (
                            <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{o.category}</div>
                          )}
                          {o.mission_statement && (
                            <p style={{ margin: "6px 0 0", fontSize: "0.8rem", color: "var(--muted)" }}>
                              {o.mission_statement.length > 90 ? `${o.mission_statement.slice(0, 90)}…` : o.mission_statement}
                            </p>
                          )}
                        </div>
                      </a>
                    ))}
                  </div>
                </div>
              )}

              {showArticles && results.articles.length > 0 && (
                <div style={{ marginTop: 24 }}>
                  <h3 style={{ fontSize: "0.9rem", color: "var(--muted)", textTransform: "uppercase", letterSpacing: "0.05em", marginBottom: 12 }}>
                    Stories
                  </h3>
                  <div className="hm-articles">
                    {results.articles.map((a) => (
                      <article key={a.public_id} className={`hm-article-card${a.is_read ? " hm-article-read" : ""}`}>
                        <div className="hm-article-top">
                          <span className="hm-article-org">{a.organization.name ?? "NarLit"}</span>
                          {a.category && <span className="hm-article-cat">{a.category}</span>}
                          {a.is_read && <span className="hm-article-done">✓ Read</span>}
                        </div>
                        <h3 className="hm-article-title">{a.title}</h3>
                        <p className="hm-article-excerpt">{a.excerpt}</p>
                        <div className="hm-article-footer">
                          <span className="hm-article-time" />
                          <a href={`/articles/${a.public_id}`} className="hm-article-btn">Read →</a>
                        </div>
                      </article>
                    ))}
                  </div>
                </div>
              )}
            </>
          )}

          {!results && !searching && (
            <div style={{ textAlign: "center", padding: 60 }}>
              <div style={{ fontSize: "3rem" }}>🔍</div>
              <p style={{ color: "var(--muted)", marginTop: 12 }}>Search for stories, nonprofits, or categories.</p>
            </div>
          )}
        </section>
      </main>
    </div>
  );
}
