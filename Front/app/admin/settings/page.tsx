"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

interface PlatformSettings {
  donation: {
    amount_per_completed_read: number;
    completed_read_percent: number;
    nonprofit_share_percent: number;
    operations_share_percent: number;
    growth_share_percent: number;
  };
  subscription: {
    monthly_amount: number;
    yearly_amount: number;
    currency: string;
    trial_days: number;
  };
  payouts: {
    minimum_amount: number;
    cycle: "weekly" | "biweekly" | "monthly";
    auto_execute: boolean;
    hold_days: number;
  };
  security: {
    require_phone_mfa: boolean;
    require_email_verification: boolean;
    max_failed_logins: number;
    session_lifetime_hours: number;
  };
  content: {
    require_review_before_publish: boolean;
    default_read_time_minutes: number;
    max_body_length: number;
  };
  emails: {
    from_name: string;
    from_address: string;
    support_address: string;
  };
}

type TabKey = "donation" | "subscription" | "payouts" | "security" | "content" | "emails";

const TABS: { key: TabKey; label: string; icon: string }[] = [
  { key: "donation", label: "Donation split", icon: "💰" },
  { key: "subscription", label: "Subscription plans", icon: "💳" },
  { key: "payouts", label: "Payouts", icon: "🏦" },
  { key: "security", label: "Security", icon: "🔒" },
  { key: "content", label: "Content", icon: "📰" },
  { key: "emails", label: "Emails", icon: "✉️" },
];

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState<PlatformSettings | null>(null);
  const [tab, setTab] = useState<TabKey>("donation");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    (async () => {
      try {
        const res = await adminFetch("/admin/settings");
        const data = await res.json();
        if (!res.ok) setError(data?.message ?? "Failed to load settings.");
        else setSettings(data.data?.settings ?? null);
      } catch {
        setError("Failed to load settings.");
      }
      setLoading(false);
    })();
  }, []);

  function handleSave(section: TabKey) {
    if (!settings) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/settings/${section}`, {
        method: "PATCH",
        body: JSON.stringify(settings[section]),
      });
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to save.");
        return;
      }
      setFeedback("Settings saved.");
    });
  }

  function update<K extends TabKey>(section: K, field: keyof PlatformSettings[K], value: unknown) {
    if (!settings) return;
    setSettings({
      ...settings,
      [section]: { ...settings[section], [field]: value },
    });
  }

  if (loading) return <div><h2 className="admin-page-title">Settings</h2><p className="admin-empty">Loading…</p></div>;
  if (error && !settings) return <div><h2 className="admin-page-title">Settings</h2><p className="narlit-feedback narlit-feedback-error">{error}</p></div>;
  if (!settings) return null;

  const donationTotal =
    settings.donation.nonprofit_share_percent +
    settings.donation.operations_share_percent +
    settings.donation.growth_share_percent;

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Settings</h2>
      </div>

      <div className="admin-tabs">
        {TABS.map((t) => (
          <button key={t.key} className={`admin-tab ${tab === t.key ? "admin-tab-active" : ""}`} onClick={() => { setTab(t.key); setFeedback(""); setError(""); }}>
            {t.icon} {t.label}
          </button>
        ))}
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      <div className="admin-table-wrap" style={{ padding: 24 }}>
        {tab === "donation" && (
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("donation"); }}>
            <h3 style={{ marginTop: 0 }}>Donation calculation</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem", marginBottom: 20 }}>
              Controls how much each completed read earns for the nonprofit, and how subscription revenue is split.
            </p>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              <label className="narlit-field">
                <span>Amount per completed read ($)</span>
                <input type="number" step="0.01" min="0" value={settings.donation.amount_per_completed_read}
                  onChange={(e) => update("donation", "amount_per_completed_read", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Read percent threshold (%)</span>
                <input type="number" min="0" max="100" value={settings.donation.completed_read_percent}
                  onChange={(e) => update("donation", "completed_read_percent", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Nonprofit share (%)</span>
                <input type="number" min="0" max="100" value={settings.donation.nonprofit_share_percent}
                  onChange={(e) => update("donation", "nonprofit_share_percent", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Operations share (%)</span>
                <input type="number" min="0" max="100" value={settings.donation.operations_share_percent}
                  onChange={(e) => update("donation", "operations_share_percent", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Growth share (%)</span>
                <input type="number" min="0" max="100" value={settings.donation.growth_share_percent}
                  onChange={(e) => update("donation", "growth_share_percent", Number(e.target.value))} />
              </label>
            </div>

            {donationTotal !== 100 && (
              <p className="narlit-feedback narlit-feedback-error" style={{ marginTop: 12 }}>
                Shares total {donationTotal}%. They must sum to 100%.
              </p>
            )}

            <button type="submit" className="admin-btn admin-btn-approve" style={{ marginTop: 20 }} disabled={isPending || donationTotal !== 100}>
              Save donation settings
            </button>
          </form>
        )}

        {tab === "subscription" && (
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("subscription"); }}>
            <h3 style={{ marginTop: 0 }}>Subscription plans</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem", marginBottom: 20 }}>
              Prices are stored in Stripe. Changes here update the price references only.
            </p>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              <label className="narlit-field">
                <span>Monthly price (cents)</span>
                <input type="number" min="0" value={settings.subscription.monthly_amount}
                  onChange={(e) => update("subscription", "monthly_amount", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Yearly price (cents)</span>
                <input type="number" min="0" value={settings.subscription.yearly_amount}
                  onChange={(e) => update("subscription", "yearly_amount", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Currency</span>
                <input type="text" maxLength={3} value={settings.subscription.currency}
                  onChange={(e) => update("subscription", "currency", e.target.value.toUpperCase())} />
              </label>
              <label className="narlit-field">
                <span>Trial days</span>
                <input type="number" min="0" max="90" value={settings.subscription.trial_days}
                  onChange={(e) => update("subscription", "trial_days", Number(e.target.value))} />
              </label>
            </div>

            <button type="submit" className="admin-btn admin-btn-approve" style={{ marginTop: 20 }} disabled={isPending}>
              Save subscription settings
            </button>
          </form>
        )}

        {tab === "payouts" && (
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("payouts"); }}>
            <h3 style={{ marginTop: 0 }}>Payout policy</h3>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              <label className="narlit-field">
                <span>Minimum payout ($)</span>
                <input type="number" step="0.01" min="0" value={settings.payouts.minimum_amount}
                  onChange={(e) => update("payouts", "minimum_amount", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Cycle</span>
                <select value={settings.payouts.cycle}
                  onChange={(e) => update("payouts", "cycle", e.target.value)}>
                  <option value="weekly">Weekly</option>
                  <option value="biweekly">Biweekly</option>
                  <option value="monthly">Monthly</option>
                </select>
              </label>
              <label className="narlit-field">
                <span>Hold days (fraud window)</span>
                <input type="number" min="0" max="60" value={settings.payouts.hold_days}
                  onChange={(e) => update("payouts", "hold_days", Number(e.target.value))} />
              </label>
              <label className="narlit-field" style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <input type="checkbox" checked={settings.payouts.auto_execute}
                  onChange={(e) => update("payouts", "auto_execute", e.target.checked)} />
                <span>Auto-execute payout batches</span>
              </label>
            </div>

            <button type="submit" className="admin-btn admin-btn-approve" style={{ marginTop: 20 }} disabled={isPending}>
              Save payout settings
            </button>
          </form>
        )}

        {tab === "security" && (
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("security"); }}>
            <h3 style={{ marginTop: 0 }}>Security policy</h3>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              <label className="narlit-field" style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <input type="checkbox" checked={settings.security.require_phone_mfa}
                  onChange={(e) => update("security", "require_phone_mfa", e.target.checked)} />
                <span>Require phone MFA on first login</span>
              </label>
              <label className="narlit-field" style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <input type="checkbox" checked={settings.security.require_email_verification}
                  onChange={(e) => update("security", "require_email_verification", e.target.checked)} />
                <span>Require email verification</span>
              </label>
              <label className="narlit-field">
                <span>Max failed logins before lockout</span>
                <input type="number" min="1" max="20" value={settings.security.max_failed_logins}
                  onChange={(e) => update("security", "max_failed_logins", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Session lifetime (hours)</span>
                <input type="number" min="1" max="720" value={settings.security.session_lifetime_hours}
                  onChange={(e) => update("security", "session_lifetime_hours", Number(e.target.value))} />
              </label>
            </div>

            <button type="submit" className="admin-btn admin-btn-approve" style={{ marginTop: 20 }} disabled={isPending}>
              Save security settings
            </button>
          </form>
        )}

        {tab === "content" && (
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("content"); }}>
            <h3 style={{ marginTop: 0 }}>Content policy</h3>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              <label className="narlit-field" style={{ flexDirection: "row", alignItems: "center", gap: 8, gridColumn: "span 2" }}>
                <input type="checkbox" checked={settings.content.require_review_before_publish}
                  onChange={(e) => update("content", "require_review_before_publish", e.target.checked)} />
                <span>Require admin review before publishing</span>
              </label>
              <label className="narlit-field">
                <span>Default read time (min)</span>
                <input type="number" min="1" max="60" value={settings.content.default_read_time_minutes}
                  onChange={(e) => update("content", "default_read_time_minutes", Number(e.target.value))} />
              </label>
              <label className="narlit-field">
                <span>Max article body (chars)</span>
                <input type="number" min="500" max="200000" value={settings.content.max_body_length}
                  onChange={(e) => update("content", "max_body_length", Number(e.target.value))} />
              </label>
            </div>

            <button type="submit" className="admin-btn admin-btn-approve" style={{ marginTop: 20 }} disabled={isPending}>
              Save content settings
            </button>
          </form>
        )}

        {tab === "emails" && (
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("emails"); }}>
            <h3 style={{ marginTop: 0 }}>Email configuration</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem", marginBottom: 20 }}>
              SMTP credentials live in the server .env — this only controls the friendly names.
            </p>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              <label className="narlit-field">
                <span>From name</span>
                <input type="text" value={settings.emails.from_name}
                  onChange={(e) => update("emails", "from_name", e.target.value)} />
              </label>
              <label className="narlit-field">
                <span>From address</span>
                <input type="email" value={settings.emails.from_address}
                  onChange={(e) => update("emails", "from_address", e.target.value)} />
              </label>
              <label className="narlit-field" style={{ gridColumn: "span 2" }}>
                <span>Support address (Reply-To)</span>
                <input type="email" value={settings.emails.support_address}
                  onChange={(e) => update("emails", "support_address", e.target.value)} />
              </label>
            </div>

            <button type="submit" className="admin-btn admin-btn-approve" style={{ marginTop: 20 }} disabled={isPending}>
              Save email settings
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
