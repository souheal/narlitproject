"use client";

import { useEffect, useState } from "react";
import { adminFetch } from "@/lib/api";

type Range = "7d" | "30d" | "90d" | "12m";

interface TopOrg { public_id: string; name: string; reads: number; earned: string }
interface TopArticle { public_id: string; title: string; organization: string; reads: number }
interface CategoryStat { category: string; reads: number; percent: number }
interface FunnelStage { stage: string; count: number; percent: number }
interface CohortRow { cohort: string; total: number; retained: number[] }
interface Analytics {
  range: Range;
  currency: string;
  totals: {
    signups: number;
    activated: number;
    activation_rate: number;
    paying: number;
    conversion_rate: number;
  };
  timeseries: {
    signups: { date: string; count: number }[];
    activations: { date: string; count: number }[];
    reads: { date: string; count: number }[];
    revenue: { date: string; amount: number }[];
  };
  top_organizations: TopOrg[];
  top_articles: TopArticle[];
  categories: CategoryStat[];
  funnel: FunnelStage[];
  cohorts: CohortRow[];
  retention_avg: number;
}

function LineChart({ points, color, height = 140 }: { points: { date: string; count?: number; amount?: number }[]; color: string; height?: number }) {
  const values = points.map((p) => (p.count ?? p.amount ?? 0));
  if (values.length === 0) return <p className="admin-empty">No data.</p>;
  const max = Math.max(1, ...values);
  const w = 300, h = height;
  const step = w / Math.max(1, values.length - 1);
  const path = values.map((v, i) => `${i === 0 ? "M" : "L"} ${(i * step).toFixed(2)},${(h - (v / max) * (h - 10)).toFixed(2)}`).join(" ");
  const areaPath = `${path} L ${w},${h} L 0,${h} Z`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{ width: "100%", height }}>
      <path d={areaPath} fill={color} opacity={0.15} />
      <path d={path} fill="none" stroke={color} strokeWidth="2" />
    </svg>
  );
}

export default function AdminAnalyticsPage() {
  const [range, setRange] = useState<Range>("30d");
  const [data, setData] = useState<Analytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    (async () => {
      setLoading(true);
      setError("");
      try {
        const res = await adminFetch(`/admin/analytics?range=${range}`);
        const payload = await res.json();
        if (!res.ok) setError(payload?.message ?? "Failed to load analytics.");
        else setData(payload.data?.analytics ?? null);
      } catch {
        setError("Failed to load analytics.");
      }
      setLoading(false);
    })();
  }, [range]);

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Analytics</h2>
        <div className="admin-tabs" style={{ marginTop: 0 }}>
          {(["7d", "30d", "90d", "12m"] as Range[]).map((r) => (
            <button key={r} className={`admin-tab ${range === r ? "admin-tab-active" : ""}`} onClick={() => setRange(r)}>
              {r}
            </button>
          ))}
        </div>
      </div>

      {loading && <p className="admin-empty">Loading analytics…</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      {data && (
        <>
          <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">Signups</span>
              <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{data.totals.signups.toLocaleString()}</span>
              <span className="admin-stat-hint">In selected range</span>
            </div>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">Activation rate</span>
              <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{data.totals.activation_rate.toFixed(1)}%</span>
              <span className="admin-stat-hint">{data.totals.activated} activated</span>
            </div>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">Conversion to paid</span>
              <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{data.totals.conversion_rate.toFixed(1)}%</span>
              <span className="admin-stat-hint">{data.totals.paying} paying users</span>
            </div>
            <div className="admin-stat-card" style={{ cursor: "default" }}>
              <span className="admin-stat-label">Avg retention</span>
              <span style={{ fontSize: "1.6rem", fontWeight: 900 }}>{data.retention_avg.toFixed(1)}%</span>
              <span className="admin-stat-hint">Month 1 → Month 3</span>
            </div>
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 20 }}>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 8px", fontSize: "1rem" }}>Signups over time</h3>
              <LineChart points={data.timeseries.signups} color="var(--orange)" />
            </div>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 8px", fontSize: "1rem" }}>Reads over time</h3>
              <LineChart points={data.timeseries.reads} color="var(--teal)" />
            </div>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 8px", fontSize: "1rem" }}>Activations over time</h3>
              <LineChart points={data.timeseries.activations} color="#7c5cbf" />
            </div>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 8px", fontSize: "1rem" }}>Revenue over time</h3>
              <LineChart points={data.timeseries.revenue} color="#4caf50" />
            </div>
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 20 }}>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 8px", fontSize: "1rem" }}>Top organizations</h3>
              <table className="admin-table">
                <thead><tr><th>Organization</th><th>Reads</th><th>Earned</th></tr></thead>
                <tbody>
                  {data.top_organizations.length === 0 && <tr><td colSpan={3} className="admin-empty">No data.</td></tr>}
                  {data.top_organizations.map((o) => (
                    <tr key={o.public_id}>
                      <td>{o.name}</td>
                      <td>{o.reads}</td>
                      <td>${o.earned}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 8px", fontSize: "1rem" }}>Top articles</h3>
              <table className="admin-table">
                <thead><tr><th>Article</th><th>Org</th><th>Reads</th></tr></thead>
                <tbody>
                  {data.top_articles.length === 0 && <tr><td colSpan={3} className="admin-empty">No data.</td></tr>}
                  {data.top_articles.map((a) => (
                    <tr key={a.public_id}>
                      <td>{a.title}</td>
                      <td style={{ fontSize: "0.75rem", color: "var(--muted)" }}>{a.organization}</td>
                      <td>{a.reads}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 20 }}>
            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Category breakdown</h3>
              {data.categories.map((c) => (
                <div key={c.category} style={{ marginBottom: 10 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: 4 }}>
                    <span>{c.category}</span>
                    <span style={{ color: "var(--muted)" }}>{c.reads} · {c.percent}%</span>
                  </div>
                  <div style={{ height: 8, background: "var(--panel)", borderRadius: 4, overflow: "hidden" }}>
                    <div style={{ width: `${c.percent}%`, height: "100%", background: "linear-gradient(90deg, var(--orange), var(--teal))" }} />
                  </div>
                </div>
              ))}
              {data.categories.length === 0 && <p className="admin-empty">No data.</p>}
            </div>

            <div className="admin-table-wrap">
              <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Onboarding funnel</h3>
              {data.funnel.map((s, i) => (
                <div key={s.stage} style={{ marginBottom: 12 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: 4 }}>
                    <span>{i + 1}. {s.stage}</span>
                    <span style={{ color: "var(--muted)" }}>{s.count} · {s.percent}%</span>
                  </div>
                  <div style={{ height: 22, background: "var(--panel)", borderRadius: 6, overflow: "hidden", position: "relative" }}>
                    <div style={{ width: `${s.percent}%`, height: "100%", background: `hsl(${200 - i * 30}, 60%, 50%)` }} />
                  </div>
                </div>
              ))}
              {data.funnel.length === 0 && <p className="admin-empty">No data.</p>}
            </div>
          </div>

          <div className="admin-table-wrap">
            <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Cohort retention</h3>
            {data.cohorts.length === 0 && <p className="admin-empty">No cohort data yet.</p>}
            {data.cohorts.length > 0 && (
              <table className="admin-table">
                <thead>
                  <tr>
                    <th>Cohort</th>
                    <th>Size</th>
                    {data.cohorts[0].retained.map((_, i) => <th key={i}>M{i}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {data.cohorts.map((c) => (
                    <tr key={c.cohort}>
                      <td>{c.cohort}</td>
                      <td>{c.total}</td>
                      {c.retained.map((r, i) => {
                        const pct = c.total > 0 ? Math.round((r / c.total) * 100) : 0;
                        return (
                          <td key={i} style={{ background: `rgba(17, 182, 200, ${pct / 100})`, textAlign: "center" }}>
                            {pct}%
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </>
      )}
    </div>
  );
}
