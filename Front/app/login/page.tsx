"use client";

import { FormEvent, useState, useTransition } from "react";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isPending, startTransition] = useTransition();

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");

    startTransition(async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ email, password }),
        });

        const payload = await response.json();

        if (!response.ok) {
          throw new Error(
            payload?.message ||
              Object.values(payload?.errors ?? {}).flat().join(" ") ||
              "Login failed."
          );
        }

        if (payload?.data?.token) {
          localStorage.setItem("auth_token", payload.data.token);
        }
        window.location.href = "/dashboard";
      } catch (err) {
        setError(err instanceof Error ? err.message : "Something went wrong.");
      }
    });
  }

  return (
    <main className="login-shell" suppressHydrationWarning>
      <div className="narlit-backdrop narlit-backdrop-one" suppressHydrationWarning />
      <div className="narlit-backdrop narlit-backdrop-two" suppressHydrationWarning />

      <div className="login-card" suppressHydrationWarning>
        <div className="login-brand" suppressHydrationWarning>
          <img src="/logo.png" alt="NarLit" className="su-logo" />
          <div suppressHydrationWarning>
            <h1 className="login-title">
              <span style={{ color: "var(--orange)" }}>NAR</span>
              <span style={{ color: "var(--teal)" }}>LIT</span>
            </h1>
            <p className="narlit-eyebrow" style={{ marginTop: 4 }}>Welcome back</p>
          </div>
        </div>

        {error && <p className="narlit-feedback narlit-feedback-error">{error}</p>}

        <form className="login-form" onSubmit={handleSubmit}>
          <label className="narlit-field">
            <span>Email</span>
            <input
              type="email"
              value={email}
              autoComplete="email"
              placeholder="you@example.com"
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </label>

          <label className="narlit-field">
            <span>Password</span>
            <input
              type="password"
              value={password}
              autoComplete="current-password"
              placeholder="••••••••"
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </label>

          <button
            className="narlit-button narlit-button-primary"
            type="submit"
            disabled={isPending}
          >
            {isPending ? "Signing in..." : "Sign in"}
          </button>
        </form>

        <p className="login-footer">
          Don&apos;t have an account?{" "}
          <a href="/signup" className="login-link">
            Create one
          </a>
        </p>
      </div>
    </main>
  );
}
