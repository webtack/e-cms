"use client"

import * as React from "react"
import { cn } from "cn"
import { ChevronDown } from "lucide-react"
import { Accordion as AccordionPrimitive } from "radix-ui"

/**
 * Stack of sections that expand and collapse (shadcn style, on `radix-ui`). Each item has its own border and the items
 * are spaced apart. Use `type="single" collapsible` with a controlled `value` (the open item, `''` for none) to keep one
 * section open at a time and to open one from code.
 *
 * @example
 * <Accordion type="single" collapsible value={open} onValueChange={setOpen}>
 *     <AccordionItem value="details">
 *         <AccordionTrigger>Details</AccordionTrigger>
 *         <AccordionContent>…</AccordionContent>
 *     </AccordionItem>
 * </Accordion>
 */
function Accordion({ className, ...props }: React.ComponentProps<typeof AccordionPrimitive.Root>) {
  return <AccordionPrimitive.Root data-slot="accordion" className={cn("space-y-2", className)} {...props} />
}

/** One section of an `Accordion` with its own border; `value` identifies it among the open sections. */
function AccordionItem({ className, ...props }: React.ComponentProps<typeof AccordionPrimitive.Item>) {
  return <AccordionPrimitive.Item data-slot="accordion-item" className={cn("rounded-md border px-3", className)} {...props} />
}

/** The heading button of a section; it names the section and toggles it. */
function AccordionTrigger({ className, children, ...props }: React.ComponentProps<typeof AccordionPrimitive.Trigger>) {
  return (
    <AccordionPrimitive.Header className="flex">
      <AccordionPrimitive.Trigger
        data-slot="accordion-trigger"
        className={cn(
          "cursor-pointer flex flex-1 items-center justify-between gap-4 rounded-md py-3 text-left text-sm font-medium outline-none transition-all hover:underline focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 [&[data-state=open]>svg]:rotate-180",
          className
        )}
        {...props}
      >
        {children}
        <ChevronDown aria-hidden className="pointer-events-none size-4 shrink-0 text-muted-foreground transition-transform duration-200" />
      </AccordionPrimitive.Trigger>
    </AccordionPrimitive.Header>
  )
}

/** The body of a section; Radix keeps it unmounted while the section is closed. */
function AccordionContent({ className, children, ...props }: React.ComponentProps<typeof AccordionPrimitive.Content>) {
  return (
    <AccordionPrimitive.Content data-slot="accordion-content" className="overflow-hidden text-sm" {...props}>
      <div className={cn("space-y-4 pt-1 pb-4", className)}>{children}</div>
    </AccordionPrimitive.Content>
  )
}

export { Accordion, AccordionItem, AccordionTrigger, AccordionContent }
