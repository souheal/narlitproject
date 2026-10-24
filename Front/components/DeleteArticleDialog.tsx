"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";

interface Props {
  article: { public_id: string; title: string; status: string };
  onClose: () => void;
  onDeleted: () => void;
}

/** Confirmation for an organization deleting one of its own articles. */
export default function DeleteArticleDialog({ article, onClose, onDeleted }: Props) {
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape" && !deleting) onClose();
    }
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [deleting, onClose]);

  async function confirm() {
    setDeleting(true);
    setError("");
    try {
      const res = await apiFetch(`/organization/articles/${article.public_id}`, { method: "DELETE" });
      if (!res.ok) {
        const payload = await res.json().catch(() => null);
        throw new Error(payload?.message ?? "Failed to delete article.");
      }
      onDeleted();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to delete article.");
      setDeleting(false);
    }
  }

  return (
    <div className="admin-modal-overlay" onClick={() => !deleting && onClose()}>
      <div
        className="admin-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-article-title"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 id="delete-article-title" className="oa-dialog-title">Delete this article?</h3>
        <p className="oa-dialog-text">
          <strong>&ldquo;{article.title}&rdquo;</strong> will be removed from your articles
          {article.status === "published" && <> and readers will no longer be able to see it</>}.
          If you change your mind, contact NarLit support to have it restored.
        </p>

        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        <div className="oa-dialog-actions">
          <button type="button" className="narlit-button narlit-button-secondary" onClick={onClose} disabled={deleting}>
            Cancel
          </button>
          <button type="button" className="narlit-button oa-button-danger" onClick={confirm} disabled={deleting} autoFocus>
            {deleting ? "Deleting…" : "Delete article"}
          </button>
        </div>
      </div>
    </div>
  );
}
