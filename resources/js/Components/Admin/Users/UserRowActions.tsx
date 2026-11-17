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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';

/** The part of a table row the menu needs. */
export type UserActionsRow = {
    id: number;
    name: string;
    is_self: boolean;
    can_manage: boolean;
};

type Props = {
    row: UserActionsRow;
};

/**
 * Actions of a user row, a button group: the Edit button and the "⋮" menu with Edit and Delete. The signed-in
 * user's own row has no Delete. The server refuses to delete an author of content (and the last active
 * administrator) with a message; this menu does not know either, so it asks for a plain confirmation. A row with
 * `can_manage === false` (an administrator seen by a non-administrator) has no actions at all; the server refuses
 * them with 403 anyway.
 *
 * @example
 * <DataTable rowActions={(row) => <UserRowActions row={row} />} ... />
 */
export function UserRowActions({ row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);

    if (!row.can_manage) {
        return null;
    }

    const confirm = () => {
        router.delete(`/admin/users/${row.id}`, { onFinish: () => setConfirmOpen(false) });
    };

    return (
        <>
            <ButtonGroup>
                <Button variant="outline" size="sm" asChild>
                    <Link href={`/admin/users/${row.id}/edit`}>{t('Edit')}</Link>
                </Button>
                {!row.is_self && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="icon-sm" aria-label={t('Actions')}>
                                <MoreHorizontal aria-hidden />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link href={`/admin/users/${row.id}/edit`}>
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
                )}
            </ButtonGroup>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete user')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('Delete user ":name"? A user who authored content cannot be deleted; deactivate them instead.', { name: row.name })}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('Cancel')}</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={confirm}>
                            {t('Delete')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
