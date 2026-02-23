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

export async function POST(request: Request) {
  try {
    const session = await getSession()
    if (!session || session.role !== "adviser") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
    }

    const body = await request.json()
    const { action } = body
    const sql = getSQL()
    const tables = getTablesForStrand(session.strand)

    if (!tables) {
      return NextResponse.json({ error: "Unknown strand" }, { status: 400 })
    }

    if (action === "save_attendance") {
      const { lrn, full_name, date, time, status } = body
      const month = new Date(date).getMonth() + 1
      const semester = month >= 8 ? "1st" : month <= 5 ? "2nd" : "summer"

      await sql`
        INSERT INTO ${sql(tables.att)} (lrn, full_name, attendance_date, attendance_time, status, semester)
        VALUES (${lrn}, ${full_name}, ${date}, ${time || null}, ${status}, ${semester})
        ON CONFLICT (lrn, attendance_date) DO UPDATE SET
          attendance_time = EXCLUDED.attendance_time,
          status = EXCLUDED.status,
          semester = EXCLUDED.semester
      `
      return NextResponse.json({ success: true })
    }

    if (action === "remove_attendance") {
      const { lrn, date } = body
      await sql`DELETE FROM ${sql(tables.att)} WHERE lrn = ${lrn} AND attendance_date = ${date}`
      return NextResponse.json({ success: true })
    }

    if (action === "save_subject") {
      const { subject_name, semester } = body
      await sql`
        INSERT INTO advisers_schedule (strand, semester, subject_name)
        VALUES (${session.strand}, ${semester}, ${subject_name})
      `
      return NextResponse.json({ success: true })
    }

    if (action === "deploy_subject") {
      const { subject_name, semester, schedule_time } = body
      await sql`
        INSERT INTO ${sql(tables.subject)} (strand, semester, subject_name, schedule_time)
        VALUES (${session.strand}, ${semester}, ${subject_name}, ${schedule_time})
        ON CONFLICT (semester, subject_name) DO UPDATE SET
          strand = EXCLUDED.strand,
          schedule_time = EXCLUDED.schedule_time
      `
      return NextResponse.json({ success: true })
    }

    if (action === "save_grade") {
      const { lrn, semester, subject, grade } = body
      await sql`
        INSERT INTO ${sql(tables.grades)} (lrn, semester, subject, grade, is_deployed)
        VALUES (${lrn}, ${semester}, ${subject}, ${grade}, 0)
      `
      return NextResponse.json({ success: true })
    }

    if (action === "deploy_grades") {
      const { semester } = body
      await sql`
        UPDATE ${sql(tables.grades)} SET is_deployed = 1 WHERE semester = ${semester}
      `
      return NextResponse.json({ success: true })
    }

    if (action === "delete_subject") {
      const { id } = body
      await sql`DELETE FROM advisers_schedule WHERE id = ${id}`
      return NextResponse.json({ success: true })
    }

    return NextResponse.json({ error: "Unknown action" }, { status: 400 })
  } catch (error) {
    console.error("Adviser action error:", error)
    return NextResponse.json({ error: "Internal server error" }, { status: 500 })
  }
}
