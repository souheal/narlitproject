"use client";

import { useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useSearchParams } from "next/navigation";
import { adminFetch } from "@/lib/api";
import { Skeleton, SkeletonText } from "@/components/Skeleton";

type Status = "pending" | "approved" | "rejected";

const VALID_STATUSES: Status[] = ["pending", "approved", "rejected"];

interface Org {
  public_id: string;
  organization_name: string;
  email: string;
  irs_verified: boolean;
  verification_status: Status;
  reviewed_at: string | null;
  rejection_reason: string | null;
}

export default function AdminOrganizationsPage() {
  const searchParams = useSearchParams();
  const initialTab = useMemo<Status>(() => {
    const raw = searchParams?.get("status");
    return raw && (VALID_STATUSES as string[]).includes(raw) ? (raw as Status) : "pending";
  }, [searchParams]);
  const highlightId = searchParams?.get("open") ?? null;

  const [orgs, setOrgs]           = useState<Org[]>([]);
  const [tab, setTab]             = useState<Status>(initialTab);
  const [loading, setLoading]     = useState(true);
  const [rejectId, setRejectId]   = useState<string | null>(null);
  const [reason, setReason]       = useState("");
  const [feedback, setFeedback]   = useState("");
  const [error, setError]         = useState("");
  const [isPending, startTransition] = useTransition();
  const highlightRef = useRef<HTMLTableRowElement | null>(null);

  useEffect(() => {
    if (highlightId && highlightRef.current) {
      highlightRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }, [highlightId, orgs]);

  async function fetchOrgs(status: Status) {
    setLoading(true);
    setError("");
    try {
      const res  = await adminFetch(`/admin/organizations?status=${status}`);
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to load organizations.");
        setOrgs([]);
      } else {
        setOrgs(data.data?.organizations?.data ?? []);
      }
    } catch {
      setError("Failed to load organizations.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { fetchOrgs(tab); }, [tab]);

  function handleApprove(publicId: string) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res  = await adminFetch(`/admin/organizations/${publicId}/approve`, {
        method: "POST",
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to approve."); return; }
      setFeedback("Organization approved successfully.");
      fetchOrgs(tab);
    });
  }

  function handleRejectSubmit() {
    if (!rejectId || !reason.trim()) return;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res  = await adminFetch(`/admin/organizations/${rejectId}/reject`, {
        method: "POST",
        body: JSON.stringify({ reason }),
      });
      const data = await res.json();
      if (!res.ok) { setError(data?.message ?? "Failed to reject."); return; }
      setFeedback("Organization rejected.");
      setRejectId(null);
      setReason("");
      fetchOrgs(tab);
    });
  }

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header" suppressHydrationWarning>
        <h2 className="admin-page-title">Organizations</h2>
        <div className="admin-tabs" suppressHydrationWarning>
          {(["pending", "approved", "rejected"] as Status[]).map((s) => (
            <button
              key={s}
              onClick={() => setTab(s)}
              className={`admin-tab ${tab === s ? "admin-tab-active" : ""}`}
            >
              {s.charAt(0).toUpperCase() + s.slice(1)}
            </button>
          ))}
        </div>
      </div>

      {feedback && <p className="narlit-feedback narlit-feedback-success" style={{ marginBottom: 16 }}>{feedback}</p>}
      {error    && <p className="narlit-feedback narlit-feedback-error"   style={{ marginBottom: 16 }}>{error}</p>}

      {loading ? (
        <div className="admin-table-wrap" suppressHydrationWarning>
          <table className="admin-table" aria-busy="true" aria-label="Loading organizations">
            <thead>
              <tr>
                <th>Organization</th>
                <th>Email</th>
                <th>IRS</th>
                <th>Status</th>
                {tab === "pending" && <th>Actions</th>}
                {tab === "rejected" && <th>Reason</th>}
              </tr>
            </thead>
            <tbody>
              {Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  <td><Skeleton width="70%" height={14} /></td>
                  <td><Skeleton width="80%" height={14} /></td>
                  <td><Skeleton width={70} height={20} radius={6} /></td>
                  <td><Skeleton width={70} height={20} radius={6} /></td>
                  {tab === "pending" && (
                    <td>
                      <div className="admin-actions">
                        <Skeleton width={72} height={28} radius={8} />
                        <Skeleton width={64} height={28} radius={8} />
                      </div>
                    </td>
                  )}
                  {tab === "rejected" && (
                    <td><SkeletonText lines={2} widths={["80%", "50%"]} /></td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : orgs.length === 0 ? (
        <div className="admin-empty" suppressHydrationWarning>
          <p>No {tab} organizations.</p>
        </div>
      ) : (
        <div className="admin-table-wrap" suppressHydrationWarning>
          <table className="admin-table">
            <thead>
              <tr>
                <th>Organization</th>
                <th>Email</th>
                <th>IRS</th>
                <th>Status</th>
                {tab === "pending" && <th>Actions</th>}
                {tab === "rejected" && <th>Reason</th>}
              </tr>
            </thead>
            <tbody>
              {orgs.map((org) => (
                <tr
                  key={org.public_id}
                  ref={highlightId === org.public_id ? highlightRef : undefined}
                  style={highlightId === org.public_id ? { background: "rgba(230,126,34,0.08)", outline: "2px solid rgba(230,126,34,0.5)" } : undefined}
                >
                  <td style={{ fontWeight: 700 }}>{org.organization_name}</td>
                  <td style={{ color: "var(--muted)" }}>{org.email}</td>
                  <td>
                    <span className={`admin-badge ${org.irs_verified ? "admin-badge-success" : "admin-badge-warn"}`}>
                      {org.irs_verified ? "Verified" : "Unverified"}
                    </span>
                  </td>
                  <td>
                    <span className={`admin-badge admin-badge-${org.verification_status}`}>
                      {org.verification_status}
                    </span>
                  </td>
                  {tab === "pending" && (
                    <td>
                      <div className="admin-actions" suppressHydrationWarning>
                        <button
                          className="admin-btn admin-btn-approve"
                          disabled={isPending}
                          onClick={() => handleApprove(org.public_id)}
                        >
                          Approve
                        </button>
                        <button
                          className="admin-btn admin-btn-reject"
                          disabled={isPending}
                          onClick={() => { setRejectId(org.public_id); setReason(""); setError(""); }}
                        >
                          Reject
                        </button>
                      </div>
                    </td>
                  )}
                  {tab === "rejected" && (
                    <td style={{ color: "var(--muted)", fontSize: "0.88rem" }}>
                      {org.rejection_reason ?? "—"}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {rejectId && (
        <div className="admin-modal-overlay" suppressHydrationWarning onClick={() => setRejectId(null)}>
          <div className="admin-modal" suppressHydrationWarning onClick={(e) => e.stopPropagation()}>
            <h3 style={{ margin: "0 0 16px" }}>Reject Organization</h3>
            <label className="narlit-field">
              <span>Reason</span>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Provide a reason for rejection..."
                rows={4}
                style={{ resize: "vertical", borderRadius: 12, padding: "10px 13px", border: "1px solid rgba(8,47,55,0.12)", fontSize: "0.92rem", width: "100%" }}
              />
            </label>
            <div className="su-actions" style={{ marginTop: 16 }} suppressHydrationWarning>
              <button
                className="narlit-button narlit-button-primary"
                disabled={isPending || !reason.trim()}
                onClick={handleRejectSubmit}
              >
                {isPending ? "Rejecting..." : "Confirm Reject"}
              </button>
              <button
                className="narlit-button narlit-button-secondary"
                onClick={() => setRejectId(null)}
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
