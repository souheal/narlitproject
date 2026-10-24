"use client";

import { ChangeEvent, FormEvent, useEffect, useRef, useState } from "react";
import { apiFetch } from "@/lib/api";
import { ARTICLE_CATEGORIES } from "@/lib/categories";

export interface OrgArticleImage {
  id: string;
  url: string | null;
}

export interface OrgArticleDetail {
  public_id: string;
  title: string;
  excerpt: string | null;
  content: string;
  category: string | null;
  status: string;
  rejection_reason: string | null;
  change_request: { reason: string | null; requested_at: string | null } | null;
  resubmission_count: number;
  can_edit: boolean;
  approval_required: boolean;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  read_time_minutes: number | null;
  total_reads: number;
  total_unique_reads: number;
  total_points_generated: number;
  cover_image_url: string | null;
  images: OrgArticleImage[];
}

/** What the org asked for; the API turns "publish" into published or pending_review. */
type Intent = "draft" | "publish" | "keep";

interface Props {
  /** Omitted when creating a new article. */
  article?: OrgArticleDetail;
  approvalRequired: boolean;
  onSaved: (article: OrgArticleDetail, message: string) => void;
}

const MAX_GALLERY = 8;
const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
const ACCEPTED = ["image/jpeg", "image/png", "image/webp", "image/gif"];

interface NewImage {
  key: string;
  file: File;
  preview: string;
}

function checkImage(file: File): string | null {
  if (!ACCEPTED.includes(file.type)) return `"${file.name}" isn't a JPG, PNG, WebP or GIF image.`;
  if (file.size > MAX_IMAGE_BYTES) return `"${file.name}" is larger than 4 MB.`;
  return null;
}

export default function ArticleEditor({ article, approvalRequired, onSaved }: Props) {
  const isNew = !article;
  const status = article?.status ?? "draft";

  const [title, setTitle] = useState(article?.title ?? "");
  const [category, setCategory] = useState(article?.category ?? ARTICLE_CATEGORIES[0]);
  const [excerpt, setExcerpt] = useState(article?.excerpt ?? "");
  const [body, setBody] = useState(article?.content ?? "");
  const [readTime, setReadTime] = useState(article?.read_time_minutes ?? 5);

  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(null);
  const [coverRemoved, setCoverRemoved] = useState(false);

  const [keptImages, setKeptImages] = useState<OrgArticleImage[]>(article?.images ?? []);
  const [removedIds, setRemovedIds] = useState<string[]>([]);
  const [newImages, setNewImages] = useState<NewImage[]>([]);

  const [error, setError] = useState("");
  const [saving, setSaving] = useState<Intent | null>(null);

  const coverInput = useRef<HTMLInputElement>(null);
  const galleryInput = useRef<HTMLInputElement>(null);

  // Revoke object URLs for previews when they go away.
  const previews = useRef<string[]>([]);
  useEffect(() => {
    previews.current = [coverPreview, ...newImages.map((n) => n.preview)].filter(Boolean) as string[];
  }, [coverPreview, newImages]);
  useEffect(() => () => previews.current.forEach((u) => URL.revokeObjectURL(u)), []);

  const shownCover = coverPreview ?? (coverRemoved ? null : article?.cover_image_url ?? null);
  const galleryCount = keptImages.length + newImages.length;

  const categories =
    article?.category && !ARTICLE_CATEGORIES.includes(article.category)
      ? [article.category, ...ARTICLE_CATEGORIES]
      : ARTICLE_CATEGORIES;

  function pickCover(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file) return;
    const problem = checkImage(file);
    if (problem) { setError(problem); return; }
    setError("");
    if (coverPreview) URL.revokeObjectURL(coverPreview);
    setCoverFile(file);
    setCoverPreview(URL.createObjectURL(file));
    setCoverRemoved(false);
  }

  function removeCover() {
    if (coverPreview) URL.revokeObjectURL(coverPreview);
    setCoverFile(null);
    setCoverPreview(null);
    setCoverRemoved(true);
  }

  function pickGallery(e: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files ?? []);
    e.target.value = "";
    if (files.length === 0) return;
    const room = MAX_GALLERY - galleryCount;
    if (files.length > room) {
      setError(room > 0 ? `You can add ${room} more photo${room === 1 ? "" : "s"} (max ${MAX_GALLERY}).` : `An article can have at most ${MAX_GALLERY} photos.`);
      return;
    }
    for (const file of files) {
      const problem = checkImage(file);
      if (problem) { setError(problem); return; }
    }
    setError("");
    setNewImages((prev) => [
      ...prev,
      ...files.map((file) => ({ key: `${file.name}-${file.size}-${Math.random()}`, file, preview: URL.createObjectURL(file) })),
    ]);
  }

  function removeKept(id: string) {
    setKeptImages((prev) => prev.filter((img) => img.id !== id));
    setRemovedIds((prev) => [...prev, id]);
  }

  function removeNew(key: string) {
    setNewImages((prev) => {
      const gone = prev.find((n) => n.key === key);
      if (gone) URL.revokeObjectURL(gone.preview);
      return prev.filter((n) => n.key !== key);
    });
  }

  async function save(intent: Intent) {
    setError("");
    if (!title.trim() || !body.trim()) {
      setError("Title and body are required.");
      return;
    }

    const form = new FormData();
    form.append("title", title);
    form.append("category", category);
    form.append("excerpt", excerpt);
    form.append("body", body);
    form.append("read_time_minutes", String(readTime));
    if (intent !== "keep") form.append("status", intent);
    if (coverFile) form.append("cover_image", coverFile);
    if (!isNew && coverRemoved && !coverFile) form.append("remove_cover", "1");
    newImages.forEach((n) => form.append("images[]", n.file));
    removedIds.forEach((id) => form.append("remove_images[]", id));

    // PHP only parses multipart bodies on POST, so edits use Laravel's method override.
    if (!isNew) form.append("_method", "PATCH");

    setSaving(intent);
    try {
      const res = await apiFetch(isNew ? "/organization/articles" : `/organization/articles/${article.public_id}`, {
        method: "POST",
        body: form,
      });
      const payload = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(
          (res.status === 413 && "The photos are too large to upload together. Try fewer or smaller images.") ||
            payload?.message ||
            Object.values(payload?.errors ?? {}).flat().join(" ") ||
            "Failed to save article."
        );
      }

      newImages.forEach((n) => URL.revokeObjectURL(n.preview));
      if (coverPreview) URL.revokeObjectURL(coverPreview);
      const saved: OrgArticleDetail = payload.data.article;
      setNewImages([]);
      setRemovedIds([]);
      setKeptImages(saved.images ?? []);
      setCoverFile(null);
      setCoverPreview(null);
      setCoverRemoved(false);
      onSaved(saved, payload.message ?? "Saved.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong.");
    } finally {
      setSaving(null);
    }
  }

  function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    save(primary.intent);
  }

  // Buttons depend on where the article is in its lifecycle.
  const primary: { intent: Intent; label: string } =
    status === "published"
      ? { intent: "publish", label: "Save changes" }
      : status === "pending_review"
        ? { intent: "keep", label: "Save changes" }
        : { intent: "publish", label: status === "rejected" || article?.rejection_reason ? "Republish" : "Publish" };

  const secondary: { intent: Intent; label: string } | null =
    status === "published"
      ? { intent: "draft", label: "Unpublish" }
      : status === "pending_review"
        ? { intent: "draft", label: "Withdraw to draft" }
        : { intent: "draft", label: "Save draft" };

  const hint =
    status === "published"
      ? approvalRequired
        ? "Changes to a live article go back to the NarLit team for approval. It will be hidden from readers until they approve it."
        : "Changes go live for readers as soon as you save."
      : status === "pending_review"
        ? "This article is waiting for approval. You can still fix it — it stays in the review queue."
        : approvalRequired
          ? "Publish sends the article to the NarLit team for approval. It goes live once they approve it."
          : "Publish makes the article visible to readers right away.";

  const busy = saving !== null;

  return (
    <form className="hm-panel oa-card" onSubmit={onSubmit}>
      {error && <p className="narlit-feedback narlit-feedback-error oa-editor-error">{error}</p>}

      <div className="oa-grid">
        <label className="narlit-field oa-span-2">
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

        <div className="narlit-field oa-span-2">
          <span>Cover image</span>
          {shownCover ? (
            <div className="oa-cover">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={shownCover} alt="Cover preview" />
              <div className="oa-cover-actions">
                <button type="button" className="hm-pager-btn" onClick={() => coverInput.current?.click()} disabled={busy}>
                  Replace
                </button>
                <button type="button" className="hm-pager-btn" onClick={removeCover} disabled={busy}>
                  Remove
                </button>
              </div>
            </div>
          ) : (
            <button type="button" className="oa-dropzone" onClick={() => coverInput.current?.click()} disabled={busy}>
              <strong>+ Add a cover image</strong>
              <small>Shown at the top of the article and on cards. JPG, PNG, WebP or GIF, up to 4 MB.</small>
            </button>
          )}
          <input ref={coverInput} type="file" accept={ACCEPTED.join(",")} onChange={pickCover} hidden />
        </div>

        <label className="narlit-field">
          <span>Category</span>
          <select value={category} onChange={(e) => setCategory(e.target.value)}>
            {categories.map((c) => (
              <option key={c} value={c}>{c}</option>
            ))}
          </select>
        </label>

        <label className="narlit-field">
          <span>Estimated read time (minutes)</span>
          <input
            type="number"
            min={1}
            max={60}
            value={readTime}
            onChange={(e) => setReadTime(Math.max(1, Math.min(60, Number(e.target.value) || 1)))}
          />
        </label>

        <label className="narlit-field oa-span-2">
          <span>Excerpt</span>
          <textarea
            value={excerpt}
            onChange={(e) => setExcerpt(e.target.value)}
            placeholder="One or two sentences shown on the listing"
            rows={3}
            maxLength={280}
          />
        </label>

        <label className="narlit-field oa-span-2">
          <span>Body</span>
          <textarea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            placeholder="The full article content"
            rows={16}
            required
          />
        </label>

        <div className="narlit-field oa-span-2">
          <span>
            Photos <small className="oa-muted">({galleryCount}/{MAX_GALLERY}, shown with the story)</small>
          </span>
          <div className="oa-gallery">
            {keptImages.map((img) =>
              img.url ? (
                <div key={img.id} className="oa-thumb">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={img.url} alt="" />
                  <button type="button" className="oa-thumb-remove" onClick={() => removeKept(img.id)} disabled={busy} aria-label="Remove photo">
                    ×
                  </button>
                </div>
              ) : null
            )}
            {newImages.map((n) => (
              <div key={n.key} className="oa-thumb oa-thumb-new">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={n.preview} alt="" />
                <button type="button" className="oa-thumb-remove" onClick={() => removeNew(n.key)} disabled={busy} aria-label="Remove photo">
                  ×
                </button>
              </div>
            ))}
            {galleryCount < MAX_GALLERY && (
              <button type="button" className="oa-thumb oa-thumb-add" onClick={() => galleryInput.current?.click()} disabled={busy}>
                + Add photos
              </button>
            )}
          </div>
          <input ref={galleryInput} type="file" accept={ACCEPTED.join(",")} multiple onChange={pickGallery} hidden />
        </div>
      </div>

      <p className="oa-hint">{hint}</p>

      <div className="oa-actions">
        {secondary && (
          <button
            type="button"
            className="narlit-button narlit-button-secondary"
            onClick={() => save(secondary.intent)}
            disabled={busy}
          >
            {saving === secondary.intent ? "Saving…" : secondary.label}
          </button>
        )}
        <button type="submit" className="narlit-button narlit-button-primary" disabled={busy}>
          {saving === primary.intent ? "Saving…" : primary.label}
        </button>
      </div>
    </form>
  );
}
