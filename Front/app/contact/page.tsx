"use client";

import { FormEvent, useState, useTransition } from "react";
import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

export default function ContactPage() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [reason, setReason] = useState("general");
  const [feedback, setFeedback] = useState("");
  const [error, setError] = useState("");
  const [isPending, startTransition] = useTransition();

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/contact`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({ name, email, subject, message, reason }),
        });
        const payload = await res.json();
        if (!res.ok) {
          setError(payload?.message ?? Object.values(payload?.errors ?? {}).flat().join(" ") ?? "Failed to send.");
          return;
        }
        setFeedback("Thanks — we'll get back to you within one business day.");
        setName(""); setEmail(""); setSubject(""); setMessage("");
      } catch { setError("Failed to send. Try again shortly."); }
    });
  }

  return (
    <div className="hm-shell">
      <PublicNav />

      <main style={{ padding: "60px 24px" }}>
        <div style={{ maxWidth: 900, margin: "0 auto" }}>
          <p style={{ color: "var(--orange)", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", fontSize: "0.8rem" }}>
            Contact us
          </p>
          <h1 style={{ fontSize: "2.4rem", margin: "8px 0 20px" }}>Let&apos;s talk.</h1>
          <p style={{ fontSize: "1.05rem", color: "var(--muted)", lineHeight: 1.6 }}>
            Questions about your subscription, help publishing on NarLit, or press requests — we read every message.
          </p>

          <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 40, marginTop: 40 }}>
            <form onSubmit={handleSubmit}>
              {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
              {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

              <label className="narlit-field">
                <span>Your name</span>
                <input type="text" value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} />
              </label>
              <label className="narlit-field" style={{ marginTop: 12 }}>
                <span>Email</span>
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </label>
              <label className="narlit-field" style={{ marginTop: 12 }}>
                <span>Reason</span>
                <select value={reason} onChange={(e) => setReason(e.target.value)}>
                  <option value="general">General inquiry</option>
                  <option value="support">Account & billing help</option>
                  <option value="nonprofit">Publishing on NarLit</option>
                  <option value="press">Press / media</option>
                  <option value="partnership">Partnership</option>
                </select>
              </label>
              <label className="narlit-field" style={{ marginTop: 12 }}>
                <span>Subject</span>
                <input type="text" value={subject} onChange={(e) => setSubject(e.target.value)} required maxLength={200} />
              </label>
              <label className="narlit-field" style={{ marginTop: 12 }}>
                <span>Message</span>
                <textarea value={message} onChange={(e) => setMessage(e.target.value)} required rows={6} />
              </label>
              <button type="submit" className="hm-article-btn" style={{ marginTop: 16 }} disabled={isPending}>
                {isPending ? "Sending…" : "Send message"}
              </button>
            </form>

            <aside>
              <div style={{ padding: 20, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 14 }}>
                <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Prefer email?</h3>
                <p style={{ fontSize: "0.85rem", margin: "0 0 16px", color: "var(--muted)" }}>
                  Reach us directly by topic.
                </p>
                <ul style={{ listStyle: "none", padding: 0, margin: 0, fontSize: "0.9rem", display: "flex", flexDirection: "column", gap: 12 }}>
                  <li>
                    <div style={{ color: "var(--muted)", fontSize: "0.75rem" }}>General</div>
                    <a href="mailto:hello@narlit.com" className="su-link">hello@narlit.com</a>
                  </li>
                  <li>
                    <div style={{ color: "var(--muted)", fontSize: "0.75rem" }}>Support</div>
                    <a href="mailto:support@narlit.com" className="su-link">support@narlit.com</a>
                  </li>
                  <li>
                    <div style={{ color: "var(--muted)", fontSize: "0.75rem" }}>Press</div>
                    <a href="mailto:press@narlit.com" className="su-link">press@narlit.com</a>
                  </li>
                </ul>
              </div>

              <div style={{ marginTop: 16, padding: 20, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 14 }}>
                <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Response times</h3>
                <p style={{ fontSize: "0.85rem", margin: 0, color: "var(--muted)", lineHeight: 1.6 }}>
                  Weekdays: within 1 business day.<br />
                  Weekends: on Monday morning.
                </p>
              </div>
            </aside>
          </div>
        </div>
      </main>

      <PublicFooter />
    </div>
  );
}
