"use client"

import { useState, useEffect, useCallback } from "react"
import { useRouter } from "next/navigation"
import {
  LayoutDashboard,
  Users,
  BookOpen,
  ClipboardCheck,
  GraduationCap,
  LogOut,
  Menu,
  Plus,
  Send,
  Trash2,
  CheckCircle,
  XCircle,
} from "lucide-react"

type Student = {
  last_name: string
  first_name: string
  middle_name: string
  lrn: string
  email: string
  strand: string
}

type Subject = {
  id: number
  strand: string
  semester: string
  subject_name: string
  schedule_time: string
}

type AdviserSchedule = {
  id: number
  strand: string
  semester: string
  subject_name: string
  schedule_time: string
}

const adviserTabs = [
  { id: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { id: "students", label: "Students", icon: Users },
  { id: "subjects", label: "Subjects", icon: BookOpen },
  { id: "attendance", label: "Attendance", icon: ClipboardCheck },
  { id: "grades", label: "Grades", icon: GraduationCap },
]

export default function AdviserPortal() {
  const router = useRouter()
  const [activeTab, setActiveTab] = useState("dashboard")
  const [adviserName, setAdviserName] = useState("Adviser")
  const [strand, setStrand] = useState("")
  const [students, setStudents] = useState<Student[]>([])
  const [subjects, setSubjects] = useState<Subject[]>([])
  const [adviserSchedule, setAdviserSchedule] = useState<AdviserSchedule[]>([])
  const [loading, setLoading] = useState(true)
  const [sidebarOpen, setSidebarOpen] = useState(false)

  // Subject form
  const [newSubject, setNewSubject] = useState("")
  const [newSemester, setNewSemester] = useState("1st")
  const [newScheduleTime, setNewScheduleTime] = useState("")

  // Attendance form
  const [attDate, setAttDate] = useState(
    new Date().toISOString().split("T")[0]
  )
  const [attTime, setAttTime] = useState(
    new Date().toLocaleTimeString("en-US", {
      hour12: false,
      hour: "2-digit",
      minute: "2-digit",
    })
  )

  // Grades form
  const [gradeSemester, setGradeSemester] = useState("1st")

  const fetchData = useCallback(async () => {
    try {
      const res = await fetch("/api/adviser/data")
      if (!res.ok) {
        router.push("/")
        return
      }
      const data = await res.json()
      setAdviserName(data.adviserName)
      setStrand(data.strand)
      setStudents(data.students)
      setSubjects(data.subjects)
      setAdviserSchedule(data.adviserSchedule)
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

  async function handleAction(action: string, data: Record<string, unknown>) {
    const res = await fetch("/api/adviser/action", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action, ...data }),
    })
    if (res.ok) fetchData()
    return res.ok
  }

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    )
  }

  const initial = (adviserName[0] || "A").toUpperCase()

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
            src="/original-logo.png"
            alt="Children of Fatima School of Mabalacat Inc."
            className="h-12 w-12 rounded-xl object-cover border border-border shadow-lg"
          />
          <div>
            <div className="text-lg font-extrabold text-foreground">CFSI</div>
            <div className="text-xs text-muted-foreground">Adviser Portal</div>
          </div>
        </div>

        <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
          Navigation
        </p>
        <nav className="flex flex-col gap-1">
          {adviserTabs.map((tab) => (
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
                {adviserTabs.find((t) => t.id === activeTab)?.label}
              </h1>
              <p className="text-xs text-muted-foreground">{strand}</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-3 rounded-full border border-border bg-muted/30 px-4 py-2 sm:flex">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-sm font-bold text-primary-foreground">
                {initial}
              </div>
              <span className="text-sm font-semibold text-foreground">
                {adviserName}
              </span>
            </div>
          </div>
        </header>

        <main className="flex-1 p-6">
          <div className="mx-auto max-w-6xl">
            {/* Dashboard */}
            {activeTab === "dashboard" && (
              <div className="space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      Total Students
                    </p>
                    <p className="text-3xl font-extrabold text-blue-400">
                      {students.length}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      Deployed Subjects
                    </p>
                    <p className="text-3xl font-extrabold text-green-400">
                      {subjects.length}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-border bg-card p-5 shadow-xl">
                    <p className="text-xs text-muted-foreground">
                      Draft Subjects
                    </p>
                    <p className="text-3xl font-extrabold text-yellow-400">
                      {adviserSchedule.length}
                    </p>
                  </div>
                </div>

                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <h3 className="mb-4 text-lg font-bold text-foreground">
                    Student Roster
                  </h3>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border bg-muted/30">
                          <th className="px-4 py-3 text-left font-semibold text-muted-foreground">
                            Name
                          </th>
                          <th className="px-4 py-3 text-left font-semibold text-muted-foreground">
                            LRN
                          </th>
                          <th className="px-4 py-3 text-left font-semibold text-muted-foreground">
                            Email
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {students.length === 0 ? (
                          <tr>
                            <td
                              colSpan={3}
                              className="px-4 py-8 text-center text-muted-foreground"
                            >
                              No students enrolled in this strand yet
                            </td>
                          </tr>
                        ) : (
                          students.map((s, i) => (
                            <tr
                              key={i}
                              className="border-b border-border/50 hover:bg-muted/20"
                            >
                              <td className="px-4 py-3 font-medium text-foreground">
                                {s.last_name}, {s.first_name}{" "}
                                {s.middle_name || ""}
                              </td>
                              <td className="px-4 py-3 text-muted-foreground">
                                {s.lrn}
                              </td>
                              <td className="px-4 py-3 text-muted-foreground">
                                {s.email}
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}

            {/* Students list */}
            {activeTab === "students" && (
              <div className="rounded-2xl border border-border bg-card shadow-xl">
                <div className="border-b border-border px-6 py-4">
                  <h3 className="text-lg font-bold text-foreground">
                    Students - {strand}
                  </h3>
                  <p className="text-sm text-muted-foreground">
                    {students.length} student{students.length !== 1 ? "s" : ""}
                  </p>
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
                      </tr>
                    </thead>
                    <tbody>
                      {students.map((s, i) => (
                        <tr
                          key={i}
                          className="border-b border-border/50 hover:bg-muted/20"
                        >
                          <td className="px-6 py-3 text-muted-foreground">
                            {i + 1}
                          </td>
                          <td className="px-6 py-3 font-medium text-foreground">
                            {s.last_name}, {s.first_name} {s.middle_name || ""}
                          </td>
                          <td className="px-6 py-3 text-muted-foreground">
                            {s.lrn}
                          </td>
                          <td className="px-6 py-3 text-muted-foreground">
                            {s.email}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Subjects */}
            {activeTab === "subjects" && (
              <div className="space-y-6">
                {/* Add subject form */}
                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <h3 className="mb-4 text-lg font-bold text-foreground">
                    Add Subject
                  </h3>
                  <div className="flex flex-wrap items-end gap-4">
                    <div>
                      <label className="mb-1 block text-sm text-muted-foreground">
                        Subject Name
                      </label>
                      <input
                        type="text"
                        value={newSubject}
                        onChange={(e) => setNewSubject(e.target.value)}
                        className="rounded-xl border border-border bg-black/30 px-4 py-2 text-sm text-foreground outline-none focus:border-primary"
                        placeholder="e.g. Oral Communication"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-sm text-muted-foreground">
                        Semester
                      </label>
                      <select
                        value={newSemester}
                        onChange={(e) => setNewSemester(e.target.value)}
                        className="rounded-xl border border-border bg-black/30 px-4 py-2 text-sm text-foreground outline-none focus:border-primary"
                      >
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                      </select>
                    </div>
                    <div>
                      <label className="mb-1 block text-sm text-muted-foreground">
                        Schedule Time
                      </label>
                      <input
                        type="text"
                        value={newScheduleTime}
                        onChange={(e) => setNewScheduleTime(e.target.value)}
                        className="rounded-xl border border-border bg-black/30 px-4 py-2 text-sm text-foreground outline-none focus:border-primary"
                        placeholder="e.g. 8:00 AM - 9:30 AM"
                      />
                    </div>
                    <button
                      onClick={async () => {
                        if (!newSubject.trim()) return
                        await handleAction("save_subject", {
                          subject_name: newSubject,
                          semester: newSemester,
                        })
                        setNewSubject("")
                      }}
                      className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                    >
                      <Plus className="h-4 w-4" />
                      Add
                    </button>
                    <button
                      onClick={async () => {
                        if (!newSubject.trim() || !newScheduleTime.trim()) return
                        await handleAction("deploy_subject", {
                          subject_name: newSubject,
                          semester: newSemester,
                          schedule_time: newScheduleTime,
                        })
                        setNewSubject("")
                        setNewScheduleTime("")
                      }}
                      className="flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-primary-foreground"
                    >
                      <Send className="h-4 w-4" />
                      Deploy
                    </button>
                  </div>
                </div>

                {/* Draft subjects */}
                <div className="rounded-2xl border border-border bg-card shadow-xl">
                  <div className="border-b border-border px-6 py-4">
                    <h3 className="text-lg font-bold text-foreground">
                      Draft Subjects (Adviser Schedule)
                    </h3>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border bg-muted/30">
                          <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                            Subject
                          </th>
                          <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                            Semester
                          </th>
                          <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {adviserSchedule.length === 0 ? (
                          <tr>
                            <td
                              colSpan={3}
                              className="px-6 py-8 text-center text-muted-foreground"
                            >
                              No draft subjects
                            </td>
                          </tr>
                        ) : (
                          adviserSchedule.map((s) => (
                            <tr
                              key={s.id}
                              className="border-b border-border/50 hover:bg-muted/20"
                            >
                              <td className="px-6 py-3 font-medium text-foreground">
                                {s.subject_name}
                              </td>
                              <td className="px-6 py-3 text-muted-foreground">
                                {s.semester}
                              </td>
                              <td className="px-6 py-3">
                                <button
                                  onClick={() =>
                                    handleAction("delete_subject", {
                                      id: s.id,
                                    })
                                  }
                                  className="text-red-400 hover:text-red-300"
                                  aria-label="Delete subject"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </button>
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Deployed subjects */}
                <div className="rounded-2xl border border-border bg-card shadow-xl">
                  <div className="border-b border-border px-6 py-4">
                    <h3 className="text-lg font-bold text-foreground">
                      Deployed Subjects
                    </h3>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border bg-muted/30">
                          <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                            Subject
                          </th>
                          <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                            Semester
                          </th>
                          <th className="px-6 py-3 text-left font-semibold text-muted-foreground">
                            Schedule
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {subjects.length === 0 ? (
                          <tr>
                            <td
                              colSpan={3}
                              className="px-6 py-8 text-center text-muted-foreground"
                            >
                              No deployed subjects yet
                            </td>
                          </tr>
                        ) : (
                          subjects.map((s) => (
                            <tr
                              key={s.id}
                              className="border-b border-border/50 hover:bg-muted/20"
                            >
                              <td className="px-6 py-3 font-medium text-foreground">
                                {s.subject_name}
                              </td>
                              <td className="px-6 py-3 text-muted-foreground">
                                {s.semester}
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
              </div>
            )}

            {/* Attendance */}
            {activeTab === "attendance" && (
              <div className="space-y-6">
                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <h3 className="mb-4 text-lg font-bold text-foreground">
                    Mark Attendance
                  </h3>
                  <div className="mb-4 flex flex-wrap gap-4">
                    <div>
                      <label className="mb-1 block text-sm text-muted-foreground">
                        Date
                      </label>
                      <input
                        type="date"
                        value={attDate}
                        onChange={(e) => setAttDate(e.target.value)}
                        className="rounded-xl border border-border bg-black/30 px-4 py-2 text-sm text-foreground outline-none focus:border-primary"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-sm text-muted-foreground">
                        Time
                      </label>
                      <input
                        type="time"
                        value={attTime}
                        onChange={(e) => setAttTime(e.target.value)}
                        className="rounded-xl border border-border bg-black/30 px-4 py-2 text-sm text-foreground outline-none focus:border-primary"
                      />
                    </div>
                  </div>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="border-b border-border bg-muted/30">
                          <th className="px-4 py-3 text-left font-semibold text-muted-foreground">
                            Student
                          </th>
                          <th className="px-4 py-3 text-left font-semibold text-muted-foreground">
                            LRN
                          </th>
                          <th className="px-4 py-3 text-center font-semibold text-muted-foreground">
                            Present
                          </th>
                          <th className="px-4 py-3 text-center font-semibold text-muted-foreground">
                            Absent
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {students.map((s, i) => (
                          <tr
                            key={i}
                            className="border-b border-border/50 hover:bg-muted/20"
                          >
                            <td className="px-4 py-3 font-medium text-foreground">
                              {s.last_name}, {s.first_name}
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                              {s.lrn}
                            </td>
                            <td className="px-4 py-3 text-center">
                              <button
                                onClick={() =>
                                  handleAction("save_attendance", {
                                    lrn: s.lrn,
                                    full_name: `${s.last_name}, ${s.first_name}`,
                                    date: attDate,
                                    time: attTime,
                                    status: "Present",
                                  })
                                }
                                className="text-green-400 hover:text-green-300"
                                aria-label={`Mark ${s.first_name} present`}
                              >
                                <CheckCircle className="h-5 w-5" />
                              </button>
                            </td>
                            <td className="px-4 py-3 text-center">
                              <button
                                onClick={() =>
                                  handleAction("save_attendance", {
                                    lrn: s.lrn,
                                    full_name: `${s.last_name}, ${s.first_name}`,
                                    date: attDate,
                                    time: attTime,
                                    status: "Absent",
                                  })
                                }
                                className="text-red-400 hover:text-red-300"
                                aria-label={`Mark ${s.first_name} absent`}
                              >
                                <XCircle className="h-5 w-5" />
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}

            {/* Grades */}
            {activeTab === "grades" && (
              <div className="space-y-6">
                <div className="rounded-2xl border border-border bg-card p-6 shadow-xl">
                  <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-bold text-foreground">
                      Manage Grades
                    </h3>
                    <div className="flex items-center gap-4">
                      <select
                        value={gradeSemester}
                        onChange={(e) => setGradeSemester(e.target.value)}
                        className="rounded-xl border border-border bg-black/30 px-4 py-2 text-sm text-foreground outline-none focus:border-primary"
                      >
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                      </select>
                      <button
                        onClick={() =>
                          handleAction("deploy_grades", {
                            semester: gradeSemester,
                          })
                        }
                        className="flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-primary-foreground"
                      >
                        <Send className="h-4 w-4" />
                        Deploy Grades
                      </button>
                    </div>
                  </div>

                  <p className="mb-4 text-sm text-muted-foreground">
                    Enter grades for each student per subject. Click Deploy to
                    make grades visible to students.
                  </p>

                  {students.map((student) => (
                    <div
                      key={student.lrn}
                      className="mb-4 rounded-xl border border-border bg-muted/20 p-4"
                    >
                      <p className="mb-2 font-semibold text-foreground">
                        {student.last_name}, {student.first_name} (
                        {student.lrn})
                      </p>
                      <div className="flex flex-wrap gap-3">
                        {subjects
                          .filter((s) => s.semester === gradeSemester)
                          .map((sub) => (
                            <GradeInput
                              key={sub.id}
                              subjectName={sub.subject_name}
                              onSave={(grade) =>
                                handleAction("save_grade", {
                                  lrn: student.lrn,
                                  semester: gradeSemester,
                                  subject: sub.subject_name,
                                  grade,
                                })
                              }
                            />
                          ))}
                        {subjects.filter((s) => s.semester === gradeSemester)
                          .length === 0 && (
                          <p className="text-sm text-muted-foreground">
                            No subjects deployed for this semester
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  )
}

function GradeInput({
  subjectName,
  onSave,
}: {
  subjectName: string
  onSave: (grade: string) => void
}) {
  const [grade, setGrade] = useState("")

  return (
    <div className="flex items-center gap-2">
      <span className="text-xs text-muted-foreground">{subjectName}:</span>
      <input
        type="number"
        min="0"
        max="100"
        value={grade}
        onChange={(e) => setGrade(e.target.value)}
        className="w-16 rounded-lg border border-border bg-black/30 px-2 py-1 text-center text-sm text-foreground outline-none focus:border-primary"
        placeholder="--"
      />
      <button
        onClick={() => {
          if (grade) {
            onSave(grade)
            setGrade("")
          }
        }}
        className="rounded-lg bg-primary/20 px-2 py-1 text-xs text-primary hover:bg-primary/30"
      >
        Save
      </button>
    </div>
  )
}
