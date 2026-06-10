"use client";

import { useEffect } from "react";

export default function DashboardPage() {
  useEffect(() => {
    if (!localStorage.getItem("auth_token")) {
      window.location.href = "/login";
    }
  }, []);

  function handleLogout() {
    localStorage.removeItem("auth_token");
    window.location.href = "/login";
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
