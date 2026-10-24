"use client";

<<<<<<< HEAD
import { useEffect, useState } from "react";
import { clearToken } from "@/lib/auth";
import { apiFetch, validateSession } from "@/lib/api";
=======
import { ChangeEvent, FormEvent, useEffect, useRef, useState, useTransition } from "react";
import { apiFetch, requireRole } from "@/lib/api";
>>>>>>> 9f279c46a8cdc1bb00b7c8644cb2bac1744a578f
import { BrandLoader } from "@/components/BrandLoader";
import OrgNav from "@/components/OrgNav";
import ArticleEditor, { OrgArticleDetail } from "@/components/ArticleEditor";
import BackLink from "@/components/BackLink";

const COVER_IMAGE_MAX_BYTES = 4 * 1024 * 1024;
const COVER_IMAGE_ACCEPT = "image/jpeg,image/png,image/webp,image/gif";

export default function NewArticlePage() {
  const [checking, setChecking] = useState(true);
<<<<<<< HEAD
  const [approvalRequired, setApprovalRequired] = useState(true);

  useEffect(() => {
    validateSession().then(async (valid) => {
      if (!valid) {
        clearToken();
        window.location.href = "/login";
        return;
      }
      // Whether "Publish" goes live directly or to the admin queue is an admin setting.
      try {
        const res = await apiFetch("/organization/articles?per_page=1");
        const data = await res.json();
        if (res.ok && typeof data.data?.approval_required === "boolean") {
          setApprovalRequired(data.data.approval_required);
        }
      } catch { /* keep the safe default */ }
      setChecking(false);
    });
  }, []);

  function handleSaved(saved: OrgArticleDetail) {
    // Continue on the article's own page, where it can be edited further.
    window.location.replace(`/organization/articles/${saved.public_id}`);
=======
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState(CATEGORIES[0]);
  const [excerpt, setExcerpt] = useState("");
  const [body, setBody] = useState("");
  const [readTime, setReadTime] = useState<number>(5);
  const [status, setStatus] = useState<"draft" | "pending_review">("pending_review");
  const [coverImage, setCoverImage] = useState<File | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(null);
  const coverInputRef = useRef<HTMLInputElement | null>(null);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    if (!coverImage) {
      setCoverPreview(null);
      return;
    }
    const url = URL.createObjectURL(coverImage);
    setCoverPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [coverImage]);

  function handleCoverChange(event: ChangeEvent<HTMLInputElement>) {
    setError("");
    const file = event.target.files?.[0] ?? null;
    if (!file) {
      setCoverImage(null);
      return;
    }
    if (file.size > COVER_IMAGE_MAX_BYTES) {
      setError("Cover image must be 4 MB or smaller.");
      event.target.value = "";
      setCoverImage(null);
      return;
    }
    if (!COVER_IMAGE_ACCEPT.split(",").includes(file.type)) {
      setError("Cover image must be JPG, PNG, WEBP, or GIF.");
      event.target.value = "";
      setCoverImage(null);
      return;
    }
    setCoverImage(file);
  }

  function removeCover() {
    setCoverImage(null);
    if (coverInputRef.current) {
      coverInputRef.current.value = "";
    }
  }

  useEffect(() => {
    requireRole(["organization"]).then((user) => {
      if (user) setChecking(false);
    });
  }, []);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSuccess("");

    startTransition(async () => {
      try {
        const formData = new FormData();
        formData.append("title", title);
        formData.append("category", category);
        formData.append("excerpt", excerpt);
        formData.append("body", body);
        formData.append("read_time_minutes", String(readTime));
        formData.append("status", status);
        if (coverImage) {
          formData.append("cover_image", coverImage);
        }

        const res = await apiFetch("/organization/articles", {
          method: "POST",
          body: formData,
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
>>>>>>> 9f279c46a8cdc1bb00b7c8644cb2bac1744a578f
  }

  if (checking) {
    return <BrandLoader />;
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <OrgNav active="articles" />

      <main className="hm-main">
        <div className="oa-wrap">
          <BackLink href="/organization/articles" className="su-link">← All articles</BackLink>
          <header className="oa-header">
            <h1 className="oa-title">New article</h1>
            <p className="oa-meta">Write your story, add a cover and photos, then publish it or save it as a draft.</p>
          </header>

<<<<<<< HEAD
          <ArticleEditor approvalRequired={approvalRequired} onSaved={handleSaved} />
        </div>
=======
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

            <div className="narlit-field">
              <span>Cover image (optional)</span>
              <input
                ref={coverInputRef}
                type="file"
                accept={COVER_IMAGE_ACCEPT}
                onChange={handleCoverChange}
              />
              <small style={{ color: "var(--muted)", fontSize: "0.8rem", marginTop: 4 }}>
                JPG, PNG, WEBP, or GIF · up to 4 MB.
              </small>
              {coverPreview && (
                <div style={{ marginTop: 10 }}>
                  <img
                    src={coverPreview}
                    alt="Cover preview"
                    style={{
                      width: "100%",
                      maxHeight: 240,
                      objectFit: "cover",
                      borderRadius: 10,
                      border: "1px solid var(--line)",
                    }}
                  />
                  <button
                    type="button"
                    className="hm-pager-btn"
                    onClick={removeCover}
                    style={{ marginTop: 8, padding: "6px 10px" }}
                  >
                    Remove image
                  </button>
                </div>
              )}
            </div>

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
>>>>>>> 9f279c46a8cdc1bb00b7c8644cb2bac1744a578f
      </main>
    </div>
  );
}
