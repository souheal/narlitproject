"use client";

import { useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface User {
  full_name: string;
  email: string;
}

export default function AccountPage() {
  const [user, setUser] = useState<User | null>(null);
  const [checking, setChecking] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [confirmDeleteText, setConfirmDeleteText] = useState("");
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [password, setPassword] = useState("");
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) { clearToken(); window.location.href = "/login"; return; }
      try {
        const meRes = await apiFetch("/auth/me");
        const meData = await meRes.json();
        setUser(meData.data?.user ?? meData.data ?? null);
      } catch { /* ignore */ }
      setChecking(false);
    });
  }, []);

  function requestExport() {
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/account/export", { method: "POST" });
        const data = await res.json();
        if (!res.ok) { setError(data?.message ?? "Failed to request export."); return; }
        setFeedback("Export requested — we'll email you a download link within 24 hours.");
      } catch { setError("Failed to request export."); }
    });
  }

  function downloadNow() {
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/account/download");
        if (!res.ok) {
          const data = await res.json();
          setError(data?.message ?? "Failed to download.");
          return;
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `narlit-data-${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
      } catch { setError("Failed to download."); }
    });
  }

  function deleteAccount() {
    if (confirmDeleteText !== "DELETE") {
      setError("Please type DELETE to confirm.");
      return;
    }
    if (!password) {
      setError("Enter your current password to confirm.");
      return;
    }
    setError(""); setFeedback("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/account", {
          method: "DELETE",
          body: JSON.stringify({ password }),
        });
        const data = await res.json();
        if (!res.ok) { setError(data?.message ?? "Failed to delete account."); return; }
        clearToken();
        window.location.href = "/?deleted=1";
      } catch { setError("Failed to delete account."); }
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

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 720, margin: "0 auto" }}>
          <h2 className="hm-section-title">Account & data</h2>
          <p className="hm-panel-sub" style={{ marginTop: 4 }}>
            Manage what happens to your data. These actions affect only your NarLit account.
          </p>

          {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

          {/* Data export */}
          <div className="hm-panel" style={{ marginTop: 24, padding: 24 }}>
            <h3 className="hm-panel-title">Export your data</h3>
            <p className="hm-panel-sub" style={{ marginTop: 6, lineHeight: 1.6 }}>
              Download a copy of everything NarLit stores about you: profile, subscription history, articles read,
              donations, and bookmarks. Delivered as a JSON file.
            </p>
            <div style={{ display: "flex", gap: 8, marginTop: 16, flexWrap: "wrap" }}>
              <button className="hm-article-btn" onClick={downloadNow} disabled={isPending}>
                Download now
              </button>
              <button className="hm-pager-btn" onClick={requestExport} disabled={isPending}>
                Email me a link instead
              </button>
            </div>
          </div>

          {/* Deactivate (soft) */}
          <div className="hm-panel" style={{ marginTop: 16, padding: 24 }}>
            <h3 className="hm-panel-title">Pause notifications</h3>
            <p className="hm-panel-sub" style={{ marginTop: 6, lineHeight: 1.6 }}>
              Not ready to leave, but want quiet? Turn everything off from Preferences.
            </p>
            <a href="/preferences" className="hm-pager-btn" style={{ marginTop: 12, display: "inline-block", textDecoration: "none" }}>
              Open preferences →
            </a>
          </div>

          {/* Delete account (danger zone) */}
          <div
            style={{
              marginTop: 16,
              padding: 24,
              border: "1px solid rgba(229, 57, 53, 0.35)",
              borderRadius: 16,
              background: "rgba(229, 57, 53, 0.05)",
            }}
          >
            <h3 style={{ margin: 0, fontSize: "1rem", color: "#e53935" }}>Danger zone</h3>
            <p style={{ color: "var(--muted)", marginTop: 6, lineHeight: 1.6 }}>
              Deleting your account is permanent. Your subscription will be canceled and all personal data will be
              erased within 30 days. Reading records tied to donations may be retained anonymously for accounting.
            </p>
            <button
              onClick={() => setShowDeleteModal(true)}
              style={{
                marginTop: 12,
                background: "#e53935",
                color: "white",
                border: 0,
                padding: "10px 20px",
                borderRadius: 999,
                fontWeight: 800,
                cursor: "pointer",
                fontFamily: "inherit",
                fontSize: "0.85rem",
              }}
            >
              Delete my account
            </button>
          </div>
        </section>
      </main>

      {showDeleteModal && (
        <div
          className="admin-modal-overlay"
          onClick={() => { setShowDeleteModal(false); setConfirmDeleteText(""); setPassword(""); setError(""); }}
        >
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 480 }}>
            <h3 style={{ marginTop: 0, color: "#e53935" }}>Delete your account?</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.9rem", lineHeight: 1.6 }}>
              This is <strong>permanent</strong>. Your subscription will be canceled and your personal data will be
              erased within 30 days.
            </p>

            {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

            <label className="narlit-field" style={{ marginTop: 16 }}>
              <span>Type DELETE to confirm</span>
              <input
                type="text"
                value={confirmDeleteText}
                onChange={(e) => setConfirmDeleteText(e.target.value)}
                placeholder="DELETE"
              />
            </label>

            <label className="narlit-field" style={{ marginTop: 12 }}>
              <span>Confirm with your password</span>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
              />
            </label>

            <div style={{ display: "flex", justifyContent: "flex-end", gap: 8, marginTop: 20 }}>
              <button
                className="hm-pager-btn"
                onClick={() => { setShowDeleteModal(false); setConfirmDeleteText(""); setPassword(""); setError(""); }}
              >
                Cancel
              </button>
              <button
                onClick={deleteAccount}
                disabled={isPending || confirmDeleteText !== "DELETE" || !password}
                style={{
                  background: "#e53935",
                  color: "white",
                  border: 0,
                  padding: "10px 20px",
                  borderRadius: 999,
                  fontWeight: 800,
                  cursor: (isPending || confirmDeleteText !== "DELETE" || !password) ? "not-allowed" : "pointer",
                  fontFamily: "inherit",
                  fontSize: "0.85rem",
                  opacity: (isPending || confirmDeleteText !== "DELETE" || !password) ? 0.5 : 1,
                }}
              >
                {isPending ? "Deleting…" : "Yes, delete permanently"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
