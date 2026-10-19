import { getToken, clearToken, getAdminToken, clearAdminToken } from "@/lib/auth";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

function redirectToLogin() {
  clearToken();
  if (typeof window !== "undefined") {
    window.location.href = "/login";
  }
}

export async function apiFetch(
  path: string,
  options: RequestInit = {}
): Promise<Response> {
  const token = getToken();

  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(options.headers as Record<string, string>),
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  if (!(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  const res = await fetch(`${API_BASE_URL}${path}`, { ...options, headers });

  if (res.status === 401) {
    redirectToLogin();
    throw new Error("Session expired. Please sign in again.");
  }

  return res;
}

export async function validateSession(): Promise<boolean> {
  try {
    const res = await apiFetch("/auth/me");
    return res.ok;
  } catch {
    return false;
  }
}

export type UserRole = "user" | "organization" | "admin";

export interface AuthenticatedUser {
  id: number;
  full_name?: string;
  email?: string;
  role_id?: number;
  role_name?: UserRole;
  has_organization_profile?: boolean;
  [key: string]: unknown;
}

export async function fetchCurrentUser(): Promise<AuthenticatedUser | null> {
  try {
    const res = await apiFetch("/auth/me");
    if (!res.ok) return null;
    const payload = await res.json();
    return payload?.data?.user ?? payload?.data ?? null;
  } catch {
    return null;
  }
}

/**
 * Verifies the signed-in user has one of the allowed roles.
 * Returns the user on success; redirects (login or /dashboard) and returns null otherwise.
 */
export async function requireRole(
  allowed: UserRole[]
): Promise<AuthenticatedUser | null> {
  const user = await fetchCurrentUser();

  if (!user) {
    if (typeof window !== "undefined") {
      const next = encodeURIComponent(window.location.pathname + window.location.search);
      clearToken();
      window.location.href = `/login?next=${next}`;
    }
    return null;
  }

  const role = user.role_name;
  if (!role || !allowed.includes(role)) {
    if (typeof window !== "undefined") {
      // Send user back to a safe landing based on their actual role
      const landing =
        role === "organization" ? "/organization/dashboard"
        : role === "admin" ? "/admin/dashboard"
        : "/dashboard";
      window.location.replace(landing);
    }
    return null;
  }

  return user;
}

function redirectToAdminLogin() {
  clearAdminToken();
  if (typeof window !== "undefined") {
    window.location.href = "/login";
  }
}

export async function adminFetch(
  path: string,
  options: RequestInit = {}
): Promise<Response> {
  const token = getAdminToken();

  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(options.headers as Record<string, string>),
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  if (!(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  const res = await fetch(`${API_BASE_URL}${path}`, { ...options, headers });

  if (res.status === 401) {
    redirectToAdminLogin();
    throw new Error("Admin session expired. Please sign in again.");
  }

  return res;
}
