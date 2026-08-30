"use client";

import { useEffect, useRef, useState } from "react";
import type { ConfirmVariant } from "./ConfirmDialog";

export interface PromptDialogProps {
  open: boolean;
  title: string;
  description?: React.ReactNode;
  label: string;
  hint?: string;
  placeholder?: string;
  defaultValue?: string;
  inputType?: "text" | "month" | "date" | "email" | "number";
  multiline?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: RegExp;
  patternError?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: ConfirmVariant;
  loading?: boolean;
  onSubmit: (value: string) => void;
  onCancel: () => void;
}

export function PromptDialog({
  open,
  title,
  description,
  label,
  hint,
  placeholder,
  defaultValue = "",
  inputType = "text",
  multiline = false,
  minLength,
  maxLength,
  pattern,
  patternError,
  confirmLabel = "Submit",
  cancelLabel = "Cancel",
  variant = "primary",
  loading = false,
  onSubmit,
  onCancel,
}: PromptDialogProps) {
  const [value, setValue] = useState(defaultValue);
  const [touched, setTouched] = useState(false);
  const inputRef = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null);

  useEffect(() => {
    if (open) {
      setValue(defaultValue);
      setTouched(false);
      const t = setTimeout(() => inputRef.current?.focus(), 40);
      return () => clearTimeout(t);
    }
  }, [open, defaultValue]);

  useEffect(() => {
    if (!open) return;

    function handleKey(e: KeyboardEvent) {
      if (e.key === "Escape" && !loading) {
        e.preventDefault();
        onCancel();
      }
    }
    document.addEventListener("keydown", handleKey);
    return () => document.removeEventListener("keydown", handleKey);
  }, [open, loading, onCancel]);

  if (!open) return null;

  const trimmed = value.trim();
  const meetsMin = minLength === undefined || trimmed.length >= minLength;
  const meetsPattern = !pattern || pattern.test(trimmed);
  const validationError = !meetsMin
    ? `Must be at least ${minLength} character${minLength === 1 ? "" : "s"}.`
    : !meetsPattern
    ? patternError ?? "Please enter a valid value."
    : null;
  const canSubmit = meetsMin && meetsPattern && !loading;

  const confirmClass =
    variant === "danger"
      ? "admin-btn admin-btn-reject"
      : "admin-btn admin-btn-approve";

  const commonInputStyle: React.CSSProperties = {
    padding: 10,
    border: "1px solid var(--line)",
    borderRadius: 8,
    background: "var(--panel)",
    color: "var(--foreground)",
    fontFamily: "inherit",
    fontSize: "0.95rem",
    width: "100%",
  };

  function handleSubmit(e?: React.FormEvent) {
    e?.preventDefault();
    if (!canSubmit) {
      setTouched(true);
      return;
    }
    onSubmit(trimmed);
  }

  return (
    <div
      className="admin-modal-overlay"
      role="dialog"
      aria-modal="true"
      aria-labelledby="prompt-dialog-title"
      onClick={loading ? undefined : onCancel}
      suppressHydrationWarning
    >
      <div
        className="admin-modal"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: 480 }}
        suppressHydrationWarning
      >
        <form onSubmit={handleSubmit}>
          <h3 id="prompt-dialog-title" style={{ marginTop: 0 }}>
            {title}
          </h3>

          {description && (
            <div style={{ fontSize: "0.88rem", color: "var(--muted)", lineHeight: 1.5, marginBottom: 12 }}>
              {description}
            </div>
          )}

          <label style={{ display: "flex", flexDirection: "column", gap: 4, fontSize: "0.85rem" }}>
            <span style={{ fontWeight: 700 }}>{label}</span>
            {multiline ? (
              <textarea
                ref={inputRef as React.RefObject<HTMLTextAreaElement>}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onBlur={() => setTouched(true)}
                rows={4}
                placeholder={placeholder}
                maxLength={maxLength}
                style={{ ...commonInputStyle, resize: "vertical" }}
              />
            ) : (
              <input
                ref={inputRef as React.RefObject<HTMLInputElement>}
                type={inputType}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onBlur={() => setTouched(true)}
                placeholder={placeholder}
                maxLength={maxLength}
                style={commonInputStyle}
              />
            )}
            {hint && !touched && (
              <span style={{ color: "var(--muted)", fontSize: "0.75rem" }}>{hint}</span>
            )}
            {touched && validationError && (
              <span style={{ color: "#c62828", fontSize: "0.78rem" }}>{validationError}</span>
            )}
          </label>

          <div
            style={{
              display: "flex",
              gap: 8,
              justifyContent: "flex-end",
              marginTop: 20,
              flexWrap: "wrap",
            }}
          >
            <button type="button" className="admin-btn" onClick={onCancel} disabled={loading}>
              {cancelLabel}
            </button>
            <button
              type="submit"
              className={confirmClass}
              disabled={!canSubmit || loading}
            >
              {loading ? "Working…" : confirmLabel}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
