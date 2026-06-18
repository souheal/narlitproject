"use client";

import { FormEvent, useState, useTransition } from "react";

type Plan = "monthly" | "yearly";
type Step = "register" | "verify" | "payment" | "complete";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

const initialForm = {
  full_name: "",
  username: "",
  email: "",
  phone: "",
  password: "",
  password_confirmation: "",
  subscription_plan: "monthly" as Plan,
};

export default function SignupPage() {
  const [step, setStep] = useState<Step>("register");
  const [form, setForm] = useState(initialForm);
  const [otp, setOtp] = useState("");
  const [otpExpiresAt, setOtpExpiresAt] = useState("");
  const [emailVerifiedAt, setEmailVerifiedAt] = useState("");
  const [publicId, setPublicId] = useState("");
  const [statusMessage, setStatusMessage] = useState("");
  const [errorMessage, setErrorMessage] = useState("");
  const [isPending, startTransition] = useTransition();

  function field(key: keyof typeof initialForm) {
    return (value: string) => setForm((f) => ({ ...f, [key]: value }));
  }

  function reset() {
    setErrorMessage("");
    setStatusMessage("");
  }

  async function handleRegister(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    startTransition(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/auth/register`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify(form),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMessage(data.message);
        setOtpExpiresAt(data.data.otp_expires_at);
        setPublicId(data.data.user.public_id);
        setStep("verify");
      } catch (err) {
        setErrorMessage(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handleVerify(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    startTransition(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/auth/verify-otp`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email: form.email, otp }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMessage(data.message);
        setEmailVerifiedAt(data.data.user.email_verified_at);
        setStep("payment");
      } catch (err) {
        setErrorMessage(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handlePayment() {
    reset();
    startTransition(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/billing/checkout`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email: form.email, subscription_plan: form.subscription_plan }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMessage(data.message);
        if (data.data.checkout_url) {
          window.location.href = data.data.checkout_url;
          return;
        }
        setStep("complete");
        setTimeout(() => { window.location.href = "/login"; }, 2000);
      } catch (err) {
        setErrorMessage(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handleResendOtp() {
    reset();
    startTransition(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/auth/resend-otp`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email: form.email }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMessage(data.message);
        setOtpExpiresAt(data.data.otp_expires_at);
      } catch (err) {
        setErrorMessage(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  const steps: { key: Step; label: string }[] = [
    { key: "register", label: "Account" },
    { key: "verify", label: "Verify" },
    { key: "payment", label: "Payment" },
    { key: "complete", label: "Done" },
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
            <p className="narlit-eyebrow" style={{ marginTop: 4 }}>Create account</p>
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
        {statusMessage && <p className="narlit-feedback narlit-feedback-success">{statusMessage}</p>}
        {errorMessage && <p className="narlit-feedback narlit-feedback-error">{errorMessage}</p>}

        {/* Step: Register */}
        {step === "register" && (
          <form className="su-form" onSubmit={handleRegister}>
            <div className="su-grid" suppressHydrationWarning>
              <Field label="Full name" value={form.full_name} onChange={field("full_name")} required />
              <Field label="Username" value={form.username} onChange={field("username")} required />
              <Field label="Email" type="email" value={form.email} onChange={field("email")} required />
              <Field label="Phone" value={form.phone} onChange={field("phone")} required />
              <Field label="Password" type="password" value={form.password} onChange={field("password")} required />
              <Field label="Confirm password" type="password" value={form.password_confirmation} onChange={field("password_confirmation")} required />
            </div>

            <div className="su-plans" suppressHydrationWarning>
              <PlanOption
                title="Monthly"
                price="$7 / mo"
                active={form.subscription_plan === "monthly"}
                onClick={() => field("subscription_plan")("monthly")}
              />
              <PlanOption
                title="Yearly"
                price="$96 / yr"
                note="Save 20%"
                active={form.subscription_plan === "yearly"}
                onClick={() => field("subscription_plan")("yearly")}
              />
            </div>

            <button className="narlit-button narlit-button-primary" disabled={isPending}>
              {isPending ? "Creating account…" : "Continue"}
            </button>

            <p className="su-footer">
              Already have an account? <a href="/login" className="su-link">Sign in</a>
            </p>
          </form>
        )}

        {/* Step: Verify */}
        {step === "verify" && (
          <form className="su-form" onSubmit={handleVerify}>
            <div className="su-info-box">
              <Row label="Account ID" value={publicId} />
              <Row label="Email" value={form.email} />
              <Row label="OTP expires" value={formatDate(otpExpiresAt)} />
            </div>

            <Field
              label="Verification code"
              value={otp}
              onChange={setOtp}
              inputMode="numeric"
              placeholder="6-digit code"
            />

            <div className="su-actions">
              <button className="narlit-button narlit-button-primary" disabled={isPending}>
                {isPending ? "Verifying…" : "Verify email"}
              </button>
              <button
                type="button"
                className="narlit-button narlit-button-secondary"
                disabled={isPending}
                onClick={handleResendOtp}
              >
                Resend OTP
              </button>
            </div>
          </form>
        )}

        {/* Step: Payment */}
        {step === "payment" && (
          <div className="su-form">
            <div className="su-info-box">
              <Row label="Email" value={form.email} />
              <Row label="Verified at" value={formatDate(emailVerifiedAt)} />
              <Row label="Plan" value={form.subscription_plan === "monthly" ? "Monthly — $7" : "Yearly — $96"} />
            </div>

            <button
              className="narlit-button narlit-button-primary"
              disabled={isPending}
              onClick={handlePayment}
            >
              {isPending ? "Preparing…" : "Open Stripe checkout"}
            </button>
          </div>
        )}

        {/* Step: Complete */}
        {step === "complete" && (
          <div className="su-complete">
            <span className="su-badge">Account activated</span>
            <h2>You&apos;re all set.</h2>
            <p>Your account is verified and your membership is active.</p>
          </div>
        )}
      </div>
    </main>
  );
}

function Field({
  label,
  value,
  onChange,
  type = "text",
  inputMode,
  placeholder,
  required,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  inputMode?: React.InputHTMLAttributes<HTMLInputElement>["inputMode"];
  placeholder?: string;
  required?: boolean;
}) {
  return (
    <label className="narlit-field">
      <span>{label}</span>
      <input
        type={type}
        value={value}
        inputMode={inputMode}
        placeholder={placeholder}
        required={required}
        onChange={(e) => onChange(e.target.value)}
      />
    </label>
  );
}

function PlanOption({
  title,
  price,
  note,
  active,
  onClick,
}: {
  title: string;
  price: string;
  note?: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button type="button" onClick={onClick} className={`su-plan ${active ? "su-plan-active" : ""}`}>
      <span className="su-plan-title">{title}</span>
      <span className="su-plan-price">{price}</span>
      {note && <span className="su-plan-note">{note}</span>}
    </button>
  );
}

function Row({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <div className="su-row">
      <span className="su-row-label">{label}</span>
      <span className={highlight ? "su-row-highlight" : "su-row-value"}>{value}</span>
    </div>
  );
}

function readError(payload: Record<string, unknown>) {
  if (typeof payload.message === "string") return payload.message;
  const errors = payload.errors as Record<string, string[]> | undefined;
  if (errors) return Object.values(errors).flat().join(" ");
  return "Something went wrong.";
}

function formatDate(value: string) {
  if (!value) return "—";
  const d = new Date(value);
  if (isNaN(d.getTime())) return value;
  return new Intl.DateTimeFormat("en-US", { dateStyle: "medium", timeStyle: "short" }).format(d);
}
