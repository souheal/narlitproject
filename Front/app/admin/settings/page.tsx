"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

interface ImpactSplit {
  nonprofit_percentage: number;
  operations_percentage: number;
  growth_percentage: number;
}

interface SubscriptionPlan {
  key: string;
  name: string;
  billing_interval: "monthly" | "yearly";
  display_price: string;
  stripe_price_id: string | null;
  enabled: boolean;
  founding_member_cap: number | null;
}

interface SubscriptionPlans {
  plans: SubscriptionPlan[];
}

interface PayoutSettings {
  minimum_payout_amount: string;
  payout_day_of_month: number;
  automatic_execution_enabled: boolean;
  retry_attempts: number;
  execution_mode_warning: string;
}

interface SecuritySettings {
  email_otp_expiry_minutes: number;
  phone_mfa_expiry_minutes: number;
  login_attempt_limit: number;
  lockout_duration_minutes: number;
  token_expiration_days: number;
  read_rate_limit_per_minute: number;
}

interface ContentSettings {
  minimum_reading_seconds: number;
  minimum_scroll_percentage: number;
  one_counted_read_period_hours: number;
  article_approval_required: boolean;
  featured_article_limit: number;
}

interface EmailSettings {
  sender_name: string;
  sender_address: string;
  support_email: string;
  transactional_emails: {
    email_otp: boolean;
    password_reset: boolean;
    phone_mfa: boolean;
    subscription_receipts: boolean;
    organization_review_updates: boolean;
  };
}

interface PlatformSettings {
  impact_split: ImpactSplit;
  subscription_plans: SubscriptionPlans;
  payout: PayoutSettings;
  security: SecuritySettings;
  content: ContentSettings;
  email: EmailSettings;
}

type GroupKey = keyof PlatformSettings;

const TABS: { key: GroupKey; label: string; icon: string; description: string }[] = [
  { key: "impact_split", label: "Revenue split", icon: "💰", description: "How revenue is distributed" },
  { key: "subscription_plans", label: "Plans & pricing", icon: "💳", description: "Subscription plans" },
  { key: "payout", label: "Payouts", icon: "🏦", description: "When nonprofits are paid" },
  { key: "email", label: "Contact", icon: "✉️", description: "Sender and support addresses" },
];

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState<PlatformSettings | null>(null);
  const [tab, setTab] = useState<GroupKey>("impact_split");
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
        else setSettings((data.data?.settings ?? null) as PlatformSettings | null);
      } catch {
        setError("Failed to load settings.");
      }
      setLoading(false);
    })();
  }, []);

  function handleSave(group: GroupKey) {
    if (!settings) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/settings/${group}`, {
        method: "PUT",
        body: JSON.stringify(settings[group]),
      });
      const data = await res.json();
      if (!res.ok) {
        const detail = data?.errors ? Object.values(data.errors).flat().join(" ") : "";
        setError((data?.message ?? "Failed to save.") + (detail ? ` ${detail}` : ""));
        return;
      }
      setFeedback("Settings saved successfully.");
      setSettings((prev) => (prev ? { ...prev, [group]: data.data?.settings ?? prev[group] } : prev));
    });
  }

  function updateGroup<K extends GroupKey>(group: K, patch: Partial<PlatformSettings[K]>) {
    if (!settings) return;
    setSettings({ ...settings, [group]: { ...settings[group], ...patch } });
  }

  function updatePlan(index: number, patch: Partial<SubscriptionPlan>) {
    if (!settings) return;
    const plans = [...settings.subscription_plans.plans];
    plans[index] = { ...plans[index], ...patch };
    updateGroup("subscription_plans", { plans });
  }

  function addPlan() {
    if (!settings) return;
    const plans = [
      ...settings.subscription_plans.plans,
      {
        key: `plan_${settings.subscription_plans.plans.length + 1}`,
        name: "New plan",
        billing_interval: "monthly" as const,
        display_price: "0.00",
        stripe_price_id: null,
        enabled: true,
        founding_member_cap: null,
      },
    ];
    updateGroup("subscription_plans", { plans });
  }

  function removePlan(index: number) {
    if (!settings) return;
    if (settings.subscription_plans.plans.length <= 1) return;
    const plans = settings.subscription_plans.plans.filter((_, i) => i !== index);
    updateGroup("subscription_plans", { plans });
  }

  if (loading) {
    return (
      <div suppressHydrationWarning>
        <h2 className="admin-page-title">Settings</h2>
        <p className="admin-empty">Loading…</p>
      </div>
    );
  }

  if (error && !settings) {
    return (
      <div suppressHydrationWarning>
        <h2 className="admin-page-title">Settings</h2>
        <p className="narlit-feedback narlit-feedback-error">{error}</p>
      </div>
    );
  }

  if (!settings) return null;

  const activeTab = TABS.find((t) => t.key === tab)!;
  const impactTotal =
    settings.impact_split.nonprofit_percentage +
    settings.impact_split.operations_percentage +
    settings.impact_split.growth_percentage;

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Platform settings</h2>
      </div>

      <div className="admin-settings-shell">
        <aside className="admin-settings-nav">
          {TABS.map((t) => (
            <button
              key={t.key}
              className={`admin-settings-nav-item${tab === t.key ? " admin-settings-nav-item-active" : ""}`}
              onClick={() => { setTab(t.key); setFeedback(""); setError(""); }}
            >
              <span className="admin-settings-nav-icon">{t.icon}</span>
              <span className="admin-settings-nav-body">
                <span className="admin-settings-nav-label">{t.label}</span>
                <span className="admin-settings-nav-hint">{t.description}</span>
              </span>
            </button>
          ))}
        </aside>

        <section className="admin-panel admin-settings-content">
          <header className="admin-panel-header">
            <div className="admin-ts-heading">
              <span className={`admin-ts-icon admin-ts-icon-${tab === "impact_split" || tab === "payout" ? "orange" : "teal"}`}>
                {activeTab.icon}
              </span>
              <div>
                <h3 className="admin-panel-title">{activeTab.label}</h3>
                <p className="admin-panel-sub">{activeTab.description}</p>
              </div>
            </div>
          </header>

          {feedback && <p className="narlit-feedback narlit-feedback-success" style={{ marginTop: 12 }}>{feedback}</p>}
          {error && <p className="narlit-feedback narlit-feedback-error" style={{ marginTop: 12 }}>{error}</p>}

          {tab === "impact_split" && (
            <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("impact_split"); }} className="admin-settings-form">
              <div className="admin-settings-grid">
                <SettingField label="Nonprofit share" hint="Percent of net revenue paid out to nonprofits">
                  <PercentInput
                    value={settings.impact_split.nonprofit_percentage}
                    onChange={(v) => updateGroup("impact_split", { nonprofit_percentage: v })}
                  />
                </SettingField>
                <SettingField label="Operations share" hint="Kept for platform operations">
                  <PercentInput
                    value={settings.impact_split.operations_percentage}
                    onChange={(v) => updateGroup("impact_split", { operations_percentage: v })}
                  />
                </SettingField>
                <SettingField label="Growth share" hint="Reserved for marketing & growth">
                  <PercentInput
                    value={settings.impact_split.growth_percentage}
                    onChange={(v) => updateGroup("impact_split", { growth_percentage: v })}
                  />
                </SettingField>
              </div>

              <div className={`admin-split-summary${impactTotal === 100 ? " admin-split-summary-ok" : " admin-split-summary-bad"}`}>
                <div className="admin-split-bar">
                  <div style={{ width: `${settings.impact_split.nonprofit_percentage}%` }} className="admin-split-seg admin-split-seg-nonprofit" />
                  <div style={{ width: `${settings.impact_split.operations_percentage}%` }} className="admin-split-seg admin-split-seg-ops" />
                  <div style={{ width: `${settings.impact_split.growth_percentage}%` }} className="admin-split-seg admin-split-seg-growth" />
                </div>
                <p>
                  Total: <strong>{impactTotal}%</strong>{" "}
                  {impactTotal === 100 ? "✓ balanced" : "— must equal 100%"}
                </p>
              </div>

              <SaveBar
                disabled={isPending || impactTotal !== 100}
                pending={isPending}
                label="Save impact split"
              />
            </form>
          )}

          {tab === "subscription_plans" && (
            <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("subscription_plans"); }} className="admin-settings-form">
              <ul className="admin-plan-editor-list">
                {settings.subscription_plans.plans.map((plan, i) => (
                  <li key={i} className="admin-plan-editor">
                    <div className="admin-plan-editor-head">
                      <label className="admin-toggle">
                        <input
                          type="checkbox"
                          checked={plan.enabled}
                          onChange={(e) => updatePlan(i, { enabled: e.target.checked })}
                        />
                        <span className="admin-toggle-track" />
                        <span className="admin-toggle-label">{plan.enabled ? "Enabled" : "Disabled"}</span>
                      </label>
                      {settings.subscription_plans.plans.length > 1 && (
                        <button type="button" className="admin-btn admin-btn-reject" onClick={() => removePlan(i)}>
                          Remove
                        </button>
                      )}
                    </div>
                    <div className="admin-settings-grid">
                      <SettingField label="Plan name">
                        <input
                          type="text"
                          className="admin-input"
                          value={plan.name}
                          onChange={(e) => {
                            const name = e.target.value;
                            const key = name.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "") || plan.key;
                            updatePlan(i, { name, key });
                          }}
                        />
                      </SettingField>
                      <SettingField label="Billing">
                        <select
                          className="admin-input"
                          value={plan.billing_interval}
                          onChange={(e) => updatePlan(i, { billing_interval: e.target.value as "monthly" | "yearly" })}
                        >
                          <option value="monthly">Monthly</option>
                          <option value="yearly">Yearly</option>
                        </select>
                      </SettingField>
                      <SettingField label="Price">
                        <div className="admin-input-prefix">
                          <span>$</span>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            className="admin-input"
                            value={plan.display_price}
                            onChange={(e) => updatePlan(i, { display_price: e.target.value })}
                          />
                        </div>
                      </SettingField>
                      <SettingField label="Stripe price ID" hint="price_xxx (from Stripe dashboard)">
                        <input
                          type="text"
                          className="admin-input"
                          value={plan.stripe_price_id ?? ""}
                          placeholder="price_..."
                          onChange={(e) => updatePlan(i, { stripe_price_id: e.target.value.trim() || null })}
                        />
                      </SettingField>
                    </div>
                  </li>
                ))}
              </ul>
              <button type="button" className="admin-btn admin-btn-approve" onClick={addPlan} disabled={settings.subscription_plans.plans.length >= 10}>
                + Add plan
              </button>

              <SaveBar disabled={isPending} pending={isPending} label="Save subscription plans" />
            </form>
          )}

          {tab === "payout" && (
            <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("payout"); }} className="admin-settings-form">
              <div className="admin-settings-grid">
                <SettingField label="Minimum payout" hint="Below this, payouts roll to next cycle">
                  <div className="admin-input-prefix">
                    <span>$</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      className="admin-input"
                      value={settings.payout.minimum_payout_amount}
                      onChange={(e) => updateGroup("payout", { minimum_payout_amount: e.target.value })}
                    />
                  </div>
                </SettingField>
                <SettingField label="Payout day of month" hint="Day the monthly batch runs (1–28)">
                  <input
                    type="number"
                    min="1"
                    max="28"
                    className="admin-input"
                    value={settings.payout.payout_day_of_month}
                    onChange={(e) => updateGroup("payout", { payout_day_of_month: Number(e.target.value) })}
                  />
                </SettingField>
                <SettingField label="Automatic execution" hint="Run payouts without manual approval">
                  <label className="admin-toggle">
                    <input
                      type="checkbox"
                      checked={settings.payout.automatic_execution_enabled}
                      onChange={(e) => updateGroup("payout", { automatic_execution_enabled: e.target.checked })}
                    />
                    <span className="admin-toggle-track" />
                    <span className="admin-toggle-label">
                      {settings.payout.automatic_execution_enabled ? "Enabled" : "Disabled"}
                    </span>
                  </label>
                </SettingField>
              </div>

              <SaveBar disabled={isPending} pending={isPending} label="Save payout settings" />
            </form>
          )}

          {tab === "email" && (
            <form onSubmit={(e: FormEvent) => { e.preventDefault(); handleSave("email"); }} className="admin-settings-form">
              <div className="admin-settings-grid">
                <SettingField label="Sender name" hint="From name in outbound emails">
                  <input
                    type="text"
                    className="admin-input"
                    value={settings.email.sender_name}
                    onChange={(e) => updateGroup("email", { sender_name: e.target.value })}
                  />
                </SettingField>
                <SettingField label="Sender address" hint="From email address">
                  <input
                    type="email"
                    className="admin-input"
                    value={settings.email.sender_address}
                    onChange={(e) => updateGroup("email", { sender_address: e.target.value })}
                  />
                </SettingField>
                <SettingField label="Support address" hint="Reply-To for user replies">
                  <input
                    type="email"
                    className="admin-input"
                    value={settings.email.support_email}
                    onChange={(e) => updateGroup("email", { support_email: e.target.value })}
                  />
                </SettingField>
              </div>

              <SaveBar disabled={isPending} pending={isPending} label="Save email settings" />
            </form>
          )}
        </section>
      </div>
    </div>
  );
}

function SettingField({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <label className="admin-setting-field">
      <span className="admin-setting-field-label">{label}</span>
      {hint && <span className="admin-setting-field-hint">{hint}</span>}
      <div className="admin-setting-field-input">{children}</div>
    </label>
  );
}

function PercentInput({ value, onChange, min = 0 }: { value: number; onChange: (v: number) => void; min?: number }) {
  return (
    <div className="admin-input-suffix">
      <input
        type="number"
        min={min}
        max={100}
        className="admin-input"
        value={value}
        onChange={(e) => onChange(Number(e.target.value))}
      />
      <span>%</span>
    </div>
  );
}

function SaveBar({ disabled, pending, label }: { disabled: boolean; pending: boolean; label: string }) {
  return (
    <div className="admin-settings-save-bar">
      <button type="submit" className="admin-btn admin-btn-approve" disabled={disabled}>
        {pending ? "Saving…" : label}
      </button>
    </div>
  );
}
