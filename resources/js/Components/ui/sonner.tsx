"use client"

import { useTheme } from "@/Components/Admin/ThemeProvider"
import { Toaster as Sonner, type ToasterProps } from "sonner"
import { CircleCheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon } from "lucide-react"

/**
 * Shared admin toast container (Sonner), mounted once in `AdminLayout`.
 *
 * Toasts appear in the top-right corner (no offset, so they may overlap the
 * header and never cover the Save button of the bottom action bar), are about
 * 440px wide with larger text and icons, and always have a close button.
 * Each status (success, error, warning, info) gets a border, a tinted
 * background and an icon color from the theme tokens `--success`,
 * `--destructive`, `--warning` and `--info`, so both themes are covered
 * without Sonner `richColors`. The tint is mixed into `--popover`, so the
 * toast stays opaque. The status classes use `!` because Sonner's own styles
 * have a higher specificity than a single utility class.
 *
 * Durations are not set here: Sonner has one `duration` per toast, see
 * `@/lib/toastDurations`.
 */
const Toaster = ({ ...props }: ToasterProps) => {
  const { resolvedTheme } = useTheme()

  return (
    <Sonner
      theme={resolvedTheme}
      className="toaster group"
      position="top-right"
      closeButton
      icons={{
        success: (
          <CircleCheckIcon className="size-5" />
        ),
        info: (
          <InfoIcon className="size-5" />
        ),
        warning: (
          <TriangleAlertIcon className="size-5" />
        ),
        error: (
          <OctagonXIcon className="size-5" />
        ),
        loading: (
          <Loader2Icon className="size-5 animate-spin" />
        ),
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
          "--width": "440px",
        } as React.CSSProperties
      }
      toastOptions={{
        classNames: {
          toast: "cn-toast !gap-3 !p-4 !text-base",
          success:
            "!border-success/50 !bg-[color-mix(in_oklab,var(--success)_12%,var(--popover))] [&_[data-icon]]:!text-success",
          error:
            "!border-destructive/50 !bg-[color-mix(in_oklab,var(--destructive)_12%,var(--popover))] [&_[data-icon]]:!text-destructive",
          warning:
            "!border-warning/50 !bg-[color-mix(in_oklab,var(--warning)_12%,var(--popover))] [&_[data-icon]]:!text-warning",
          info:
            "!border-info/50 !bg-[color-mix(in_oklab,var(--info)_12%,var(--popover))] [&_[data-icon]]:!text-info",
        },
      }}
      {...props}
    />
  )
}

export { Toaster }
