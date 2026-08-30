import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === "production";

const apiBase = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";
const apiOrigin = (() => {
  try {
    return new URL(apiBase).origin;
  } catch {
    return "http://127.0.0.1:8000";
  }
})();

const stripeCsp = ["https://js.stripe.com", "https://checkout.stripe.com", "https://api.stripe.com"];

// Development uses inline scripts/styles from Next.js React refresh + Fast Refresh.
// Production locks these down.
const cspDirectives: Record<string, string[]> = {
  "default-src": ["'self'"],
  "script-src": [
    "'self'",
    ...(isProd ? [] : ["'unsafe-inline'", "'unsafe-eval'"]),
    ...stripeCsp,
  ],
  "style-src": ["'self'", "'unsafe-inline'"], // Next.js + inline styles across the app
  "img-src": ["'self'", "data:", "blob:", "https:"],
  "font-src": ["'self'", "data:"],
  "connect-src": [
    "'self'",
    apiOrigin,
    ...(isProd ? [] : ["ws:", "wss:"]),
    ...stripeCsp,
  ],
  "frame-src": ["'self'", "https://js.stripe.com", "https://hooks.stripe.com"],
  "frame-ancestors": ["'none'"],
  "base-uri": ["'self'"],
  "form-action": ["'self'"],
  "object-src": ["'none'"],
  ...(isProd ? { "upgrade-insecure-requests": [] } : {}),
};

const csp = Object.entries(cspDirectives)
  .map(([key, values]) => (values.length > 0 ? `${key} ${values.join(" ")}` : key))
  .join("; ");

const securityHeaders = [
  { key: "Content-Security-Policy", value: csp },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=(), interest-cohort=()" },
  { key: "X-DNS-Prefetch-Control", value: "on" },
  { key: "X-XSS-Protection", value: "0" }, // Modern browsers use CSP; legacy filter can be an XSS vector
  ...(isProd
    ? [{ key: "Strict-Transport-Security", value: "max-age=63072000; includeSubDomains; preload" }]
    : []),
];

const nextConfig: NextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: securityHeaders,
      },
    ];
  },
};

export default nextConfig;
