import { neon } from "@neondatabase/serverless"

export function getSQL() {
  const sql = neon(process.env.DATABASE_URL!)
  return sql
}
