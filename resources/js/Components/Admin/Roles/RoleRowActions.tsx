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
export type RoleActionsRow = {
    id: number;
    name: string;
    users_count: number;
    is_system: boolean;
};

type Props = {
    row: RoleActionsRow;
};

/**
 * Actions of a role row, a button group: the Edit button and the "⋮" menu with Edit and Delete. The system role
 * has no Delete; for a role that has users the dialog explains why it cannot be deleted and offers no
 * confirmation. The server checks the same rules again.
 *
 * @example
 * <DataTable rowActions={(row) => <RoleRowActions row={row} />} ... />
 */
export function RoleRowActions({ row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const blocker = row.users_count > 0 ? t('Move the users of this role to another role first.') : null;

    const confirm = () => {
        router.delete(`/admin/roles/${row.id}`, { onFinish: () => setConfirmOpen(false) });
    };

    return (
        <>
            <ButtonGroup>
                <Button variant="outline" size="sm" asChild>
                    <Link href={`/admin/roles/${row.id}/edit`}>{row.is_system ? t('View') : t('Edit')}</Link>
                </Button>
                {!row.is_system && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="icon-sm" aria-label={t('Actions')}>
                                <MoreHorizontal aria-hidden />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link href={`/admin/roles/${row.id}/edit`}>
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
                        <AlertDialogTitle>{t('Delete role')}</AlertDialogTitle>
                        <AlertDialogDescription>{blocker ?? t('Delete role ":name"?', { name: row.name })}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('Cancel')}</AlertDialogCancel>
                        {!blocker && (
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
