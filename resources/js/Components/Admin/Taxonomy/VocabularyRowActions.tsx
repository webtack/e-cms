import { Link, router } from '@inertiajs/react';
import { ListTree, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
export type VocabularyActionsRow = {
    key: string;
    name: string;
    terms_count: number;
};

type Props = {
    row: VocabularyActionsRow;
};

/**
 * Actions of a vocabulary row, a button group: the Terms button (link to the term tree) and the "⋮" menu with
 * Terms, Edit and Delete (set apart as destructive). Delete opens a confirmation dialog that names the
 * vocabulary; when the row says it has terms, the dialog explains why it cannot be deleted and offers no
 * confirmation. A vocabulary used by a field is refused by the server, which flashes the reason.
 *
 * @example
 * <DataTable rowActions={(row) => <VocabularyRowActions row={row} />} ... />
 */
export function VocabularyRowActions({ row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const blocker = row.terms_count > 0 ? t('Delete the terms of this vocabulary first.') : null;

    const confirm = () => {
        router.delete(`/admin/taxonomy/${row.key}`, { onFinish: () => setConfirmOpen(false) });
    };

    return (
        <>
            <ButtonGroup>
                <Button variant="outline" size="sm" asChild>
                    <Link href={`/admin/taxonomy/${row.key}/terms`}>{t('Terms')}</Link>
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon-sm" aria-label={t('Actions')}>
                            <MoreHorizontal aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`/admin/taxonomy/${row.key}/terms`}>
                                <ListTree aria-hidden />
                                {t('Terms')}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={`/admin/taxonomy/${row.key}/edit`}>
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
                        <AlertDialogTitle>{t('Delete vocabulary')}</AlertDialogTitle>
                        <AlertDialogDescription>{blocker ?? t('Delete vocabulary ":name"?', { name: row.name })}</AlertDialogDescription>
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
