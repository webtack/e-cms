import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import { ContentTypeNav, type NavType } from '@/Components/Admin/ContentTypes/ContentTypeNav';
import { type TypeSection } from '@/Components/Admin/ContentTypes/section';
import { SitemapSettingsSection, type SitemapValue } from '@/Components/Admin/ContentTypes/SitemapSettingsSection';
import { FormActionBar } from '@/Components/Admin/FormActionBar';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    type: NavType;
    section: TypeSection;
    /** Whether the type has a slug (`ContentTypeSitemapController::edit()`). */
    hasSlug: boolean;
    /** Stored sitemap settings with the defaults filled in. */
    sitemap: SitemapValue;
    changefreqOptions: string[];
    priorityOptions: string[];
};

/**
 * The "Sitemap" tab of a content or page type (`GET {basePath}/{key}/sitemap`): the XML and web sitemap settings of
 * the type in one form, saved by one sticky "Save" with `PUT {basePath}/{key}/sitemap`.
 */
export default function Sitemap({ type, section, hasSlug, sitemap, changefreqOptions, priorityOptions }: Props) {
    const { t } = useTranslation();
    const { data, setData, put, transform, processing, errors } = useForm<{ settings: { sitemap: SitemapValue } }>({ settings: { sitemap } });
    // The field errors come as `settings.sitemap.*`; all of them are listed below the blocks.
    const messages = Object.entries(errors as Record<string, string>)
        .filter(([key]) => key.startsWith('settings'))
        .map(([, message]) => message);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // A page type has no list page: its request must not carry `type` (the server rejects it).
        transform((form) => {
            if (section.kind === 'content') return form;
            const { type: _xmlType, ...xml } = form.settings.sitemap.xml;
            const { type: _webType, ...web } = form.settings.sitemap.web;
            return { settings: { sitemap: { xml, web } } };
        });
        put(`${section.basePath}/${type.key}/sitemap`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${t('Sitemap')} — ${type.name}`} />
            <ContentTypeNav section={section} type={type} active="sitemap" />
            <form onSubmit={submit} noValidate data-testid="type-sitemap-form">
                <SitemapSettingsSection
                    kind={section.kind}
                    value={data.settings.sitemap}
                    onChange={(value) => setData('settings', { sitemap: value })}
                    changefreqOptions={changefreqOptions}
                    priorityOptions={priorityOptions}
                    hasSlug={hasSlug}
                    errors={messages}
                />
                <FormActionBar cancelHref={section.basePath} processing={processing} />
            </form>
        </>
    );
}

Sitemap.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
