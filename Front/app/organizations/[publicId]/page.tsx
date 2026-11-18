"use client";

import { use, useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { safeHref, safeImageSrc, EXTERNAL_LINK_REL } from "@/lib/safeUrl";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";
import BackLink from "@/components/BackLink";

interface Article {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  is_read: boolean;
  read_time_minutes: number | null;
  published_at: string | null;
}

interface OrgDetail {
  public_id: string;
  organization_name: string;
  mission_statement: string | null;
  description: string | null;
  category: string | null;
  city: string | null;
  country: string | null;
  website: string | null;
  logo_url: string | null;
  founded_year: number | null;
  total_articles: number;
  // Not sent by GET /organizations/{id} yet — null hides the related UI.
  total_supporters: number | null;
  total_reads: number;
  total_donations_received: string | null;
  currency: string | null;
  is_supported_by_me: boolean;
  my_donation_to_them: string | null;
  articles: Article[];
}

interface User { full_name: string; email: string }

type Raw = Record<string, unknown>;
const str = (v: unknown): string | null => (typeof v === "string" && v !== "" ? v : null);
const num = (v: unknown): number | null => (typeof v === "number" && Number.isFinite(v) ? v : null);

/**
 * GET /organizations/{id} returns `{ organization: {name, ...}, articles: [...] }`.
 * Map that (and the richer member shape, if the backend adds it) onto OrgDetail
 * so a missing field shows as empty instead of crashing the page.
 */
function normaliseOrg(data: Raw | null | undefined): OrgDetail | null {
  const o = (data?.organization ?? null) as Raw | null;
  if (!o || typeof o !== "object") return null;
  const rawArticles = Array.isArray(o.articles) ? o.articles : Array.isArray(data?.articles) ? data.articles : [];
  const articles: Article[] = (rawArticles as Raw[]).map((a) => ({
    public_id: String(a.public_id ?? ""),
    title: str(a.title) ?? "Untitled",
    excerpt: str(a.excerpt),
    category: str(a.category),
    is_read: a.is_read === true,
    read_time_minutes: num(a.read_time_minutes),
    published_at: str(a.published_at),
  }));
  return {
    public_id: String(o.public_id ?? ""),
    organization_name: str(o.organization_name) ?? str(o.name) ?? "Organization",
    mission_statement: str(o.mission_statement),
    description: str(o.description),
    category: str(o.category),
    city: str(o.city),
    country: str(o.country),
    website: str(o.website),
    logo_url: str(o.logo_url),
    founded_year: num(o.founded_year),
    total_articles: num(o.total_articles) ?? num(o.published_articles_count) ?? articles.length,
    total_supporters: num(o.total_supporters),
    total_reads: num(o.total_reads) ?? 0,
    total_donations_received: str(o.total_donations_received),
    currency: str(o.currency),
    is_supported_by_me: o.is_supported_by_me === true,
    my_donation_to_them: str(o.my_donation_to_them),
    articles,
  };
}

export default function OrgDetailPage({ params }: { params: Promise<{ publicId: string }> }) {
  const { publicId } = use(params);
  const [user, setUser] = useState<User | null>(null);
  const [org, setOrg] = useState<OrgDetail | null>(null);
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  async function load() {
    setLoading(true);
    try {
      const [meRes, orgRes] = await Promise.all([
        apiFetch("/auth/me"),
        apiFetch(`/organizations/${publicId}`),
      ]);
      const meData = await meRes.json();
      const orgData = await orgRes.json();
      setUser(meData.data?.user ?? meData.data ?? null);
      const normalised = orgRes.ok ? normaliseOrg(orgData?.data) : null;
      if (normalised) setOrg(normalised);
      else setError(orgData?.message ?? "Organization not found.");
    } catch { setError("Failed to load organization."); }
    setLoading(false);
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      setChecking(false);
      load();
    });
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [publicId]);

  if (checking) {
    return (
      <BrandLoader />
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";

  if (loading) {
    return (
      <div className="hm-shell" suppressHydrationWarning>
        <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
        <main className="hm-main" aria-busy="true" aria-label="Loading organization">
          <div style={{ maxWidth: 960, margin: "0 auto" }}>
            <section className="hm-welcome" style={{ marginTop: 12 }}>
              <div className="hm-welcome-inner" style={{ alignItems: "flex-start" }}>
                <div style={{ display: "flex", gap: 20, alignItems: "flex-start", flex: 1 }}>
                  <Skeleton width={80} height={80} radius={16} />
                  <div style={{ flex: 1 }}>
                    <Skeleton width="60%" height={28} />
                    <div style={{ marginTop: 10 }}>
                      <SkeletonText lines={2} />
                    </div>
                    <div style={{ marginTop: 12, display: "flex", gap: 10 }}>
                      <Skeleton width={80} height={12} />
                      <Skeleton width={120} height={12} />
                      <Skeleton width={100} height={12} />
                    </div>
                  </div>
                </div>
              </div>
            </section>
            <section className="hm-section">
              <div className="hm-stats-grid">
                {Array.from({ length: 3 }).map((_, i) => (
                  <div key={i} className="hm-stat-card">
                    <Skeleton width={40} height={40} radius={12} />
                    <div style={{ marginTop: 10 }}>
                      <Skeleton width="55%" height={22} />
                    </div>
                    <div style={{ marginTop: 8 }}>
                      <Skeleton width="70%" height={12} />
                    </div>
                  </div>
                ))}
              </div>
            </section>
            <section className="hm-section">
              <Skeleton width={160} height={20} />
              <div className="hm-articles" style={{ marginTop: 16 }}>
                {Array.from({ length: 3 }).map((_, i) => (
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
                      <Skeleton width={90} height={12} />
                      <Skeleton width={90} height={32} radius={8} />
                    </div>
                  </article>
                ))}
              </div>
            </section>
          </div>
        </main>
      </div>
    );
  }

  if (error || !org) {
    return (
      <div className="hm-shell" suppressHydrationWarning>
        <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
        <main className="hm-main">
          <section className="hm-section" style={{ textAlign: "center", maxWidth: 500, margin: "0 auto" }}>
            <h2 className="hm-section-title">Organization unavailable</h2>
            <p className="hm-empty">{error || "This organization does not exist."}</p>
            <BackLink href="/organizations" className="hm-article-btn" style={{ display: "inline-block", marginTop: 16 }}>
              ← Back to directory
            </BackLink>
          </section>
        </main>
      </div>
    );
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <div style={{ maxWidth: 960, margin: "0 auto" }}>
          <BackLink href="/organizations" className="su-link" style={{ fontSize: "0.85rem" }}>← Back to directory</BackLink>

          {/* Hero */}
          <section
            className="hm-welcome"
            style={{
              marginTop: 12,
              background: "linear-gradient(135deg, rgba(255,138,71,0.15), rgba(17,182,200,0.15))",
            }}
          >
            <div className="hm-welcome-inner" style={{ alignItems: "flex-start" }}>
              <div style={{ display: "flex", gap: 20, alignItems: "flex-start" }}>
                {org.logo_url ? (
                  <img src={safeImageSrc(org.logo_url)} alt="" style={{ width: 80, height: 80, borderRadius: 16, objectFit: "cover" }} />
                ) : (
                  <div style={{
                    width: 80, height: 80, borderRadius: 16,
                    background: "linear-gradient(135deg, var(--orange), var(--teal))",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    color: "white", fontWeight: 900, fontSize: "1.8rem",
                  }}>
                    {org.organization_name.slice(0, 2).toUpperCase()}
                  </div>
                )}
                <div>
                  <h1 className="hm-welcome-title" style={{ marginTop: 0 }}>{org.organization_name}</h1>
                  {org.mission_statement && (
                    <p className="hm-welcome-sub">{org.mission_statement}</p>
                  )}
                  <div style={{ display: "flex", gap: 12, flexWrap: "wrap", marginTop: 12, fontSize: "0.85rem", color: "var(--muted)" }}>
                    {org.category && <span>🏷️ {org.category}</span>}
                    {(org.city || org.country) && <span>📍 {[org.city, org.country].filter(Boolean).join(", ")}</span>}
                    {org.founded_year && <span>📅 Founded {org.founded_year}</span>}
                    {org.website && <a href={safeHref(org.website)} target="_blank" rel={EXTERNAL_LINK_REL} className="su-link">🌐 Website</a>}
                  </div>
                </div>
              </div>
              {org.is_supported_by_me && org.my_donation_to_them && (
                <div className="hm-welcome-badge">
                  ❤️ You&apos;ve donated ${org.my_donation_to_them}
                </div>
              )}
            </div>
          </section>

          {/* Stats */}
          <section className="hm-section">
            <div className="hm-stats-grid">
              {org.total_donations_received !== null && (
                <div className="hm-stat-card">
                  <div className="hm-stat-icon hm-stat-icon-orange">💰</div>
                  <div className="hm-stat-value">${org.total_donations_received}</div>
                  <div className="hm-stat-label">Total Received</div>
                  <div className="hm-stat-hint">{org.currency ?? "USD"}</div>
                </div>
              )}
              <div className="hm-stat-card">
                <div className="hm-stat-icon hm-stat-icon-teal">📖</div>
                <div className="hm-stat-value">{org.total_reads.toLocaleString()}</div>
                <div className="hm-stat-label">Total Reads</div>
                <div className="hm-stat-hint">{org.total_articles} articles</div>
              </div>
              {org.total_supporters !== null && (
                <div className="hm-stat-card">
                  <div className="hm-stat-icon hm-stat-icon-purple">👥</div>
                  <div className="hm-stat-value">{org.total_supporters.toLocaleString()}</div>
                  <div className="hm-stat-label">Supporters</div>
                  <div className="hm-stat-hint">On NarLit</div>
                </div>
              )}
            </div>
          </section>

          {/* About */}
          {org.description && (
            <section className="hm-section">
              <h2 className="hm-section-title">About</h2>
              <div className="hm-panel">
                <div style={{ whiteSpace: "pre-wrap", lineHeight: 1.7, fontSize: "0.95rem" }}>
                  {org.description}
                </div>
              </div>
            </section>
          )}

          {/* Articles */}
          <section className="hm-section">
            <div className="hm-section-header">
              <h2 className="hm-section-title">Their stories</h2>
              <span className="hm-article-time">
                {org.articles.length < org.total_articles
                  ? `Latest ${org.articles.length} of ${org.total_articles}`
                  : `${org.articles.length} articles`}
              </span>
            </div>

            {org.articles.length === 0 && (
              <p className="hm-empty">{org.organization_name} hasn&apos;t published any stories yet.</p>
            )}

            <div className="hm-articles">
              {org.articles.map((a) => (
                <article key={a.public_id} className={`hm-article-card${a.is_read ? " hm-article-read" : ""}`}>
                  <div className="hm-article-top">
                    <span className="hm-article-org">{org.organization_name}</span>
                    {a.category && <span className="hm-article-cat">{a.category}</span>}
                    {a.is_read && <span className="hm-article-done">✓ Read</span>}
                  </div>
                  <h3 className="hm-article-title">{a.title}</h3>
                  <p className="hm-article-excerpt">{a.excerpt}</p>
                  <div className="hm-article-footer">
                    <span className="hm-article-time">
                      {a.read_time_minutes ? `${a.read_time_minutes} min` : "Quick read"}
                      {a.published_at && ` · ${new Date(a.published_at).toLocaleDateString()}`}
                    </span>
                    <a href={`/articles/${a.public_id}`} className="hm-article-btn">
                      {a.is_read ? "Read again" : "Read now"}
                    </a>
                  </div>
                </article>
              ))}
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}
