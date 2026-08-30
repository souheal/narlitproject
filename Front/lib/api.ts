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
