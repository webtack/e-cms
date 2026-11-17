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

/** A place that uses a term: the field of a content type and the number of values. */
export type TermUsagePlace = {
    owner: string;
    content_type: string;
    field_key: string;
    count: number;
};

/** The part of a table row the menu needs. */
export type TermActionsRow = {
    uuid: string;
    name: string | null;
    children_count: number;
    usages: TermUsagePlace[];
};

type Props = {
    /** Key of the vocabulary the term belongs to. */
    vocabularyKey: string;
    row: TermActionsRow;
};

/**
 * Actions of a term row, a button group: the Edit button (link to the edit form) and the "⋮" menu with Edit
 * and Delete (set apart as destructive). Delete opens a confirmation dialog that names the term. A term with
 * child terms or with uses gets a dialog that explains why it cannot be deleted (for uses it lists the places:
 * field, content type and number of values) and offers no confirmation. The server checks both rules again.
 *
 * @example
 * <DataTable rowActions={(row) => <TermRowActions vocabularyKey="categories" row={row} />} ... />
 */
export function TermRowActions({ vocabularyKey, row }: Props) {
    const { t } = useTranslation();
    const [confirmOpen, setConfirmOpen] = useState(false);
    const base = `/admin/taxonomy/${vocabularyKey}/terms/${row.uuid}`;
    const name = row.name ?? '';

    const confirm = () => {
        router.delete(base, { onFinish: () => setConfirmOpen(false) });
    };

    let description: string;
    let places: string[] = [];

    if (row.children_count > 0) {
        description = t('Move or delete the child terms of this term first.');
    } else if (row.usages.length > 0) {
        description = t('This term is used by content and cannot be deleted. Used in:');
        places = row.usages.map((place) => `${place.field_key} (${place.content_type}): ${place.count}`);
    } else {
        description = t('Delete term ":name"?', { name });
    }

    const blocked = row.children_count > 0 || row.usages.length > 0;

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
                        <AlertDialogTitle>{t('Delete term')}</AlertDialogTitle>
                        <AlertDialogDescription>{description}</AlertDialogDescription>
                        {places.length > 0 && (
                            <ul className="list-disc pl-5 text-sm text-muted-foreground" data-testid="term-usages">
                                {places.map((place) => (
                                    <li key={place}>{place}</li>
                                ))}
                            </ul>
                        )}
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
