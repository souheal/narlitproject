"use client";

import { useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Subscription {
  public_id: string | null;
  plan: string | null;
  amount: string;
  currency: string;
  status: string;
  started_at: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  cancel_at: string | null;
  canceled_at: string | null;
  card_brand: string | null;
  card_last_four: string | null;
  payment_method_id: string | null;
}

interface Plan {
  key: string;
  name: string;
  amount: string;
  interval: "month" | "year";
  currency: string;
  features: string[];
  is_current: boolean;
  savings_note?: string;
}

interface Invoice {
  public_id: string;
  amount: string;
  currency: string;
  status: string;
  invoice_number: string | null;
  paid_at: string | null;
  pdf_url: string | null;
  created_at: string;
}

interface SubPayload {
  subscription: Subscription;
  plans: Plan[];
  invoices: Invoice[];
}

interface User { full_name: string; email: string }

export default function SubscriptionPage() {
  const [user, setUser] = useState<User | null>(null);
  const [data, setData] = useState<SubPayload | null>(null);
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [isPending, startTransition] = useTransition();
  const [showCancelModal, setShowCancelModal] = useState(false);

  async function load() {
    try {
      const [meRes, subRes] = await Promise.all([apiFetch("/auth/me"), apiFetch("/member/subscription")]);
      const meData = await meRes.json();
      const subData = await subRes.json();
      setUser(meData.data?.user ?? meData.data ?? null);
      if (subRes.ok) setData(subData.data ?? null);
      else setError(subData?.message ?? "Failed to load subscription.");
    } catch { setError("Failed to load subscription."); }
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      await load();
      setChecking(false);
    });
  }, []);

  function switchPlan(plan: Plan) {
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/subscription/change-plan", {
          method: "POST",
          body: JSON.stringify({ plan: plan.key }),
        });
        const payload = await res.json();
        if (!res.ok) { setError(payload?.message ?? "Failed to change plan."); return; }
        if (payload.data?.checkout_url) {
          window.location.href = payload.data.checkout_url;
          return;
        }
        setFeedback(`Switched to ${plan.name}.`);
        await load();
      } catch { setError("Failed to change plan."); }
    });
  }

  function cancelSubscription() {
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/subscription/cancel", { method: "POST" });
        const payload = await res.json();
        if (!res.ok) { setError(payload?.message ?? "Failed to cancel."); return; }
        setFeedback("Subscription will end at the current period's close.");
        setShowCancelModal(false);
        await load();
      } catch { setError("Failed to cancel."); }
    });
  }

  function resume() {
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/subscription/resume", { method: "POST" });
        const payload = await res.json();
        if (!res.ok) { setError(payload?.message ?? "Failed to resume."); return; }
        setFeedback("Subscription resumed.");
        await load();
      } catch { setError("Failed to resume."); }
    });
  }

  function updatePaymentMethod() {
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/subscription/update-payment-method", { method: "POST" });
        const payload = await res.json();
        if (!res.ok) { setError(payload?.message ?? "Failed."); return; }
        if (payload.data?.portal_url) window.location.href = payload.data.portal_url;
      } catch { setError("Failed."); }
    });
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" /><span className="hm-loading-dot" /><span className="hm-loading-dot" />
      </div>
    );
  }

  const initials = user?.full_name?.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase() ?? "NL";
  const sub = data?.subscription;
  const plans = data?.plans ?? [];
  const invoices = data?.invoices ?? [];
  const isActive = sub?.status === "active";
  const isCanceling = !!sub?.cancel_at;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 900, margin: "0 auto" }}>
          <h2 className="hm-section-title">Your subscription</h2>

          {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          {/* Current status */}
          <div className="hm-panel" style={{ marginTop: 16 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 16, flexWrap: "wrap" }}>
              <div>
                <h3 className="hm-panel-title" style={{ textTransform: "capitalize" }}>
                  {sub?.plan ?? "No plan"} plan
                  {isCanceling && <span style={{ marginLeft: 10, fontSize: "0.75rem", color: "var(--orange)" }}>Ending soon</span>}
                </h3>
                <p className="hm-panel-sub">
                  {isActive
                    ? `${sub?.currency} $${sub?.amount} · Renews ${sub?.current_period_end ? new Date(sub.current_period_end).toLocaleDateString() : "—"}`
                    : sub?.status === "canceled"
                      ? "Your subscription is canceled."
                      : "You don't have an active subscription."}
                </p>
                {sub?.card_last_four && (
                  <p style={{ margin: "8px 0 0", fontSize: "0.85rem", color: "var(--muted)" }}>
                    💳 {sub.card_brand?.toUpperCase()} •••• {sub.card_last_four}
                  </p>
                )}
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                {isActive && sub?.payment_method_id && (
                  <button className="hm-pager-btn" onClick={updatePaymentMethod} disabled={isPending}>
                    Update payment method
                  </button>
                )}
                {isActive && !isCanceling && (
                  <button className="hm-pager-btn" onClick={() => setShowCancelModal(true)}>
                    Cancel subscription
                  </button>
                )}
                {isCanceling && (
                  <button className="hm-article-btn" onClick={resume} disabled={isPending}>
                    Resume subscription
                  </button>
                )}
                {!isActive && (
                  <a href="/signup" className="hm-article-btn" style={{ textAlign: "center" }}>
                    Start subscription
                  </a>
                )}
              </div>
            </div>

            {isCanceling && sub?.cancel_at && (
              <div style={{ marginTop: 16, padding: 12, background: "rgba(255,138,71,0.08)", border: "1px solid rgba(255,138,71,0.25)", borderRadius: 10, fontSize: "0.85rem" }}>
                Your subscription will end on <strong>{new Date(sub.cancel_at).toLocaleDateString()}</strong>. Until then, you keep full access.
              </div>
            )}
          </div>

          {/* Plans */}
          {plans.length > 0 && (
            <>
              <h2 className="hm-section-title" style={{ marginTop: 32 }}>Available plans</h2>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))", gap: 14 }}>
                {plans.map((p) => (
                  <div
                    key={p.key}
                    className="hm-panel"
                    style={{
                      border: p.is_current ? "2px solid var(--teal)" : "1px solid var(--line)",
                      padding: 20,
                    }}
                  >
                    <h3 style={{ margin: 0, fontSize: "1.05rem" }}>{p.name}</h3>
                    <div style={{ fontSize: "1.6rem", fontWeight: 900, margin: "8px 0" }}>
                      ${p.amount}<span style={{ fontSize: "0.85rem", color: "var(--muted)", fontWeight: 600 }}>/{p.interval}</span>
                    </div>
                    {p.savings_note && (
                      <div style={{ fontSize: "0.72rem", color: "var(--teal)", fontWeight: 700, marginBottom: 8 }}>
                        {p.savings_note}
                      </div>
                    )}
                    <ul style={{ margin: "12px 0", padding: 0, listStyle: "none", fontSize: "0.85rem" }}>
                      {p.features.map((f) => (
                        <li key={f} style={{ padding: "4px 0", display: "flex", gap: 8 }}>
                          <span style={{ color: "var(--teal)" }}>✓</span> {f}
                        </li>
                      ))}
                    </ul>
                    {p.is_current ? (
                      <div style={{ textAlign: "center", padding: "10px 0", color: "var(--teal)", fontWeight: 700, fontSize: "0.85rem" }}>
                        Current plan
                      </div>
                    ) : (
                      <button
                        className="hm-article-btn"
                        style={{ width: "100%" }}
                        onClick={() => switchPlan(p)}
                        disabled={isPending}
                      >
                        {isActive ? "Switch to this plan" : "Start plan"}
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </>
          )}

          {/* Invoices */}
          <h2 className="hm-section-title" style={{ marginTop: 32 }}>Billing history</h2>
          <div className="hm-panel">
            {invoices.length === 0 && <p className="hm-empty">No invoices yet.</p>}
            {invoices.length > 0 && (
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "0.9rem" }}>
                <thead>
                  <tr style={{ textAlign: "left", color: "var(--muted)", fontSize: "0.75rem", textTransform: "uppercase" }}>
                    <th style={{ padding: "10px 0" }}>Date</th>
                    <th>Invoice</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {invoices.map((inv) => (
                    <tr key={inv.public_id} style={{ borderTop: "1px solid var(--line)" }}>
                      <td style={{ padding: "12px 0" }}>{new Date(inv.paid_at ?? inv.created_at).toLocaleDateString()}</td>
                      <td>{inv.invoice_number ?? inv.public_id.slice(0, 8)}</td>
                      <td>${inv.amount} {inv.currency}</td>
                      <td>
                        <span className={`admin-badge ${inv.status === "paid" ? "admin-badge-success" : "admin-badge-pending"}`}>
                          {inv.status}
                        </span>
                      </td>
                      <td style={{ textAlign: "right" }}>
                        {inv.pdf_url && (
                          <a href={inv.pdf_url} target="_blank" rel="noreferrer" className="su-link">Download PDF</a>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </section>
      </main>

      {showCancelModal && (
        <div className="admin-modal-overlay" onClick={() => setShowCancelModal(false)}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()}>
            <h3 style={{ marginTop: 0 }}>Cancel your subscription?</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.9rem", lineHeight: 1.6 }}>
              Your subscription will remain active until{" "}
              <strong>{sub?.current_period_end ? new Date(sub.current_period_end).toLocaleDateString() : "the end of the period"}</strong>.
              After that, you&apos;ll lose access to stories and your donations will pause.
            </p>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem", lineHeight: 1.6 }}>
              You can resume anytime before then.
            </p>
            <div style={{ display: "flex", justifyContent: "flex-end", gap: 8, marginTop: 20 }}>
              <button className="hm-pager-btn" onClick={() => setShowCancelModal(false)}>Never mind</button>
              <button className="hm-article-btn" onClick={cancelSubscription} disabled={isPending}>
                {isPending ? "Canceling…" : "Yes, cancel"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
