"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { Eye, EyeOff, LogIn } from "lucide-react"
import Link from "next/link"

export default function LoginForm() {
  const router = useRouter()
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState("")
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError("")
    setLoading(true)

    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      })

      const data = await res.json()

      if (!res.ok) {
        setError(data.error || "Login failed")
        return
      }

      router.push(data.redirectTo)
    } catch {
      setError("An error occurred. Please try again.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center p-4">
      <section
        className="w-full max-w-md rounded-2xl border border-border bg-card p-8 shadow-2xl backdrop-blur-sm transition-all hover:border-primary/30 hover:shadow-primary/10"
        aria-label="Login card"
      >
        <div className="mb-6 flex items-center gap-4 border-b border-border pb-5">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-border bg-muted/50 text-2xl font-bold text-primary">
            CF
          </div>
          <div>
            <h1 className="text-lg font-extrabold leading-tight text-foreground text-balance">
              Children of Fatima School of Mabalacat Inc.
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">Portal Login</p>
          </div>
        </div>

        <div className="mb-5 flex items-center gap-3">
          <LogIn className="h-6 w-6 text-primary" />
          <h2 className="text-2xl font-extrabold text-foreground">
            Welcome Back
          </h2>
        </div>

        {error && (
          <div
            className="mb-4 rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-center text-sm font-semibold text-red-400"
            role="alert"
          >
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} autoComplete="on">
          <div className="mb-4">
            <label
              htmlFor="email"
              className="mb-1.5 block text-sm font-semibold text-muted-foreground"
            >
              Email Address
            </label>
            <input
              id="email"
              name="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full rounded-xl border border-border bg-black/30 px-4 py-3 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div className="mb-4">
            <label
              htmlFor="password"
              className="mb-1.5 block text-sm font-semibold text-muted-foreground"
            >
              Password
            </label>
            <div className="relative">
              <input
                id="password"
                name="password"
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full rounded-xl border border-border bg-black/30 px-4 py-3 pr-12 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg border border-border bg-muted/30 p-1.5 text-muted-foreground transition-all hover:border-primary hover:bg-muted/50"
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? (
                  <EyeOff className="h-4 w-4" />
                ) : (
                  <Eye className="h-4 w-4" />
                )}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="mt-5 flex w-full items-center justify-center gap-3 rounded-xl bg-gradient-to-br from-primary to-accent px-4 py-3.5 text-base font-bold text-primary-foreground shadow-lg shadow-primary/40 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/50 disabled:opacity-60"
          >
            <LogIn className="h-5 w-5" />
            <span>{loading ? "Logging in..." : "Login to Portal"}</span>
          </button>

          <div className="mt-4 text-center">
            <Link
              href="/register"
              className="inline-block rounded-lg px-3 py-2 text-sm font-semibold text-muted-foreground transition-all hover:bg-muted/30 hover:text-foreground"
            >
              {"Don't have an account? Register"}
            </Link>
          </div>
        </form>
      </section>
    </main>
  )
}
