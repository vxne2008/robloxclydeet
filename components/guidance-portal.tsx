"use client"

import { useState, useEffect, useCallback } from "react"
import { useRouter } from "next/navigation"
import {
  LayoutDashboard,
  Users,
  School,
  LogOut,
  Menu,
  Search,
} from "lucide-react"

type StrandCard = {
  name: string
  badge: string
  key: string
  initials: string
  adviser: string
  studentCount: number
}

type Student = {
  last_name: string
  first_name: string
  middle_name: string
  lrn: string
  email: string
  strand: string
  section: string
}

const guidanceTabs = [
  { id: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { id: "strands", label: "Strands", icon: School },
  { id: "students", label: "All Students", icon: Users },
]

export default function GuidancePortal() {
  const router = useRouter()
  const [activeTab, setActiveTab] = useState("dashboard")
  const [guidanceName, setGuidanceName] = useState("Guidance")
  const [strandCards, setStrandCards] = useState<StrandCard[]>([])
  const [students, setStudents] = useState<Student[]>([])
  const [totalStudents, setTotalStudents] = useState(0)
  const [loading, setLoading] = useState(true)
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState("")

  const fetchData = useCallback(async () => {
    try {
      const res = await fetch("/api/guidance/data")
      if (!res.ok) {
        router.push("/")
        return
      }
      const data = await res.json()
      setGuidanceName(data.guidanceName)
      setStrandCards(data.strandCards)
      setStudents(data.students)
      setTotalStudents(data.totalStudents)
    } catch {
      router.push("/")
    } finally {
      setLoading(false)
    }
  }, [router])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  async function handleLogout() {
    await fetch("/api/auth/logout", { method: "POST" })
    router.push("/")
  }

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    )
  }

  const initial = (guidanceName[0] || "G").toUpperCase()

  const filteredStudents = students.filter((s) => {
    if (!searchQuery) return true
    const q = searchQuery.toLowerCase()
    return (
      s.last_name.toLowerCase().includes(q) ||
      s.first_name.toLowerCase().includes(q) ||
      s.lrn.toLowerCase().includes(q) ||
      s.email.toLowerCase().includes(q) ||
      s.strand.toLowerCase().includes(q)
    )
  })

  return (
    <div className="flex min-h-screen">
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-border bg-muted/95 p-5 shadow-2xl backdrop-blur-xl transition-transform lg:static lg:translate-x-0 ${sidebarOpen ? "translate-x-0" : "-translate-x-full"}`}
      >
        <div className="mb-6 flex items-center gap-3 border-b border-border pb-5">
          <img
            src="/logo.jpg"
            alt="Children of Fatima School of Mabalacat Inc."
            className="h-12 w-12 rounded-xl object-cover border border-border shadow-lg"
          />
          <div>
            <div className="text-lg font-extrabold text-foreground">CFSI</div>
            <div className="text-xs text-muted-foreground">
              Guidance Portal
            </div>
          </div>
        </div>

        <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
          Navigation
        </p>
        <nav className="flex flex-col gap-1">
          {guidanceTabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => {
                setActiveTab(tab.id)
                setSidebarOpen(false)
              }}
              className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all ${
                activeTab === tab.id
                  ? "border border-primary/40 bg-primary/10 text-primary shadow-lg shadow-primary/10"
                  : "border border-transparent text-muted-foreground hover:border-border hover:bg-muted/50 hover:text-foreground"
              }`}
            >
              <tab.icon className="h-5 w-5" />
              {tab.label}
            </button>
          ))}
        </nav>

        <div className="mt-auto pt-4">
          <button
            onClick={handleLogout}
            className="flex w-full items-center gap-3 rounded-xl border border-border px-4 py-3 text-sm font-medium text-muted-foreground transition-all hover:border-destructive/40 hover:bg-destructive/10 hover:text-red-400"
          >
            <LogOut className="h-5 w-5" />
            Logout
          </button>
        </div>
      </aside>

      {/* Main */}
      <div className="flex flex-1 flex-col">
        <header className="sticky top-0 z-30 flex items-center justify-between border-b border-border bg-muted/70 px-6 py-3 backdrop-blur-xl">
          <div className="flex items-center gap-4">
            <button
              onClick={() => setSidebarOpen(true)}
              className="rounded-lg border border-border p-2 text-muted-foreground lg:hidden"
              aria-label="Open sidebar"
            >
              <Menu className="h-5 w-5" />
            </button>
            <div>
              <h1 className="text-xl font-extrabold text-foreground">
                {guidanceTabs.find((t) => t.id === activeTab)?.label}
              </h1>
              <p className="text-xs text-muted-foreground">
                Guidance Advocate Portal
              </p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-3 rounded-full border border-border bg-muted/30 px-4 py-2 sm:flex">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-sm font-bold text-primary-foreground">
                {initial}
              </div>
              <span className="text-sm font-semibold text-foreground">
                {guidanceName}
              </span>
            </div>
          </div>
        </header>

        <main className="flex-1 p-6">
          <div className="mx-auto max-w-6xl">
            {/* Dashboard */}
            {activeTab === "dashboard" && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      Total Students
                    </p>
                    <p className="text-3xl font-extrabold text-blue-400">
                      {totalStudents}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      Total Strands
                    </p>
                    <p className="text-3xl font-extrabold text-green-400">
                      {strandCards.length}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      With Advisers
                    </p>
                    <p className="text-3xl font-extrabold text-yellow-400">
                      {
                        strandCards.filter(
                          (c) => c.adviser !== "No Adviser Yet"
                        ).length
                      }
                    </p>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      Without Advisers
                    </p>
                    <p className="text-3xl font-extrabold text-red-400">
                      {
                        strandCards.filter(
                          (c) => c.adviser === "No Adviser Yet"
                        ).length
                      }
                    </p>
                  </div>
                </div>

                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <h3 className="mb-4 text-lg font-bold text-foreground">
                    Strand Overview
                  </h3>
                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {strandCards.map((card) => (
                      <div
                        key={card.key}
                        className="flex items-center gap-4 rounded-xl border border-border bg-muted/20 p-4 transition-all hover:border-primary/30"
                      >
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-accent text-sm font-bold text-primary-foreground">
                          {card.initials}
                        </div>
                        <div className="flex-1">
                          <p className="font-bold text-foreground">
                            {card.name}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {card.badge} - Adviser: {card.adviser}
                          </p>
                        </div>
                        <span className="rounded-full bg-primary/10 px-3 py-1 text-sm font-bold text-primary">
                          {card.studentCount}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}

            {/* Strands */}
            {activeTab === "strands" && (
              <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                {strandCards.map((card) => {
                  const strandStudents = students.filter(
                    (s) => s.strand.toUpperCase() === card.key
                  )
                  return (
                    <div
                      key={card.key}
                      className="rounded-2xl border border-border bg-card shadow-xl transition-all hover:border-primary/30"
                    >
                      <div className="flex items-center gap-4 border-b border-border p-5">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-accent text-lg font-bold text-primary-foreground">
                          {card.initials}
                        </div>
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <h3 className="text-lg font-bold text-foreground">
                              {card.name}
                            </h3>
                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                              {card.badge}
                            </span>
                          </div>
                          <p className="text-sm text-muted-foreground">
                            Adviser: {card.adviser}
                          </p>
                        </div>
                        <span className="text-2xl font-extrabold text-primary">
                          {card.studentCount}
                        </span>
                      </div>
                      <div className="max-h-60 overflow-y-auto p-4">
                        {strandStudents.length === 0 ? (
                          <p className="text-sm text-muted-foreground">
                            No students in this strand
                          </p>
                        ) : (
                          <div className="space-y-2">
                            {strandStudents.map((s, i) => (
                              <div
                                key={i}
                                className="flex items-center justify-between rounded-lg bg-muted/20 px-3 py-2 text-sm"
                              >
                                <span className="font-medium text-foreground">
                                  {s.last_name}, {s.first_name}
                                </span>
                                <span className="text-muted-foreground">
                                  {s.lrn}
                                </span>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            )}

            {/* All Students */}
            {activeTab === "students" && (
              <div className="rounded-2xl border border-border bg-card shadow-xl">
                <div className="flex items-center justify-between border-b border-border px-6 py-4">
                  <div>
                    <h3 className="text-lg font-bold text-foreground">
                      All Registered Students
                    </h3>
                    <p className="text-sm text-muted-foreground">
                      {filteredStudents.length} of {students.length} students
                    </p>
                  </div>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                      type="text"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      placeholder="Search students..."
                      className="rounded-xl border border-border bg-black/30 py-2 pl-10 pr-4 text-sm text-foreground outline-none focus:border-primary"
                    />
                  </div>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-border bg-muted/30">
                        <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                          #
                        </th>
                        <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                          Name
                        </th>
                        <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                          LRN
                        </th>
                        <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                          Email
                        </th>
                        <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                          Strand
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredStudents.length === 0 ? (
                        <tr>
                          <td
                            colSpan={5}
                            className="px-6 py-8 text-center text-muted-foreground"
                          >
                            No students found
                          </td>
                        </tr>
                      ) : (
                        filteredStudents.map((s, i) => (
                          <tr
                            key={i}
                            className="border-b border-border/50 hover:bg-muted/20"
                          >
                            <td className="px-6 py-3 text-muted-foreground">
                              {i + 1}
                            </td>
                            <td className="px-6 py-3 font-medium text-foreground">
                              {s.last_name}, {s.first_name}{" "}
                              {s.middle_name || ""}
                            </td>
                            <td className="px-6 py-3 text-muted-foreground">
                              {s.lrn}
                            </td>
                            <td className="px-6 py-3 text-muted-foreground">
                              {s.email}
                            </td>
                            <td className="px-6 py-3">
                              <span className="rounded-full border border-border bg-muted/50 px-2 py-0.5 text-xs font-semibold text-foreground">
                                {s.strand}
                              </span>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  )
}
