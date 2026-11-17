import { Head } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import { FileUpload, type UploadedFile } from '@/Components/Admin/FileUpload/FileUpload';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { PageTitle } from '@/Components/Admin/PageTitle';

/**
 * Developer demo of `FileUpload`: one widget per starting profile, each showing the uuid the form would receive
 * (`file.uuid`), and a widget in `multiple` mode that lists the names of the files uploaded through it. Exists
 * outside production only.
 */
export default function FileUploadDemo() {
    const { t } = useTranslation();
    const [image, setImage] = useState<UploadedFile | null>(null);
    const [document, setDocument] = useState<UploadedFile | null>(null);
    const [many, setMany] = useState<UploadedFile[]>([]);

    return (
        <>
            <Head title={t('File upload demo')} />
            <PageTitle>{t('File upload demo')}</PageTitle>

            <div className="mt-6 grid max-w-3xl gap-8 md:grid-cols-2">
                <section className="flex flex-col gap-2">
                    <FileUpload profile="image" accept="image/*" label={t('Image')} value={image} onChange={setImage} />
                    <p className="text-muted-foreground text-xs" data-testid="file-upload-image-id">
                        {t('Selected file uuid')}: {image?.uuid ?? t('none')}
                    </p>
                </section>

                <section className="flex flex-col gap-2">
                    <FileUpload profile="document" label={t('Document')} value={document} onChange={setDocument} />
                    <p className="text-muted-foreground text-xs" data-testid="file-upload-document-id">
                        {t('Selected file uuid')}: {document?.uuid ?? t('none')}
                    </p>
                </section>

                <section className="flex flex-col gap-2 md:col-span-2">
                    <FileUpload
                        multiple
                        profile="image"
                        accept="image/png"
                        id="file-upload-input-many"
                        label={t('Several images')}
                        data-testid="file-upload-many"
                        onUploadedMany={(files) => setMany((current) => [...current, ...files])}
                    />
                    <ul className="text-muted-foreground text-xs" data-testid="file-upload-many-list">
                        {many.map((file) => (
                            <li key={file.uuid}>{file.name}</li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

FileUploadDemo.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
