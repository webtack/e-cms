import { Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { ButtonGroup } from '@/Components/ui/button-group';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';

/** The part of a table row the menu needs. */
export type MenuItemActionsRow = {
    uuid: string;
    title: string | null;
    children_count: number;
};

type Props = {
    /** Key of the menu the item belongs to. */
    menuKey: string;
    row: MenuItemActionsRow;
};

/**
 * Actions of a menu item row, a button group: the Edit button (link to the edit form) and the "⋮" menu with Edit
 * and Delete (set apart as destructive). Delete opens a confirmation dialog that names the item. An item with
 * child items gets a dialog that explains that they must be moved or deleted first and offers no confirmation.
 * The server checks the same rule again.
 *
 * @example
 * <DataTable rowActions={(row) => <MenuItemRowActions menuKey="main" row={row} />} ... />
 */
export function MenuItemRowActions({ menuKey, row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const base = `/admin/menus/${menuKey}/items/${row.uuid}`;
    const blocked = row.children_count > 0;

    const confirm = () => {
        router.delete(base, { onFinish: () => setConfirmOpen(false) });
    };

    return (
        <>
            <ButtonGroup>
                <Button variant="outline" size="sm" asChild>
                    <Link href={`${base}/edit`}>{t('Edit')}</Link>
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon-sm" aria-label={t('Actions')}>
                            <MoreHorizontal aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`${base}/edit`}>
                                <Pencil aria-hidden />
                                {t('Edit')}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" onSelect={() => setConfirmOpen(true)}>
                            <Trash2 aria-hidden />
                            {t('Delete')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </ButtonGroup>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete menu item')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {blocked ? t('Move or delete the child items of this item first.') : t('Delete menu item ":name"?', { name: row.title ?? '' })}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('Cancel')}</AlertDialogCancel>
                        {!blocked && (
                            <AlertDialogAction variant="destructive" onClick={confirm}>
                                {t('Delete')}
                            </AlertDialogAction>
                        )}
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
