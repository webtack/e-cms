import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';

import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import { ContentFieldRowActions } from '@/Components/Admin/ContentFields/ContentFieldRowActions';
import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column } from '@/Components/Admin/DataTable/types';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { TOAST_ERROR_DURATION } from '@/lib/toastDurations';

type ContentFieldRow = {
    key: string;
    name: string;
    type: string;
    is_multiple: boolean;
    is_required: boolean;
    is_translatable: boolean;
    is_system: boolean;
    weight: number;
    /** Service value for the delete dialog; not a column. */
    has_values: boolean;
};

type Props = {
    section: TypeSection;
    type: NavType;
    /** All fields of the type, already ordered by weight. */
    fields: ContentFieldRow[];
};

/**
 * Fields of a type (kind `content` or `page`): a client `DataTable` (all fields are passed in,
 * search, sorting and paging run in the browser, no filters) with a Create button and a row menu
 * (edit, delete). Without a sort the table keeps the order the server sent (by weight).
 *
 * Rows can be reordered by dragging. The page keeps a local copy of the fields so the new order
 * shows at once; the order is then saved (`PUT …/fields/order`, only the `fields` and `flash` props reload).
 * If the server rejects it, the previous order comes back and the error is shown as a toast.
 */
export default function Index({ section, type, fields }: Props) {
    const { t } = useTranslation();
    const [items, setItems] = useState(fields);

    // A reload (after saving the order, or any other visit) brings the server's truth.
    useEffect(() => setItems(fields), [fields]);

    const reorder = (ids: string[]) => {
        const previous = items;

        setItems(ids.map((id) => items.find((field) => field.key === id)!));
        router.put(
            `${section.basePath}/${type.key}/fields/order`,
            { order: ids },
            {
                preserveScroll: true,
                only: ['fields', 'flash'],
                onError: (errors) => {
                    setItems(previous);
                    toast.error(errors.order ?? Object.values(errors)[0], { duration: TOAST_ERROR_DURATION });
                },
            },
        );
    };

    const columns: Column<ContentFieldRow>[] = [
        {
            key: 'name',
            header: 'Name',
            type: 'text',
            sortable: true,
            hideable: false,
            cell: (row) => (
                <div>
                    <div>{row.name}</div>
                    {row.is_system && <div className="text-xs text-muted-foreground">{t('System')}</div>}
                </div>
            ),
        },
        { key: 'key', header: 'Key', type: 'text', sortable: true, hideable: false },
        { key: 'type', header: 'Value type', type: 'text', sortable: true },
        { key: 'is_multiple', header: 'Multiple', type: 'boolean', sortable: true },
        { key: 'is_required', header: 'Required', type: 'boolean', sortable: true },
        { key: 'is_translatable', header: 'Translatable', type: 'boolean', sortable: true },
        { key: 'weight', header: 'Weight', type: 'number', sortable: true },
    ];

    return (
        <>
            <Head title={t('Fields of “:name”', { name: type.name })} />
            <ContentTypeNav section={section} type={type} active="fields" />

            <div className="mt-6">
                <DataTable
                    mode="client"
                    tableId="content-fields"
                    columns={columns}
                    getRowId={(row) => row.key}
                    rows={items}
                    reorder={{ onReorder: reorder }}
                    rowActions={(row) => <ContentFieldRowActions section={section} typeKey={type.key} row={row} />}
                    toolbarActions={
                        <Button asChild>
                            <Link href={`${section.basePath}/${type.key}/fields/create`}>{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
