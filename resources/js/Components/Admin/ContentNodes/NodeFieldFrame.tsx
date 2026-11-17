import type { UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { Label } from '@/Components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';

import { NodeFieldInput } from './NodeFieldInput';
import { NodeFieldList } from './NodeFieldList';
import type { NodeField, NodeFieldValue, NodeFileMetaEditor, NodeFiles } from './types';

type Props = {
    field: NodeField;
    /** Key of the content type the field is edited for; the file widgets upload by it. */
    typeKey: string;
    /** The form state of the field: a list for a multiple field, a scalar otherwise. */
    value: NodeFieldValue | undefined;
    onChange: (value: NodeFieldValue) => void;
    /** Known files by stored value, and the callback for a file uploaded in this form. */
    files?: NodeFiles;
    onFile?: (file: UploadedFile) => void;
    /** Editor of the files' names and descriptions; without it a file input has no such fields. */
    fileMeta?: NodeFileMetaEditor;
    /** Server error at a path such as `values.title` or `values.tags.1`. */
    error: (path: string) => string | undefined;
};

/** The frame of a field's container and its caption: the same classes as the file settings block of the field form. */
const FRAME = 'min-w-0 space-y-2 rounded-md border px-3 py-4 bg-card';
const LEGEND = 'px-1 text-sm font-medium -mb-1';

/**
 * One field of a node (or of a type) as the form shows it: a bordered `fieldset` whose `legend` is the field's
 * name (a boolean field has no legend: its checkbox and label share a row in the frame), then the description, the
 * input and the error. A multiple field gets an editable list instead of one input, with an "n / max" counter when
 * the field has a limit. Required fields are marked. The input is `NodeFieldInput` or, for a multiple field,
 * `NodeFieldList`; server errors are looked up at `values.{key}` and `values.{key}.{index}`.
 *
 * Shared by the main fields of `ContentNodeForm`, its "SEO" panel and the panels of the type settings page.
 *
 * @example
 * <NodeFieldFrame field={field} typeKey="news" value={data.values.title} onChange={(v) => setValue('title', v)} error={(path) => errors[path]} />
 */
export function NodeFieldFrame({ field, typeKey, value, onChange, files, onFile, fileMeta, error }: Props) {
    const { t } = useTranslation();
    const id = `field-${field.key}`;
    const key = `values.${field.key}`;
    const label = (
        <Label htmlFor={field.is_multiple ? undefined : id} id={`${id}-label`}>
            {field.name}
            {field.is_required && (
                <span aria-hidden className="text-destructive">
                    *
                </span>
            )}
            {field.is_required && <span className="sr-only">{t('(required)')}</span>}
        </Label>
    );
    /** The field's description, right under its legend; nothing is drawn without one. */
    const hint = field.description && <p className="text-sm text-muted-foreground">{field.description}</p>;
    const message = error(key) && (
        <p role="alert" className="text-sm text-destructive">
            {error(key)}
        </p>
    );

    if (field.is_multiple) {
        return (
            <fieldset className={FRAME}>
                <legend className={`${LEGEND} flex items-center gap-2`}>
                    {label}
                    {field.max_items > 0 && (
                        <span className="font-normal text-muted-foreground" data-testid={`${id}-counter`}>
                            {Array.isArray(value) ? value.length : 0} / {field.max_items}
                        </span>
                    )}
                </legend>
                {hint}
                <NodeFieldList
                    field={field}
                    typeKey={typeKey}
                    files={files}
                    onFile={onFile}
                    fileMeta={fileMeta}
                    id={id}
                    value={Array.isArray(value) ? value : []}
                    onChange={onChange}
                    errorAt={(index) => error(`${key}.${index}`)}
                />
                {message}
            </fieldset>
        );
    }

    return (
        <fieldset className={FRAME}>
            {field.type === 'boolean' ? (
                <>
                    <div className="flex items-center gap-2">
                        <NodeFieldInput
                            field={field}
                            typeKey={typeKey}
                            files={files}
                            onFile={onFile}
                            id={id}
                            value={value}
                            invalid={!!error(key)}
                            onChange={onChange}
                        />
                        {label}
                    </div>
                    {hint}
                </>
            ) : (
                <>
                    <legend className={LEGEND}>{label}</legend>
                    {hint}
                    <NodeFieldInput
                        field={field}
                        typeKey={typeKey}
                        files={files}
                        onFile={onFile}
                        fileMeta={fileMeta}
                        id={id}
                        value={value}
                        invalid={!!error(key)}
                        onChange={onChange}
                    />
                </>
            )}
            {message}
        </fieldset>
    );
}
