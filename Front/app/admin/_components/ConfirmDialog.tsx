"use client";

import { useEffect, useRef } from "react";

export type ConfirmVariant = "default" | "danger" | "primary";

export interface ConfirmDialogProps {
  open: boolean;
  title: string;
  message: React.ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: ConfirmVariant;
  loading?: boolean;
  disableConfirm?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = "Confirm",
  cancelLabel = "Cancel",
  variant = "default",
  loading = false,
  disableConfirm = false,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const confirmBtnRef = useRef<HTMLButtonElement | null>(null);

  useEffect(() => {
    if (!open) return;
    const previouslyFocused = document.activeElement as HTMLElement | null;
    confirmBtnRef.current?.focus();

    function handleKey(e: KeyboardEvent) {
      if (e.key === "Escape" && !loading) {
        e.preventDefault();
        onCancel();
      }
      if (e.key === "Enter" && !loading && !disableConfirm) {
        e.preventDefault();
        onConfirm();
      }
    }
    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("keydown", handleKey);
      previouslyFocused?.focus?.();
    };
  }, [open, loading, disableConfirm, onConfirm, onCancel]);

  if (!open) return null;

  const confirmClass =
    variant === "danger"
      ? "admin-btn admin-btn-reject"
      : variant === "primary"
      ? "admin-btn admin-btn-approve"
      : "admin-btn";

  return (
    <div
      className="admin-modal-overlay"
      role="dialog"
      aria-modal="true"
      aria-labelledby="confirm-dialog-title"
      onClick={loading ? undefined : onCancel}
      suppressHydrationWarning
    >
      <div
        className="admin-modal"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: 460 }}
        suppressHydrationWarning
      >
        <h3
          id="confirm-dialog-title"
          style={{ marginTop: 0, color: variant === "danger" ? "#c62828" : undefined }}
        >
          {variant === "danger" && "⚠️ "}
          {title}
        </h3>

        <div style={{ fontSize: "0.92rem", lineHeight: 1.55, color: "var(--foreground)" }}>
          {message}
        </div>

        <div
          style={{
            display: "flex",
            gap: 8,
            justifyContent: "flex-end",
            marginTop: 20,
            flexWrap: "wrap",
          }}
        >
          <button className="admin-btn" onClick={onCancel} disabled={loading}>
            {cancelLabel}
          </button>
          <button
            ref={confirmBtnRef}
            className={confirmClass}
            onClick={onConfirm}
            disabled={loading || disableConfirm}
          >
            {loading ? "Working…" : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
