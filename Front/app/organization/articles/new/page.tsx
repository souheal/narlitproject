"use client";

import { FormEvent, useEffect, useState, useTransition } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
import { BrandLoader } from "@/components/BrandLoader";
import OrgNav from "@/components/OrgNav";

const CATEGORIES = [
  "Civil Rights",
  "Food Security",
  "Healthcare",
  "Education",
  "Environment",
  "Housing",
  "Women & Children",
  "Refugees",
  "Other",
];

export default function NewArticlePage() {
  const [checking, setChecking] = useState(true);
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState(CATEGORIES[0]);
  const [excerpt, setExcerpt] = useState("");
  const [body, setBody] = useState("");
  const [readTime, setReadTime] = useState<number>(5);
  const [status, setStatus] = useState<"draft" | "pending_review">("pending_review");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    validateSession().then((valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      setChecking(false);
    });
  }, []);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSuccess("");

    startTransition(async () => {
      try {
        const res = await apiFetch("/organization/articles", {
          method: "POST",
          body: JSON.stringify({
            title,
            category,
            excerpt,
            body,
            read_time_minutes: readTime,
            status,
          }),
        });
        const payload = await res.json();
        if (!res.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Failed to submit article."
          );
        }
        setSuccess("Article saved. Redirecting…");
        setTimeout(() => {
          window.location.href = "/organization/articles";
        }, 900);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  if (checking) {
    return (
      <BrandLoader />
    );
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <OrgNav active="articles" />

      <main className="hm-main">
        <section className="hm-section" style={{ maxWidth: 780, margin: "0 auto" }}>
          <div className="hm-section-header">
            <h2 className="hm-section-title">Submit a new article</h2>
            <a href="/organization/articles" className="su-link">← Back</a>
          </div>

          {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}
          {success && <p className="narlit-feedback narlit-feedback-success">{success}</p>}

          <form className="login-form" onSubmit={handleSubmit}>
            <label className="narlit-field">
              <span>Title</span>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="A short, compelling title"
                maxLength={180}
                required
              />
            </label>

            <label className="narlit-field">
              <span>Category</span>
              <select value={category} onChange={(e) => setCategory(e.target.value)} required>
                {CATEGORIES.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            </label>

            <label className="narlit-field">
              <span>Excerpt</span>
              <textarea
                value={excerpt}
                onChange={(e) => setExcerpt(e.target.value)}
                placeholder="One or two sentences shown on the listing"
                rows={3}
                maxLength={280}
                required
              />
            </label>

            <label className="narlit-field">
              <span>Body</span>
              <textarea
                value={body}
                onChange={(e) => setBody(e.target.value)}
                placeholder="The full article content"
                rows={14}
                required
              />
            </label>

            <label className="narlit-field">
              <span>Estimated read time (minutes)</span>
              <input
                type="number"
                min={1}
                max={60}
                value={readTime}
                onChange={(e) => setReadTime(Math.max(1, Math.min(60, Number(e.target.value) || 1)))}
                required
              />
            </label>

            <label className="narlit-field">
              <span>Save as</span>
              <select value={status} onChange={(e) => setStatus(e.target.value as "draft" | "pending_review")}>
                <option value="pending_review">Submit for review</option>
                <option value="draft">Draft (save without submitting)</option>
              </select>
            </label>

            <button
              type="submit"
              className="narlit-button narlit-button-primary"
              disabled={isPending}
            >
              {isPending ? "Saving…" : status === "draft" ? "Save draft" : "Submit for review"}
            </button>
          </form>
        </section>
      </main>
    </div>
  );
}
