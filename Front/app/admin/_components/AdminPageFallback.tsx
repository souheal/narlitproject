"use client";

import { Skeleton } from "@/components/Skeleton";

interface AdminPageFallbackProps {
  title: string;
  /** Number of placeholder tab pills to draw. 0 hides the tab row. */
  tabs?: number;
  /** Number of placeholder stat cards to draw. 0 hides the stats grid. */
  stats?: number;
  /** Number of placeholder table rows to draw. */
  rows?: number;
}

/**
 * Suspense fallback for admin pages that read the query string with useSearchParams().
 * Mirrors the shared page chrome so swapping in the real page doesn't shift layout.
 */
export function AdminPageFallback({
  title,
  tabs = 0,
  stats = 0,
  rows = 6,
}: AdminPageFallbackProps) {
  return (
    <div aria-busy="true" aria-label={`Loading ${title.toLowerCase()}`}>
      <div className="admin-page-header">
        <h2 className="admin-page-title">{title}</h2>
        <Skeleton width={120} height={20} radius={8} />
      </div>

      {stats > 0 && (
        <div className="admin-stats-grid" style={{ marginBottom: 20 }}>
          {Array.from({ length: stats }).map((_, i) => (
            <div key={i} className="admin-stat-card" style={{ cursor: "default" }}>
              <Skeleton width={130} height={12} />
              <div style={{ marginTop: 10 }}><Skeleton width="60%" height={26} /></div>
              <div style={{ marginTop: 8 }}><Skeleton width="80%" height={12} /></div>
            </div>
          ))}
        </div>
      )}

      {tabs > 0 && (
        <div className="admin-tabs">
          {Array.from({ length: tabs }).map((_, i) => (
            <Skeleton key={i} width={104} height={32} radius={8} />
          ))}
        </div>
      )}

      <div style={{ marginTop: 16, display: "flex", flexDirection: "column", gap: 10 }}>
        {Array.from({ length: rows }).map((_, i) => (
          <Skeleton key={i} height={52} radius={10} />
        ))}
      </div>
    </div>
  );
}
