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

function LineChart({
  points,
  color,
  colorSoft,
  height = 160,
  gradientId,
}: {
  points: { date: string; count?: number; amount?: number }[];
  color: string;
  colorSoft: string;
  height?: number;
  gradientId: string;
}) {
  const values = points.map((p) => (p.count ?? p.amount ?? 0));
  if (values.length === 0 || values.every((v) => v === 0)) {
    return (
      <div className="admin-chart-empty" style={{ minHeight: height, marginTop: 4 }}>
        <span className="admin-chart-empty-icon">📉</span>
        <span className="admin-chart-empty-title">No data yet</span>
        <span className="admin-chart-empty-hint">Data will appear here once activity is recorded in this range.</span>
      </div>
    );
  }
  const max = Math.max(1, ...values);
  const w = 300, h = height;
  const step = w / Math.max(1, values.length - 1);
  const coords = values.map((v, i) => ({
    x: i * step,
    y: h - (v / max) * (h - 20) - 4,
  }));
  const path = coords.map((c, i) => `${i === 0 ? "M" : "L"} ${c.x.toFixed(2)},${c.y.toFixed(2)}`).join(" ");
  const areaPath = `${path} L ${w},${h} L 0,${h} Z`;
  const peak = coords.reduce((best, c, i) => (values[i] > values[best.i] ? { i, c } : best), { i: 0, c: coords[0] });

  return (
    <div className="admin-line-chart-wrap" style={{ height }}>
      <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{ width: "100%", height }}>
        <defs>
          <linearGradient id={gradientId} x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stopColor={color} stopOpacity="0.35" />
            <stop offset="100%" stopColor={color} stopOpacity="0" />
          </linearGradient>
        </defs>
        {[0.25, 0.5, 0.75].map((r) => (
          <line key={r} x1="0" y1={h * r} x2={w} y2={h * r} stroke="rgba(5,46,53,0.05)" strokeWidth="1" />
        ))}
        <path d={areaPath} fill={`url(#${gradientId})`} />
        <path d={path} fill="none" stroke={color} strokeWidth="2.2" strokeLinejoin="round" strokeLinecap="round" />
        <circle cx={peak.c.x} cy={peak.c.y} r="3.5" fill={color} stroke="#fff" strokeWidth="1.5" />
      </svg>
    </div>
  );
}

function sumOf(points: { count?: number; amount?: number }[]): number {
  return points.reduce((sum, p) => sum + (p.count ?? p.amount ?? 0), 0);
}

function trendPct(points: { count?: number; amount?: number }[]): { pct: number; up: boolean } | null {
  if (points.length < 4) return null;
  const half = Math.floor(points.length / 2);
  const first = points.slice(0, half).reduce((s, p) => s + (p.count ?? p.amount ?? 0), 0);
  const second = points.slice(half).reduce((s, p) => s + (p.count ?? p.amount ?? 0), 0);
  if (first === 0 && second === 0) return null;
  if (first === 0) return { pct: 100, up: true };
  const change = ((second - first) / first) * 100;
  return { pct: Math.abs(change), up: change >= 0 };
}

function TimeseriesPanel({
  title,
  subtitle,
  icon,
  tone,
  points,
  color,
  gradientId,
  format,
  statLabel,
}: {
  title: string;
  subtitle: string;
  icon: string;
  tone: "orange" | "teal" | "purple" | "green";
  points: { date: string; count?: number; amount?: number }[];
  color: string;
  gradientId: string;
  format: (n: number) => string;
  statLabel: string;
}) {
  const total = sumOf(points);
  const trend = trendPct(points);
  return (
    <section className="admin-panel">
      <header className="admin-panel-header">
        <div className="admin-ts-heading">
          <span className={`admin-ts-icon admin-ts-icon-${tone}`}>{icon}</span>
          <div>
            <h3 className="admin-panel-title">{title}</h3>
            <p className="admin-panel-sub">{subtitle}</p>
          </div>
        </div>
        <div className="admin-panel-stat">
          <span className="admin-panel-stat-value" style={{ color }}>{format(total)}</span>
          <span className="admin-panel-stat-label">
            {trend ? (
              <span className={`admin-ts-trend ${trend.up ? "admin-ts-trend-up" : "admin-ts-trend-down"}`}>
                {trend.up ? "▲" : "▼"} {trend.pct.toFixed(0)}%
              </span>
            ) : statLabel}
          </span>
        </div>
      </header>
      <LineChart points={points} color={color} colorSoft={color} gradientId={gradientId} />
    </section>
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

          <div className="admin-dash-two-col" style={{ marginBottom: 20 }}>
            <TimeseriesPanel
              title="Signups over time"
              subtitle={`New user registrations · ${range}`}
              icon="👥"
              tone="orange"
              points={data.timeseries.signups}
              color="var(--orange)"
              gradientId="sig-grad"
              format={(n) => n.toLocaleString()}
              statLabel="total"
            />
            <TimeseriesPanel
              title="Reads over time"
              subtitle={`Article reads · ${range}`}
              icon="📖"
              tone="teal"
              points={data.timeseries.reads}
              color="var(--teal)"
              gradientId="reads-grad"
              format={(n) => n.toLocaleString()}
              statLabel="total"
            />
            <TimeseriesPanel
              title="Activations over time"
              subtitle={`Users who completed onboarding · ${range}`}
              icon="⚡"
              tone="purple"
              points={data.timeseries.activations}
              color="#7c5cbf"
              gradientId="act-grad"
              format={(n) => n.toLocaleString()}
              statLabel="activated"
            />
            <TimeseriesPanel
              title="Revenue over time"
              subtitle={`Gross revenue · ${range}`}
              icon="💵"
              tone="green"
              points={data.timeseries.revenue}
              color="#3fa650"
              gradientId="rev-grad"
              format={(n) => `$${n.toLocaleString(undefined, { maximumFractionDigits: 0 })}`}
              statLabel="earned"
            />
          </div>

          <div className="admin-dash-two-col" style={{ marginBottom: 20 }}>
            <section className="admin-panel">
              <header className="admin-panel-header">
                <div>
                  <h3 className="admin-panel-title">Top organizations</h3>
                  <p className="admin-panel-sub">Highest reads · gross earned</p>
                </div>
                <div className="admin-panel-stat">
                  <span className="admin-panel-stat-value">{data.top_organizations.length}</span>
                  <span className="admin-panel-stat-label">tracked</span>
                </div>
              </header>

              {data.top_organizations.length === 0 ? (
                <div className="admin-chart-empty">
                  <span className="admin-chart-empty-icon">🏢</span>
                  <span className="admin-chart-empty-title">No organizations to rank</span>
                  <span className="admin-chart-empty-hint">Rankings appear once orgs receive reads in this range.</span>
                </div>
              ) : (
                <ul className="admin-rank-list">
                  {data.top_organizations.map((o, i) => {
                    const max = data.top_organizations[0]?.reads || 1;
                    const share = Math.max(4, Math.round((o.reads / max) * 100));
                    return (
                      <li key={o.public_id} className="admin-rank-row">
                        <span className={`admin-rank-medal admin-rank-medal-${i <= 2 ? i : "other"}`}>
                          {i === 0 ? "🥇" : i === 1 ? "🥈" : i === 2 ? "🥉" : `#${i + 1}`}
                        </span>
                        <div className="admin-rank-info">
                          <div className="admin-rank-info-top">
                            <span className="admin-rank-name">{o.name}</span>
                            <span className="admin-rank-earn">${o.earned}</span>
                          </div>
                          <div className="admin-rank-track">
                            <div className="admin-rank-fill admin-rank-fill-teal" style={{ width: `${share}%` }} />
                          </div>
                          <span className="admin-rank-meta">{o.reads.toLocaleString()} reads</span>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}
            </section>

            <section className="admin-panel">
              <header className="admin-panel-header">
                <div>
                  <h3 className="admin-panel-title">Top articles</h3>
                  <p className="admin-panel-sub">Most-read articles in this range</p>
                </div>
                <div className="admin-panel-stat">
                  <span className="admin-panel-stat-value">{data.top_articles.length}</span>
                  <span className="admin-panel-stat-label">tracked</span>
                </div>
              </header>

              {data.top_articles.length === 0 ? (
                <div className="admin-chart-empty">
                  <span className="admin-chart-empty-icon">📰</span>
                  <span className="admin-chart-empty-title">No articles to rank</span>
                  <span className="admin-chart-empty-hint">Article rankings appear once reads are recorded.</span>
                </div>
              ) : (
                <ul className="admin-rank-list">
                  {data.top_articles.map((a, i) => {
                    const max = data.top_articles[0]?.reads || 1;
                    const share = Math.max(4, Math.round((a.reads / max) * 100));
                    return (
                      <li key={a.public_id} className="admin-rank-row">
                        <span className={`admin-rank-medal admin-rank-medal-${i <= 2 ? i : "other"}`}>
                          {i === 0 ? "🥇" : i === 1 ? "🥈" : i === 2 ? "🥉" : `#${i + 1}`}
                        </span>
                        <div className="admin-rank-info">
                          <div className="admin-rank-info-top">
                            <span className="admin-rank-name">{a.title}</span>
                            <span className="admin-rank-earn">{a.reads.toLocaleString()}</span>
                          </div>
                          <div className="admin-rank-track">
                            <div className="admin-rank-fill admin-rank-fill-orange" style={{ width: `${share}%` }} />
                          </div>
                          <span className="admin-rank-meta">{a.organization}</span>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              )}
            </section>
          </div>

          <div className="admin-dash-two-col" style={{ marginBottom: 20 }}>
            <section className="admin-panel">
              <header className="admin-panel-header">
                <div>
                  <h3 className="admin-panel-title">Category breakdown</h3>
                  <p className="admin-panel-sub">Reads distribution by category</p>
                </div>
                <div className="admin-panel-stat">
                  <span className="admin-panel-stat-value">
                    {data.categories.reduce((sum, c) => sum + c.reads, 0).toLocaleString()}
                  </span>
                  <span className="admin-panel-stat-label">reads</span>
                </div>
              </header>

              {data.categories.length === 0 ? (
                <div className="admin-chart-empty">
                  <span className="admin-chart-empty-icon">🏷️</span>
                  <span className="admin-chart-empty-title">No category data</span>
                  <span className="admin-chart-empty-hint">Category breakdown appears once articles get reads.</span>
                </div>
              ) : (
                <ul className="admin-cat-list">
                  {data.categories.map((c, i) => (
                    <li key={c.category} className="admin-cat-row">
                      <div className="admin-cat-header">
                        <span className="admin-cat-name">
                          <span className={`admin-cat-dot admin-cat-dot-${i % 4}`} />
                          {c.category}
                        </span>
                        <span className="admin-cat-stats">
                          <strong>{c.reads.toLocaleString()}</strong>
                          <span>·</span>
                          <span>{c.percent}%</span>
                        </span>
                      </div>
                      <div className="admin-cat-track">
                        <div className={`admin-cat-fill admin-cat-fill-${i % 4}`} style={{ width: `${Math.max(c.percent, 2)}%` }} />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <section className="admin-panel">
              <header className="admin-panel-header">
                <div>
                  <h3 className="admin-panel-title">Onboarding funnel</h3>
                  <p className="admin-panel-sub">Conversion from signup to first read</p>
                </div>
                <div className="admin-panel-stat">
                  <span className="admin-panel-stat-value">
                    {data.funnel.length > 0
                      ? `${data.funnel[data.funnel.length - 1]?.percent ?? 0}%`
                      : "—"}
                  </span>
                  <span className="admin-panel-stat-label">end-to-end</span>
                </div>
              </header>

              {data.funnel.length === 0 ? (
                <div className="admin-chart-empty">
                  <span className="admin-chart-empty-icon">🚀</span>
                  <span className="admin-chart-empty-title">No funnel data</span>
                  <span className="admin-chart-empty-hint">Onboarding stages will populate once users sign up.</span>
                </div>
              ) : (
                <ol className="admin-funnel-list">
                  {data.funnel.map((s, i) => {
                    const prev = i > 0 ? data.funnel[i - 1].percent : null;
                    const drop = prev !== null && prev > 0 ? Math.max(0, prev - s.percent) : null;
                    return (
                      <li key={s.stage} className="admin-funnel-row">
                        <div className="admin-funnel-header">
                          <span className="admin-funnel-step">
                            <span className="admin-funnel-num">{i + 1}</span>
                            <span className="admin-funnel-stage">{s.stage}</span>
                          </span>
                          <span className="admin-funnel-stats">
                            <strong>{s.count.toLocaleString()}</strong>
                            <span className="admin-funnel-pct">{s.percent}%</span>
                          </span>
                        </div>
                        <div className="admin-funnel-track">
                          <div className="admin-funnel-fill" style={{ width: `${Math.max(s.percent, s.count > 0 ? 3 : 0)}%` }} />
                        </div>
                        {drop !== null && drop > 0 && (
                          <span className="admin-funnel-drop">▼ {drop.toFixed(0)}% drop-off</span>
                        )}
                      </li>
                    );
                  })}
                </ol>
              )}
            </section>
          </div>

          <section className="admin-panel" style={{ marginBottom: 20 }}>
            <header className="admin-panel-header">
              <div>
                <h3 className="admin-panel-title">Cohort retention</h3>
                <p className="admin-panel-sub">Users retained by month since signup</p>
              </div>
              <div className="admin-panel-stat">
                <span className="admin-panel-stat-value">{data.retention_avg.toFixed(1)}%</span>
                <span className="admin-panel-stat-label">avg retention</span>
              </div>
            </header>

            {data.cohorts.length === 0 ? (
              <div className="admin-chart-empty">
                <span className="admin-chart-empty-icon">📅</span>
                <span className="admin-chart-empty-title">No cohort data yet</span>
                <span className="admin-chart-empty-hint">Cohort retention needs at least one month of signup history.</span>
              </div>
            ) : (
              <div className="admin-cohort-scroll">
                <table className="admin-cohort-table">
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
                        <td className="admin-cohort-label">{c.cohort}</td>
                        <td className="admin-cohort-size">{c.total.toLocaleString()}</td>
                        {c.retained.map((r, i) => {
                          const pct = c.total > 0 ? Math.round((r / c.total) * 100) : 0;
                          const alpha = pct / 100;
                          const isBright = alpha > 0.45;
                          return (
                            <td
                              key={i}
                              className="admin-cohort-cell"
                              style={{
                                background: `rgba(17, 182, 200, ${Math.max(alpha, 0.04)})`,
                                color: isBright ? "#fff" : "var(--text)",
                                fontWeight: isBright ? 800 : 700,
                              }}
                            >
                              {pct}%
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </div>
  );
}
