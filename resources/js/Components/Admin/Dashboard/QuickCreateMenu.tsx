import { Link } from '@inertiajs/react';
import { ChevronDown, Plus } from 'lucide-react';

import type { NodeKind, QuickCreateType } from '@/Components/Admin/Dashboard/types';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/useTranslation';

const KINDS: NodeKind[] = ['content', 'page', 'block'];

/**
 * The "Create" menu; a choice opens the creation form of the type. By default the types are grouped by kind (the
 * server sends them ordered by kind and name). When `flat` is true (the administrator configured the menu) they are
 * listed in the given order without kind headings.
 *
 * @example
 * <QuickCreateMenu types={[{ key: 'news', name: 'News', kind: 'content', url: '/admin/content-types/news/nodes/create' }]} flat={false} />
 */
export function QuickCreateMenu({ types, flat = false }: { types: QuickCreateType[]; flat?: boolean }) {
    const { t } = useTranslation();
    const groups = flat ? [] : KINDS.map((kind) => ({ kind, items: types.filter((type) => type.kind === kind) })).filter((group) => group.items.length > 0);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button data-testid="dashboard-create">
                    <Plus />
                    {t('Create')}
                    <ChevronDown />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56" data-testid="dashboard-create-menu">
                {flat &&
                    types.map((type) => (
                        <DropdownMenuItem key={type.key} asChild>
                            <Link href={type.url}>{type.name}</Link>
                        </DropdownMenuItem>
                    ))}
                {groups.map((group, index) => (
                    <DropdownMenuGroup key={group.kind}>
                        {index > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel>{t(group.kind === 'content' ? 'Content' : group.kind === 'page' ? 'Page' : 'Block')}</DropdownMenuLabel>
                        {group.items.map((type) => (
                            <DropdownMenuItem key={type.key} asChild>
                                <Link href={type.url}>{type.name}</Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
