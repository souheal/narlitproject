"use client";

import type { CSSProperties } from "react";

type Variant = "text" | "rect" | "circle";

interface SkeletonProps {
  variant?: Variant;
  width?: string | number;
  height?: string | number;
  radius?: string | number;
  className?: string;
  style?: CSSProperties;
}

/**
 * Shimmer skeleton placeholder used while data loads.
 * Prefer this over "Loading..." text — it preserves layout and communicates progress.
 */
export function Skeleton({
  variant = "rect",
  width,
  height,
  radius,
  className = "",
  style,
}: SkeletonProps) {
  const resolvedRadius = radius ?? (variant === "circle" ? "50%" : variant === "text" ? 4 : 8);
  const resolvedHeight = height ?? (variant === "text" ? "1em" : 16);
  const resolvedWidth = width ?? "100%";

  return (
    <span
      aria-hidden="true"
      className={`skeleton ${className}`.trim()}
      style={{
        display: "inline-block",
        width: resolvedWidth,
        height: resolvedHeight,
        borderRadius: resolvedRadius,
        ...style,
      }}
    />
  );
}

/**
 * Convenience layout: stacks of text-like skeleton lines. Useful in cards / list items.
 */
export function SkeletonText({ lines = 3, widths }: { lines?: number; widths?: (string | number)[] }) {
  return (
    <span style={{ display: "flex", flexDirection: "column", gap: 6 }}>
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton
          key={i}
          variant="text"
          width={widths?.[i] ?? (i === lines - 1 ? "60%" : "100%")}
        />
      ))}
    </span>
  );
}
