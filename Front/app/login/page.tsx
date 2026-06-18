"use client";

import { FormEvent, useState, useTransition } from "react";
import { saveToken, saveAdminToken } from "@/lib/auth";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

type Step = "login" | "mfa";

export default function LoginPage() {
  const [step, setStep] = useState<Step>("login");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [mfaCode, setMfaCode] = useState("");
  const [mfaExpiresAt, setMfaExpiresAt] = useState("");
  const [error, setError] = useState("");
  const [status, setStatus] = useState("");
  const [isPending, startTransition] = useTransition();

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setStatus("");

    startTransition(async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ email, password }),
        });

        const payload = await response.json();

        if (!response.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Login failed."
          );
        }

        if (payload?.data?.next_step === "phone_mfa_required") {
          setMfaExpiresAt(payload.data.phone_mfa_expires_at ?? "");
          setStatus("A verification code was sent to your phone.");
          setStep("mfa");
          return;
        }

        if (payload?.data?.token) {
          const token = payload.data.token;

          // Check if admin
          const adminCheck = await fetch(`${API_BASE_URL}/admin/organizations?per_page=1`, {
            headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
          });

          if (adminCheck.ok) {
            saveAdminToken(token);
            window.location.href = "/admin/dashboard";
          } else {
            saveToken(token);
            window.location.href = "/dashboard";
          }
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  function handleMfa(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setStatus("");

    startTransition(async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/auth/verify-phone-mfa`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ email, code: mfaCode }),
        });

        const payload = await response.json();

        if (!response.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Verification failed."
          );
        }

        if (payload?.data?.token) {
          saveToken(payload.data.token);
        }
        window.location.href = "/dashboard";
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handleResendMfa() {
    setError("");
    setStatus("");
    startTransition(async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/auth/resend-phone-mfa`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ email }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload?.message || "Failed to resend.");
        setMfaExpiresAt(payload.data?.phone_mfa_expires_at ?? "");
        setStatus("A new code was sent to your phone.");
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  return (
    <main className="login-shell" suppressHydrationWarning>
      <div className="narlit-backdrop narlit-backdrop-one" suppressHydrationWarning />
      <div className="narlit-backdrop narlit-backdrop-two" suppressHydrationWarning />

      <div className="login-card" suppressHydrationWarning>
        <div className="login-brand" suppressHydrationWarning>
          <img src="/logo.png" alt="NarLit" className="su-logo" />
          <div suppressHydrationWarning>
            <h1 className="login-title">
              <span style={{ color: "var(--orange)" }}>NAR</span>
              <span style={{ color: "var(--teal)" }}>LIT</span>
            </h1>
            <p className="narlit-eyebrow" style={{ marginTop: 4 }}>
              {step === "login" ? "Welcome back" : "Phone verification"}
            </p>
          </div>
        </div>

        {error  && <p className="narlit-feedback narlit-feedback-error">{error}</p>}
        {status && <p className="narlit-feedback narlit-feedback-success">{status}</p>}

        {step === "login" && (
          <form className="login-form" onSubmit={handleSubmit}>
            <label className="narlit-field">
              <span>Email</span>
              <input
                type="email"
                value={email}
                autoComplete="email"
                placeholder="you@example.com"
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </label>

            <label className="narlit-field">
              <span>Password</span>
              <input
                type="password"
                value={password}
                autoComplete="current-password"
                placeholder="••••••••"
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </label>

            <button
              className="narlit-button narlit-button-primary"
              type="submit"
              disabled={isPending}
            >
              {isPending ? "Signing in..." : "Sign in"}
            </button>
            <p className="su-footer">
              <a href="/forgot-password" className="su-link">Forgot password?</a>
            </p>
          </form>
        )}

        {step === "mfa" && (
          <form className="login-form" onSubmit={handleMfa}>
            {mfaExpiresAt && (
              <div className="su-info-box" suppressHydrationWarning>
                <div className="su-row">
                  <span className="su-row-label">Code expires</span>
                  <span className="su-row-value">{formatDate(mfaExpiresAt)}</span>
                </div>
              </div>
            )}

            <label className="narlit-field">
              <span>Verification code</span>
              <input
                type="text"
                inputMode="numeric"
                value={mfaCode}
                placeholder="6-digit code"
                onChange={(e) => setMfaCode(e.target.value)}
                required
              />
            </label>

            <div className="su-actions" suppressHydrationWarning>
              <button
                className="narlit-button narlit-button-primary"
                type="submit"
                disabled={isPending}
              >
                {isPending ? "Verifying..." : "Verify"}
              </button>
              <button
                type="button"
                className="narlit-button narlit-button-secondary"
                disabled={isPending}
                onClick={handleResendMfa}
              >
                Resend code
              </button>
            </div>
          </form>
        )}

        {step === "login" && (
          <div className="login-signup-section" suppressHydrationWarning>
            <p className="login-footer">Don&apos;t have an account?</p>
            <div className="login-signup-buttons" suppressHydrationWarning>
              <a href="/signup" className="login-signup-btn login-signup-btn-user">
                <span className="login-signup-icon">👤</span>
                User
              </a>
              <a href="/signup/organizer" className="login-signup-btn login-signup-btn-organizer">
                <span className="login-signup-icon">🎪</span>
                Organization
              </a>
            </div>
          </div>
        )}
      </div>
    </main>
  );
}

function formatDate(value: string) {
  if (!value) return "—";
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  return new Intl.DateTimeFormat("en-US", { dateStyle: "medium", timeStyle: "short" }).format(d);
}
