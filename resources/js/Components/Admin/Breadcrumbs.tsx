import { Link, usePage } from '@inertiajs/react';
import { Fragment } from 'react';

import { getBreadcrumbs } from '@/Components/Admin/navigation';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { useTranslation } from '@/hooks/useTranslation';

/** Breadcrumbs derived from the current URL and `navigation.ts`. */
export function Breadcrumbs() {
    const { url } = usePage();
    const { t } = useTranslation();
    const crumbs = getBreadcrumbs(url);

    return (
        <Breadcrumb>
            <BreadcrumbList>
                {crumbs.map((crumb, index) => {
                    const isLast = index === crumbs.length - 1;

                    return (
                        <Fragment key={crumb.title}>
                            <BreadcrumbItem>
                                {isLast || !crumb.href ? (
                                    <BreadcrumbPage>{t(crumb.title)}</BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink asChild>
                                        <Link href={crumb.href}>{t(crumb.title)}</Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                            {!isLast && <BreadcrumbSeparator />}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
