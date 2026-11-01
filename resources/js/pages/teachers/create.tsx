import { Head, setLayoutProps } from '@inertiajs/react';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import TeacherForm from './teacher-form';

export default function CreateTeacher() {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('teachers.index.title'),
                href: TeacherController.index().url,
            },
            {
                title: t('common.create'),
            },
        ],
    });

    return (
        <>
            <Head title={t('teachers.create.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('teachers.create.title')}
                    description={t('teachers.create.description')}
                />

                <TeacherForm
                    action={TeacherController.store.form()}
                    cancelUrl={TeacherController.index().url}
                    submitLabel={t('teachers.create.submit')}
                    defaults={{
                        name: '',
                        teacherNumber: null,
                        phoneNumber: null,
                    }}
                />
            </div>
        </>
    );
}
