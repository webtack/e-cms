import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column } from '@/Components/Admin/DataTable/types';
import { MenuItemRowActions } from '@/Components/Admin/Menus/MenuItemRowActions';
import { MenuItemStatusBadge, type MenuItemStatus } from '@/Components/Admin/Menus/MenuItemStatusBadge';
import { StatusToggleBadge } from '@/Components/Admin/StatusToggleBadge';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';
import { TOAST_ERROR_DURATION } from '@/lib/toastDurations';

type ItemRow = {
    uuid: string;
    title: string | null;
    url: string | null;
    weight: number;
    /** Level in the tree; a root has 1. */
    depth: number;
    children_count: number;
    /** Computed from the item's own flag and dates; ancestors are not considered. */
    status: MenuItemStatus;
};

type Props = {
    menu: { key: string; name: string; max_depth: number | null };
    /** All items of the menu in tree order. */
    items: ItemRow[];
};

/**
 * Items of a menu as a tree: a client `DataTable` (all items are passed in, search and sorting run in
 * the browser, no filters) with a Create button and a row menu (edit, delete). The title is indented by `depth`.
 * Without a sort the table keeps the order the server sent (the tree walk).
 *
 * Rows can be reordered by dragging, which changes only the order (the weights): the parent of an item is changed
 * in its form. The page keeps a local copy of the items so the new order shows at once; the order is then saved
 * (`PUT …/items/order`, only the `items` and `flash` props reload). If the server rejects it, the previous order
 * comes back and the error is shown as a toast.
 */
export default function Index({ menu, items: serverItems }: Props) {
    const { t } = useTranslation();
    const [items, setItems] = useState(serverItems);

    // A reload (after saving the order, or any other visit) brings the server's truth.
    useEffect(() => setItems(serverItems), [serverItems]);

    const base = `/admin/menus/${menu.key}/items`;

    const reorder = (ids: string[]) => {
        const previous = items;

        setItems(ids.map((id) => items.find((item) => item.uuid === id)!));
        router.put(
            `${base}/order`,
            { order: ids },
            {
                preserveScroll: true,
                only: ['items', 'flash'],
                onError: (errors) => {
                    setItems(previous);
                    toast.error(errors.order ?? Object.values(errors)[0], { duration: TOAST_ERROR_DURATION });
                },
            },
        );
    };

    const columns: Column<ItemRow>[] = [
        {
            key: 'title',
            header: 'Title',
            type: 'text',
            sortable: true,
            hideable: false,
            cell: (row) => (
                <div style={{ paddingLeft: `${(row.depth - 1) * 1.25}rem` }} data-depth={row.depth}>
                    {row.title}
                </div>
            ),
        },
        { key: 'url', header: 'Address', type: 'text', sortable: true, cell: (row) => row.url ?? '' },
        {
            key: 'status',
            header: 'Status',
            type: 'text',
            sortable: true,
            cell: (row) => (
                <StatusToggleBadge status={row.status} url={`${base}/${row.uuid}/active`} only={['items', 'flash']}>
                    <MenuItemStatusBadge status={row.status} />
                </StatusToggleBadge>
            ),
        },
        { key: 'weight', header: 'Weight', type: 'number', sortable: true },
        { key: 'children_count', header: 'Children', type: 'number', sortable: true },
    ];

    return (
        <>
            <Head title={t('Items of “:name”', { name: menu.name })} />
            <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <PageTitle>{t('Items of “:name”', { name: menu.name })}</PageTitle>
                <Link href={`/admin/menus/${menu.key}/edit`} className="text-sm text-link underline underline-offset-4">
                    {t('Edit menu')}
                </Link>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">{t('Drag a row to change the order. The parent of an item is changed in its form.')}</p>

            <div className="mt-6">
                <DataTable
                    mode="client"
                    tableId="menu-items"
                    columns={columns}
                    getRowId={(row) => row.uuid}
                    rows={items}
                    paginated={false}
                    reorder={{ onReorder: reorder }}
                    rowActions={(row) => <MenuItemRowActions menuKey={menu.key} row={row} />}
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
