import { NextResponse } from "next/server"
import { getSession } from "@/lib/auth"
import { getSQL } from "@/lib/db"

function getTablesForStrand(strand: string) {
  const s = strand.toUpperCase()
  if (s.includes("STALLMAN"))
    return { att: "attendance_stallman", subject: "subject_schedule_stallman", grades: "grades_stallman" }
  if (s.includes("ZUCKERBERG"))
    return { att: "attendance_zuckerberg", subject: "subject_schedule_zuckerberg", grades: "grades_zuckerberg" }
  if (s.includes("MASLOW"))
    return { att: "attendance_maslow", subject: "subject_schedule_maslow", grades: "grades_maslow" }
  if (s.includes("VOLTAIRE"))
    return { att: "attendance_voltaire", subject: "subject_schedule_voltaire", grades: "grades_voltaire" }
  return null
}

export async function GET() {
  try {
    const session = await getSession()
    if (!session || session.role !== "adviser") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
    }

    const sql = getSQL()
    const strand = session.strand

    // Fetch adviser name
    const adviserRows = await sql`
      SELECT first_name, last_name FROM registration_adviser_guidanceportal
      WHERE strand = ${strand} ORDER BY id DESC LIMIT 1
    `
    const adviserName = adviserRows.length > 0
      ? `${adviserRows[0].first_name} ${adviserRows[0].last_name}`
      : "Adviser"

    // Fetch students in this strand
    const students = await sql`
      SELECT last_name, first_name, middle_name, lrn, email, strand
      FROM registration_student_guidanceinformation
      WHERE UPPER(strand) = UPPER(${strand})
      ORDER BY last_name ASC
    `

    const tables = getTablesForStrand(strand)
    let subjects: Record<string, unknown>[] = []
    let adviserSchedule: Record<string, unknown>[] = []

    if (tables) {
      try {
        subjects = await sql`SELECT * FROM ${sql(tables.subject)} ORDER BY created_at ASC`
      } catch { subjects = [] }

      try {
        adviserSchedule = await sql`SELECT * FROM advisers_schedule WHERE UPPER(strand) = UPPER(${strand}) ORDER BY created_at ASC`
      } catch { adviserSchedule = [] }
    }

    return NextResponse.json({
      adviserName,
      strand,
      students,
      subjects,
      adviserSchedule,
      tables: tables ? { att: tables.att, subject: tables.subject, grades: tables.grades } : null,
    })
  } catch (error) {
    console.error("Adviser data error:", error)
    return NextResponse.json({ error: "Internal server error" }, { status: 500 })
  }
}
