"use client";

import { useEffect, useState, useTransition } from "react";
import { adminFetch } from "@/lib/api";

interface AdminUser {
  public_id: string;
  full_name: string;
  username: string | null;
  email: string;
  phone: string | null;
  role: string;
  is_active: boolean;
  email_verified_at: string | null;
  mfa_completed: boolean;
  subscription_status: string;
  subscription_plan: string | null;
  articles_read: number;
  total_impact: string;
  created_at: string;
  last_login_at: string | null;
}

type Filter = "all" | "active" | "suspended" | "unverified" | "subscribers" | "non_subscribers";

export default function AdminUsersPage() {
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState<Filter>("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [feedback, setFeedback] = useState("");
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [isPending, startTransition] = useTransition();

  async function fetchUsers(page = 1) {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "15", page: String(page), filter });
      if (search) params.set("search", search);
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
        {loading && <p className="admin-empty">Loading…</p>}
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
                      <span className="admin-badge admin-badge-success">{u.subscription_plan ?? "active"}</span>
                    ) : (
                      <span style={{ color: "var(--muted)", fontSize: "0.8rem" }}>{u.subscription_status}</span>
                    )}
                  </td>
                  <td>{u.articles_read}</td>
                  <td>{new Date(u.created_at).toLocaleDateString()}</td>
                  <td>
                    {u.is_active ? (
                      <span className="admin-badge admin-badge-success">Active</span>
                    ) : (
                      <span className="admin-badge admin-badge-rejected">Suspended</span>
                    )}
                  </td>
                  <td>
                    <div className="admin-actions">
                      <button className="admin-btn" onClick={() => setSelectedUser(u)}>View</button>
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
        <div className="admin-modal-overlay" onClick={() => setSelectedUser(null)}>
          <div className="admin-modal" onClick={(e) => e.stopPropagation()}>
            <h3 style={{ marginTop: 0 }}>{selectedUser.full_name}</h3>
            <p style={{ color: "var(--muted)", fontSize: "0.85rem" }}>{selectedUser.email}</p>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, margin: "16px 0" }}>
              <div><strong>Role:</strong> {selectedUser.role}</div>
              <div><strong>Phone:</strong> {selectedUser.phone ?? "—"}</div>
              <div><strong>Subscription:</strong> {selectedUser.subscription_status}</div>
              <div><strong>Plan:</strong> {selectedUser.subscription_plan ?? "—"}</div>
              <div><strong>Articles read:</strong> {selectedUser.articles_read}</div>
              <div><strong>Total impact:</strong> ${selectedUser.total_impact}</div>
              <div><strong>MFA:</strong> {selectedUser.mfa_completed ? "✅" : "❌"}</div>
              <div><strong>Verified:</strong> {selectedUser.email_verified_at ? "✅" : "❌"}</div>
              <div><strong>Joined:</strong> {new Date(selectedUser.created_at).toLocaleDateString()}</div>
              <div><strong>Last login:</strong> {selectedUser.last_login_at ? new Date(selectedUser.last_login_at).toLocaleString() : "Never"}</div>
            </div>

            <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
              <button className="admin-btn admin-btn-approve" onClick={() => sendReset(selectedUser)} disabled={isPending}>
                Send password reset
              </button>
              <button className="admin-btn" onClick={() => resetMfa(selectedUser)} disabled={isPending}>
                Reset MFA
              </button>
              <button
                className="admin-btn admin-btn-reject"
                onClick={() => { toggleSuspend(selectedUser); setSelectedUser(null); }}
                disabled={isPending}
              >
                {selectedUser.is_active ? "Suspend" : "Activate"}
              </button>
              <button className="admin-btn" onClick={() => setSelectedUser(null)}>Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
