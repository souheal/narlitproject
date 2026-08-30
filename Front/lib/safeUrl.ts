/**
 * URL safety helpers that block javascript:, data:, vbscript:, and other
 * script-executing schemes before they reach an href or src attribute.
 *
 * Use safeHref() for anchor href values that come from user input or API data.
 * Use safeImageSrc() for image src values from user input or API data.
 */

const ALLOWED_LINK_PROTOCOLS = new Set(["http:", "https:", "mailto:", "tel:"]);
const ALLOWED_IMAGE_PROTOCOLS = new Set(["http:", "https:"]);

function isSafeProtocol(raw: string, allowed: Set<string>): boolean {
  const trimmed = raw.trim().toLowerCase();

  if (trimmed.startsWith("/") || trimmed.startsWith("#") || trimmed.startsWith("?")) {
    return true;
  }

  const colon = trimmed.indexOf(":");
  if (colon === -1) {
    return true;
  }

  const protocol = trimmed.slice(0, colon + 1);
  return allowed.has(protocol);
}

export function safeHref(url: string | null | undefined, fallback = "#"): string {
  if (!url || typeof url !== "string") return fallback;
  return isSafeProtocol(url, ALLOWED_LINK_PROTOCOLS) ? url : fallback;
}

export function safeImageSrc(url: string | null | undefined, fallback = ""): string {
  if (!url || typeof url !== "string") return fallback;
  return isSafeProtocol(url, ALLOWED_IMAGE_PROTOCOLS) ? url : fallback;
}

/**
 * Consistent rel attribute for links that open in a new tab. Prevents both
 * referrer leakage and window.opener tab-hijacking attacks.
 */
export const EXTERNAL_LINK_REL = "noopener noreferrer nofollow";
