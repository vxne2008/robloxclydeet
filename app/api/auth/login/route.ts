import { NextResponse } from "next/server"
import bcrypt from "bcryptjs"
import { getSQL } from "@/lib/db"
import { setSession } from "@/lib/auth"

export async function POST(request: Request) {
  try {
    const { email, password } = await request.json()

    if (!email || !password) {
      return NextResponse.json(
        { error: "Email and password are required" },
        { status: 400 }
      )
    }

    const sql = getSQL()
    const rows = await sql`
      SELECT id, role, strand, password FROM registered_account 
      WHERE email = ${email} LIMIT 1
    `

    if (rows.length === 0) {
      return NextResponse.json(
        { error: "Invalid email or password" },
        { status: 401 }
      )
    }

    const user = rows[0]
    const valid = await bcrypt.compare(password, user.password)

    if (!valid) {
      return NextResponse.json(
        { error: "Invalid email or password" },
        { status: 401 }
      )
    }

    const role = (user.role as string).toLowerCase().trim()
    const strand = ((user.strand as string) || "").toUpperCase().trim()

    await setSession({ email, role, strand })

    let redirectTo = "/"

    if (role === "guidance") {
      redirectTo = "/guidance"
    } else if (role === "adviser") {
      redirectTo = "/adviser"
    } else if (role === "student") {
      redirectTo = "/student"
    }

    return NextResponse.json({ success: true, redirectTo })
  } catch (error) {
    console.error("Login error:", error)
    return NextResponse.json(
      { error: "An internal error occurred" },
      { status: 500 }
    )
  }
}
