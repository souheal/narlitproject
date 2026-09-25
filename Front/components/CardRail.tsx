"use client";

import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";

interface Props {
  children: ReactNode;
  /** Announced to screen readers, e.g. "Trending this week". */
  label?: string;
  busy?: boolean;
}

/** Sub-pixel scroll positions and display scaling mean an exact edge comparison
 *  never quite lands; a few pixels of slack keeps the fade off at the ends. */
const EDGE_EPSILON = 8;

/** The thumb stays grabbable even when the row is very long. */
const MIN_THUMB_RATIO = 0.14;

export default function CardRail({ children, label, busy }: Props) {
  const trackRef = useRef<HTMLDivElement>(null);
  const barRef = useRef<HTMLDivElement>(null);

  const [overflow, setOverflow] = useState(false);
  const [atStart, setAtStart] = useState(true);
  const [atEnd, setAtEnd] = useState(true);
  const [ratio, setRatio] = useState(1);
  const [progress, setProgress] = useState(0);
  const [dragging, setDragging] = useState(false);

  const sync = useCallback(() => {
    const el = trackRef.current;
    if (!el) return;

    const max = el.scrollWidth - el.clientWidth;
    setOverflow(max > EDGE_EPSILON);
    setAtStart(el.scrollLeft <= EDGE_EPSILON);
    setAtEnd(el.scrollLeft >= max - EDGE_EPSILON);
    setRatio(Math.max(MIN_THUMB_RATIO, Math.min(1, el.clientWidth / el.scrollWidth)));
    setProgress(max > 0 ? Math.min(1, Math.max(0, el.scrollLeft / max)) : 0);
  }, []);

  useEffect(() => {
    const el = trackRef.current;
    if (!el) return;

    sync();
    el.addEventListener("scroll", sync, { passive: true });

    // Cards arrive asynchronously and the window resizes; either changes
    // whether the row overflows and how wide the thumb should be.
    const observer = new ResizeObserver(sync);
    observer.observe(el);
    for (const child of Array.from(el.children)) observer.observe(child);

    return () => {
      el.removeEventListener("scroll", sync);
      observer.disconnect();
    };
  }, [sync, children]);

  /** Where on the thumb the pointer grabbed it, so the thumb doesn't jump. */
  const grabRef = useRef(0);

  /** Map a pointer position on the bar to a scroll offset. */
  const scrollToPointer = useCallback((clientX: number) => {
    const track = trackRef.current;
    const bar = barRef.current;
    if (!track || !bar) return;

    const rect = bar.getBoundingClientRect();
    const thumbWidth = rect.width * ratio;
    const usable = rect.width - thumbWidth;
    if (usable <= 0) return;

    const offset = clientX - rect.left - grabRef.current;
    const next = Math.min(1, Math.max(0, offset / usable));
    track.scrollLeft = next * (track.scrollWidth - track.clientWidth);
  }, [ratio]);

  function onBarPointerDown(e: React.PointerEvent<HTMLDivElement>) {
    if (!overflow || !barRef.current) return;
    e.preventDefault();
    e.currentTarget.setPointerCapture(e.pointerId);

    const rect = barRef.current.getBoundingClientRect();
    const thumbWidth = rect.width * ratio;
    const thumbLeft = rect.left + progress * (rect.width - thumbWidth);
    const onThumb = e.clientX >= thumbLeft && e.clientX <= thumbLeft + thumbWidth;
    // Grabbing the thumb keeps it under the same spot of the cursor; clicking
    // the empty bar centres the thumb on the cursor.
    grabRef.current = onThumb ? e.clientX - thumbLeft : thumbWidth / 2;

    setDragging(true);
    scrollToPointer(e.clientX);
  }

  function onBarPointerMove(e: React.PointerEvent<HTMLDivElement>) {
    if (!dragging) return;
    scrollToPointer(e.clientX);
  }

  function endDrag(e: React.PointerEvent<HTMLDivElement>) {
    if (!dragging) return;
    setDragging(false);
    if (e.currentTarget.hasPointerCapture(e.pointerId)) {
      e.currentTarget.releasePointerCapture(e.pointerId);
    }
  }

  function onKeyDown(e: React.KeyboardEvent<HTMLDivElement>) {
    const el = trackRef.current;
    if (!el) return;
    if (e.key === "ArrowRight") { e.preventDefault(); el.scrollBy({ left: el.clientWidth * 0.85, behavior: "smooth" }); }
    if (e.key === "ArrowLeft") { e.preventDefault(); el.scrollBy({ left: -el.clientWidth * 0.85, behavior: "smooth" }); }
  }

  return (
    <div
      className={
        "hm-rail" +
        (atStart ? "" : " hm-rail-fade-start") +
        (atEnd ? "" : " hm-rail-fade-end") +
        (dragging ? " hm-rail-dragging" : "")
      }
    >
      <div
        ref={trackRef}
        className="hm-rail-track"
        role="group"
        aria-label={label}
        aria-busy={busy}
        tabIndex={overflow ? 0 : -1}
        onKeyDown={onKeyDown}
      >
        {children}
      </div>

      {overflow && (
        <div
          ref={barRef}
          className={`hm-rail-bar${dragging ? " hm-rail-bar-dragging" : ""}`}
          onPointerDown={onBarPointerDown}
          onPointerMove={onBarPointerMove}
          onPointerUp={endDrag}
          onPointerCancel={endDrag}
        >
          <span
            className="hm-rail-thumb"
            style={{
              width: `${ratio * 100}%`,
              // Travel only the free space, so the thumb stops flush at each end.
              left: `${progress * (100 - ratio * 100)}%`,
            }}
          />
        </div>
      )}
    </div>
  );
}
