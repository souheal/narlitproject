"use client";

import { Suspense, useEffect, useRef, useState, useTransition } from "react";
import { useSearchParams } from "next/navigation";
import { adminFetch } from "@/lib/api";
import { ConfirmDialog } from "@/app/admin/_components/ConfirmDialog";
import { Skeleton, SkeletonText } from "@/components/Skeleton";
import { AdminPageFallback } from "@/app/admin/_components/AdminPageFallback";

interface AdminUser {
  public_id: string;
  full_name: string;
  username: string | null;
  email: string;
  phone: string | null;
  role: string;
  is_active: boolean;
  email_verified: boolean;
  email_verified_at: string | null;
  mfa_completed: boolean;
  subscription_status: string;
  articles_read: number;
  total_impact: string;
  registered_at: string | null;
  last_login_at: string | null;
}

type Filter = "all" | "active" | "suspended" | "unverified" | "subscribers" | "non_subscribers";

interface AdminUserDetail {
  profile: {
    public_id: string;
    full_name: string;
    username: string | null;
    email: string;
    phone: string | null;
    role: string;
    email_verified: boolean;
    email_verified_at: string | null;
    phone_mfa_completed: boolean;
    phone_mfa_completed_at: string | null;
    account_status: string;
    is_active: boolean;
    registered_at: string | null;
  };
  subscription: {
    public_id: string;
    plan: string;
    amount: string;
    currency: string;
    status: string;
    started_at: string | null;
    expires_at: string | null;
    canceled_at: string | null;
  } | null;
  payment_summary: {
    total_paid: string;
    paid_count: number;
    failed_count: number;
    recent_payments: { public_id: string; amount: string; currency: string; status: string; paid_at: string | null; created_at: string | null }[];
  };
  read_impact_summary: {
    total_reads: number;
    completed_reads: number;
    total_points: number;
    total_impact_amount: string;
    organizations_supported: number;
  };
  recent_login: {
    last_login_at: string | null;
    last_login_ip: string | null;
    locked_until: string | null;
    failed_login_attempts: number;
  };
  admin_action_history: { action: string; admin_name: string; created_at: string }[];
}

function AdminUsersPageContent() {
  const searchParams = useSearchParams();
  const openId = searchParams?.get("open") ?? null;
  const autoOpenedRef = useRef<string | null>(null);

  const [users, setUsers] = useState<AdminUser[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState<Filter>("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [detail, setDetail] = useState<AdminUserDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [isPending, startTransition] = useTransition();
  const [revokeTarget, setRevokeTarget] = useState<AdminUser | null>(null);

  async function fetchUsers(page = 1) {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "15", page: String(page) });
      if (search) params.set("search", search);
      switch (filter) {
        case "active":
          params.set("account_status", "active");
          break;
        case "suspended":
          params.set("account_status", "suspended");
          break;
        case "unverified":
          params.set("email_verified", "0");
          break;
        case "subscribers":
          params.set("subscription_status", "active");
          break;
        case "non_subscribers":
          params.set("subscription_status", "inactive");
          break;
      }
      const res = await adminFetch(`/admin/users?${params.toString()}`);
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to load users.");
      } else {
        const payload = data.data?.users;
        setUsers(payload?.data ?? []);
        setMeta({
          current_page: payload?.current_page ?? page,
          last_page: payload?.last_page ?? 1,
          total: payload?.total ?? 0,
        });
      }
    } catch {
      setError("Failed to load users.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { fetchUsers(1); /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [filter]);

  useEffect(() => {
    if (!openId || autoOpenedRef.current === openId) return;
    autoOpenedRef.current = openId;
    (async () => {
      try {
        const res = await adminFetch(`/admin/users/${openId}`);
        const data = await res.json();
        if (!res.ok) { setError(data?.message ?? "Failed to load user."); return; }
        const d = data.data?.user;
        if (!d) return;
        const stub: AdminUser = {
          public_id: d.profile.public_id,
          full_name: d.profile.full_name,
          username: d.profile.username,
          email: d.profile.email,
          phone: d.profile.phone,
          role: d.profile.role,
          is_active: d.profile.is_active,
          email_verified: d.profile.email_verified,
          email_verified_at: d.profile.email_verified_at,
          mfa_completed: d.profile.phone_mfa_completed,
          subscription_status: d.subscription?.status ?? "inactive",
          articles_read: d.read_impact_summary?.completed_reads ?? 0,
          total_impact: d.read_impact_summary?.total_impact_amount ?? "0.00",
          registered_at: d.profile.registered_at,
          last_login_at: d.recent_login?.last_login_at ?? null,
        };
        setSelectedUser(stub);
        setDetail(d);
      } catch {
        setError("Failed to load user.");
      }
    })();
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [openId]);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    fetchUsers(1);
  }

  function toggleSuspend(u: AdminUser) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const action = u.is_active ? "suspend" : "activate";
      const res = await adminFetch(`/admin/users/${u.public_id}/${action}`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? `Failed to ${action}.`);
        return;
      }
      setFeedback(`User ${action}d.`);
      fetchUsers(meta.current_page);
    });
  }

  function resetMfa(u: AdminUser) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/users/${u.public_id}/reset-mfa`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to reset MFA.");
        return;
      }
      setFeedback("MFA reset. User will be challenged on next login.");
    });
  }

  function sendReset(u: AdminUser) {
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/users/${u.public_id}/send-password-reset`, { method: "POST" });
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to send reset.");
        return;
      }
      setFeedback("Password reset email sent.");
    });
  }

  async function openDetail(u: AdminUser) {
    setSelectedUser(u);
    setDetail(null);
    setDetailLoading(true);
    try {
      const res = await adminFetch(`/admin/users/${u.public_id}`);
      const data = await res.json();
      if (res.ok) setDetail(data.data?.user ?? null);
    } catch {
      /* keep list-level fallback */
    }
    setDetailLoading(false);
  }

  function closeDetail() {
    setSelectedUser(null);
    setDetail(null);
  }

  function confirmRevokeTokens() {
    if (!revokeTarget) return;
    const u = revokeTarget;
    setFeedback(""); setError("");
    startTransition(async () => {
      const res = await adminFetch(`/admin/users/${u.public_id}/tokens`, { method: "DELETE" });
      const data = await res.json();
      if (!res.ok) {
        setError(data?.message ?? "Failed to revoke tokens.");
        return;
      }
      setFeedback(`Revoked ${data.data?.tokens_revoked ?? 0} session(s).`);
      setRevokeTarget(null);
    });
  }

  return (
    <div suppressHydrationWarning>
      <div className="admin-page-header">
        <h2 className="admin-page-title">Users</h2>
        <span style={{ color: "var(--muted)", fontSize: "0.9rem" }}>
          {meta.total.toLocaleString()} total
        </span>
      </div>

      <div className="admin-tabs">
        {(["all", "active", "suspended", "unverified", "subscribers", "non_subscribers"] as Filter[]).map((f) => (
          <button
            key={f}
            className={`admin-tab ${filter === f ? "admin-tab-active" : ""}`}
            onClick={() => setFilter(f)}
          >
            {f.replaceAll("_", " ").replace(/\b\w/g, (c) => c.toUpperCase())}
          </button>
        ))}
      </div>

      <form onSubmit={handleSearch} style={{ display: "flex", gap: 8, marginBottom: 16 }}>
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search name, email, username…"
          style={{
            flex: 1,
            padding: "10px 14px",
            border: "1px solid var(--line)",
            borderRadius: 10,
            background: "var(--panel)",
            color: "var(--foreground)",
            fontSize: "0.9rem",
          }}
        />
        <button type="submit" className="admin-btn admin-btn-approve">Search</button>
      </form>

      {feedback && <p className="narlit-feedback narlit-feedback-success">{feedback}</p>}
      {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

      <div className="admin-table-wrap">
        {loading && (
          <table className="admin-table" aria-busy="true" aria-label="Loading users">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Subscription</th>
                <th>Reads</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {Array.from({ length: 6 }).map((_, i) => (
                <tr key={i}>
                  <td><SkeletonText lines={2} widths={["70%", "40%"]} /></td>
                  <td><Skeleton width="80%" height={14} /></td>
                  <td><Skeleton width={60} height={20} radius={6} /></td>
                  <td><Skeleton width={70} height={20} radius={6} /></td>
                  <td><Skeleton width={30} height={14} /></td>
                  <td><Skeleton width={80} height={14} /></td>
                  <td><Skeleton width={70} height={20} radius={6} /></td>
                  <td>
                    <div className="admin-actions">
                      <Skeleton width={56} height={28} radius={8} />
                      <Skeleton width={72} height={28} radius={8} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {!loading && users.length === 0 && <p className="admin-empty">No users match.</p>}
        {!loading && users.length > 0 && (
          <table className="admin-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Subscription</th>
                <th>Reads</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.public_id}>
                  <td>
                    <div style={{ fontWeight: 700 }}>{u.full_name}</div>
                    {u.username && <div style={{ fontSize: "0.75rem", color: "var(--muted)" }}>@{u.username}</div>}
                  </td>
                  <td>
                    {u.email}
                    {!u.email_verified_at && <div style={{ fontSize: "0.7rem", color: "var(--orange)" }}>Unverified</div>}
                  </td>
                  <td><span className="admin-badge">{u.role}</span></td>
                  <td>
                    {u.subscription_status === "active" ? (
                      <span className="admin-badge admin-badge-success">{u.subscription_status}</span>
                    ) : (
                      <span style={{ color: "var(--muted)", fontSize: "0.8rem" }}>{u.subscription_status}</span>
                    )}
                  </td>
                  <td>{u.articles_read}</td>
                  <td>{u.registered_at ? new Date(u.registered_at).toLocaleDateString() : "—"}</td>
                  <td>
                    {u.is_active ? (
                      <span className="admin-badge admin-badge-success">Active</span>
                    ) : (
                      <span className="admin-badge admin-badge-rejected">Suspended</span>
                    )}
                  </td>
                  <td>
                    <div className="admin-actions">
                      <button className="admin-btn" onClick={() => openDetail(u)}>View</button>
                      <button className="admin-btn admin-btn-reject" onClick={() => toggleSuspend(u)} disabled={isPending}>
                        {u.is_active ? "Suspend" : "Activate"}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {meta.last_page > 1 && (
          <div style={{ display: "flex", justifyContent: "center", gap: 16, marginTop: 16 }}>
            <button
              className="admin-btn"
              disabled={meta.current_page <= 1 || loading}
              onClick={() => fetchUsers(meta.current_page - 1)}
            >
              ← Prev
            </button>
            <span style={{ alignSelf: "center", fontSize: "0.85rem", color: "var(--muted)" }}>
              Page {meta.current_page} of {meta.last_page}
            </span>
            <button
              className="admin-btn"
              disabled={meta.current_page >= meta.last_page || loading}
              onClick={() => fetchUsers(meta.current_page + 1)}
            >
              Next →
            </button>
          </div>
        )}
      </div>

      {selectedUser && (
        <div className="admin-modal-overlay" onClick={closeDetail}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 720 }}>
            <h3 style={{ marginTop: 0 }}>{detail?.profile.full_name ?? selectedUser.full_name}</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem" }}>
              {detail?.profile.email ?? selectedUser.email}
            </p>

            {detailLoading && (
              <div aria-busy="true" aria-label="Loading user details" style={{ marginTop: 12 }}>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 12 }}>
                  {Array.from({ length: 6 }).map((_, i) => (
                    <Skeleton key={i} width="80%" height={14} />
                  ))}
                </div>
                <Skeleton height={80} radius={8} />
                <div style={{ marginTop: 12, display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 8 }}>
                  <Skeleton height={64} radius={8} />
                  <Skeleton height={64} radius={8} />
                  <Skeleton height={64} radius={8} />
                </div>
              </div>
            )}

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, margin: "16px 0" }}>
              <div><strong>Role:</strong> {detail?.profile.role ?? selectedUser.role}</div>
              <div><strong>Phone:</strong> {detail?.profile.phone ?? selectedUser.phone ?? "—"}</div>
              <div><strong>Status:</strong> {detail?.profile.account_status ?? (selectedUser.is_active ? "active" : "suspended")}</div>
              <div><strong>Verified:</strong> {(detail?.profile.email_verified ?? !!selectedUser.email_verified_at) ? "✅" : "❌"}</div>
              <div><strong>MFA:</strong> {(detail?.profile.phone_mfa_completed ?? selectedUser.mfa_completed) ? "✅" : "❌"}</div>
              <div><strong>Joined:</strong> {(() => {
                const joined = detail?.profile.registered_at ?? selectedUser.registered_at;
                return joined ? new Date(joined).toLocaleDateString() : "—";
              })()}</div>
            </div>

            {detail?.subscription && (
              <div style={{ padding: 12, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8, marginBottom: 12 }}>
                <div style={{ fontWeight: 700, marginBottom: 6 }}>Subscription</div>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 6, fontSize: "0.85rem" }}>
                  <div><strong>Plan:</strong> {detail.subscription.plan}</div>
                  <div><strong>Status:</strong> {detail.subscription.status}</div>
                  <div><strong>Amount:</strong> ${detail.subscription.amount} {detail.subscription.currency}</div>
                  <div><strong>Started:</strong> {detail.subscription.started_at ? new Date(detail.subscription.started_at).toLocaleDateString() : "—"}</div>
                  <div><strong>Expires:</strong> {detail.subscription.expires_at ? new Date(detail.subscription.expires_at).toLocaleDateString() : "—"}</div>
                  <div><strong>Canceled:</strong> {detail.subscription.canceled_at ? new Date(detail.subscription.canceled_at).toLocaleDateString() : "—"}</div>
                </div>
              </div>
            )}

            {detail && (
              <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 8, marginBottom: 12 }}>
                <div style={{ padding: 10, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8 }}>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>Reads</div>
                  <div style={{ fontSize: "1.2rem", fontWeight: 800 }}>{detail.read_impact_summary.total_reads}</div>
                  <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{detail.read_impact_summary.completed_reads} completed</div>
                </div>
                <div style={{ padding: 10, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8 }}>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>Impact</div>
                  <div style={{ fontSize: "1.2rem", fontWeight: 800 }}>${detail.read_impact_summary.total_impact_amount}</div>
                  <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{detail.read_impact_summary.organizations_supported} orgs</div>
                </div>
                <div style={{ padding: 10, background: "var(--panel)", border: "1px solid var(--line)", borderRadius: 8 }}>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)" }}>Paid</div>
                  <div style={{ fontSize: "1.2rem", fontWeight: 800 }}>${detail.payment_summary.total_paid}</div>
                  <div style={{ fontSize: "0.7rem", color: "var(--muted)" }}>{detail.payment_summary.paid_count} payments · {detail.payment_summary.failed_count} failed</div>
                </div>
              </div>
            )}

            {detail && (
              <div style={{ fontSize: "0.8rem", color: "var(--muted)", marginBottom: 12 }}>
                Last login {detail.recent_login.last_login_at ? new Date(detail.recent_login.last_login_at).toLocaleString() : "never"}
                {detail.recent_login.last_login_ip && <> from <span style={{ fontFamily: "monospace" }}>{detail.recent_login.last_login_ip}</span></>}
                {detail.recent_login.locked_until && (
                  <> · <span style={{ color: "var(--orange)" }}>locked until {new Date(detail.recent_login.locked_until).toLocaleString()}</span></>
                )}
              </div>
            )}

            {detail && detail.admin_action_history.length > 0 && (
              <>
                <div style={{ fontWeight: 700, marginBottom: 6 }}>Admin actions</div>
                <ul style={{ margin: 0, paddingLeft: 20, fontSize: "0.8rem", maxHeight: 120, overflow: "auto" }}>
                  {detail.admin_action_history.map((log, i) => (
                    <li key={i}>
                      <strong>{log.action}</strong> by {log.admin_name} · {new Date(log.created_at).toLocaleString()}
                    </li>
                  ))}
                </ul>
              </>
            )}

            <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginTop: 16 }}>
              <button className="admin-btn admin-btn-approve" onClick={() => sendReset(selectedUser)} disabled={isPending}>
                Send password reset
              </button>
              <button className="admin-btn" onClick={() => resetMfa(selectedUser)} disabled={isPending}>
                Reset MFA
              </button>
              <button className="admin-btn admin-btn-reject" onClick={() => setRevokeTarget(selectedUser)} disabled={isPending}>
                Revoke sessions
              </button>
              <button
                className="admin-btn admin-btn-reject"
                onClick={() => { toggleSuspend(selectedUser); closeDetail(); }}
                disabled={isPending}
              >
                {selectedUser.is_active ? "Suspend" : "Activate"}
              </button>
              <button className="admin-btn" onClick={closeDetail}>Close</button>
            </div>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={revokeTarget !== null}
        title="Revoke all sessions?"
        variant="danger"
        confirmLabel="Revoke sessions"
        loading={isPending}
        message={
          revokeTarget ? (
            <>
              Revoke every active session for <strong>{revokeTarget.full_name}</strong>{" "}
              ({revokeTarget.email})? They will be signed out from every device and
              will need to log in again.
            </>
          ) : null
        }
        onConfirm={confirmRevokeTokens}
        onCancel={() => setRevokeTarget(null)}
      />
    </div>
  );
}

export default function AdminUsersPage() {
  return (
    <Suspense fallback={<AdminPageFallback title="Users" tabs={6} stats={0} />}>
      <AdminUsersPageContent />
    </Suspense>
  );
}
