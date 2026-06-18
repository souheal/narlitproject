"use client";

import { FormEvent, useState, useTransition } from "react";

type Step = "email" | "verify" | "reset" | "done";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

export default function ForgotPasswordPage() {
  const [step, setStep]           = useState<Step>("email");
  const [email, setEmail]         = useState("");
  const [otp, setOtp]             = useState("");
  const [password, setPassword]   = useState("");
  const [confirm, setConfirm]     = useState("");
  const [statusMsg, setStatusMsg] = useState("");
  const [errorMsg, setErrorMsg]   = useState("");
  const [isPending, startTransition] = useTransition();

  function reset() { setStatusMsg(""); setErrorMsg(""); }

  // Step 1 — send OTP to email
  function handleEmailSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    startTransition(async () => {
      try {
        const res  = await fetch(`${API_BASE_URL}/auth/forgot-password`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMsg(data.message ?? "Reset code sent. Check your email.");
        setStep("verify");
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  // Step 2 — verify OTP
  function handleVerifySubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    startTransition(async () => {
      try {
        const res  = await fetch(`${API_BASE_URL}/auth/verify-reset-otp`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email, otp }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMsg(data.message ?? "Code verified. Enter your new password.");
        setStep("reset");
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  // Step 3 — reset password
  function handleResetSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    if (password !== confirm) { setErrorMsg("Passwords do not match."); return; }
    startTransition(async () => {
      try {
        const res  = await fetch(`${API_BASE_URL}/auth/reset-password`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email, otp, password, password_confirmation: confirm }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStep("done");
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  const steps: { key: Step; label: string }[] = [
    { key: "email",  label: "Email"  },
    { key: "verify", label: "Verify" },
    { key: "reset",  label: "Reset"  },
    { key: "done",   label: "Done"   },
  ];
  const stepIndex = steps.findIndex((s) => s.key === step);

  return (
    <main className="su-shell" suppressHydrationWarning>
      <div className="narlit-backdrop narlit-backdrop-one" suppressHydrationWarning />
      <div className="narlit-backdrop narlit-backdrop-two" suppressHydrationWarning />

      <div className="su-card" suppressHydrationWarning>

        {/* Brand */}
        <div className="su-brand" suppressHydrationWarning>
          <img src="/logo.png" alt="NarLit" className="su-logo" />
          <div suppressHydrationWarning>
            <h1 className="su-title">
              <span style={{ color: "var(--orange)" }}>NAR</span>
              <span style={{ color: "var(--teal)" }}>LIT</span>
            </h1>
            <p className="narlit-eyebrow" style={{ marginTop: 4 }}>Reset password</p>
          </div>
        </div>

        {/* Steps */}
        <div className="su-steps" suppressHydrationWarning>
          {steps.map((s, i) => (
            <div
              key={s.key}
              suppressHydrationWarning
              className={`su-step ${i === stepIndex ? "su-step-active" : ""} ${i < stepIndex ? "su-step-done" : ""}`}
            >
              <span className="su-step-dot" />
              <span className="su-step-label">{s.label}</span>
            </div>
          ))}
        </div>

        {/* Feedback */}
        {statusMsg && <p className="narlit-feedback narlit-feedback-success">{statusMsg}</p>}
        {errorMsg  && <p className="narlit-feedback narlit-feedback-error">{errorMsg}</p>}

        {/* Step 1 — Email */}
        {step === "email" && (
          <form className="su-form" onSubmit={handleEmailSubmit}>
            <label className="narlit-field">
              <span>Email address</span>
              <input
                type="email"
                value={email}
                placeholder="you@example.com"
                autoComplete="email"
                required
                onChange={(e) => setEmail(e.target.value)}
              />
            </label>
            <button className="narlit-button narlit-button-primary" disabled={isPending}>
              {isPending ? "Sending…" : "Send reset code"}
            </button>
            <p className="su-footer">
              Remember your password?{" "}
              <a href="/login" className="su-link">Sign in</a>
            </p>
          </form>
        )}

        {/* Step 2 — Verify OTP */}
        {step === "verify" && (
          <form className="su-form" onSubmit={handleVerifySubmit}>
            <div className="su-info-box" suppressHydrationWarning>
              <div className="su-row">
                <span className="su-row-label">Email</span>
                <span className="su-row-value">{email}</span>
              </div>
            </div>
            <label className="narlit-field">
              <span>Verification code</span>
              <input
                type="text"
                inputMode="numeric"
                value={otp}
                placeholder="6-digit code"
                required
                onChange={(e) => setOtp(e.target.value)}
              />
            </label>
            <div className="su-actions" suppressHydrationWarning>
              <button className="narlit-button narlit-button-primary" disabled={isPending}>
                {isPending ? "Verifying…" : "Verify code"}
              </button>
              <button
                type="button"
                className="narlit-button narlit-button-secondary"
                disabled={isPending}
                onClick={() => { reset(); setStep("email"); }}
              >
                ← Back
              </button>
            </div>
          </form>
        )}

        {/* Step 3 — New Password */}
        {step === "reset" && (
          <form className="su-form" onSubmit={handleResetSubmit}>
            <label className="narlit-field">
              <span>New password</span>
              <input
                type="password"
                value={password}
                placeholder="••••••••"
                autoComplete="new-password"
                required
                onChange={(e) => setPassword(e.target.value)}
              />
            </label>
            <label className="narlit-field">
              <span>Confirm new password</span>
              <input
                type="password"
                value={confirm}
                placeholder="••••••••"
                autoComplete="new-password"
                required
                onChange={(e) => setConfirm(e.target.value)}
              />
            </label>
            <div className="su-actions" suppressHydrationWarning>
              <button className="narlit-button narlit-button-primary" disabled={isPending}>
                {isPending ? "Resetting…" : "Reset password"}
              </button>
              <button
                type="button"
                className="narlit-button narlit-button-secondary"
                disabled={isPending}
                onClick={() => { reset(); setStep("verify"); }}
              >
                ← Back
              </button>
            </div>
          </form>
        )}

        {/* Step 4 — Done */}
        {step === "done" && (
          <div className="su-complete" suppressHydrationWarning>
            <span className="su-badge">Password updated</span>
            <h2>All done!</h2>
            <p>Your password has been reset. You can now sign in with your new password.</p>
            <a
              href="/login"
              className="narlit-button narlit-button-primary"
              style={{ marginTop: 8, textAlign: "center" }}
            >
              Go to sign in
            </a>
          </div>
        )}

      </div>
    </main>
  );
}

function readError(payload: Record<string, unknown>) {
  if (typeof payload.message === "string") return payload.message;
  const errors = payload.errors as Record<string, string[]> | undefined;
  if (errors) return Object.values(errors).flat().join(" ");
  return "Something went wrong.";
}
