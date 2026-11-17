import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import { FormSection } from '@/Components/Admin/FormSection';
import { UserAvatar } from '@/Components/Admin/UserAvatar';
import { Button } from '@/Components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';

type Props = {
    /** The user's name; its initials are the fallback of the preview. */
    name: string;
    /** URL of the saved avatar, or `null` when there is none. */
    avatar: string | null;
};

/**
 * "Avatar" section of the profile page: a large preview, an upload zone (profile `avatar`) and a remove button.
 * Nothing is written until "Save" (`PUT /admin/profile/avatar`): a picked file or a removal is only a pending change.
 * A file of another profile or a non-image is refused by the server and the error is shown under the field.
 *
 * @example
 * <ProfileAvatar name="Anna" avatar={profile.avatar} />
 */
export function ProfileAvatar({ name, avatar }: Props) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<UploadedFile | null>(null);
    const [removed, setRemoved] = useState(false);
    const { transform, put, processing, errors } = useForm({ avatar: '' });

    const preview = removed ? null : (selected?.url ?? avatar);
    const dirty = selected !== null || removed;

    const pick = (file: UploadedFile | null) => {
        setSelected(file);
        setRemoved(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform(() => ({ avatar: selected?.uuid ?? '' }));
        put('/admin/profile/avatar', {
            preserveScroll: true,
            onSuccess: () => {
                setSelected(null);
                setRemoved(false);
            },
        });
    };

    return (
        <form onSubmit={submit} noValidate data-testid="profile-avatar-form">
            <FormSection as="section" title={t('Avatar')} titleId="profile-avatar-heading" className="max-w-xl space-y-4">
                <div className="flex items-center gap-4">
                    <UserAvatar name={name} src={preview} className="size-24 text-2xl" />
                    {preview !== null && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSelected(null);
                                setRemoved(true);
                            }}
                            data-testid="profile-avatar-remove"
                        >
                            {t('Remove avatar')}
                        </Button>
                    )}
                </div>

                <FileUpload
                    profile="avatar"
                    id="profile-avatar"
                    value={selected}
                    onChange={pick}
                    error={errors.avatar}
                    aria-label={t('Avatar')}
                    data-testid="file-upload-avatar"
                />

                <Button type="submit" disabled={processing || !dirty}>
                    {t('Save')}
                </Button>
            </FormSection>
        </form>
    );
}
