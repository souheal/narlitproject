"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { safeImageSrc } from "@/lib/safeUrl";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { BrandLoader } from "@/components/BrandLoader";

interface OrgCard {
  public_id: string;
  organization_name: string;
  mission_statement: string | null;
  category: string | null;
  city: string | null;
  country: string | null;
  logo_url: string | null;
  total_articles: number;
  total_supporters: number;
  is_supported_by_me: boolean;
}

interface User { full_name: string; email: string }

export default function OrganizationsPage() {
  const [user, setUser] = useState<User | null>(null);
  const [orgs, setOrgs] = useState<OrgCard[]>([]);
  const [categories, setCategories] = useState<string[]>([]);
  const [selectedCat, setSelectedCat] = useState<string>("all");
  const [search, setSearch] = useState("");
  const [checking, setChecking] = useState(true);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  async function load() {
    setLoading(true);
    try {
      const params = new URLSearchParams({ per_page: "24" });
      if (selectedCat !== "all") params.set("category", selectedCat);
      if (search) params.set("search", search);
      const res = await apiFetch(`/organizations?${params.toString()}`);
      const data = await res.json();
      if (res.ok) {
        setOrgs(data.data?.organizations?.data ?? data.data?.organizations ?? []);
        setCategories(data.data?.categories ?? []);
      } else {
        setError(data?.message ?? "Failed to load.");
      }
    } catch { setError("Failed to load organizations."); }
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
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [selectedCat]);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    load();
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
            <h2 className="hm-section-title">Nonprofits on NarLit</h2>
          </div>

          <form onSubmit={handleSearch} style={{ display: "flex", gap: 8, marginBottom: 16 }}>
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by name…"
              style={{
                flex: 1,
                padding: "12px 16px",
                border: "1px solid var(--line)",
                borderRadius: 12,
                background: "var(--panel)",
                color: "var(--foreground)",
                fontSize: "0.9rem",
              }}
            />
            <button type="submit" className="hm-article-btn">Search</button>
          </form>

          <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 24 }}>
            <button
              className={`admin-tab ${selectedCat === "all" ? "admin-tab-active" : ""}`}
              onClick={() => setSelectedCat("all")}
            >
              All
            </button>
            {categories.map((c) => (
              <button
                key={c}
                className={`admin-tab ${selectedCat === c ? "admin-tab-active" : ""}`}
                onClick={() => setSelectedCat(c)}
              >
                {c}
              </button>
            ))}
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          {loading && (
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))", gap: 16 }} aria-busy="true" aria-label="Loading organizations">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="hm-panel" style={{ padding: 20 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
                    <Skeleton width={48} height={48} radius={10} />
                    <div style={{ flex: 1 }}>
                      <Skeleton width="70%" height={16} />
                      <div style={{ marginTop: 6 }}>
                        <Skeleton width="45%" height={10} />
                      </div>
                    </div>
                  </div>
                  <div style={{ marginBottom: 10 }}>
                    <Skeleton width={90} height={20} radius={999} />
                  </div>
                  <div style={{ marginBottom: 12 }}>
                    <SkeletonText lines={2} />
                  </div>
                  <div style={{ display: "flex", justifyContent: "space-between", paddingTop: 12, borderTop: "1px solid var(--line)" }}>
                    <Skeleton width={90} height={12} />
                    <Skeleton width={90} height={12} />
                  </div>
                </div>
              ))}
            </div>
          )}

          {!loading && orgs.length === 0 && <p className="hm-empty">No organizations found.</p>}

          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))", gap: 16 }}>
            {!loading && orgs.map((o) => (
              <a
                key={o.public_id}
                href={`/organizations/${o.public_id}`}
                className="hm-panel"
                style={{ textDecoration: "none", color: "inherit", padding: 20, transition: "transform 0.15s" }}
              >
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
                  {o.logo_url ? (
                    <img src={safeImageSrc(o.logo_url)} alt="" style={{ width: 48, height: 48, borderRadius: 10, objectFit: "cover" }} />
                  ) : (
                    <div
                      style={{
                        width: 48, height: 48, borderRadius: 10,
                        background: "linear-gradient(135deg, var(--orange), var(--teal))",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        color: "white", fontWeight: 800,
                      }}
                    >
                      {o.organization_name.slice(0, 2).toUpperCase()}
                    </div>
                  )}
                  <div style={{ flex: 1 }}>
                    <h3 style={{ margin: 0, fontSize: "1rem", fontWeight: 800 }}>{o.organization_name}</h3>
                    {(o.city || o.country) && (
                      <p style={{ margin: "2px 0 0", fontSize: "0.72rem", color: "var(--muted)" }}>
                        📍 {[o.city, o.country].filter(Boolean).join(", ")}
                      </p>
                    )}
                  </div>
                  {o.is_supported_by_me && (
                    <span
                      style={{
                        background: "rgba(17,182,200,0.15)",
                        color: "var(--teal)",
                        fontSize: "0.65rem",
                        fontWeight: 700,
                        padding: "3px 8px",
                        borderRadius: 999,
                      }}
                    >
                      Supported
                    </span>
                  )}
                </div>

                {o.category && (
                  <span
                    style={{
                      display: "inline-block",
                      fontSize: "0.7rem",
                      padding: "3px 10px",
                      borderRadius: 999,
                      background: "rgba(255,138,71,0.1)",
                      color: "var(--orange)",
                      marginBottom: 10,
                    }}
                  >
                    {o.category}
                  </span>
                )}

                {o.mission_statement && (
                  <p style={{ fontSize: "0.85rem", color: "var(--muted)", margin: "0 0 12px", lineHeight: 1.5 }}>
                    {o.mission_statement.length > 140 ? `${o.mission_statement.slice(0, 140)}…` : o.mission_statement}
                  </p>
                )}

                <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.75rem", color: "var(--muted)", paddingTop: 12, borderTop: "1px solid var(--line)" }}>
                  <span>📖 {o.total_articles} stories</span>
                  <span>❤️ {o.total_supporters} supporters</span>
                </div>
              </a>
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}
