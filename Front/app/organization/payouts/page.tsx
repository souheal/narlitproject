"use client";

import { useEffect, useState, useTransition } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Payout {
  public_id: string;
  amount: string;
  currency: string;
  status: string;
  period_start: string;
  period_end: string;
  total_reads: number;
  paid_at: string | null;
  created_at: string;
}

interface ConnectStatus {
  connected: boolean;
  charges_enabled: boolean;
  payouts_enabled: boolean;
  details_submitted: boolean;
  requirements_due: string[];
}

interface PayoutsResponse {
  summary: {
    total_paid: string;
    pending_payout: string;
    next_payout_date: string | null;
    currency: string;
  };
  connect: ConnectStatus;
  payouts: {
    data: Payout[];
    current_page: number;
    last_page: number;
    total: number;
  };
}

const STATUS_LABEL: Record<string, string> = {
  pending: "Pending",
  processing: "Processing",
  paid: "Paid",
  failed: "Failed",
  on_hold: "On hold",
};

const STATUS_COLOR: Record<string, string> = {
  pending: "var(--orange)",
  processing: "var(--teal)",
  paid: "#4caf50",
  failed: "#e53935",
  on_hold: "#7c5cbf",
};

export default function OrgPayoutsPage() {
  const [data, setData] = useState<PayoutsResponse | null>(null);
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");
  const [status, setStatus] = useState("");
  const [isPending, startTransition] = useTransition();

  async function loadPayouts() {
    try {
      const res = await apiFetch("/organization/payouts");
      const payload = await res.json();
      if (!res.ok) {
        setError(payload?.message ?? "Failed to load payouts.");
      } else {
        setData(payload.data ?? null);
      }
    } catch {
      setError("Failed to load payouts.");
    }
  }

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      await loadPayouts();
      setChecking(false);
    });
  }, []);

  function handleStartOnboarding() {
    setError(""); setStatus("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/organization/stripe-connect/onboarding", {
          method: "POST",
        });
        const payload = await res.json();
        if (!res.ok) {
          throw new Error(payload?.message ?? "Failed to start onboarding.");
        }
        const url = payload.data?.onboarding_url;
        if (url) {
          window.location.href = url;
        } else {
          setStatus("Onboarding request received. Follow the Stripe email to continue.");
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  function handleRefreshConnect() {
    setError(""); setStatus("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/organization/stripe-connect/status");
        const payload = await res.json();
        if (!res.ok) throw new Error(payload?.message ?? "Failed to refresh.");
        await loadPayouts();
        setStatus("Connect status refreshed.");
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  if (checking) {
    return (
      <div className="hm-loading" suppressHydrationWarning>
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
        <span className="hm-loading-dot" />
      </div>
    );
  }

  const connect = data?.connect;
  const summary = data?.summary;
  const payouts = data?.payouts?.data ?? [];
  const currency = summary?.currency ?? "USD";

  const readyToReceive = connect?.charges_enabled && connect?.payouts_enabled;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <nav className="hm-nav">
        <div className="hm-nav-inner">
          <a href="/organization/dashboard" className="hm-nav-brand">
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span className="hm-nav-wordmark">NarLit · Org</span>
          </a>
          <div className="hm-nav-links">
            <a href="/organization/dashboard" className="hm-nav-link">Overview</a>
            <a href="/organization/articles" className="hm-nav-link">Articles</a>
            <a href="/organization/payouts" className="hm-nav-link hm-nav-link-active">Payouts</a>
          </div>
          <div className="hm-nav-user" />
        </div>
      </nav>

      <main className="hm-main">
        <section className="hm-section">
          <h2 className="hm-section-title">Payouts</h2>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}
          {status && <p className="narlit-feedback narlit-feedback-success">{status}</p>}

          {/* Stripe Connect status card */}
          <div className="hm-panel" style={{ marginTop: 16 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 16, flexWrap: "wrap" }}>
              <div>
                <h3 className="hm-panel-title">
                  Stripe Connect {readyToReceive ? "· Ready" : connect?.connected ? "· Action needed" : "· Not connected"}
                </h3>
                <p className="hm-panel-sub">
                  {readyToReceive
                    ? "Your account is verified — payouts will be sent to your bank automatically."
                    : connect?.connected
                      ? "Complete the remaining Stripe requirements to start receiving payouts."
                      : "Connect a Stripe account to receive your monthly donations."}
                </p>
                {connect?.requirements_due && connect.requirements_due.length > 0 && (
                  <ul style={{ marginTop: 10, paddingLeft: 18, fontSize: "0.85rem", color: "var(--muted)" }}>
                    {connect.requirements_due.slice(0, 5).map((req) => (
                      <li key={req}>{req.replaceAll("_", " ")}</li>
                    ))}
                  </ul>
                )}
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                {!readyToReceive && (
                  <button
                    type="button"
                    className="hm-article-btn"
                    onClick={handleStartOnboarding}
                    disabled={isPending}
                  >
                    {isPending ? "Loading…" : connect?.connected ? "Continue onboarding →" : "Connect Stripe →"}
                  </button>
                )}
                {connect?.connected && (
                  <button
                    type="button"
                    className="hm-pager-btn"
                    onClick={handleRefreshConnect}
                    disabled={isPending}
                  >
                    Refresh status
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Summary stats */}
          <div className="hm-stats-grid" style={{ marginTop: 20 }}>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-orange">$</div>
              <div className="hm-stat-value">${summary?.total_paid ?? "0.00"}</div>
              <div className="hm-stat-label">Total Paid</div>
              <div className="hm-stat-hint">All time · {currency}</div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-teal">⏳</div>
              <div className="hm-stat-value">${summary?.pending_payout ?? "0.00"}</div>
              <div className="hm-stat-label">Pending Payout</div>
              <div className="hm-stat-hint">
                {summary?.next_payout_date
                  ? `Next: ${new Date(summary.next_payout_date).toLocaleDateString()}`
                  : "Awaiting next cycle"}
              </div>
            </div>
            <div className="hm-stat-card">
              <div className="hm-stat-icon hm-stat-icon-purple">📊</div>
              <div className="hm-stat-value">{payouts.length}</div>
              <div className="hm-stat-label">Recent Payouts</div>
              <div className="hm-stat-hint">Last {payouts.length} cycles</div>
            </div>
          </div>

          {/* Payout history */}
          <h3 className="hm-section-title" style={{ marginTop: 32, fontSize: "1.1rem" }}>Payout history</h3>
          <div className="hm-articles" style={{ marginTop: 12 }}>
            {payouts.length === 0 && (
              <p className="hm-empty">No payouts yet. Your first payout arrives once articles get read.</p>
            )}
            {payouts.map((p) => (
              <article key={p.public_id} className="hm-article-card">
                <div className="hm-article-top">
                  <span
                    className="hm-article-org"
                    style={{ background: STATUS_COLOR[p.status] ?? "var(--teal)" }}
                  >
                    {STATUS_LABEL[p.status] ?? p.status}
                  </span>
                  <span className="hm-article-cat">
                    {new Date(p.period_start).toLocaleDateString()} — {new Date(p.period_end).toLocaleDateString()}
                  </span>
                </div>
                <h3 className="hm-article-title">
                  ${p.amount} {p.currency}
                </h3>
                <p className="hm-article-excerpt">
                  {p.total_reads} reads counted this period.
                </p>
                <div className="hm-article-footer">
                  <span className="hm-article-time">
                    {p.paid_at
                      ? `Paid ${new Date(p.paid_at).toLocaleDateString()}`
                      : `Created ${new Date(p.created_at).toLocaleDateString()}`}
                  </span>
                </div>
              </article>
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}
