import { Form, Head, setLayoutProps } from '@inertiajs/react';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

export default function ImportUpload() {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('students.index.title'),
                href: StudentController.index().url,
            },
            {
                title: t('students.import.crumb'),
                href: StudentImportController.create().url,
            },
        ],
    });

    return (
        <>
            <Head title={t('students.import.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('students.import.title')}
                    description={t('students.import.description')}
                />

                <Form
                    {...StudentImportController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="grid max-w-xl gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="file">
                                    {t('students.import.file')}
                                </Label>
                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,.txt,.xlsx"
                                    required
                                />
                                <p className="text-muted-foreground text-sm">
                                    {t('students.import.file_hint')}
                                </p>
                                <InputError message={errors.file} />
                            </div>

                            <div className="flex items-center gap-3">
                                <input
                                    id="auto_create_classes"
                                    type="checkbox"
                                    name="auto_create_classes"
                                    value="1"
                                    className="border-input dark:bg-input/30 accent-primary size-4 shrink-0 rounded-[4px] border shadow-xs outline-none"
                                />
                                <Label htmlFor="auto_create_classes">
                                    {t('students.import.auto_create_classes')}
                                </Label>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('students.import.upload')}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
