import { Head, setLayoutProps } from '@inertiajs/react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import StudentForm from './student-form';

type ClassOption = {
    id: number;
    name: string;
};

type GuardianOption = {
    id: number;
    name: string;
    phone_number: string;
};

type Props = {
    classes: ClassOption[];
    guardians: GuardianOption[];
};

export default function CreateStudent({ classes, guardians }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('students.index.title'),
                href: StudentController.index().url,
            },
            {
                title: t('common.create'),
            },
        ],
    });

    return (
        <>
            <Head title={t('students.create.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('students.create.title')}
                    description={t('students.create.description')}
                />

                <StudentForm
                    action={StudentController.store.form()}
                    cancelUrl={StudentController.index().url}
                    submitLabel={t('students.create.submit')}
                    defaults={{
                        fullName: '',
                        nickname: '',
                        dob: '',
                        gender: 'L',
                        birthPlace: '',
                        religion: '',
                        address: '',
                        studentNumber: '',
                        classId: '',
                    }}
                    classes={classes}
                    guardians={guardians}
                />
            </div>
        </>
    );
}
