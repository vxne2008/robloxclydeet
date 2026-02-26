"use client"

import { useState, useEffect, useCallback } from "react"
import { useRouter } from "next/navigation"
import {
  LayoutDashboard,
  BookOpen,
  GraduationCap,
  ClipboardCheck,
  User,
  LogOut,
  Calendar,
  Award,
  TrendingUp,
  Edit3,
  Save,
  X,
  Menu,
} from "lucide-react"

type Student = {
  last_name: string
  first_name: string
  middle_name: string
  lrn: string
  email: string
  contact: string
  address: string
  birthdate: string
  guardian_name: string
  guardian_contact: string
  strand: string
}

type Subject = {
  strand: string
  semester: string
  subject_name: string
  schedule_time: string
}

type Grade = {
  semester: string
  subject: string
  grade: string
  is_deployed: boolean
}

const tabs = [
  { id: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { id: "subjects", label: "Subjects", icon: BookOpen },
  { id: "grades", label: "Grades", icon: GraduationCap },
  { id: "attendance", label: "Attendance", icon: ClipboardCheck },
  { id: "personal", label: "Personal Info", icon: User },
]

function computeGWA(grades: Grade[]) {
  let sum = 0
  let count = 0
  for (const g of grades) {
    const n = parseFloat(g.grade)
    if (!isNaN(n)) {
      sum += n
      count++
    }
  }
  if (count === 0) return { gwa: "--", honor: "--", honorClass: "" }
  const gwa = (sum / count).toFixed(2)
  const g = parseFloat(gwa)
  if (g <= 74) return { gwa, honor: "Failed", honorClass: "text-red-400" }
  if (g <= 84)
    return { gwa, honor: "Passed", honorClass: "text-muted-foreground" }
  if (g <= 89)
    return { gwa, honor: "Academic Awardee", honorClass: "text-blue-400" }
  if (g <= 94)
    return { gwa, honor: "With Honors", honorClass: "text-green-400" }
  if (g <= 97)
    return { gwa, honor: "With High Honors", honorClass: "text-yellow-400" }
  return {
    gwa,
    honor: "With Highest Honor",
    honorClass: "text-amber-300",
  }
}

export default function StudentPortal() {
  const router = useRouter()
  const [activeTab, setActiveTab] = useState("dashboard")
  const [student, setStudent] = useState<Student | null>(null)
  const [subjects, setSubjects] = useState<Subject[]>([])
  const [grades, setGrades] = useState<Grade[]>([])
  const [attendance, setAttendance] = useState({ present: 0, absent: 0 })
  const [loading, setLoading] = useState(true)
  const [editing, setEditing] = useState(false)
  const [editForm, setEditForm] = useState<Partial<Student>>({})
  const [sidebarOpen, setSidebarOpen] = useState(false)

  const fetchData = useCallback(async () => {
    try {
      const res = await fetch("/api/student/data")
      if (!res.ok) {
        router.push("/")
        return
      }
      const data = await res.json()
      setStudent(data.student)
      setSubjects(data.subjects)
      setGrades(data.grades)
      setAttendance(data.attendance)
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

  async function handleSaveInfo() {
    const res = await fetch("/api/student/update", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(editForm),
    })
    if (res.ok) {
      setEditing(false)
      fetchData()
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    )
  }

  if (!student) return null

  const fullName = `${student.last_name}, ${student.first_name}${student.middle_name ? " " + student.middle_name : ""}`
  const avatarLetter = (student.first_name || "S")[0].toUpperCase()

  const grades1st = grades.filter((g) => g.semester === "1st")
  const grades2nd = grades.filter((g) => g.semester === "2nd")
  const gwa1st = computeGWA(grades1st)
  const gwa2nd = computeGWA(grades2nd)

  const subjects1st = subjects.filter((s) => s.semester === "1st")
  const subjects2nd = subjects.filter((s) => s.semester === "2nd")

  return (
    <div className="flex min-h-screen">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-border bg-muted/95 p-5 shadow-2xl backdrop-blur-xl transition-transform lg:static lg:translate-x-0 ${
          sidebarOpen ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        <div className="mb-6 flex items-center gap-3 border-b border-border pb-5">
          <img
            src="/original-logo.png"
            alt="Children of Fatima School of Mabalacat Inc."
            className="h-12 w-12 rounded-xl object-cover border border-border shadow-lg"
          />
          <div>
            <div className="text-lg font-extrabold text-foreground">CFSI</div>
            <div className="text-xs text-muted-foreground">Student Portal</div>
          </div>
        </div>

        <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
          Navigation
        </p>
        <nav className="flex flex-col gap-1">
          {tabs.map((tab) => (
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
        {/* Topbar */}
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
                {tabs.find((t) => t.id === activeTab)?.label}
              </h1>
              <p className="text-xs text-muted-foreground">
                {student.strand || "Student"}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-3 rounded-full border border-border bg-muted/30 px-4 py-2 sm:flex">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-sm font-bold text-primary-foreground">
                {avatarLetter}
              </div>
              <span className="text-sm font-semibold text-foreground">
                {fullName}
              </span>
            </div>
          </div>
        </header>

        {/* Content */}
        <main className="flex-1 p-6">
          <div className="mx-auto max-w-6xl">
            {/* Dashboard */}
            {activeTab === "dashboard" && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <KPICard
                    icon={<BookOpen className="h-5 w-5" />}
                    label="Enrolled Subjects"
                    value={String(subjects.length)}
                    color="text-blue-400"
                  />
                  <KPICard
                    icon={<ClipboardCheck className="h-5 w-5" />}
                    label="Days Present"
                    value={String(attendance.present)}
                    color="text-green-400"
                  />
                  <KPICard
                    icon={<Calendar className="h-5 w-5" />}
                    label="Days Absent"
                    value={String(attendance.absent)}
                    color="text-red-400"
                  />
                  <KPICard
                    icon={<TrendingUp className="h-5 w-5" />}
                    label="GWA (1st Sem)"
                    value={gwa1st.gwa}
                    color="text-yellow-400"
                  />
                </div>

                {/* Student Info Card */}
                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <h3 className="mb-4 text-lg font-bold text-foreground">
                    Student Information
                  </h3>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <InfoItem label="Full Name" value={fullName} />
                    <InfoItem label="LRN" value={student.lrn || "--"} />
                    <InfoItem label="Email" value={student.email} />
                    <InfoItem label="Strand" value={student.strand || "--"} />
                    <InfoItem
                      label="Contact"
                      value={student.contact || "--"}
                    />
                    <InfoItem
                      label="Guardian"
                      value={student.guardian_name || "--"}
                    />
                  </div>
                </div>

                {/* Honor status */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                    <div className="flex items-center gap-3">
                      <Award className="h-6 w-6 text-primary" />
                      <div>
                        <p className="text-sm text-muted-foreground">
                          1st Semester Honor
                        </p>
                        <p
                          className={`text-lg font-bold ${gwa1st.honorClass || "text-foreground"}`}
                        >
                          {gwa1st.honor}
                        </p>
                      </div>
                    </div>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                    <div className="flex items-center gap-3">
                      <Award className="h-6 w-6 text-accent" />
                      <div>
                        <p className="text-sm text-muted-foreground">
                          2nd Semester Honor
                        </p>
                        <p
                          className={`text-lg font-bold ${gwa2nd.honorClass || "text-foreground"}`}
                        >
                          {gwa2nd.honor}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Subjects */}
            {activeTab === "subjects" && (
              <div className="space-y-6">
                {["1st", "2nd"].map((sem) => {
                  const semSubjects =
                    sem === "1st" ? subjects1st : subjects2nd
                  return (
                    <div
                      key={sem}
                      className="rounded-2xl border border-border bg-card shadow-xl"
                    >
                      <div className="border-b border-border px-6 py-4">
                        <h3 className="text-lg font-bold text-foreground">
                          {sem} Semester Subjects
                        </h3>
                        <p className="text-sm text-muted-foreground">
                          {semSubjects.length} subject
                          {semSubjects.length !== 1 ? "s" : ""}
                        </p>
                      </div>
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead>
                            <tr className="border-b border-border bg-muted/30">
                              <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                                Subject
                              </th>
                              <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                                Strand
                              </th>
                              <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                                Schedule
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            {semSubjects.length === 0 ? (
                              <tr>
                                <td
                                  colSpan={3}
                                  className="px-6 py-8 text-center text-muted-foreground"
                                >
                                  No subjects found for this semester
                                </td>
                              </tr>
                            ) : (
                              semSubjects.map((s, i) => (
                                <tr
                                  key={i}
                                  className="border-b border-border/50 hover:bg-muted/20"
                                >
                                  <td className="px-6 py-3 font-medium text-foreground">
                                    {s.subject_name}
                                  </td>
                                  <td className="px-6 py-3 text-muted-foreground">
                                    {s.strand}
                                  </td>
                                  <td className="px-6 py-3 text-muted-foreground">
                                    {s.schedule_time || "--"}
                                  </td>
                                </tr>
                              ))
                            )}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )
                })}
              </div>
            )}

            {/* Grades */}
            {activeTab === "grades" && (
              <div className="space-y-6">
                {["1st", "2nd"].map((sem) => {
                  const semGrades = sem === "1st" ? grades1st : grades2nd
                  const gwa = sem === "1st" ? gwa1st : gwa2nd
                  return (
                    <div
                      key={sem}
                      className="rounded-2xl border border-border bg-card shadow-xl"
                    >
                      <div className="flex items-center justify-between border-b border-border px-6 py-4">
                        <div>
                          <h3 className="text-lg font-bold text-foreground">
                            {sem} Semester Grades
                          </h3>
                          <p className="text-sm text-muted-foreground">
                            GWA: {gwa.gwa}
                          </p>
                        </div>
                        <span
                          className={`rounded-full border border-border bg-muted/50 px-3 py-1 text-xs font-semibold ${gwa.honorClass}`}
                        >
                          {gwa.honor}
                        </span>
                      </div>
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead>
                            <tr className="border-b border-border bg-muted/30">
                              <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                                Subject
                              </th>
                              <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                                Grade
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            {semGrades.length === 0 ? (
                              <tr>
                                <td
                                  colSpan={2}
                                  className="px-6 py-8 text-center text-muted-foreground"
                                >
                                  No grades deployed yet
                                </td>
                              </tr>
                            ) : (
                              semGrades.map((g, i) => (
                                <tr
                                  key={i}
                                  className="border-b border-border/50 hover:bg-muted/20"
                                >
                                  <td className="px-6 py-3 font-medium text-foreground">
                                    {g.subject}
                                  </td>
                                  <td className="px-6 py-3">
                                    <span
                                      className={`font-bold ${parseFloat(g.grade) >= 75 ? "text-green-400" : "text-red-400"}`}
                                    >
                                      {g.grade}
                                    </span>
                                  </td>
                                </tr>
                              ))
                            )}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )
                })}
              </div>
            )}

            {/* Attendance */}
            {activeTab === "attendance" && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                    <div className="flex items-center gap-4">
                      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-green-500/10 text-green-400">
                        <ClipboardCheck className="h-7 w-7" />
                      </div>
                      <div>
                        <p className="text-sm text-muted-foreground">
                          Total Days Present
                        </p>
                        <p className="text-3xl font-extrabold text-green-400">
                          {attendance.present}
                        </p>
                      </div>
                    </div>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                    <div className="flex items-center gap-4">
                      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-500/10 text-red-400">
                        <X className="h-7 w-7" />
                      </div>
                      <div>
                        <p className="text-sm text-muted-foreground">
                          Total Days Absent
                        </p>
                        <p className="text-3xl font-extrabold text-red-400">
                          {attendance.absent}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <h3 className="mb-2 text-lg font-bold text-foreground">
                    Attendance Rate
                  </h3>
                  <div className="mt-4">
                    {attendance.present + attendance.absent > 0 ? (
                      <>
                        <div className="mb-2 flex items-center justify-between text-sm">
                          <span className="text-muted-foreground">
                            Attendance Rate
                          </span>
                          <span className="font-bold text-foreground">
                            {(
                              (attendance.present /
                                (attendance.present + attendance.absent)) *
                              100
                            ).toFixed(1)}
                            %
                          </span>
                        </div>
                        <div className="h-3 overflow-hidden rounded-full bg-muted">
                          <div
                            className="h-full rounded-full bg-gradient-to-r from-primary to-green-400 transition-all"
                            style={{
                              width: `${(attendance.present / (attendance.present + attendance.absent)) * 100}%`,
                            }}
                          />
                        </div>
                      </>
                    ) : (
                      <p className="text-sm text-muted-foreground">
                        No attendance records yet
                      </p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Personal Info */}
            {activeTab === "personal" && (
              <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                <div className="mb-6 flex items-center justify-between">
                  <h3 className="text-lg font-bold text-foreground">
                    Personal Information
                  </h3>
                  {!editing ? (
                    <button
                      onClick={() => {
                        setEditForm({ ...student })
                        setEditing(true)
                      }}
                      className="flex items-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition-all hover:border-primary hover:text-primary"
                    >
                      <Edit3 className="h-4 w-4" />
                      Edit
                    </button>
                  ) : (
                    <div className="flex gap-2">
                      <button
                        onClick={handleSaveInfo}
                        className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                      >
                        <Save className="h-4 w-4" />
                        Save
                      </button>
                      <button
                        onClick={() => setEditing(false)}
                        className="flex items-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground"
                      >
                        <X className="h-4 w-4" />
                        Cancel
                      </button>
                    </div>
                  )}
                </div>

                {editing ? (
                  <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {[
                      { key: "last_name", label: "Last Name" },
                      { key: "first_name", label: "First Name" },
                      { key: "middle_name", label: "Middle Name" },
                      { key: "lrn", label: "LRN" },
                      { key: "email", label: "Email" },
                      { key: "contact", label: "Contact" },
                      { key: "address", label: "Address" },
                      { key: "birthdate", label: "Birth Date" },
                      { key: "guardian_name", label: "Guardian Name" },
                      { key: "guardian_contact", label: "Guardian Contact" },
                    ].map((field) => (
                      <div key={field.key}>
                        <label className="mb-1 block text-sm font-medium text-muted-foreground">
                          {field.label}
                        </label>
                        <input
                          type={field.key === "birthdate" ? "date" : "text"}
                          value={
                            (editForm[
                              field.key as keyof Student
                            ] as string) || ""
                          }
                          onChange={(e) =>
                            setEditForm((prev) => ({
                              ...prev,
                              [field.key]: e.target.value,
                            }))
                          }
                          className="w-full rounded-xl border border-border bg-black/30 px-4 py-2.5 text-sm text-foreground outline-none transition-all focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <InfoItem
                      label="Last Name"
                      value={student.last_name || "--"}
                    />
                    <InfoItem
                      label="First Name"
                      value={student.first_name || "--"}
                    />
                    <InfoItem
                      label="Middle Name"
                      value={student.middle_name || "--"}
                    />
                    <InfoItem label="LRN" value={student.lrn || "--"} />
                    <InfoItem label="Email" value={student.email} />
                    <InfoItem
                      label="Contact"
                      value={student.contact || "--"}
                    />
                    <InfoItem
                      label="Address"
                      value={student.address || "--"}
                    />
                    <InfoItem
                      label="Birth Date"
                      value={student.birthdate || "--"}
                    />
                    <InfoItem label="Strand" value={student.strand || "--"} />
                    <InfoItem
                      label="Guardian"
                      value={student.guardian_name || "--"}
                    />
                    <InfoItem
                      label="Guardian Contact"
                      value={student.guardian_contact || "--"}
                    />
                  </div>
                )}
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  )
}

function KPICard({
  icon,
  label,
  value,
  color,
}: {
  icon: React.ReactNode
  label: string
  value: string
  color: string
}) {
  return (
    <div className="rounded-2xl border border-border bg-card p-5 shadow-xl transition-all hover:border-primary/30 hover:shadow-primary/5">
      <div className="flex items-center gap-3">
        <div className={`${color}`}>{icon}</div>
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className={`text-2xl font-extrabold ${color}`}>{value}</p>
        </div>
      </div>
    </div>
  )
}

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="text-sm font-semibold text-foreground">{value}</p>
    </div>
  )
}
