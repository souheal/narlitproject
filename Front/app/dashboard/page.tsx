"use client";

import { useEffect, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

export default function DashboardPage() {
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    validateSession().then((valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
      } else {
        setChecking(false);
      }
    });
  }, []);

  async function handleLogout() {
    try {
      await apiFetch("/auth/logout", { method: "POST" });
    } catch {
      // token already invalid — still clear and redirect
    }
    clearToken();
    window.location.href = "/login";
  }

  if (checking) {
    return (
      <main className="state-shell">
        <p style={{ color: "var(--muted)", fontWeight: 600 }}>Verifying session…</p>
      </main>
    );
  }

  return (
    <main className="state-shell">
      <section className="state-card">
        <div className="state-pill state-pill-success">Active session</div>
        <h1>Welcome to NarLit.</h1>
        <p>Your account is active and your subscription is running.</p>
        <div className="state-actions">
          <button
            className="landing-button landing-button-primary"
            onClick={handleLogout}
          >
            Sign out
          </button>
        </div>
      </section>
    </main>
  );
}
