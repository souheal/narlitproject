"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface ReadEntry {
  public_id: string;
  read_percent: number;
  reading_seconds: number;
  counted_for_payout: boolean;
  created_at: string;
  article: {
    public_id: string;
    title: string;
    category: string | null;
    organization: { public_id: string | null; name: string | null };
  };
}

interface HistoryPayload {
  reads: {
    data: ReadEntry[];
    current_page: number;
    last_page: number;
    total: number;
  };
  by_day: { date: string; count: number; minutes: number }[];
  category_breakdown: { category: string; count: number }[];
}

interface User { full_name: string; email: string }

export default function HistoryPage() {
  const [user, setUser] = useState<User | null>(null);
  const [data, setData] = useState<HistoryPayload | null>(null);
  const [page, setPage] = useState(1);
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function load(p: number) {
    setLoading(true);
    try {
      const res = await apiFetch(`/member/reading-history?page=${p}&per_page=20`);
      const payload = await res.json();
      if (res.ok) setData(payload.data ?? null);
      else setError(payload?.message ?? "Failed to load history.");
    } catch { setError("Failed to load history."); }
    setLoading(false);
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const meRes = await apiFetch("/auth/me");
        const meData = await meRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
      } catch { /* ignore */ }
      await load(1);
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
  const reads = data?.reads;
  const byDay = data?.by_day ?? [];
  const categories = data?.category_breakdown ?? [];
  const maxDay = Math.max(1, ...byDay.map((d) => d.count));
  const totalCategoryReads = categories.reduce((s, c) => s + c.count, 0) || 1;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        <div className="hm-content-grid">
          <section className="hm-section">
            <h2 className="hm-section-title">Reading history</h2>
            <p className="hm-panel-sub">{reads?.total ?? 0} entries in total</p>

            {loading && <p className="hm-empty">Loading…</p>}
            {!loading && (reads?.data?.length ?? 0) === 0 && (
              <p className="hm-empty">You haven&apos;t read anything yet. <a href="/articles" className="su-link">Find something</a>.</p>
            )}

            <div className="hm-articles">
              {reads?.data?.map((r) => (
                <article key={r.public_id} className="hm-article-card">
                  <div className="hm-article-top">
                    <span className="hm-article-org">{r.article.organization.name ?? "NarLit"}</span>
                    {r.article.category && <span className="hm-article-cat">{r.article.category}</span>}
                    {r.counted_for_payout && <span className="hm-article-done">💰 Counted</span>}
                  </div>
                  <h3 className="hm-article-title">{r.article.title}</h3>
                  <div className="hm-article-footer">
                    <span className="hm-article-time">
                      {new Date(r.created_at).toLocaleString()} · {r.read_percent}% read · {Math.round(r.reading_seconds / 60)} min
                    </span>
                    <a href={`/articles/${r.article.public_id}`} className="hm-article-btn">Open →</a>
                  </div>
                </article>
              ))}
            </div>

            {reads && reads.last_page > 1 && (
              <div className="hm-pager">
                <button type="button" className="hm-pager-btn" disabled={page <= 1 || loading}
                  onClick={() => { setPage(page - 1); load(page - 1); }}>← Prev</button>
                <span className="hm-pager-info">Page {reads.current_page} of {reads.last_page}</span>
                <button type="button" className="hm-pager-btn" disabled={page >= reads.last_page || loading}
                  onClick={() => { setPage(page + 1); load(page + 1); }}>Next →</button>
              </div>
            )}
          </section>

          <div className="hm-side">
            <section className="hm-panel">
              <h2 className="hm-panel-title">Last 30 days</h2>
              <p className="hm-panel-sub">Reads by day</p>
              <div style={{ display: "flex", alignItems: "flex-end", gap: 3, height: 100, marginTop: 12 }}>
                {byDay.map((d) => (
                  <div
                    key={d.date}
                    title={`${d.date}: ${d.count} reads · ${d.minutes} min`}
                    style={{
                      flex: 1,
                      height: `${Math.round((d.count / maxDay) * 100)}%`,
                      minHeight: 2,
                      background: "var(--teal)",
                      borderRadius: 2,
                      opacity: 0.7 + (d.count / maxDay) * 0.3,
                    }}
                  />
                ))}
              </div>
              {byDay.length === 0 && <p className="hm-empty">No reads in the last 30 days.</p>}
            </section>

            <section className="hm-panel">
              <h2 className="hm-panel-title">By category</h2>
              <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 12 }}>
                {categories.length === 0 && <p className="hm-empty">No categories yet.</p>}
                {categories.map((c) => {
                  const pct = Math.round((c.count / totalCategoryReads) * 100);
                  return (
                    <div key={c.category}>
                      <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: 4 }}>
                        <span>{c.category}</span>
                        <span style={{ color: "var(--muted)" }}>{c.count} · {pct}%</span>
                      </div>
                      <div style={{ height: 6, background: "var(--panel)", borderRadius: 3, overflow: "hidden" }}>
                        <div style={{ width: `${pct}%`, height: "100%", background: "var(--orange)" }} />
                      </div>
                    </div>
                  );
                })}
              </div>
            </section>
          </div>
        </div>
      </main>
    </div>
  );
}
