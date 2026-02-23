import type { Metadata, Viewport } from "next"
import { Inter } from "next/font/google"
import "./globals.css"

const inter = Inter({ subsets: ["latin"], variable: "--font-inter" })

export const metadata: Metadata = {
  title: "Children of Fatima School of Mabalacat Inc. - Portal",
  description:
    "Student, Adviser, and Guidance portal for Children of Fatima School of Mabalacat Inc.",
}

export const viewport: Viewport = {
  themeColor: "#0b1120",
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en">
      <body className={`${inter.variable} font-sans antialiased`}>
        {children}
      </body>
    </html>
  )
}
