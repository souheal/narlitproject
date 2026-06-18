"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { clearAdminToken } from "@/lib/auth";

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  if (pathname === "/admin/login") return <>{children}</>;

  function handleLogout() {
    clearAdminToken();
    window.location.href = "/admin/login";
  }

  const navItems = [
    { href: "/admin/dashboard",      label: "Dashboard",      icon: "🏠" },
    { href: "/admin/organizations",  label: "Organizations",  icon: "🏢" },
  ];

  return (
    <div className="admin-shell" suppressHydrationWarning>
      <aside className="admin-sidebar" suppressHydrationWarning>
        <div className="admin-brand" suppressHydrationWarning>
          <span style={{ color: "var(--orange)", fontWeight: 900 }}>NAR</span>
          <span style={{ color: "var(--teal)", fontWeight: 900 }}>LIT</span>
          <span className="admin-brand-label">Admin</span>
        </div>

        <nav className="admin-nav" suppressHydrationWarning>
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={`admin-nav-item ${pathname.startsWith(item.href) ? "admin-nav-item-active" : ""}`}
            >
              <span>{item.icon}</span>
              <span>{item.label}</span>
            </Link>
          ))}
        </nav>

        <button className="admin-logout-btn" onClick={handleLogout}>
          Sign out
        </button>
      </aside>

      <main className="admin-main" suppressHydrationWarning>
        {children}
      </main>
    </div>
  );
}
