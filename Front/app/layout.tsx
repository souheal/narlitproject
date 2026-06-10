import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "NarLit",
  description: "NarLit signup, verification, and Stripe checkout frontend.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body suppressHydrationWarning>{children}</body>
    </html>
  );
}
