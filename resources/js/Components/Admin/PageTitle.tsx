import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * The first-level heading of an admin page: 20px semibold. Each page passes its own spacing
 * (for example `mb-6` on form pages) through `className`; the auth pages keep their own heading.
 *
 * @example
 * <PageTitle className="mb-6">{t('Edit user')}</PageTitle>
 */
export function PageTitle({ className, ...props }: ComponentProps<'h1'>) {
    return <h1 className={cn('text-xl font-semibold', className)} {...props} />;
}
