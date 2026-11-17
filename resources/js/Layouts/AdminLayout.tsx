import type { ReactNode } from 'react';

import { AdminHeader } from '@/Components/Admin/AdminHeader';
import { AppSidebar } from '@/Components/Admin/AppSidebar';
import { FlashToaster } from '@/Components/Admin/FlashToaster';
import { ThemeProvider } from '@/Components/Admin/ThemeProvider';
import { SidebarInset, SidebarProvider } from '@/Components/ui/sidebar';
import { Toaster } from '@/Components/ui/sonner';
import { TooltipProvider } from '@/Components/ui/tooltip';

/**
 * Persistent admin shell: theme, sidebar, header and toasts. Pages opt in via
 * `Page.layout = (page) => <AdminLayout>{page}</AdminLayout>` so the shell
 * stays mounted between Inertia visits (keeping the sidebar state).
 *
 * @example
 * Dashboard.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <ThemeProvider>
            <TooltipProvider>
                <SidebarProvider>
                    <AppSidebar />
                    {/* `min-w-0` lets the flex item shrink below its content width, so a wide table
                        scrolls inside its own container instead of widening the page. */}
                    <SidebarInset className="min-w-0">
                        <AdminHeader />
                        <main className="flex-1 p-4 md:p-6">{children}</main>
                    </SidebarInset>
                </SidebarProvider>
                <FlashToaster />
                <Toaster />
            </TooltipProvider>
        </ThemeProvider>
    );
}
