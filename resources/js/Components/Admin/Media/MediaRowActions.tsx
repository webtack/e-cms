import { router } from '@inertiajs/react';
import { Eye, MoreHorizontal, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { MediaDetails } from '@/Components/Admin/Media/MediaDetails';
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

/** The part of a library row the actions need. */
export type MediaActionsRow = {
    uuid: string;
    name: string;
    /** Number of places the file is used in; a used file cannot be deleted. */
    uses: number;
    /** Whether the user's level allows deleting this file; without it the menu offers Details only. */
    can_delete: boolean;
};

type Props = {
    row: MediaActionsRow;
};

/**
 * Actions of a media library row: "Details" (the panel with the places the file is used in) and the "⋮" menu with
 * Details and Delete. Delete is offered only for an unused file and asks for confirmation; the server checks again
 * and refuses a used file whatever this menu showed. The dialog and the panel sit next to the menu, not inside it,
 * so they stay open after the menu closes.
 *
 * @example
 * <DataTable rowActions={(row) => <MediaRowActions row={row} />} ... />
 */
export function MediaRowActions({ row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);

    const confirm = () => {
        router.delete(`/admin/media/${row.uuid}`, { preserveScroll: true, onFinish: () => setConfirmOpen(false) });
    };

    return (
        <>
            <ButtonGroup>
                <Button variant="outline" size="sm" onClick={() => setDetailsOpen(true)}>
                    {t('Details')}
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon-sm" aria-label={t('Actions')}>
                            <MoreHorizontal aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => setDetailsOpen(true)}>
                            <Eye aria-hidden />
                            {t('Details')}
                        </DropdownMenuItem>
                        {row.can_delete && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem variant="destructive" disabled={row.uses > 0} onSelect={() => setConfirmOpen(true)}>
                                    <Trash2 aria-hidden />
                                    {t('Delete')}
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </ButtonGroup>

            <MediaDetails uuid={row.uuid} open={detailsOpen} onOpenChange={setDetailsOpen} />

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete file')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('Delete the file “:name”? It is not used anywhere. This cannot be undone.', { name: row.name })}</AlertDialogDescription>
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
