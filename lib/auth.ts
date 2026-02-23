import { cookies } from "next/headers"

export async function getSession() {
  const cookieStore = await cookies()
  const session = cookieStore.get("session")
  if (!session) return null
  try {
    return JSON.parse(session.value) as {
      email: string
      role: string
      strand: string
    }
  } catch {
    return null
  }
}

export async function setSession(data: {
  email: string
  role: string
  strand: string
}) {
  const cookieStore = await cookies()
  cookieStore.set("session", JSON.stringify(data), {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: 60 * 60 * 24, // 1 day
  })
}

export async function clearSession() {
  const cookieStore = await cookies()
  cookieStore.delete("session")
}
