"use client";

import { FormEvent, useState, useTransition, useRef } from "react";

type Step = "personal" | "organization" | "documents" | "verify" | "pending";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

const initialPersonal = {
  full_name: "",
  email: "",
  phone: "",
  password: "",
  password_confirmation: "",
};

const initialOrg = {
  organization_name: "",
  website: "",
  landline: "",
  tax_id: "",
};

const STEPS: { key: Step; label: string }[] = [
  { key: "personal",     label: "Personal"     },
  { key: "organization", label: "Organization" },
  { key: "documents",    label: "Documents"    },
  { key: "verify",       label: "Verify"       },
  { key: "pending",      label: "Review"       },
];

export default function OrganizerSignupPage() {
  const [step, setStep]                   = useState<Step>("personal");
  const [personal, setPersonal]           = useState(initialPersonal);
  const [org, setOrg]                     = useState(initialOrg);
  const [certificateFile, setCertFile]    = useState<File | null>(null);
  const [otp, setOtp]                     = useState("");
  const [otpExpiresAt, setOtpExpiresAt]   = useState("");
  const [publicId, setPublicId]           = useState("");
  const [statusMsg, setStatusMsg]         = useState("");
  const [errorMsg, setErrorMsg]           = useState("");
  const [isPending, startTransition]      = useTransition();
  const fileInputRef                      = useRef<HTMLInputElement>(null);

  const stepIndex = STEPS.findIndex((s) => s.key === step);

  function pField(key: keyof typeof initialPersonal) {
    return (v: string) => setPersonal((f) => ({ ...f, [key]: v }));
  }
  function oField(key: keyof typeof initialOrg) {
    return (v: string) => setOrg((f) => ({ ...f, [key]: v }));
  }
  function reset() { setErrorMsg(""); setStatusMsg(""); }

  function handlePersonalSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (personal.password !== personal.password_confirmation) {
      setErrorMsg("Passwords do not match.");
      return;
    }
    reset();
    setStep("organization");
  }

  function handleOrgSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    setStep("documents");
  }

  function handleDocumentSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!certificateFile) { setErrorMsg("Please upload your registration certificate."); return; }
    reset();
    startTransition(async () => {
      try {
        const fd = new FormData();
        fd.append("full_name",              personal.full_name);
        fd.append("email",                  personal.email);
        fd.append("phone",                  personal.phone);
        fd.append("password",               personal.password);
        fd.append("password_confirmation",  personal.password_confirmation);
        fd.append("organization_name",      org.organization_name);
        if (org.website)  fd.append("website",  org.website);
        if (org.landline) fd.append("landline", org.landline);
        fd.append("tax_id",          org.tax_id);
        fd.append("certificate_pdf", certificateFile);

        const res  = await fetch(`${API_BASE_URL}/auth/organization/register`, {
          method: "POST",
          headers: { Accept: "application/json" },
          body: fd,
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMsg(data.message);
        setOtpExpiresAt(data.data?.otp_expires_at ?? "");
        setPublicId(data.data?.user?.public_id ?? "");
        setStep("verify");
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handleVerify(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    reset();
    startTransition(async () => {
      try {
        const res  = await fetch(`${API_BASE_URL}/auth/verify-otp`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email: personal.email, otp }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMsg(data.message);
        setStep("pending");
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handleResendOtp() {
    reset();
    startTransition(async () => {
      try {
        const res  = await fetch(`${API_BASE_URL}/auth/resend-otp`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ email: personal.email }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(readError(data));
        setStatusMsg(data.message);
        setOtpExpiresAt(data.data?.otp_expires_at ?? "");
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  return (
    <main className="su-shell" suppressHydrationWarning>
      <div className="narlit-backdrop narlit-backdrop-one" suppressHydrationWarning />
      <div className="narlit-backdrop narlit-backdrop-two" suppressHydrationWarning />

      <div className="su-card org-card" suppressHydrationWarning>

        {/* ── Brand ── */}
        <div className="su-brand" suppressHydrationWarning>
          <img src="/logo.png" alt="NarLit" className="su-logo" />
          <div suppressHydrationWarning>
            <h1 className="su-title">
              <span style={{ color: "var(--orange)" }}>NAR</span>
              <span style={{ color: "var(--teal)" }}>LIT</span>
            </h1>
            <p className="narlit-eyebrow" style={{ marginTop: 4 }}>Organization registration</p>
          </div>
        </div>

        {/* ── Step Progress Bar ── */}
        <div className="org-progress" suppressHydrationWarning>
          {STEPS.map((s, i) => (
            <div
              key={s.key}
              suppressHydrationWarning
              className={`org-progress-item ${i < stepIndex ? "org-item-done" : ""}`}
            >
              <div suppressHydrationWarning className={`org-progress-circle ${i < stepIndex ? "org-circle-done" : ""} ${i === stepIndex ? "org-circle-active" : ""}`}>
                {i < stepIndex ? "✓" : i + 1}
              </div>
              <span className={`org-progress-label ${i === stepIndex ? "org-label-active" : ""}`}>
                {s.label}
              </span>
            </div>
          ))}
        </div>

        {/* ── Feedback ── */}
        {statusMsg && <p className="narlit-feedback narlit-feedback-success">{statusMsg}</p>}
        {errorMsg  && <p className="narlit-feedback narlit-feedback-error">{errorMsg}</p>}

        {/* ── Step 1: Personal ── */}
        {step === "personal" && (
          <form className="su-form" onSubmit={handlePersonalSubmit}>
            <div className="org-step-header" suppressHydrationWarning>
              <span className="org-step-badge">Step 1 of 5</span>
              <h3 className="org-step-title">Personal Information</h3>
            </div>
            <div className="su-grid" suppressHydrationWarning>
              <Field label="Full name"        value={personal.full_name}             onChange={pField("full_name")}             required />
              <Field label="Email"            type="email" value={personal.email}   onChange={pField("email")}                 required />
              <Field label="Phone"            value={personal.phone}                onChange={pField("phone")}   placeholder="+1234567890" required />
              <Field label="Password"         type="password" value={personal.password} onChange={pField("password")}          required />
              <Field label="Confirm password" type="password" value={personal.password_confirmation} onChange={pField("password_confirmation")} required />
            </div>
            <button className="narlit-button narlit-button-primary">Continue →</button>
            <p className="su-footer">
              Already have an account?{" "}
              <a href="/login" className="su-link">Sign in</a>
            </p>
          </form>
        )}

        {/* ── Step 2: Organization ── */}
        {step === "organization" && (
          <form className="su-form" onSubmit={handleOrgSubmit}>
            <div className="org-step-header" suppressHydrationWarning>
              <span className="org-step-badge">Step 2 of 5</span>
              <h3 className="org-step-title">Organization Details</h3>
            </div>
            <div className="su-grid" suppressHydrationWarning>
              <Field label="Organization name" value={org.organization_name} onChange={oField("organization_name")} required />
              <Field label="Tax ID"            value={org.tax_id}            onChange={oField("tax_id")}  placeholder="12-3456789" required />
              <Field label="Website"           type="url" value={org.website} onChange={oField("website")} placeholder="https://yourorg.com" />
              <Field label="Landline"          value={org.landline}          onChange={oField("landline")} placeholder="+1 555 000 0000" />
            </div>
            <div className="su-actions" suppressHydrationWarning>
              <button type="button" className="narlit-button narlit-button-secondary"
                onClick={() => { reset(); setStep("personal"); }}>← Back</button>
              <button className="narlit-button narlit-button-primary">Continue →</button>
            </div>
          </form>
        )}

        {/* ── Step 3: Documents ── */}
        {step === "documents" && (
          <form className="su-form" onSubmit={handleDocumentSubmit}>
            <div className="org-step-header" suppressHydrationWarning>
              <span className="org-step-badge">Step 3 of 5</span>
              <h3 className="org-step-title">Upload Certificate</h3>
            </div>

            <div className="org-upload-area" suppressHydrationWarning onClick={() => fileInputRef.current?.click()}>
              {certificateFile ? (
                <div className="org-upload-selected" suppressHydrationWarning>
                  <span className="org-upload-icon">📄</span>
                  <span className="org-upload-filename">{certificateFile.name}</span>
                  <span className="org-upload-size">{(certificateFile.size / 1024).toFixed(1)} KB</span>
                </div>
              ) : (
                <div className="org-upload-placeholder" suppressHydrationWarning>
                  <span className="org-upload-icon">📁</span>
                  <span className="org-upload-text">Click to upload registration certificate</span>
                  <span className="org-upload-hint">PDF, JPG, PNG — max 10 MB</span>
                </div>
              )}
              <input
                ref={fileInputRef}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="org-upload-input"
                onChange={(e) => {
                  const file = e.target.files?.[0] ?? null;
                  setCertFile(file);
                  if (file) setErrorMsg("");
                }}
              />
            </div>

            <div className="org-info-note" suppressHydrationWarning>
              <span>ℹ️</span>
              <p>Our team will review your details within 2–3 business days. You'll receive an email once approved.</p>
            </div>

            <div className="su-actions" suppressHydrationWarning>
              <button type="button" className="narlit-button narlit-button-secondary"
                onClick={() => { reset(); setStep("organization"); }}>← Back</button>
              <button className="narlit-button narlit-button-primary" disabled={isPending}>
                {isPending ? "Submitting…" : "Submit application"}
              </button>
            </div>
          </form>
        )}

        {/* ── Step 4: Verify ── */}
        {step === "verify" && (
          <form className="su-form" onSubmit={handleVerify}>
            <div className="org-step-header" suppressHydrationWarning>
              <span className="org-step-badge">Step 4 of 5</span>
              <h3 className="org-step-title">Verify Email</h3>
            </div>
            <div className="su-info-box" suppressHydrationWarning>
              {publicId      && <Row label="Account ID"   value={publicId} />}
              <Row label="Email"        value={personal.email} />
              {otpExpiresAt  && <Row label="Code expires" value={formatDate(otpExpiresAt)} />}
            </div>
            <Field
              label="Verification code"
              value={otp}
              onChange={setOtp}
              inputMode="numeric"
              placeholder="6-digit code"
              required
            />
            <div className="su-actions" suppressHydrationWarning>
              <button className="narlit-button narlit-button-primary" disabled={isPending}>
                {isPending ? "Verifying…" : "Verify email"}
              </button>
              <button type="button" className="narlit-button narlit-button-secondary"
                disabled={isPending} onClick={handleResendOtp}>
                Resend code
              </button>
            </div>
          </form>
        )}

        {/* ── Step 5: Pending ── */}
        {step === "pending" && (
          <div className="su-complete">
            <span className="org-pending-icon">⏳</span>
            <span className="su-badge" style={{ background: "rgba(255,160,0,0.15)", color: "var(--orange)" }}>
              Under Review
            </span>
            <h2>Application submitted!</h2>
            <p>
              Email verified. Our team will review your organization details and documents within{" "}
              <strong>2–3 business days</strong>.
            </p>
            <p style={{ fontSize: "0.85rem", color: "var(--muted)", marginTop: 8 }}>
              Approval email will be sent to <strong>{personal.email}</strong>.
            </p>
            <a href="/login" className="narlit-button narlit-button-secondary"
              style={{ marginTop: 16, display: "inline-block", textAlign: "center" }}>
              Back to login
            </a>
          </div>
        )}
      </div>
    </main>
  );
}

/* ── Field ── */
function Field({
  label, value, onChange, type = "text", inputMode, placeholder, required,
}: {
  label: string; value: string; onChange: (v: string) => void;
  type?: string; inputMode?: React.InputHTMLAttributes<HTMLInputElement>["inputMode"];
  placeholder?: string; required?: boolean;
}) {
  return (
    <label className="narlit-field">
      <span>{label}</span>
      <input
        type={type} value={value} inputMode={inputMode}
        placeholder={placeholder} required={required}
        onChange={(e) => onChange(e.target.value)}
      />
    </label>
  );
}

/* ── Row ── */
function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="su-row">
      <span className="su-row-label">{label}</span>
      <span className="su-row-value">{value}</span>
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
