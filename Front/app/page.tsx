"use client";

import { useEffect } from "react";

export default function HomePage() {
  useEffect(() => {
    const token = localStorage.getItem("auth_token");
    window.location.href = token ? "/dashboard" : "/login";
  }, []);

  return null;
}
