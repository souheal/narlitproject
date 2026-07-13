import { NextRequest, NextResponse } from "next/server";

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

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

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
  const adminToken = request.cookies.get("admin_token")?.value;

  // Admin routes — require admin_token
  if (pathname.startsWith("/admin") && pathname !== "/admin/login") {
    if (!adminToken) {
      return NextResponse.redirect(new URL("/admin/login", request.url));
    }
    return NextResponse.next();
  }

  // Truly public pages — always let through
  if (isTrulyPublic) {
    return NextResponse.next();
  }

  // Logged-in user tries to open login/signup → send to dashboard
  if (isAuthPage && token) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  // Auth pages accessible to guests
  if (isAuthPage || isBillingPage) {
    return NextResponse.next();
  }

  // Guest tries to open a protected page → send to login
  if (!token) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("next", pathname);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|logo.png|api/).*)"],
};
