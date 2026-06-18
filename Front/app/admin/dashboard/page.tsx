"use client";

import Link from "next/link";

export default function AdminDashboardPage() {
  return (
    <div suppressHydrationWarning>
      <h2 className="admin-page-title">Dashboard</h2>
      <div className="admin-stats-grid" suppressHydrationWarning>
        <Link href="/admin/organizations?status=pending" className="admin-stat-card">
          <span className="admin-stat-label">Pending Organizations</span>
          <span className="admin-stat-icon">⏳</span>
          <span className="admin-stat-hint">Review & approve</span>
        </Link>
        <Link href="/admin/organizations?status=approved" className="admin-stat-card">
          <span className="admin-stat-label">Approved Organizations</span>
          <span className="admin-stat-icon">✅</span>
          <span className="admin-stat-hint">View approved</span>
        </Link>
        <Link href="/admin/organizations?status=rejected" className="admin-stat-card">
          <span className="admin-stat-label">Rejected Organizations</span>
          <span className="admin-stat-icon">❌</span>
          <span className="admin-stat-hint">View rejected</span>
        </Link>
      </div>
    </div>
  );
}
