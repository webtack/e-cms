import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column } from '@/Components/Admin/DataTable/types';
import { TermRowActions, type TermUsagePlace } from '@/Components/Admin/Taxonomy/TermRowActions';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';
import { TOAST_ERROR_DURATION } from '@/lib/toastDurations';

type TermRow = {
    uuid: string;
    name: string | null;
    slug: string;
    weight: number;
    /** Level in the tree; a root has 1. */
    depth: number;
    children_count: number;
    /** Service value for the delete dialog; not a column. */
    usages: TermUsagePlace[];
};

type Props = {
    vocabulary: { key: string; name: string; max_depth: number | null };
    /** All terms of the vocabulary in tree order. */
    terms: TermRow[];
};

/**
 * Terms of a vocabulary as a tree: a client `DataTable` (all terms are passed in, search, sorting and paging run
 * in the browser, no filters) with a Create button and a row menu (edit, delete). The name is indented by `depth`.
 * Without a sort the table keeps the order the server sent (the tree walk).
 *
 * Rows can be reordered by dragging, which changes only the order (the weights): the parent of a term is changed in
 * its form. The page keeps a local copy of the terms so the new order shows at once; the order is then saved
 * (`PUT …/terms/order`, only the `terms` and `flash` props reload). If the server rejects it, the previous order
 * comes back and the error is shown as a toast.
 */
export default function Index({ vocabulary, terms }: Props) {
    const { t } = useTranslation();
    const [items, setItems] = useState(terms);

    // A reload (after saving the order, or any other visit) brings the server's truth.
    useEffect(() => setItems(terms), [terms]);

    const base = `/admin/taxonomy/${vocabulary.key}/terms`;

    const reorder = (ids: string[]) => {
        const previous = items;

        setItems(ids.map((id) => items.find((term) => term.uuid === id)!));
        router.put(
            `${base}/order`,
            { order: ids },
            {
                preserveScroll: true,
                only: ['terms', 'flash'],
                onError: (errors) => {
                    setItems(previous);
                    toast.error(errors.order ?? Object.values(errors)[0], { duration: TOAST_ERROR_DURATION });
                },
            },
        );
    };

    const columns: Column<TermRow>[] = [
        {
            key: 'name',
            header: 'Name',
            type: 'text',
            sortable: true,
            hideable: false,
            cell: (row) => (
                <div style={{ paddingLeft: `${(row.depth - 1) * 1.25}rem` }} data-depth={row.depth}>
                    {row.name}
                </div>
            ),
        },
        { key: 'slug', header: 'Slug', type: 'text', sortable: true },
        { key: 'weight', header: 'Weight', type: 'number', sortable: true },
        { key: 'children_count', header: 'Children', type: 'number', sortable: true },
    ];

    return (
        <>
            <Head title={t('Terms of “:name”', { name: vocabulary.name })} />
            <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <PageTitle>{t('Terms of “:name”', { name: vocabulary.name })}</PageTitle>
                <Link href={`/admin/taxonomy/${vocabulary.key}/edit`} className="text-sm text-link underline underline-offset-4">
                    {t('Edit vocabulary')}
                </Link>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">{t('Drag a row to change the order. The parent of a term is changed in its form.')}</p>

            <div className="mt-6">
                <DataTable
                    mode="client"
                    tableId="taxonomy-terms"
                    columns={columns}
                    getRowId={(row) => row.uuid}
                    rows={items}
                    reorder={{ onReorder: reorder }}
                    rowActions={(row) => <TermRowActions vocabularyKey={vocabulary.key} row={row} />}
                    toolbarActions={
                        <Button asChild>
                            <Link href={`${base}/create`}>{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
