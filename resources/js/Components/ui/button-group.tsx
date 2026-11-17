import * as React from "react"
import { cn } from "cn"

/**
 * Joins adjacent buttons into one visual group: borders overlap and only the outer
 * corners stay rounded. Buttons inside get `in-data-[slot=button-group]` styles from `Button`.
 *
 * @example
 * <ButtonGroup>
 *   <Button variant="outline" size="sm" asChild><Link href="/edit">Edit</Link></Button>
 *   <Button variant="outline" size="icon-sm" aria-label="Actions"><MoreHorizontal /></Button>
 * </ButtonGroup>
 */
function ButtonGroup({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      role="group"
      data-slot="button-group"
      className={cn(
        "inline-flex w-fit items-stretch *:focus-visible:relative *:focus-visible:z-10 [&>*:not(:first-child)]:rounded-l-none [&>*:not(:first-child)]:border-l-0 [&>*:not(:last-child)]:rounded-r-none",
        className
      )}
      {...props}
    />
  )
}

export { ButtonGroup }
