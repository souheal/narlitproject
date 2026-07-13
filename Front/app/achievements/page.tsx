"use client";

import { useEffect, useState } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Achievement {
  key: string;
  title: string;
  description: string;
  icon: string;
  category: string;
  earned: boolean;
  earned_at: string | null;
  progress: number;
  goal: number;
  points: number;
}

interface AchievementsPayload {
  achievements: Achievement[];
  totals: {
    earned: number;
    total: number;
    points: number;
    level: number;
    next_level_points: number;
    current_streak: number;
    longest_streak: number;
  };
}

interface User { full_name: string; email: string }

export default function AchievementsPage() {
  const [user, setUser] = useState<User | null>(null);
  const [data, setData] = useState<AchievementsPayload | null>(null);
  const [filter, setFilter] = useState<"all" | "earned" | "locked">("all");
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const [meRes, achRes] = await Promise.all([apiFetch("/auth/me"), apiFetch("/member/achievements")]);
        const meData = await meRes.json();
        const achData = await achRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
        if (achRes.ok) setData(achData.data ?? null);
        else setError(achData?.message ?? "Failed to load achievements.");
      } catch { setError("Failed to load achievements."); }
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
  const totals = data?.totals;
  const achievements = data?.achievements ?? [];
  const filtered = achievements.filter((a) => filter === "all" || (filter === "earned" ? a.earned : !a.earned));
  const grouped = filtered.reduce<Record<string, Achievement[]>>((acc, a) => {
    (acc[a.category] ??= []).push(a);
    return acc;
  }, {});
  const levelProgress = totals ? Math.min(100, Math.round((totals.points / totals.next_level_points) * 100)) : 0;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        <section className="hm-welcome">
          <div className="hm-welcome-inner">
            <div>
              <p className="hm-welcome-kicker">Level {totals?.level ?? 1}</p>
              <h1 className="hm-welcome-title">
                <span className="hm-welcome-name">{totals?.points ?? 0}</span> points
              </h1>
              <p className="hm-welcome-sub">
                {totals ? `${totals.next_level_points - totals.points} points to Level ${totals.level + 1}` : "Read to earn points"}
              </p>
              <div style={{ marginTop: 12, height: 8, background: "rgba(255,255,255,0.2)", borderRadius: 4, overflow: "hidden", maxWidth: 400 }}>
                <div style={{ width: `${levelProgress}%`, height: "100%", background: "white" }} />
              </div>
            </div>
            <div className="hm-welcome-badge">
              🔥 {totals?.current_streak ?? 0} day streak
            </div>
          </div>
        </section>

        <section className="hm-section">
          <div className="hm-section-header">
            <h2 className="hm-section-title">Badges</h2>
            <span className="hm-article-time">{totals?.earned ?? 0} / {totals?.total ?? achievements.length} unlocked</span>
          </div>

          <div className="admin-tabs" style={{ marginBottom: 20 }}>
            {(["all", "earned", "locked"] as const).map((f) => (
              <button key={f} className={`admin-tab ${filter === f ? "admin-tab-active" : ""}`} onClick={() => setFilter(f)}>
                {f.charAt(0).toUpperCase() + f.slice(1)}
              </button>
            ))}
          </div>

          {filtered.length === 0 && <p className="hm-empty">No achievements here.</p>}

          {Object.entries(grouped).map(([category, list]) => (
            <div key={category} style={{ marginBottom: 32 }}>
              <h3 style={{ fontSize: "1rem", color: "var(--muted)", marginBottom: 12, textTransform: "uppercase", letterSpacing: "0.05em" }}>
                {category}
              </h3>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(220px, 1fr))", gap: 14 }}>
                {list.map((a) => {
                  const pct = a.goal > 0 ? Math.min(100, Math.round((a.progress / a.goal) * 100)) : 0;
                  return (
                    <div
                      key={a.key}
                      className="hm-panel"
                      style={{
                        opacity: a.earned ? 1 : 0.65,
                        border: a.earned ? "2px solid var(--orange)" : "1px solid var(--line)",
                        padding: 16,
                      }}
                    >
                      <div style={{ fontSize: "2.4rem", textAlign: "center", marginBottom: 8, filter: a.earned ? "none" : "grayscale(1)" }}>
                        {a.icon}
                      </div>
                      <h4 style={{ margin: "0 0 4px", fontSize: "0.95rem", textAlign: "center" }}>{a.title}</h4>
                      <p style={{ fontSize: "0.75rem", color: "var(--muted)", textAlign: "center", margin: "0 0 12px" }}>
                        {a.description}
                      </p>
                      {a.earned ? (
                        <div style={{ textAlign: "center", fontSize: "0.7rem", color: "var(--teal)" }}>
                          ✓ Earned{a.earned_at ? ` on ${new Date(a.earned_at).toLocaleDateString()}` : ""}
                        </div>
                      ) : (
                        <>
                          <div style={{ height: 6, background: "var(--panel)", borderRadius: 3, overflow: "hidden" }}>
                            <div style={{ width: `${pct}%`, height: "100%", background: "var(--orange)" }} />
                          </div>
                          <div style={{ fontSize: "0.7rem", color: "var(--muted)", textAlign: "center", marginTop: 4 }}>
                            {a.progress} / {a.goal}
                          </div>
                        </>
                      )}
                      <div style={{ fontSize: "0.7rem", color: "var(--muted)", textAlign: "center", marginTop: 8 }}>
                        +{a.points} pts
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </section>
      </main>
    </div>
  );
}
