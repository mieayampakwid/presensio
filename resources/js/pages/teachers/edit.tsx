import { Head, setLayoutProps } from '@inertiajs/react';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import TeacherForm from './teacher-form';

type TeacherRow = {
    id: number;
    name: string;
    teacher_number: string | null;
    phone_number: string | null;
};

type Props = {
    teacher: TeacherRow;
};

export default function EditTeacher({ teacher }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('teachers.index.title'),
                href: TeacherController.index().url,
            },
            {
                title: t('common.edit'),
            },
        ],
    });

    return (
        <>
            <Head title={t('teachers.edit.head', { name: teacher.name })} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('teachers.edit.title')}
                    description={t('teachers.edit.description')}
                />

                <TeacherForm
                    action={TeacherController.update.form({
                        teacher: teacher.id,
                    })}
                    cancelUrl={TeacherController.index().url}
                    submitLabel={t('common.save_changes')}
                    defaults={{
                        name: teacher.name,
                        teacherNumber: teacher.teacher_number,
                        phoneNumber: teacher.phone_number,
                    }}
                />
            </div>
        </>
    );
}
