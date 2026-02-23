import { NextResponse } from "next/server"
import { getSession } from "@/lib/auth"
import { getSQL } from "@/lib/db"

export async function GET() {
  try {
    const session = await getSession()
    if (!session || session.role !== "guidance") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
    }

    const sql = getSQL()

    // Fetch guidance name
    const guidanceRows = await sql`
      SELECT first_name, last_name FROM registration_adviser_guidanceportal
      WHERE email = ${session.email} AND UPPER(strand) = 'GUIDANCE'
      ORDER BY id DESC LIMIT 1
    `
    const guidanceName = guidanceRows.length > 0
      ? `${guidanceRows[0].first_name} ${guidanceRows[0].last_name}`
      : "Guidance"

    // Fetch all advisers
    const adviserRows = await sql`
      SELECT first_name, last_name, strand FROM registration_adviser_guidanceportal
      WHERE UPPER(strand) != 'GUIDANCE'
    `
    const advisers: Record<string, string> = {}
    for (const row of adviserRows) {
      advisers[row.strand as string] = `${row.first_name} ${row.last_name}`
    }

    // Fetch all students
    const students = await sql`
      SELECT last_name, first_name, middle_name, lrn, email, strand, section
      FROM registration_student_guidanceinformation
      ORDER BY last_name ASC
    `

    // Build strand cards
    const strands = [
      { name: "11 STALLMAN", badge: "ICT", key: "ICT STALLMAN", initials: "IS" },
      { name: "11 ZUCKERBERG", badge: "ICT", key: "ICT ZUCKERBERG", initials: "IZ" },
      { name: "11 VOLTAIRE", badge: "HUMSS", key: "HUMSS VOLTAIRE", initials: "HV" },
      { name: "12 MASLOW", badge: "ABM", key: "ABM MASLOW", initials: "AM" },
    ]

    const strandCards = strands.map((s) => ({
      ...s,
      adviser: advisers[s.key] || "No Adviser Yet",
      studentCount: students.filter(
        (st) => (st.strand as string).toUpperCase() === s.key
      ).length,
    }))

    return NextResponse.json({
      guidanceName,
      strandCards,
      students,
      totalStudents: students.length,
    })
  } catch (error) {
    console.error("Guidance data error:", error)
    return NextResponse.json({ error: "Internal server error" }, { status: 500 })
  }
}
