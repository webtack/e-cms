import { Link, router } from '@inertiajs/react';
import { Copy, Eye, MoreHorizontal, Trash2 } from 'lucide-react';
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
import { CloneTypeDialog } from '@/Components/Admin/ContentTypes/CloneTypeDialog';
import type { ParentOption } from '@/Components/Admin/ContentTypes/ContentTypeForm';
import { sectionLabels, type TypeSection, typePublicUrl } from '@/Components/Admin/ContentTypes/section';
import { typeSectionItems } from '@/Components/Admin/ContentTypes/typeSectionItems';
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
export type ContentTypeActionsRow = {
    key: string;
    name: string;
    /** Public list address is built from it (`typePublicUrl`); empty for page and block types. */
    slug?: string | null;
    /** Pre-fills the parent select of the clone dialog. */
    parent_type?: string | null;
    nodes_count: number;
    children_count: number;
};

type Props = {
    section: TypeSection;
    row: ContentTypeActionsRow;
    /** Types the clone dialog offers as the parent (the list page's `parentOptions`). */
    parentOptions: ParentOption[];
    /** True on the "Child types" tab: deleting a row then returns to that tab instead of the types list. */
    fromChildren?: boolean;
};

/**
 * Actions of a type row (content or page, per `section`), a button group: the Settings button (link to the edit form) and
 * the "⋮" menu with the destinations from `typeSectionItems(section.kind)` (Edit, Display, SEO, Fields, Nodes, Child types; no SEO
 * for blocks), shared with the section tabs, then Preview (the public list page of the type in a new tab, only when
 * `typePublicUrl` gives an address), Clone (opens `CloneTypeDialog`) and Delete (set apart as destructive). Delete opens a confirmation dialog that names the type;
 * when the row says the type has nodes or child types, the dialog explains why it cannot be
 * deleted and offers no confirmation. The server checks this again on its own.
 *
 * The dialog is rendered next to the menu, not inside it, so it stays open after the menu closes.
 *
 * @example
 * <DataTable rowActions={(row) => <ContentTypeRowActions section={section} row={row} />} ... />
 */
export function ContentTypeRowActions({ section, row, parentOptions, fromChildren = false }: Props) {
    const { t } = useTranslation();
    const labels = sectionLabels(section.kind);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [cloneOpen, setCloneOpen] = useState(false);
    const publicUrl = typePublicUrl(section, row.slug);

    const blocker =
        row.nodes_count > 0
            ? t('Delete the nodes of this type first.')
            : row.children_count > 0
              ? t('Delete or move the child types of this type first.')
              : null;

    const confirm = () => {
        router.delete(`${section.basePath}/${row.key}`, {
            // The server returns to the parent's "Child types" tab for this flag.
            data: fromChildren ? { from_children: 1 } : {},
            onFinish: () => setConfirmOpen(false) });
    };

    return (
        <>
            <ButtonGroup>
                <Button variant="outline" size="sm" asChild>
                    <Link href={`${section.basePath}/${row.key}/nodes`}>{t('Nodes')}</Link>
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon-sm" aria-label={t('Actions')}>
                            <MoreHorizontal aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {typeSectionItems(section.kind).map(({ id, label, menuLabel, path, icon: Icon }) => (
                            <DropdownMenuItem key={id} asChild>
                                <Link href={`${section.basePath}/${row.key}/${path}`}>
                                    <Icon aria-hidden />
                                    {t(menuLabel ?? label)}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                        {publicUrl && (
                            <DropdownMenuItem asChild>
                                <a href={publicUrl} target="_blank" rel="noopener noreferrer">
                                    <Eye aria-hidden />
                                    {t('Preview')}
                                </a>
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem onSelect={() => setCloneOpen(true)}>
                            <Copy aria-hidden />
                            {t('Clone')}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" onSelect={() => setConfirmOpen(true)}>
                            <Trash2 aria-hidden />
                            {t('Delete')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </ButtonGroup>

            <CloneTypeDialog
                section={section}
                source={{ key: row.key, name: row.name, parent_type: row.parent_type ?? null }}
                parentOptions={parentOptions}
                open={cloneOpen}
                onOpenChange={setCloneOpen}
            />

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t(labels.deleteTitle)}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {blocker ?? t(labels.deleteConfirm, { name: row.name })}
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
