const MAX_AGE = 60 * 60 * 24 * 30;

function setCookie(name: string, value: string, maxAge = MAX_AGE) {
  document.cookie = `${name}=${value}; path=/; max-age=${maxAge}; SameSite=Lax`;
}

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function deleteCookie(name: string) {
  document.cookie = `${name}=; path=/; max-age=0`;
}

// User
export const saveToken  = (token: string) => setCookie("auth_token", token);
export const getToken   = ()               => getCookie("auth_token");
export const clearToken = ()               => deleteCookie("auth_token");

// Admin
export const saveAdminToken  = (token: string) => setCookie("admin_token", token);
export const getAdminToken   = ()               => getCookie("admin_token");
export const clearAdminToken = ()               => deleteCookie("admin_token");
