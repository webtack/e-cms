import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { DataTable } from '@/Components/Admin/DataTable/DataTable';
import type { Column, TablePageProps } from '@/Components/Admin/DataTable/types';
import { VocabularyRowActions } from '@/Components/Admin/Taxonomy/VocabularyRowActions';
import { Button } from '@/Components/ui/button';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

type VocabularyRow = {
    key: string;
    name: string;
    terms_count: number;
    max_depth: number | null;
    /** Server-only column: searched, shown under the name. */
    description: string | null;
};

type Props = {
    table: TablePageProps<VocabularyRow>;
};

const columns: Column<VocabularyRow>[] = [
    {
        key: 'name',
        header: 'Name',
        type: 'text',
        sortable: true,
        hideable: false,
        width: '24rem',
        // `width` only bounds the cell; each line is cut to one line here.
        cell: (row) => (
            <div>
                <div className="truncate" title={row.name}>
                    {row.name}
                </div>
                {row.description && (
                    <div className="truncate text-xs text-muted-foreground" title={row.description}>
                        {row.description}
                    </div>
                )}
            </div>
        ),
    },
    { key: 'key', header: 'Key', type: 'text', sortable: true, hideable: false },
    { key: 'terms_count', header: 'Terms', type: 'number', sortable: true },
    { key: 'max_depth', header: 'Maximum depth', type: 'number', sortable: true, searchable: false, cell: (row) => row.max_depth ?? '' },
];

/**
 * Vocabularies list (`GET /admin/taxonomy`): a server `DataTable` (state in the address, no filters) with a Create
 * button linking to the create form and a row menu (terms, edit, delete).
 */
export default function Index({ table }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Taxonomy')} />
            <PageTitle>{t('Taxonomy')}</PageTitle>

            <div className="mt-6">
                <DataTable
                    tableId="taxonomy-vocabularies"
                    columns={columns}
                    getRowId={(row) => row.key}
                    data={table}
                    rowActions={(row) => <VocabularyRowActions row={row} />}
                    toolbarActions={
                        <Button asChild>
                            <Link href="/admin/taxonomy/create">{t('Create')}</Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
