import { Link, usePage } from '@inertiajs/react';
import { Blocks, ChevronRight, FileStack, FileText, Folder, FolderOpen, Navigation, Settings, Star, Table2, type LucideIcon } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

import { BrandLogo } from '@/Components/Admin/BrandLogo';
import { activeItem, isActive, visibleNavigation, visibleSections, type NavItem } from '@/Components/Admin/navigation';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
} from '@/Components/ui/sidebar';
import { useTranslation } from '@/hooks/useTranslation';
import type { NavigationGroup, NavigationLink } from '@/types';

/** Icon of a link to a type, by the kind of the type. */
const KIND_ICONS: Record<NavigationLink['kind'], LucideIcon> = { content: Table2, page: FileStack, block: Blocks };

/** Icon of every link to a node, whatever the kind of its type. */
const NODE_ICON: LucideIcon = FileText;

/** The icon of a menu or favorites link. */
const iconOf = (link: NavigationLink): LucideIcon => (link.node ? NODE_ICON : KIND_ICONS[link.kind]);

/**
 * Whether a link is the active item for `url`. A node link matches its exact edit path. A type link matches by
 * prefix, but yields to a node link that matches `url`, so the edit page of a linked node highlights one item only.
 */
const linkActive = (url: string, link: NavigationLink, nodeLinkMatches: boolean): boolean =>
    link.node ? url.split('?')[0] === link.href : !nodeLinkMatches && isActive(url, link.href);

/** Settings page of the admin menu; the footer link owns it, so the Site item is not highlighted there. */
const ADMIN_MENU_HREF = '/admin/settings/navigation';

/**
 * Admin sidebar built on the shadcn `Sidebar` primitive: collapses to icons on desktop and becomes a Sheet drawer
 * on mobile. From top to bottom: the header (the logo and, under it, the site name from the shared `site.name` prop;
 * hidden when collapsed to icons), Dashboard, the user's favorites, the admin menu titled "Navigation" (links to types and nodes
 * and groups, all already filtered by access on the server), Media Library, the "Structure" and "Access" sections
 * and, in the footer, a link to the admin menu settings. The active item is derived from the current Inertia URL;
 * a menu group that holds it is expanded.
 */
export function AppSidebar() {
    const { url, props } = usePage();
    const { t } = useTranslation();
    const can = props.auth.user!.can;
    const favorites = props.navigation?.favorites ?? [];
    const menu = props.navigation?.menu ?? [];
    const [dashboard, ...rest] = visibleNavigation(can);
    const sections = visibleSections(can);

    // The node list of a type that the favorites or the menu link to belongs to that link, not to a section; so does
    // the edit page of a node that the menu links to.
    const links = [...favorites, ...menu.flatMap((entry) => (entry.type === 'group' ? entry.children : [entry]))];
    const nodeLinkMatches = links.some((link) => link.node && linkActive(url, link, false));
    const ownedByLink = links.some((link) => linkActive(url, link, nodeLinkMatches)) || nodeLinkMatches || isActive(url, ADMIN_MENU_HREF);
    const leafActive = (item: NavItem) => !ownedByLink && activeItem(url) === item;

    const renderItem = (item: NavItem) => (
        <SidebarMenuItem key={item.href}>
            {item.mock ? (
                <SidebarMenuButton tooltip={t(item.title)} aria-disabled="true" data-mock="true" className="cursor-not-allowed opacity-60">
                    <item.icon />
                    <span>{t(item.title)}</span>
                </SidebarMenuButton>
            ) : (
                <SidebarMenuButton asChild tooltip={t(item.title)} isActive={isActive(url, item.href)}>
                    <Link href={item.href}>
                        <item.icon />
                        <span>{t(item.title)}</span>
                    </Link>
                </SidebarMenuButton>
            )}
        </SidebarMenuItem>
    );

    const typeLink = (link: NavigationLink, testId: string) => {
        const Icon = iconOf(link);

        return (
            <SidebarMenuItem key={link.key}>
                <SidebarMenuButton asChild tooltip={link.name} isActive={linkActive(url, link, nodeLinkMatches)} data-testid={testId}>
                    <Link href={link.href}>
                        <Icon />
                        <span>{link.name}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    };

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <div className="flex flex-col gap-1 py-1.5 pl-2 pr-10 md:pr-2 group-data-[collapsible=icon]:hidden">
                    <BrandLogo />
                    <span className="truncate text-sm pl-[26px] truncate text-mute font-medium" data-testid="sidebar-site-name">
                        {props.site.name}
                    </span>
                </div>
            </SidebarHeader>
            <SidebarContent className="gap-0">
                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>{dashboard && renderItem(dashboard)}</SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                {favorites.length > 0 && (
                    <SidebarGroup data-testid="sidebar-favorites">
                        <SidebarGroupLabel>
                            <Star aria-hidden className="mr-1.5 size-3.5" />
                            {t('Favorites')}
                        </SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>{favorites.map((link) => typeLink(link, 'sidebar-favorite'))}</SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}

                {menu.length > 0 && (
                    <SidebarGroup data-testid="sidebar-menu">
                        <SidebarGroupLabel>
                            <Navigation aria-hidden className="mr-1.5 size-3.5" />
                            {t('Navigation')}
                        </SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {menu.map((entry) =>
                                    entry.type === 'group' ? (
                                        <MenuGroup key={`group-${entry.id}`} group={entry} url={url} nodeLinkMatches={nodeLinkMatches} />
                                    ) : (
                                        typeLink(entry, 'sidebar-menu-link')
                                    ),
                                )}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}

                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>{rest.map(renderItem)}</SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                {sections.map((section) => (
                    <SidebarGroup key={section.title} data-testid="sidebar-section">
                        <SidebarGroupLabel>{t(section.title)}</SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {section.items.map((item) => (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton asChild tooltip={t(item.title)} isActive={leafActive(item)}>
                                            <Link href={item.href}>
                                                <item.icon />
                                                <span>{t(item.title)}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ))}
            </SidebarContent>
            {can.system.settings && (
                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip={t('Admin menu')}
                                isActive={isActive(url, ADMIN_MENU_HREF)}
                                data-testid="sidebar-admin-menu-link"
                            >
                                <Link href={ADMIN_MENU_HREF}>
                                    <Settings />
                                    <span>{t('Admin menu')}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
            )}
            <SidebarRail />
        </Sidebar>
    );
}

/** A collapsible group of the admin menu; it is open while it holds the active link. */
function MenuGroup({ group, url, nodeLinkMatches }: { group: NavigationGroup; url: string; nodeLinkMatches: boolean }) {
    const active = group.children.some((link) => linkActive(url, link, nodeLinkMatches));

    return (
        <Expandable testId="sidebar-menu-group" label={group.title} icon={Folder} openIcon={FolderOpen} active={active} url={url}>
            {group.children.map((link) => {
                const Icon = iconOf(link);

                return (
                    <SidebarMenuSubItem key={link.key}>
                        <SidebarMenuSubButton asChild isActive={linkActive(url, link, nodeLinkMatches)} data-testid="sidebar-menu-link">
                            <Link href={link.href}>
                                <Icon />
                                <span>{link.name}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                );
            })}
        </Expandable>
    );
}

type ExpandableProps = {
    label: string;
    icon: LucideIcon;
    /** Icon shown while open; defaults to `icon`. */
    openIcon?: LucideIcon;
    /** Whether the group holds the active item; the group opens whenever this becomes true (on each visit). */
    active: boolean;
    /** Current URL: a visit to another page re-opens a group that holds the active item. */
    url: string;
    testId: string;
    children: ReactNode;
};

/**
 * A sidebar entry that expands to a sub-list. Opened by the user or by an active item inside; closing by hand is
 * not remembered between visits (an active group opens again).
 */
function Expandable({ label, icon, openIcon, active, url, testId, children }: ExpandableProps) {
    const [open, setOpen] = useState(active);
    const Icon = open ? (openIcon ?? icon) : icon;

    useEffect(() => {
        if (active) {
            setOpen(true);
        }
    }, [active, url]);

    return (
        <SidebarMenuItem data-testid={testId} data-state={open ? 'open' : 'closed'}>
            <SidebarMenuButton tooltip={label} aria-expanded={open} onClick={() => setOpen(!open)} data-testid={`${testId}-toggle`}>
                <Icon />
                <span>{label}</span>
                <ChevronRight className={`ml-auto transition-transform ${open ? 'rotate-90' : ''}`} aria-hidden />
            </SidebarMenuButton>
            {open && <SidebarMenuSub>{children}</SidebarMenuSub>}
        </SidebarMenuItem>
    );
}
