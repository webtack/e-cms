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
import type { TypeSection } from '@/Components/Admin/ContentTypes/section';
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
export type ContentFieldActionsRow = {
    key: string;
    name: string;
    is_system: boolean;
    has_values: boolean;
};

type Props = {
    section: TypeSection;
    /** Key of the content type that owns the field. */
    typeKey: string;
    row: ContentFieldActionsRow;
};

/**
 * Actions of a content field row, a button group: the Edit button (link to the edit form, always
 * enabled) and the "⋮" menu with Edit (same form) and Delete (set apart as destructive). Delete is
 * disabled for a system field. For a field that has values it opens a
 * dialog that explains why the field cannot be deleted and offers no confirmation; otherwise the
 * dialog names the field and asks to confirm. The server checks both rules again on its own.
 *
 * The dialog is rendered next to the menu, not inside it, so it stays open after the menu closes.
 *
 * @example
 * <DataTable rowActions={(row) => <ContentFieldRowActions section={section} typeKey="news" row={row} />} ... />
 */
export function ContentFieldRowActions({ section, typeKey, row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const base = `${section.basePath}/${typeKey}/fields/${row.key}`;

    const blocker = row.has_values ? t('Delete the values of this field first.') : null;

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
                        <DropdownMenuItem variant="destructive" disabled={row.is_system} onSelect={() => setConfirmOpen(true)}>
                            <Trash2 aria-hidden />
                            {t('Delete')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </ButtonGroup>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete field')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {blocker ?? t('Delete the field “:name”? This cannot be undone.', { name: row.name })}
                        </AlertDialogDescription>
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
