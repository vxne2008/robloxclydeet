"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { Eye, EyeOff, UserPlus } from "lucide-react"
import Link from "next/link"

const STRANDS = [
  "ICT STALLMAN",
  "ICT ZUCKERBERG",
  "ABM MASLOW",
  "HUMSS VOLTAIRE",
]

export default function RegistrationForm() {
  const router = useRouter()
  const [role, setRole] = useState("student")
  const [formData, setFormData] = useState({
    last_name: "",
    first_name: "",
    middle_name: "",
    email: "",
    password: "",
    confirm_password: "",
    lrn: "",
    section: "",
    contact: "",
    home_address: "",
    birth_date: "",
    guardian_name: "",
    guardian_contact: "",
  })
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)
  const [error, setError] = useState("")
  const [success, setSuccess] = useState("")
  const [loading, setLoading] = useState(false)

  function updateField(field: string, value: string) {
    setFormData((prev) => ({ ...prev, [field]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError("")
    setSuccess("")
    setLoading(true)

    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...formData, role }),
      })

      const data = await res.json()

      if (!res.ok) {
        setError(data.error || "Registration failed")
        return
      }

      setSuccess("You're successfully registered!")
      setTimeout(() => router.push("/"), 2000)
    } catch {
      setError("An error occurred. Please try again.")
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-50 border-b border-border bg-muted/80 px-6 py-4 shadow-2xl backdrop-blur-xl">
        <div className="mx-auto flex max-w-5xl items-center gap-4">
          <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-muted/50 text-lg font-bold text-primary">
            CF
          </div>
          <div>
            <h1 className="text-lg font-bold text-foreground">
              Children of Fatima School of Mabalacat Inc.
            </h1>
            <p className="text-xs text-muted-foreground">
              Account Registration
            </p>
          </div>
        </div>
      </header>

      <main className="flex flex-1 items-center justify-center p-6">
        <section className="w-full max-w-3xl rounded-2xl border border-border bg-card p-8 shadow-2xl backdrop-blur-sm transition-all hover:border-primary/30">
          <h2 className="mb-2 flex items-center gap-3 text-2xl font-extrabold text-foreground">
            <UserPlus className="h-6 w-6 text-primary" />
            Create Account
          </h2>
          <p className="mb-6 text-sm text-muted-foreground">
            Register to access the school portal
          </p>

          {error && (
            <div
              className="mb-4 rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-center text-sm font-semibold text-red-400"
              role="alert"
            >
              {error}
            </div>
          )}

          {success && (
            <div className="mb-4 rounded-xl border border-success/40 bg-success/10 p-3 text-center text-sm font-semibold text-green-400">
              {success}
            </div>
          )}

          <form onSubmit={handleSubmit}>
            {/* Role selection */}
            <fieldset className="mb-6">
              <legend className="mb-2 text-sm font-semibold text-muted-foreground">
                Select Role
              </legend>
              <div className="grid grid-cols-3 gap-3">
                {["student", "adviser", "guidance"].map((r) => (
                  <label key={r} className="cursor-pointer">
                    <input
                      type="radio"
                      name="role"
                      value={r}
                      checked={role === r}
                      onChange={() => setRole(r)}
                      className="sr-only"
                    />
                    <div
                      className={`flex items-center justify-center rounded-xl border px-4 py-3 text-sm font-medium capitalize transition-all ${
                        role === r
                          ? "border-primary bg-primary/10 text-primary"
                          : "border-border text-muted-foreground hover:border-primary/40 hover:bg-muted/30"
                      }`}
                    >
                      {r}
                    </div>
                  </label>
                ))}
              </div>
            </fieldset>

            {/* Name fields */}
            <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-muted-foreground">
                  Last Name *
                </label>
                <input
                  type="text"
                  value={formData.last_name}
                  onChange={(e) => updateField("last_name", e.target.value)}
                  required
                  className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-muted-foreground">
                  First Name *
                </label>
                <input
                  type="text"
                  value={formData.first_name}
                  onChange={(e) => updateField("first_name", e.target.value)}
                  required
                  className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-muted-foreground">
                  Middle Name
                </label>
                <input
                  type="text"
                  value={formData.middle_name}
                  onChange={(e) => updateField("middle_name", e.target.value)}
                  className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>
            </div>

            {/* Student-specific fields */}
            {role === "student" && (
              <>
                <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-sm font-medium text-muted-foreground">
                      LRN *
                    </label>
                    <input
                      type="text"
                      value={formData.lrn}
                      onChange={(e) => updateField("lrn", e.target.value)}
                      required
                      className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-medium text-muted-foreground">
                      Strand / Section *
                    </label>
                    <select
                      value={formData.section}
                      onChange={(e) => updateField("section", e.target.value)}
                      required
                      className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                      <option value="">Select strand</option>
                      {STRANDS.map((s) => (
                        <option key={s} value={s}>
                          {s}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
                <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-sm font-medium text-muted-foreground">
                      Contact Number
                    </label>
                    <input
                      type="text"
                      value={formData.contact}
                      onChange={(e) => updateField("contact", e.target.value)}
                      className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-medium text-muted-foreground">
                      Birth Date
                    </label>
                    <input
                      type="date"
                      value={formData.birth_date}
                      onChange={(e) => updateField("birth_date", e.target.value)}
                      className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                  </div>
                </div>
                <div className="mb-4">
                  <label className="mb-1 block text-sm font-medium text-muted-foreground">
                    Home Address
                  </label>
                  <textarea
                    value={formData.home_address}
                    onChange={(e) => updateField("home_address", e.target.value)}
                    rows={2}
                    className="w-full rounded-xl border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                  />
                </div>
                <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-sm font-medium text-muted-foreground">
                      Guardian Name
                    </label>
                    <input
                      type="text"
                      value={formData.guardian_name}
                      onChange={(e) =>
                        updateField("guardian_name", e.target.value)
                      }
                      className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-medium text-muted-foreground">
                      Guardian Contact
                    </label>
                    <input
                      type="text"
                      value={formData.guardian_contact}
                      onChange={(e) =>
                        updateField("guardian_contact", e.target.value)
                      }
                      className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                  </div>
                </div>
              </>
            )}

            {/* Adviser-specific fields */}
            {role === "adviser" && (
              <div className="mb-4">
                <label className="mb-1 block text-sm font-medium text-muted-foreground">
                  Strand / Section *
                </label>
                <select
                  value={formData.section}
                  onChange={(e) => updateField("section", e.target.value)}
                  required
                  className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                >
                  <option value="">Select strand</option>
                  {STRANDS.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Email field */}
            <div className="mb-4">
              <label className="mb-1 block text-sm font-medium text-muted-foreground">
                Email Address *
              </label>
              <input
                type="email"
                value={formData.email}
                onChange={(e) => updateField("email", e.target.value)}
                required
                placeholder="example@cfsieducation.com"
                className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>

            {/* Password fields */}
            <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label className="mb-1 block text-sm font-medium text-muted-foreground">
                  Password *
                </label>
                <div className="relative">
                  <input
                    type={showPassword ? "text" : "password"}
                    value={formData.password}
                    onChange={(e) => updateField("password", e.target.value)}
                    required
                    className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 pr-10 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-primary"
                    aria-label={
                      showPassword ? "Hide password" : "Show password"
                    }
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                </div>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-muted-foreground">
                  Confirm Password *
                </label>
                <div className="relative">
                  <input
                    type={showConfirm ? "text" : "password"}
                    value={formData.confirm_password}
                    onChange={(e) =>
                      updateField("confirm_password", e.target.value)
                    }
                    required
                    className="w-full rounded-full border border-border bg-black/30 px-4 py-2.5 pr-10 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirm(!showConfirm)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-primary"
                    aria-label={
                      showConfirm ? "Hide password" : "Show password"
                    }
                  >
                    {showConfirm ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                </div>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="mt-2 flex w-full items-center justify-center gap-3 rounded-full bg-gradient-to-br from-primary to-accent px-4 py-3 text-base font-bold text-primary-foreground shadow-lg shadow-primary/40 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/50 disabled:opacity-60"
            >
              <UserPlus className="h-5 w-5" />
              <span>{loading ? "Registering..." : "Create Account"}</span>
            </button>

            <div className="mt-4 text-center">
              <Link
                href="/"
                className="inline-block rounded-lg px-3 py-2 text-sm font-semibold text-muted-foreground transition-all hover:bg-muted/30 hover:text-foreground"
              >
                Already have an account? Login
              </Link>
            </div>
          </form>
        </section>
      </main>
    </div>
  )
}
