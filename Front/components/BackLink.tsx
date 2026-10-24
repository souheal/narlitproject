"use client";

import { useRouter } from "next/navigation";
import type { AnchorHTMLAttributes, MouseEvent } from "react";

// Always navigates to the given parent href. history.back() was unreliable:
// it landed on "/" (which redirects logged-in orgs straight back to the
// dashboard) or on the overview page, so the arrow looked dead.
export default function BackLink({ href, onClick, ...rest }: AnchorHTMLAttributes<HTMLAnchorElement> & { href: string }) {
  const router = useRouter();
  function handleClick(e: MouseEvent<HTMLAnchorElement>) {
    onClick?.(e);
    if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    router.push(href);
  }
  return <a href={href} onClick={handleClick} {...rest} />;
}
