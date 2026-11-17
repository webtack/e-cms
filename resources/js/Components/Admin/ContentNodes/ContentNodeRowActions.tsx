import { Link, router } from '@inertiajs/react';
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
export type ContentNodeActionsRow = {
    uuid: string;
    slug: string;
    /** The value of the `title` field; names the node in the delete dialog. */
    title?: string | null;
    /** Relative public address of the node, decided by the server; `null` or absent for blocks and types without a public slug. */
    publicUrl?: string | null;
};

type Props = {
    section: TypeSection;
    /** Key of the content type that owns the node. */
    typeKey: string;
    row: ContentNodeActionsRow;
};

/**
 * Actions of a content node row, a button group: the Edit button (link to the edit form) and the "⋮" menu with
 * Edit (same form), Preview (the public address in a new tab, only when `row.publicUrl` is set) and Delete (set apart
 * as destructive). Delete opens a confirmation dialog that names the node
 * by its `title`, or by its slug when it has none. Nodes are addressed by `uuid`, never by slug.
 *
 * The dialog is rendered next to the menu, not inside it, so it stays open after the menu closes.
 *
 * @example
 * <DataTable rowActions={(row) => <ContentNodeRowActions section={section} typeKey="news" row={{ uuid, slug, title, publicUrl: '/news/hello' }} />} ... />
 */
export function ContentNodeRowActions({ section, typeKey, row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const base = `${section.basePath}/${typeKey}/nodes/${row.uuid}`;

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
                        {row.publicUrl && (
                            <DropdownMenuItem asChild>
                                <a href={row.publicUrl} target="_blank" rel="noopener noreferrer">
                                    <Eye aria-hidden />
                                    {t('Preview')}
                                </a>
                            </DropdownMenuItem>
                        )}
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
                        <AlertDialogTitle>{t('Delete node')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('Delete the node “:name”? Its values in all languages will be deleted too. This cannot be undone.', {
                                name: row.title || row.slug,
                            })}
                        </AlertDialogDescription>
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
