"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { BrandLoader } from "@/components/BrandLoader";

interface User {
  public_id?: string;
  full_name: string;
  username: string | null;
  email: string;
  phone: string | null;
  country: string | null;
  role?: string;
}

type Tab = "profile" | "password";

export default function ProfilePage() {
  const [checking, setChecking] = useState(true);
  const [tab, setTab] = useState<Tab>("profile");
  const [user, setUser] = useState<User | null>(null);

  const [fullName, setFullName] = useState("");
  const [username, setUsername] = useState("");
  const [phone, setPhone] = useState("");
  const [country, setCountry] = useState("");

  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      try {
        const res = await apiFetch("/auth/me");
        const data = await res.json();
        const u: User = data.data?.user ?? data.data;
        setUser(u);
        setFullName(u?.full_name ?? "");
        setUsername(u?.username ?? "");
        setPhone(u?.phone ?? "");
        setCountry(u?.country ?? "");
      } catch {
        /* ignore */
      }
      setChecking(false);
    });
  }, []);

  function handleProfileSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(""); setSuccess("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/auth/profile", {
          method: "PATCH",
          body: JSON.stringify({
            full_name: fullName,
            username: username || null,
            phone: phone || null,
            country: country || null,
          }),
        });
        const payload = await res.json();
        if (!res.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Failed to update profile."
          );
        }
        setUser(payload.data?.user ?? user);
        setSuccess("Profile updated.");
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  function handlePasswordChange(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(""); setSuccess("");

    if (newPassword !== confirmPassword) {
      setError("New passwords do not match.");
      return;
    }
    if (newPassword.length < 8) {
      setError("New password must be at least 8 characters.");
      return;
    }

    startTransition(async () => {
      try {
        const res = await apiFetch("/auth/password", {
          method: "PATCH",
          body: JSON.stringify({
            current_password: currentPassword,
            password: newPassword,
            password_confirmation: confirmPassword,
          }),
        });
        const payload = await res.json();
        if (!res.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Failed to change password."
          );
        }
        setSuccess("Password changed successfully.");
        setCurrentPassword("");
        setNewPassword("");
        setConfirmPassword("");
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  async function handleLogout() {
    try { await apiFetch("/auth/logout", { method: "POST" }); } catch { /* noop */ }
    clearToken();
    window.location.href = "/login";
  }

  if (checking) {
    return (
      <BrandLoader />
    );
  }

  const isOrg = user?.role === "organization" || user?.role === "organizer";
  const homeHref = isOrg ? "/organization/dashboard" : "/dashboard";

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <nav className="hm-nav">
        <div className="hm-nav-inner">
          <a href={homeHref} className="hm-nav-brand">
            <span className="hm-nav-mark">
              <span className="hm-nm-orange" />
              <span className="hm-nm-teal" />
            </span>
            <span className="hm-nav-wordmark">NarLit</span>
          </a>
          <div className="hm-nav-links">
            <a href={homeHref} className="hm-nav-link">← Back</a>
          </div>
          <div className="hm-nav-user" />
        </div>
      </nav>

      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 720, margin: "0 auto" }}>
          <h2 className="hm-section-title">Account settings</h2>
          <p className="hm-panel-sub" style={{ marginTop: 4 }}>
            {user?.email}
          </p>

          <div className="admin-tabs" style={{ marginTop: 20 }}>
            <button
              type="button"
              className={`admin-tab ${tab === "profile" ? "admin-tab-active" : ""}`}
              onClick={() => { setTab("profile"); setError(""); setSuccess(""); }}
            >
              Profile
            </button>
            <button
              type="button"
              className={`admin-tab ${tab === "password" ? "admin-tab-active" : ""}`}
              onClick={() => { setTab("password"); setError(""); setSuccess(""); }}
            >
              Password
            </button>
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}
          {success && <p className="narlit-feedback narlit-feedback-success">{success}</p>}

          {tab === "profile" && (
            <form className="login-form" onSubmit={handleProfileSave} style={{ marginTop: 16 }}>
              <label className="narlit-field">
                <span>Full name</span>
                <input
                  type="text"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  required
                />
              </label>

              <label className="narlit-field">
                <span>Username</span>
                <input
                  type="text"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Optional handle"
                />
              </label>

              <label className="narlit-field">
                <span>Email</span>
                <input type="email" value={user?.email ?? ""} disabled />
              </label>

              <label className="narlit-field">
                <span>Phone</span>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+1 555 123 4567"
                />
              </label>

              <label className="narlit-field">
                <span>Country</span>
                <input
                  type="text"
                  value={country}
                  onChange={(e) => setCountry(e.target.value)}
                  placeholder="e.g. US"
                  maxLength={2}
                />
              </label>

              <button
                type="submit"
                className="narlit-button narlit-button-primary"
                disabled={isPending}
              >
                {isPending ? "Saving…" : "Save changes"}
              </button>
            </form>
          )}

          {tab === "password" && (
            <form className="login-form" onSubmit={handlePasswordChange} style={{ marginTop: 16 }}>
              <label className="narlit-field">
                <span>Current password</span>
                <input
                  type="password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  autoComplete="current-password"
                  required
                />
              </label>

              <label className="narlit-field">
                <span>New password</span>
                <input
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  autoComplete="new-password"
                  minLength={8}
                  required
                />
              </label>

              <label className="narlit-field">
                <span>Confirm new password</span>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  autoComplete="new-password"
                  minLength={8}
                  required
                />
              </label>

              <button
                type="submit"
                className="narlit-button narlit-button-primary"
                disabled={isPending}
              >
                {isPending ? "Updating…" : "Change password"}
              </button>
            </form>
          )}

          <div style={{ marginTop: 32, paddingTop: 24, borderTop: "1px solid var(--line)" }}>
            <button
              type="button"
              className="hm-pager-btn"
              onClick={handleLogout}
            >
              Sign out
            </button>
          </div>
        </section>
      </main>
    </div>
  );
}
