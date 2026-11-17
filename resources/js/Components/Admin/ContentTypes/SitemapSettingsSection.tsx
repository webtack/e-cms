import { FieldGroup } from '@/Components/Admin/FieldGroup';
import { FormSection } from '@/Components/Admin/FormSection';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

import type { TypeSection } from './section';

/** The "XML sitemap settings" group of a type (`settings.sitemap.xml`). */
export type SitemapXmlValue = { type: boolean; nodes: boolean; changefreq: string; priority: string };

/** The "Web sitemap settings" group of a type (`settings.sitemap.web`). */
export type SitemapWebValue = { type: boolean; nodes: boolean };

/** The sitemap settings being edited. */
export type SitemapValue = { xml: SitemapXmlValue; web: SitemapWebValue };

type Props = {
    /** Kind of the type: only `content` has a list page, so only it gets the "Content type" checkboxes. */
    kind: TypeSection['kind'];
    value: SitemapValue;
    onChange: (value: SitemapValue) => void;
    /** Allowed `changefreq` values. */
    changefreqOptions: string[];
    /** Allowed `priority` values (`0.0` … `1.0`). */
    priorityOptions: string[];
    /** Whether the type has a slug (a `content` type without one has no public address). */
    hasSlug: boolean;
    /** Server errors of the settings, shown below the blocks. */
    errors?: string[];
};

/**
 * The two bordered blocks of the "Sitemap" tab: "XML sitemap settings" (the "Content type" and "Content" checkboxes,
 * "Change frequency" and "URL priority") and "Web sitemap settings" (the two checkboxes). The form of the tab holds
 * the value and saves it with its one sticky bar. "Content type" is shown for kind `content` only, and the two
 * selects only while an XML checkbox is on, as the server stores them only then.
 *
 * @example
 * <SitemapSettingsSection kind="content" value={data.settings.sitemap} onChange={(sitemap) => setData('settings', { sitemap })}
 *     changefreqOptions={changefreqOptions} priorityOptions={priorityOptions} hasSlug />
 */
export function SitemapSettingsSection({ kind, value, onChange, changefreqOptions, priorityOptions, hasSlug, errors = [] }: Props) {
    const { t } = useTranslation();
    const hasList = kind === 'content';
    const xmlOn = (hasList && value.xml.type) || value.xml.nodes;
    const setXml = (patch: Partial<SitemapXmlValue>) => onChange({ ...value, xml: { ...value.xml, ...patch } });
    const setWeb = (patch: Partial<SitemapWebValue>) => onChange({ ...value, web: { ...value.web, ...patch } });

    return (
        <div className="max-w-2xl space-y-6" data-testid="sitemap-settings">
            {hasList && !hasSlug && (
                <p className="text-sm text-muted-foreground" data-testid="sitemap-no-slug">
                    {t('This type has no public address, so it does not appear in the sitemaps.')}
                </p>
            )}
            <FormSection as="section" title={t('XML sitemap settings')} titleId="sitemap-xml-heading" className="space-y-4" data-testid="sitemap-xml">
                <FieldGroup legend={t('Include in the XML sitemap')}>
                    {hasList && (
                        <div className="flex items-center gap-2">
                            <Checkbox id="sitemap-xml-type" checked={value.xml.type} onCheckedChange={(checked) => setXml({ type: checked === true })} />
                            <Label htmlFor="sitemap-xml-type" className="cursor-pointer">
                                {t('Content type')}
                            </Label>
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <Checkbox id="sitemap-xml-nodes" checked={value.xml.nodes} onCheckedChange={(checked) => setXml({ nodes: checked === true })} />
                        <Label htmlFor="sitemap-xml-nodes" className="cursor-pointer">
                            {t('Content')}
                        </Label>
                    </div>
                </FieldGroup>
                {xmlOn && (
                    <>
                        <div className="space-y-1">
                            <Label htmlFor="sitemap-xml-changefreq">{t('Change frequency')}</Label>
                            <Select value={value.xml.changefreq} onValueChange={(changefreq) => setXml({ changefreq })}>
                                <SelectTrigger id="sitemap-xml-changefreq" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {changefreqOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="sitemap-xml-priority">{t('URL priority')}</Label>
                            <Select value={value.xml.priority} onValueChange={(priority) => setXml({ priority })}>
                                <SelectTrigger id="sitemap-xml-priority" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {priorityOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </>
                )}
            </FormSection>
            <FormSection as="section" title={t('Web sitemap settings')} titleId="sitemap-web-heading" className="space-y-4" data-testid="sitemap-web">
                <FieldGroup legend={t('Include in the web sitemap')}>
                    {hasList && (
                        <div className="flex items-center gap-2">
                            <Checkbox id="sitemap-web-type" checked={value.web.type} onCheckedChange={(checked) => setWeb({ type: checked === true })} />
                            <Label htmlFor="sitemap-web-type" className="cursor-pointer">
                                {t('Content type')}
                            </Label>
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <Checkbox id="sitemap-web-nodes" checked={value.web.nodes} onCheckedChange={(checked) => setWeb({ nodes: checked === true })} />
                        <Label htmlFor="sitemap-web-nodes" className="cursor-pointer">
                            {t('Content')}
                        </Label>
                    </div>
                </FieldGroup>
            </FormSection>
            {errors.map((message) => (
                <p key={message} role="alert" className="text-sm text-destructive">
                    {message}
                </p>
            ))}
        </div>
    );
}
