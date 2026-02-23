import { NextResponse } from "next/server"
import { getSession } from "@/lib/auth"
import { getSQL } from "@/lib/db"

function getTablesForStrand(strand: string) {
  const s = strand.toUpperCase()
  if (s.includes("STALLMAN"))
    return {
      subject: "subject_schedule_stallman",
      grades: "grades_stallman",
      attendance: "attendance_stallman",
    }
  if (s.includes("ZUCKERBERG"))
    return {
      subject: "subject_schedule_zuckerberg",
      grades: "grades_zuckerberg",
      attendance: "attendance_zuckerberg",
    }
  if (s.includes("MASLOW"))
    return {
      subject: "subject_schedule_maslow",
      grades: "grades_maslow",
      attendance: "attendance_maslow",
    }
  if (s.includes("VOLTAIRE"))
    return {
      subject: "subject_schedule_voltaire",
      grades: "grades_voltaire",
      attendance: "attendance_voltaire",
    }
  return null
}

export async function GET() {
  try {
    const session = await getSession()
    if (!session || session.role !== "student") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
    }

    const sql = getSQL()
    const students = await sql`
      SELECT last_name, first_name, middle_name, lrn, email, contact, address, birthdate, guardian_name, guardian_contact, strand
      FROM registration_student_guidanceinformation
      WHERE LOWER(email) = LOWER(${session.email})
      LIMIT 1
    `

    if (students.length === 0) {
      return NextResponse.json({ error: "Student not found" }, { status: 404 })
    }

    const student = students[0]
    const strand = ((student.strand as string) || "").trim()
    const tables = getTablesForStrand(strand)

    let subjects: Record<string, unknown>[] = []
    let grades: Record<string, unknown>[] = []
    let presentCount = 0
    let absentCount = 0

    if (tables) {
      try {
        subjects =
          await sql`SELECT strand, semester, subject_name, schedule_time FROM ${sql(tables.subject)} ORDER BY created_at ASC`
      } catch {
        subjects = []
      }

      if (student.lrn) {
        try {
          grades =
            await sql`SELECT semester, subject, grade, is_deployed FROM ${sql(tables.grades)} WHERE lrn = ${student.lrn} AND is_deployed = 1 ORDER BY created_at ASC`
        } catch {
          grades = []
        }

        try {
          const attResult = await sql`
            SELECT
              COALESCE(SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END), 0) as present_count,
              COALESCE(SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END), 0) as absent_count
            FROM ${sql(tables.attendance)} WHERE lrn = ${student.lrn}
          `
          if (attResult.length > 0) {
            presentCount = Number(attResult[0].present_count) || 0
            absentCount = Number(attResult[0].absent_count) || 0
          }
        } catch {
          presentCount = 0
          absentCount = 0
        }
      }
    }

    return NextResponse.json({
      student,
      subjects,
      grades,
      attendance: { present: presentCount, absent: absentCount },
    })
  } catch (error) {
    console.error("Student data error:", error)
    return NextResponse.json(
      { error: "Internal server error" },
      { status: 500 }
    )
  }
}
