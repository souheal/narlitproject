const MAX_AGE = 60 * 60 * 24 * 30;

function cookieFlags(): string {
  const secure = process.env.NODE_ENV === "production" ? "; Secure" : "";
  return `SameSite=Strict${secure}`;
}

function setCookie(name: string, value: string, maxAge = MAX_AGE) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; ${cookieFlags()}`;
}

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function deleteCookie(name: string) {
  document.cookie = `${name}=; path=/; max-age=0; ${cookieFlags()}`;
}

// User
export const saveToken  = (token: string) => setCookie("auth_token", token);
export const getToken   = ()               => getCookie("auth_token");
export const clearToken = () => {
  deleteCookie("auth_token");
  deleteCookie("auth_role");
  clearCachedUser();
};

// Cached profile for instant renders between page navigations.
// Not for authorization — session validity is still verified server-side.
const CACHED_USER_KEY = "narlit_cached_user_v1";

export interface CachedUser {
  full_name?: string;
  email?: string;
  role_name?: string;
}

export function cacheUser(user: CachedUser): void {
  if (typeof window === "undefined") return;
  try {
    sessionStorage.setItem(CACHED_USER_KEY, JSON.stringify(user));
  } catch { /* storage unavailable; just skip */ }
}

export function getCachedUser(): CachedUser | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = sessionStorage.getItem(CACHED_USER_KEY);
    return raw ? (JSON.parse(raw) as CachedUser) : null;
  } catch {
    return null;
  }
}

export function clearCachedUser(): void {
  if (typeof window === "undefined") return;
  try { sessionStorage.removeItem(CACHED_USER_KEY); } catch { /* ignore */ }
}

// Remembers org accounts so middleware can send them to the org area, not the member one
export const saveRole = (role: string) => setCookie("auth_role", role);
export const clearRole = () => deleteCookie("auth_role");

// Admin
export const saveAdminToken  = (token: string) => setCookie("admin_token", token);
export const getAdminToken   = ()               => getCookie("admin_token");
export const clearAdminToken = ()               => deleteCookie("admin_token");
