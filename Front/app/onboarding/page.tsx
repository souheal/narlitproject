"use client";

import { useEffect, useState, useTransition } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Category { key: string; label: string; icon: string; }
interface OrgSuggestion { public_id: string; organization_name: string; mission_statement: string | null; category: string | null; logo_url: string | null; }

const STEPS = ["welcome", "causes", "nonprofits", "goal", "notifications", "done"] as const;
type Step = typeof STEPS[number];

const CAUSES: Category[] = [
  { key: "civil_rights", label: "Civil Rights", icon: "⚖️" },
  { key: "food_security", label: "Food Security", icon: "🍽️" },
  { key: "healthcare", label: "Healthcare", icon: "🏥" },
  { key: "education", label: "Education", icon: "🎓" },
  { key: "environment", label: "Environment", icon: "🌍" },
  { key: "housing", label: "Housing", icon: "🏠" },
  { key: "refugees", label: "Refugees", icon: "🕊️" },
  { key: "women_children", label: "Women & Children", icon: "👨‍👩‍👧" },
  { key: "animals", label: "Animals", icon: "🐾" },
  { key: "arts", label: "Arts & Culture", icon: "🎨" },
];

export default function OnboardingPage() {
  const [step, setStep] = useState<Step>("welcome");
  const [selectedCauses, setSelectedCauses] = useState<string[]>([]);
  const [followedOrgs, setFollowedOrgs] = useState<string[]>([]);
  const [orgs, setOrgs] = useState<OrgSuggestion[]>([]);
  const [monthlyGoal, setMonthlyGoal] = useState(10);
  const [enableEmailDigest, setEnableEmailDigest] = useState<"weekly" | "monthly" | "off">("weekly");
  const [enableAchievements, setEnableAchievements] = useState(true);
  const [checking, setChecking] = useState(true);
  const [loadingOrgs, setLoadingOrgs] = useState(false);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    validateSession().then((valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      setChecking(false);
    });
  }, []);

  async function goToNonprofits() {
    setLoadingOrgs(true);
    try {
      const params = new URLSearchParams({ suggest: "1" });
      if (selectedCauses.length) params.set("categories", selectedCauses.join(","));
      const res = await apiFetch(`/organizations?${params.toString()}`);
      const data = await res.json();
      if (res.ok) setOrgs(data.data?.organizations?.data ?? data.data?.organizations ?? []);
    } catch { /* ignore */ }
    setLoadingOrgs(false);
    setStep("nonprofits");
  }

  function toggleCause(key: string) {
    setSelectedCauses((prev) => prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key]);
  }

  function toggleOrg(id: string) {
    setFollowedOrgs((prev) => prev.includes(id) ? prev.filter((k) => k !== id) : [...prev, id]);
  }

  function finish() {
    startTransition(async () => {
      try {
        await apiFetch("/member/onboarding/complete", {
          method: "POST",
          body: JSON.stringify({
            causes: selectedCauses,
            follows: followedOrgs,
            monthly_reading_goal: monthlyGoal,
            email_digest: enableEmailDigest,
            push_achievements: enableAchievements,
          }),
        });
      } catch { /* ignore */ }
      window.location.href = "/dashboard";
    });
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" /><span className="hm-loading-dot" /><span className="hm-loading-dot" />
      </div>
    );
  }

  const stepIndex = STEPS.indexOf(step);
  const progressPct = ((stepIndex) / (STEPS.length - 2)) * 100;

  return (
    <div className="login-shell" suppressHydrationWarning>
      <div className="narlit-backdrop narlit-backdrop-one" />
      <div className="narlit-backdrop narlit-backdrop-two" />
      <div className="login-card" style={{ maxWidth: 640 }} suppressHydrationWarning>
        <div style={{ height: 4, background: "var(--panel)", borderRadius: 2, marginBottom: 24, overflow: "hidden" }}>
          <div style={{ width: `${progressPct}%`, height: "100%", background: "linear-gradient(90deg, var(--orange), var(--teal))", transition: "width 0.3s" }} />
        </div>

        {step === "welcome" && (
          <div style={{ textAlign: "center" }}>
            <div style={{ fontSize: "3.5rem", marginBottom: 12 }}>👋</div>
            <h1 className="login-title" style={{ fontSize: "1.7rem" }}>
              Welcome to <span style={{ color: "var(--orange)" }}>NAR</span><span style={{ color: "var(--teal)" }}>LIT</span>
            </h1>
            <p className="hm-panel-sub" style={{ maxWidth: 480, margin: "12px auto 24px", lineHeight: 1.6 }}>
              Every article you finish reading donates to the nonprofit that wrote it.
              Let&apos;s tailor NarLit to what matters most to you — takes about a minute.
            </p>
            <button className="narlit-button narlit-button-primary" onClick={() => setStep("causes")}>
              Let&apos;s get started →
            </button>
            <p style={{ marginTop: 16, fontSize: "0.8rem" }}>
              <a href="/dashboard" className="su-link">Skip for now</a>
            </p>
          </div>
        )}

        {step === "causes" && (
          <div>
            <h2 className="login-title" style={{ fontSize: "1.4rem", marginBottom: 6 }}>What do you care about?</h2>
            <p className="hm-panel-sub">Pick a few — we&apos;ll surface stories that match.</p>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: 10, marginTop: 20 }}>
              {CAUSES.map((c) => {
                const active = selectedCauses.includes(c.key);
                return (
                  <button
                    key={c.key}
                    type="button"
                    onClick={() => toggleCause(c.key)}
                    style={{
                      padding: "16px 12px",
                      borderRadius: 12,
                      border: `2px solid ${active ? "var(--orange)" : "var(--line)"}`,
                      background: active ? "rgba(255,138,71,0.08)" : "var(--panel)",
                      cursor: "pointer",
                      color: "inherit",
                      fontFamily: "inherit",
                      transition: "all 0.15s",
                    }}
                  >
                    <div style={{ fontSize: "1.6rem" }}>{c.icon}</div>
                    <div style={{ fontSize: "0.85rem", fontWeight: 700, marginTop: 4 }}>{c.label}</div>
                  </button>
                );
              })}
            </div>
            <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
              <button className="narlit-button narlit-button-secondary" onClick={() => setStep("welcome")}>Back</button>
              <button className="narlit-button narlit-button-primary" onClick={goToNonprofits} disabled={selectedCauses.length === 0 || loadingOrgs}>
                {loadingOrgs ? "Loading…" : "Continue →"}
              </button>
            </div>
          </div>
        )}

        {step === "nonprofits" && (
          <div>
            <h2 className="login-title" style={{ fontSize: "1.4rem", marginBottom: 6 }}>Follow some nonprofits</h2>
            <p className="hm-panel-sub">You&apos;ll see their new stories first.</p>
            <div style={{ maxHeight: 360, overflowY: "auto", marginTop: 16, display: "flex", flexDirection: "column", gap: 8 }}>
              {orgs.length === 0 && <p className="hm-empty">No suggestions yet — you can follow them later.</p>}
              {orgs.map((o) => {
                const active = followedOrgs.includes(o.public_id);
                return (
                  <button
                    key={o.public_id}
                    type="button"
                    onClick={() => toggleOrg(o.public_id)}
                    style={{
                      padding: 12,
                      borderRadius: 10,
                      border: `1px solid ${active ? "var(--teal)" : "var(--line)"}`,
                      background: active ? "rgba(17,182,200,0.06)" : "var(--panel)",
                      display: "flex",
                      gap: 12,
                      textAlign: "left",
                      cursor: "pointer",
                      color: "inherit",
                      fontFamily: "inherit",
                    }}
                  >
                    <div style={{
                      width: 40, height: 40, borderRadius: 8,
                      background: "linear-gradient(135deg, var(--orange), var(--teal))",
                      display: "flex", alignItems: "center", justifyContent: "center",
                      color: "white", fontWeight: 800, fontSize: "0.85rem", flexShrink: 0,
                    }}>
                      {o.organization_name.slice(0, 2).toUpperCase()}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontWeight: 700, fontSize: "0.9rem" }}>{o.organization_name}</div>
                      {o.mission_statement && (
                        <div style={{ fontSize: "0.75rem", color: "var(--muted)", marginTop: 2, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                          {o.mission_statement}
                        </div>
                      )}
                    </div>
                    <div style={{ alignSelf: "center", fontSize: "0.8rem", fontWeight: 700, color: active ? "var(--teal)" : "var(--muted)" }}>
                      {active ? "✓ Following" : "+ Follow"}
                    </div>
                  </button>
                );
              })}
            </div>
            <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
              <button className="narlit-button narlit-button-secondary" onClick={() => setStep("causes")}>Back</button>
              <button className="narlit-button narlit-button-primary" onClick={() => setStep("goal")}>Continue →</button>
            </div>
          </div>
        )}

        {step === "goal" && (
          <div>
            <h2 className="login-title" style={{ fontSize: "1.4rem", marginBottom: 6 }}>Set a reading goal</h2>
            <p className="hm-panel-sub">A small monthly target keeps you engaged.</p>
            <div style={{ display: "flex", justifyContent: "center", gap: 10, marginTop: 24, flexWrap: "wrap" }}>
              {[5, 10, 20, 30].map((n) => (
                <button
                  key={n}
                  type="button"
                  onClick={() => setMonthlyGoal(n)}
                  style={{
                    padding: "20px 24px",
                    borderRadius: 14,
                    border: `2px solid ${monthlyGoal === n ? "var(--orange)" : "var(--line)"}`,
                    background: monthlyGoal === n ? "rgba(255,138,71,0.08)" : "var(--panel)",
                    cursor: "pointer",
                    color: "inherit",
                    fontFamily: "inherit",
                    minWidth: 100,
                  }}
                >
                  <div style={{ fontSize: "1.8rem", fontWeight: 900 }}>{n}</div>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>articles / month</div>
                </button>
              ))}
            </div>
            <div style={{ marginTop: 24, padding: 16, background: "var(--panel)", borderRadius: 12, textAlign: "center", fontSize: "0.85rem", color: "var(--muted)" }}>
              At {monthlyGoal} articles/month, you&apos;d donate roughly <strong>${(monthlyGoal * 0.07).toFixed(2)}</strong> to nonprofits.
            </div>
            <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
              <button className="narlit-button narlit-button-secondary" onClick={() => setStep("nonprofits")}>Back</button>
              <button className="narlit-button narlit-button-primary" onClick={() => setStep("notifications")}>Continue →</button>
            </div>
          </div>
        )}

        {step === "notifications" && (
          <div>
            <h2 className="login-title" style={{ fontSize: "1.4rem", marginBottom: 6 }}>Stay in the loop</h2>
            <p className="hm-panel-sub">You can change these anytime.</p>
            <div style={{ marginTop: 20 }}>
              <div style={{ fontSize: "0.85rem", fontWeight: 700, marginBottom: 8 }}>Email digest</div>
              <div style={{ display: "flex", gap: 8, marginBottom: 20 }}>
                {(["off", "weekly", "monthly"] as const).map((f) => (
                  <button
                    key={f}
                    type="button"
                    onClick={() => setEnableEmailDigest(f)}
                    className={`admin-tab ${enableEmailDigest === f ? "admin-tab-active" : ""}`}
                  >
                    {f.charAt(0).toUpperCase() + f.slice(1)}
                  </button>
                ))}
              </div>
              <label style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: 14, background: "var(--panel)", borderRadius: 10, cursor: "pointer" }}>
                <div>
                  <div style={{ fontWeight: 600, fontSize: "0.9rem" }}>Achievements & streaks</div>
                  <div style={{ fontSize: "0.75rem", color: "var(--muted)" }}>Celebrate milestones as you read</div>
                </div>
                <input type="checkbox" checked={enableAchievements} onChange={(e) => setEnableAchievements(e.target.checked)} style={{ width: 20, height: 20 }} />
              </label>
            </div>
            <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
              <button className="narlit-button narlit-button-secondary" onClick={() => setStep("goal")}>Back</button>
              <button className="narlit-button narlit-button-primary" onClick={() => setStep("done")}>Continue →</button>
            </div>
          </div>
        )}

        {step === "done" && (
          <div style={{ textAlign: "center" }}>
            <div style={{ fontSize: "3.5rem", marginBottom: 12 }}>🎉</div>
            <h2 className="login-title" style={{ fontSize: "1.5rem" }}>You&apos;re all set!</h2>
            <p className="hm-panel-sub" style={{ maxWidth: 420, margin: "12px auto 24px", lineHeight: 1.6 }}>
              Your dashboard is ready. Every read moves the needle.
            </p>
            <button
              className="narlit-button narlit-button-primary"
              onClick={finish}
              disabled={isPending}
            >
              {isPending ? "Finishing…" : "Take me to my dashboard →"}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
