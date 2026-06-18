import { NextRequest, NextResponse } from "next/server";

const PUBLIC_PATHS = [
  "/login",
  "/signup",
  "/signup/organizer",
  "/forgot-password",
  "/billing/success",
  "/billing/cancel",
];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const isPublic = PUBLIC_PATHS.some(
    (p) => pathname === p || pathname.startsWith(p + "/")
  );

  const token     = request.cookies.get("auth_token")?.value;
  const adminToken = request.cookies.get("admin_token")?.value;

  // Admin routes — require admin_token
  if (pathname.startsWith("/admin") && pathname !== "/admin/login") {
    if (!adminToken) {
      return NextResponse.redirect(new URL("/admin/login", request.url));
    }
    return NextResponse.next();
  }

  // Logged-in user tries to open login/signup → send to dashboard
  if (isPublic && token && pathname !== "/admin/login") {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  // Guest tries to open a protected page → send to login
  if (!isPublic && !token) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("next", pathname);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|logo.png|api/).*)"],
};
