"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Preferences {
  email_digest: "off" | "daily" | "weekly" | "monthly";
  email_new_articles: boolean;
  email_new_from_supported: boolean;
  email_impact_summary: boolean;
  email_product_updates: boolean;
  push_new_articles: boolean;
  push_achievements: boolean;
  push_payment_events: boolean;
  language: string;
  timezone: string;
}

interface User { full_name: string; email: string }

const LANGUAGES = [
  { key: "en", label: "English" },
  { key: "ar", label: "Arabic (العربية)" },
  { key: "es", label: "Spanish" },
  { key: "fr", label: "French" },
];

export default function PreferencesPage() {
  const [user, setUser] = useState<User | null>(null);
  const [prefs, setPrefs] = useState<Preferences | null>(null);
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const [meRes, prefRes] = await Promise.all([apiFetch("/auth/me"), apiFetch("/member/preferences")]);
        const meData = await meRes.json();
        const prefData = await prefRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
        if (prefRes.ok) setPrefs(prefData.data?.preferences ?? null);
      } catch { setError("Failed to load preferences."); }
      setChecking(false);
    });
  }, []);

  function save(e: FormEvent) {
    e.preventDefault();
    if (!prefs) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/preferences", {
          method: "PATCH",
          body: JSON.stringify(prefs),
        });
        const data = await res.json();
        if (!res.ok) { setError(data?.message ?? "Failed to save."); return; }
        setFeedback("Preferences saved.");
      } catch { setError("Failed to save."); }
    });
  }

  function update<K extends keyof Preferences>(key: K, value: Preferences[K]) {
    if (!prefs) return;
    setPrefs({ ...prefs, [key]: value });
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" /><span className="hm-loading-dot" /><span className="hm-loading-dot" />
      </div>
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";
  if (!prefs) {
    return (
      <div className="hm-shell" suppressHydrationWarning>
        <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
        <main className="hm-main"><p className="hm-empty">Failed to load preferences.</p></main>
      </div>
    );
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 720, margin: "0 auto" }}>
          <h2 className="hm-section-title">Preferences</h2>

          {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          <form onSubmit={save} className="hm-panel" style={{ padding: 24, marginTop: 16 }}>
            <h3 className="hm-panel-title">Email digest</h3>
            <p className="hm-panel-sub">A summary of new stories and your impact.</p>
            <div style={{ display: "flex", gap: 8, marginTop: 12, flexWrap: "wrap" }}>
              {(["off", "daily", "weekly", "monthly"] as const).map((f) => (
                <button
                  key={f}
                  type="button"
                  className={`admin-tab ${prefs.email_digest === f ? "admin-tab-active" : ""}`}
                  onClick={() => update("email_digest", f)}
                >
                  {f.charAt(0).toUpperCase() + f.slice(1)}
                </button>
              ))}
            </div>

            <h3 className="hm-panel-title" style={{ marginTop: 32 }}>Email notifications</h3>
            <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 12 }}>
              <ToggleRow label="New stories on NarLit" description="Get notified when new articles are published" checked={prefs.email_new_articles} onChange={(v) => update("email_new_articles", v)} />
              <ToggleRow label="New stories from nonprofits you support" description="Extra notifications for the causes you've backed" checked={prefs.email_new_from_supported} onChange={(v) => update("email_new_from_supported", v)} />
              <ToggleRow label="Monthly impact summary" description="Recap of your donations and reads" checked={prefs.email_impact_summary} onChange={(v) => update("email_impact_summary", v)} />
              <ToggleRow label="Product updates" description="Occasional announcements about NarLit features" checked={prefs.email_product_updates} onChange={(v) => update("email_product_updates", v)} />
            </div>

            <h3 className="hm-panel-title" style={{ marginTop: 32 }}>In-app notifications</h3>
            <div style={{ display: "flex", flexDirection: "column", gap: 10, marginTop: 12 }}>
              <ToggleRow label="New stories" checked={prefs.push_new_articles} onChange={(v) => update("push_new_articles", v)} />
              <ToggleRow label="Achievements & streaks" checked={prefs.push_achievements} onChange={(v) => update("push_achievements", v)} />
              <ToggleRow label="Payment events" checked={prefs.push_payment_events} onChange={(v) => update("push_payment_events", v)} />
            </div>

            <h3 className="hm-panel-title" style={{ marginTop: 32 }}>Language & region</h3>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginTop: 12 }}>
              <label className="narlit-field">
                <span>Language</span>
                <select value={prefs.language} onChange={(e) => update("language", e.target.value)}>
                  {LANGUAGES.map((l) => <option key={l.key} value={l.key}>{l.label}</option>)}
                </select>
              </label>
              <label className="narlit-field">
                <span>Timezone</span>
                <input type="text" value={prefs.timezone} onChange={(e) => update("timezone", e.target.value)} placeholder="e.g. America/New_York" />
              </label>
            </div>

            <button type="submit" className="hm-article-btn" style={{ marginTop: 24 }} disabled={isPending}>
              {isPending ? "Saving…" : "Save preferences"}
            </button>
          </form>
        </section>
      </main>
    </div>
  );
}

function ToggleRow({
  label,
  description,
  checked,
  onChange,
}: {
  label: string;
  description?: string;
  checked: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <label
      style={{
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
        padding: "12px 0",
        borderBottom: "1px solid var(--line)",
        cursor: "pointer",
      }}
    >
      <div>
        <div style={{ fontSize: "0.9rem", fontWeight: 600 }}>{label}</div>
        {description && <div style={{ fontSize: "0.78rem", color: "var(--muted)", marginTop: 2 }}>{description}</div>}
      </div>
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        style={{ width: 20, height: 20, cursor: "pointer" }}
      />
    </label>
  );
}
