"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { BrandLoader } from "@/components/BrandLoader";
import BackLink from "@/components/BackLink";

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
  const displayName = user?.full_name || user?.username || user?.email || "";
  const initials =
    displayName
      .split(/[\s@._-]+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join("") || "?";
  const roleLabel = isOrg ? "Organization" : user?.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : null;

  function switchTab(next: Tab) {
    setTab(next);
    setError("");
    setSuccess("");
  }

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
          <div />
          <div className="hm-nav-user">
            <BackLink href={homeHref} className="hm-nav-link">← Back</BackLink>
          </div>
        </div>
      </nav>

      <main className="hm-main">
        <div className="profile-wrap">
          <header className="profile-header">
            <h1 className="profile-title">Account settings</h1>
            <p className="profile-subtitle">Manage your personal details and password.</p>
          </header>

          <section className="hm-panel profile-identity">
            <div className="profile-avatar" aria-hidden="true">{initials}</div>
            <div className="profile-identity-text">
              <div className="profile-identity-name">
                {user?.full_name || "Your account"}
                {roleLabel && <span className="profile-role">{roleLabel}</span>}
              </div>
              <div className="profile-identity-meta">
                {user?.username && <span>@{user.username}</span>}
                <span>{user?.email}</span>
              </div>
            </div>
            <button type="button" className="hm-pager-btn profile-signout" onClick={handleLogout}>
              Sign out
            </button>
          </section>

          <div className="admin-tabs profile-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              aria-selected={tab === "profile"}
              className={`admin-tab ${tab === "profile" ? "admin-tab-active" : ""}`}
              onClick={() => switchTab("profile")}
            >
              Profile
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={tab === "password"}
              className={`admin-tab ${tab === "password" ? "admin-tab-active" : ""}`}
              onClick={() => switchTab("password")}
            >
              Password
            </button>
          </div>

          <section className="hm-panel profile-card">
            {tab === "profile" && (
              <form onSubmit={handleProfileSave}>
                <div className="profile-card-head">
                  <h2 className="hm-panel-title">Personal information</h2>
                  <p className="hm-panel-sub">This is how you appear across NarLit.</p>
                </div>

                {error && <p className="narlit-feedback narlit-feedback-error profile-feedback">{error}</p>}
                {success && <p className="narlit-feedback narlit-feedback-success profile-feedback">{success}</p>}

                <div className="profile-grid">
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

                  <label className="narlit-field profile-span-2">
                    <span>Email</span>
                    <input type="email" value={user?.email ?? ""} disabled />
                    <small className="profile-hint">Your sign-in email can&apos;t be changed here.</small>
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
                      onChange={(e) => setCountry(e.target.value.toUpperCase())}
                      placeholder="e.g. US"
                      maxLength={2}
                    />
                    <small className="profile-hint">Two-letter country code.</small>
                  </label>
                </div>

                <div className="profile-actions">
                  <button
                    type="submit"
                    className="narlit-button narlit-button-primary"
                    disabled={isPending}
                  >
                    {isPending ? "Saving…" : "Save changes"}
                  </button>
                </div>
              </form>
            )}

            {tab === "password" && (
              <form onSubmit={handlePasswordChange}>
                <div className="profile-card-head">
                  <h2 className="hm-panel-title">Change password</h2>
                  <p className="hm-panel-sub">Use at least 8 characters.</p>
                </div>

                {error && <p className="narlit-feedback narlit-feedback-error profile-feedback">{error}</p>}
                {success && <p className="narlit-feedback narlit-feedback-success profile-feedback">{success}</p>}

                <div className="profile-grid">
                  <label className="narlit-field profile-span-2">
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
                </div>

                <div className="profile-actions">
                  <button
                    type="submit"
                    className="narlit-button narlit-button-primary"
                    disabled={isPending}
                  >
                    {isPending ? "Updating…" : "Change password"}
                  </button>
                </div>
              </form>
            )}
          </section>
        </div>
      </main>
    </div>
  );
}
