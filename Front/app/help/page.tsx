"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import MemberNav from "@/components/MemberNav";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";

interface Faq {
  question: string;
  answer: string;
  category: string;
}

interface User { full_name: string; email: string }

const DEFAULT_FAQS: Faq[] = [
  {
    category: "Getting started",
    question: "How does NarLit work?",
    answer: "You subscribe monthly or yearly, and every time you read an article to completion, part of your subscription is donated to the nonprofit that published it. Your reading directly funds their mission.",
  },
  {
    category: "Getting started",
    question: "What counts as a completed read?",
    answer: "An article is counted when you've scrolled through at least 80% of the content. Skimming won't count — the system rewards engaged reading.",
  },
  {
    category: "Billing",
    question: "How is my subscription split?",
    answer: "Roughly one third goes to nonprofits (weighted by what you read), one third to platform operations, and one third to growth and reach.",
  },
  {
    category: "Billing",
    question: "Can I cancel anytime?",
    answer: "Yes. Cancellations take effect at the end of your current billing period, and you can resume before then without losing your status.",
  },
  {
    category: "Billing",
    question: "How do I update my payment method?",
    answer: "Go to Subscription → Update payment method. You'll be sent to a secure Stripe portal.",
  },
  {
    category: "Impact",
    question: "How is my impact calculated?",
    answer: "Each completed read generates a fixed donation amount for the article's nonprofit. Your monthly impact is the sum of your completed reads for that month.",
  },
  {
    category: "Impact",
    question: "Where can I see my impact history?",
    answer: "The 'My Impact' page shows lifetime totals, month-by-month contributions, and per-nonprofit breakdown.",
  },
  {
    category: "Account",
    question: "How do I change my password?",
    answer: "Go to Profile → Password tab, enter your current password and a new one at least 8 characters long.",
  },
  {
    category: "Account",
    question: "I lost my phone — how do I reset MFA?",
    answer: "Contact support at support@narlit.com from your registered email. We'll verify your identity and reset MFA.",
  },
  {
    category: "Nonprofits",
    question: "How can my nonprofit join NarLit?",
    answer: "Any 501(c)(3) can sign up on the /signup/organizer page. Our team reviews credentials before approval.",
  },
];

export default function HelpPage() {
  const [user, setUser] = useState<User | null>(null);
  const [checking, setChecking] = useState(true);
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [feedback, setFeedback] = useState("");
  const [error, setError] = useState("");
  const [openFaq, setOpenFaq] = useState<number | null>(null);
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

  function submitTicket(e: FormEvent) {
    e.preventDefault();
    setFeedback(""); setError("");
    startTransition(async () => {
      try {
        const res = await apiFetch("/member/support/tickets", {
          method: "POST",
          body: JSON.stringify({ subject, message }),
        });
        const payload = await res.json();
        if (!res.ok) {
          setError(payload?.message ?? "Failed to submit ticket.");
          return;
        }
        setFeedback("Your message was sent. We'll reply within 1 business day.");
        setSubject("");
        setMessage("");
      } catch { setError("Failed to submit ticket."); }
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
  const grouped = DEFAULT_FAQS.reduce<Record<string, Faq[]>>((acc, f) => {
    (acc[f.category] ??= []).push(f);
    return acc;
  }, {});
  let flatIdx = 0;

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <MemberNav initials={initials} name={user?.full_name} email={user?.email} />
      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 820, margin: "0 auto" }}>
          <h2 className="hm-section-title">Help & Support</h2>

          <div className="hm-content-grid" style={{ gridTemplateColumns: "1fr 1fr", gap: 20, marginTop: 20 }}>
            <a href="mailto:support@narlit.com" className="hm-panel" style={{ textDecoration: "none", color: "inherit", padding: 20 }}>
              <div style={{ fontSize: "2rem" }}>✉️</div>
              <h3 className="hm-panel-title">Email support</h3>
              <p className="hm-panel-sub">support@narlit.com · replies within 1 business day</p>
            </a>
            <a href="https://narlit.com/status" target="_blank" rel="noreferrer" className="hm-panel" style={{ textDecoration: "none", color: "inherit", padding: 20 }}>
              <div style={{ fontSize: "2rem" }}>📡</div>
              <h3 className="hm-panel-title">System status</h3>
              <p className="hm-panel-sub">Check whether NarLit is running smoothly</p>
            </a>
          </div>

          <h3 className="hm-section-title" style={{ marginTop: 40, fontSize: "1.2rem" }}>Frequently asked</h3>
          {Object.entries(grouped).map(([category, list]) => (
            <div key={category} style={{ marginTop: 20 }}>
              <h4 style={{ fontSize: "0.8rem", color: "var(--muted)", textTransform: "uppercase", letterSpacing: "0.05em", marginBottom: 10 }}>
                {category}
              </h4>
              <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                {list.map((f) => {
                  const idx = flatIdx++;
                  const isOpen = openFaq === idx;
                  return (
                    <div key={idx} className="hm-panel" style={{ padding: 0 }}>
                      <button
                        type="button"
                        onClick={() => setOpenFaq(isOpen ? null : idx)}
                        style={{
                          width: "100%",
                          textAlign: "left",
                          padding: 16,
                          background: "transparent",
                          border: 0,
                          cursor: "pointer",
                          color: "inherit",
                          fontSize: "0.95rem",
                          fontWeight: 700,
                          display: "flex",
                          justifyContent: "space-between",
                          alignItems: "center",
                          fontFamily: "inherit",
                        }}
                      >
                        <span>{f.question}</span>
                        <span style={{ color: "var(--muted)", transform: isOpen ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
                      </button>
                      {isOpen && (
                        <div style={{ padding: "0 16px 16px", color: "var(--muted)", fontSize: "0.9rem", lineHeight: 1.6 }}>
                          {f.answer}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}

          <h3 className="hm-section-title" style={{ marginTop: 40, fontSize: "1.2rem" }}>Still need help?</h3>
          <form onSubmit={submitTicket} className="hm-panel" style={{ padding: 20 }}>
            {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
            {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

            <label className="narlit-field">
              <span>Subject</span>
              <input type="text" value={subject} onChange={(e) => setSubject(e.target.value)} required maxLength={140} />
            </label>
            <label className="narlit-field" style={{ marginTop: 12 }}>
              <span>Message</span>
              <textarea value={message} onChange={(e) => setMessage(e.target.value)} required rows={5} />
            </label>
            <button type="submit" className="hm-article-btn" style={{ marginTop: 16 }} disabled={isPending}>
              {isPending ? "Sending…" : "Send message"}
            </button>
          </form>
        </section>
      </main>
    </div>
  );
}
