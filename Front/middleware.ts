import { NextRequest, NextResponse } from "next/server";

/**
 * Build an absolute URL rooted at the *public* origin behind our reverse proxy.
 * Using request.nextUrl directly keeps the internal 127.0.0.1:3000 origin in
 * some Next.js versions, so we reconstruct the URL from forwarded headers.
 */
function buildUrl(request: NextRequest, path: string): URL {
  const forwardedHost = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  const forwardedProto = request.headers.get("x-forwarded-proto") ?? request.nextUrl.protocol.replace(":", "");
  const origin = forwardedHost ? `${forwardedProto}://${forwardedHost}` : request.nextUrl.origin;
  return new URL(path, origin);
}

// Pages accessible to everyone — never redirected
const TRULY_PUBLIC_PATHS = [
  "/",
  "/about",
  "/contact",
  "/terms",
  "/privacy",
  "/cookies",
];

// Auth pages — logged-in users are bounced to /dashboard
const AUTH_PAGES = [
  "/login",
  "/signup",
  "/signup/organizer",
  "/forgot-password",
];

// Post-payment pages — accessible to logged-in users only, but always let through
const BILLING_PAGES = [
  "/billing/success",
  "/billing/cancel",
];

// Top-level segments of every page under app/. A path outside this list is not a
// page, so it goes straight to Next's 404 instead of bouncing guests to /login.
// When you add a new top-level page folder, add its segment here too.
const KNOWN_SEGMENTS = new Set([
  "about", "account", "achievements", "admin", "articles", "billing", "bookmarks",
  "contact", "cookies", "dashboard", "explore", "forgot-password", "help", "history",
  "impact", "login", "notifications", "onboarding", "organization", "organizations",
  "preferences", "privacy", "profile", "search", "signup", "subscription", "terms",
]);

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const firstSegment = pathname.split("/")[1] ?? "";
  if (pathname !== "/" && !KNOWN_SEGMENTS.has(firstSegment)) {
    return NextResponse.next();
  }

  const isTrulyPublic = TRULY_PUBLIC_PATHS.some(
    (p) => pathname === p || pathname.startsWith(p + "/")
  );
  const isAuthPage = AUTH_PAGES.some(
    (p) => pathname === p || pathname.startsWith(p + "/")
  );
  const isBillingPage = BILLING_PAGES.some(
    (p) => pathname === p || pathname.startsWith(p + "/")
  );

  const token = request.cookies.get("auth_token")?.value;
  const isOrg = request.cookies.get("auth_role")?.value === "organization";
  const adminToken = request.cookies.get("admin_token")?.value;

  // Admin routes — require admin_token
  if (pathname.startsWith("/admin")) {
    if (!adminToken) {
      const loginUrl = buildUrl(request, "/login");
      loginUrl.searchParams.set("next", pathname);
      return NextResponse.redirect(loginUrl);
    }
    return NextResponse.next();
  }

  // Logged-in org accounts skip the public landing page
  if (isOrg && token && pathname === "/") {
    return NextResponse.redirect(buildUrl(request, "/organization/dashboard"));
  }

  // Truly public pages — always let through
  if (isTrulyPublic) {
    return NextResponse.next();
  }

  // Logged-in user tries to open login/signup → send to dashboard
  if (isAuthPage && token) {
    return NextResponse.redirect(buildUrl(request, isOrg ? "/organization/dashboard" : "/dashboard"));
  }

  // Org accounts never land on the member dashboard
  if (isOrg && token && pathname === "/dashboard") {
    return NextResponse.redirect(buildUrl(request, "/organization/dashboard"));
  }

  // Auth pages accessible to guests
  if (isAuthPage || isBillingPage) {
    return NextResponse.next();
  }

  // Guest tries to open a protected page → send to login
  if (!token) {
    const loginUrl = buildUrl(request, "/login");
    loginUrl.searchParams.set("next", pathname);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|logo.png|earth/|places/|api/).*)"],
};
