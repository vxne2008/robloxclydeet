import { NextResponse } from "next/server"
import { getSession, setSession } from "@/lib/auth"
import { getSQL } from "@/lib/db"

export async function POST(request: Request) {
  try {
    const session = await getSession()
    if (!session || session.role !== "student") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 })
    }

    const body = await request.json()
    const sql = getSQL()

    await sql`
      UPDATE registration_student_guidanceinformation
      SET last_name = ${body.last_name},
          first_name = ${body.first_name},
          middle_name = ${body.middle_name},
          lrn = ${body.lrn},
          email = ${body.email},
          contact = ${body.contact},
          address = ${body.address},
          birthdate = ${body.birthdate || null},
          guardian_name = ${body.guardian_name},
          guardian_contact = ${body.guardian_contact}
      WHERE LOWER(email) = LOWER(${session.email})
    `

    await sql`
      UPDATE registered_account
      SET last_name = ${body.last_name},
          first_name = ${body.first_name},
          middle_name = ${body.middle_name},
          lrn = ${body.lrn},
          email = ${body.email}
      WHERE LOWER(email) = LOWER(${session.email}) AND role = 'student'
    `

    // Update session email if changed
    if (body.email !== session.email) {
      await setSession({ ...session, email: body.email })
    }

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error("Update error:", error)
    return NextResponse.json(
      { error: "Internal server error" },
      { status: 500 }
    )
  }
}
