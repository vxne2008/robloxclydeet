import { NextResponse } from "next/server"
import bcrypt from "bcryptjs"
import { getSQL } from "@/lib/db"

const ADVISER_EMAILS = [
  "vjperez@cfsieducation.com",
  "nhsicat@cfsieducation.com",
  "abm_maslow@cfsieducation.com",
  "jennilyndizon@cfsieducation.com",
]

const GUIDANCE_EMAILS = ["guidanceadvocateportal@cfsieducation.com"]

export async function POST(request: Request) {
  try {
    const body = await request.json()
    const {
      role,
      last_name,
      first_name,
      middle_name,
      email,
      password,
      confirm_password,
      lrn,
      section,
      contact,
      home_address,
      birth_date,
      guardian_name,
      guardian_contact,
    } = body

    if (!email || !password || !role || !last_name || !first_name) {
      return NextResponse.json(
        { error: "Required fields are missing" },
        { status: 400 }
      )
    }

    if (!email.endsWith("@cfsieducation.com")) {
      return NextResponse.json(
        { error: "The email you inputted has the wrong domain." },
        { status: 400 }
      )
    }

    if (password !== confirm_password) {
      return NextResponse.json(
        { error: "Passwords do not match" },
        { status: 400 }
      )
    }

    const sql = getSQL()
    const hashedPassword = await bcrypt.hash(password, 10)

    if (role === "adviser" || role === "guidance") {
      const allowed = role === "adviser" ? ADVISER_EMAILS : GUIDANCE_EMAILS
      if (!allowed.includes(email)) {
        return NextResponse.json(
          {
            error:
              role === "guidance"
                ? "Only authorized guidance email is allowed."
                : "Only authorized adviser emails are allowed.",
          },
          { status: 400 }
        )
      }

      // Check existing
      const existingEmail =
        await sql`SELECT id FROM registration_adviser_guidanceportal WHERE email = ${email}`
      const existingName =
        await sql`SELECT id FROM registration_adviser_guidanceportal WHERE first_name = ${first_name} AND middle_name = ${middle_name || ""} AND last_name = ${last_name}`
      const existingRegEmail =
        await sql`SELECT id FROM registered_account WHERE email = ${email} LIMIT 1`

      if (
        existingEmail.length > 0 ||
        existingName.length > 0 ||
        existingRegEmail.length > 0
      ) {
        return NextResponse.json(
          { error: "Your Name or Email Address already exists" },
          { status: 400 }
        )
      }

      const strand = role === "adviser" ? section : "GUIDANCE"

      if (role === "adviser" && !strand) {
        return NextResponse.json(
          { error: "Strand is required" },
          { status: 400 }
        )
      }

      // Check if strand already has adviser
      if (role === "adviser") {
        const existingStrand =
          await sql`SELECT id FROM registration_adviser_guidanceportal WHERE strand = ${strand}`
        if (existingStrand.length > 0) {
          return NextResponse.json(
            {
              error:
                "This strand already has an adviser. Only one adviser per strand is allowed.",
            },
            { status: 400 }
          )
        }
      }

      await sql`INSERT INTO registration_adviser_guidanceportal (last_name, first_name, middle_name, strand, email, password) VALUES (${last_name}, ${first_name}, ${middle_name || ""}, ${strand}, ${email}, ${hashedPassword})`

      const roleVal = role === "guidance" ? "guidance" : "adviser"
      await sql`INSERT INTO registered_account (role, last_name, first_name, middle_name, lrn, strand, email, password) VALUES (${roleVal}, ${last_name}, ${first_name}, ${middle_name || ""}, ${null}, ${strand}, ${email}, ${hashedPassword})`
    } else if (role === "student") {
      if (!lrn) {
        return NextResponse.json(
          { error: "LRN is required for students" },
          { status: 400 }
        )
      }

      // Check existing
      const existingLRN =
        await sql`SELECT id FROM registration_student_guidanceinformation WHERE lrn = ${lrn}`
      const existingEmail =
        await sql`SELECT id FROM registration_student_guidanceinformation WHERE email = ${email}`
      const existingRegLRN =
        await sql`SELECT id FROM registered_account WHERE lrn = ${lrn} LIMIT 1`
      const existingRegEmail =
        await sql`SELECT id FROM registered_account WHERE email = ${email} LIMIT 1`

      if (existingLRN.length > 0 || existingRegLRN.length > 0) {
        return NextResponse.json(
          { error: "Your LRN already exists" },
          { status: 400 }
        )
      }

      if (existingEmail.length > 0 || existingRegEmail.length > 0) {
        return NextResponse.json(
          { error: "Your Email Address already exists" },
          { status: 400 }
        )
      }

      const strand = (section || "").trim()

      await sql`INSERT INTO registration_student_guidanceinformation (last_name, first_name, middle_name, lrn, email, contact, address, birthdate, guardian_name, guardian_contact, strand, section, password) VALUES (${last_name}, ${first_name}, ${middle_name || ""}, ${lrn}, ${email}, ${contact || ""}, ${home_address || ""}, ${birth_date || null}, ${guardian_name || ""}, ${guardian_contact || ""}, ${strand}, ${strand}, ${hashedPassword})`

      await sql`INSERT INTO registered_account (role, last_name, first_name, middle_name, lrn, strand, email, password) VALUES ('student', ${last_name}, ${first_name}, ${middle_name || ""}, ${lrn}, ${strand}, ${email}, ${hashedPassword})`
    } else {
      return NextResponse.json({ error: "Invalid role" }, { status: 400 })
    }

    return NextResponse.json({ success: true })
  } catch (error) {
    console.error("Registration error:", error)
    return NextResponse.json(
      { error: "An internal error occurred" },
      { status: 500 }
    )
  }
}
