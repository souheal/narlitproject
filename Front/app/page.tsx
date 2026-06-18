"use client";

import { useEffect } from "react";
import { getToken } from "@/lib/auth";

export default function HomePage() {
  useEffect(() => {
    const token = getToken();
    window.location.href = token ? "/dashboard" : "/login";
  }, []);

  return null;
}
