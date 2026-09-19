import { Head } from '@inertiajs/react';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import Heading from '@/components/heading';
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
    return (
        <>
            <Head title={`Edit ${teacher.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit teacher"
                    description="A teacher homerooming a class or linked to a login account cannot be deleted."
                />

                <TeacherForm
                    action={TeacherController.update.form({
                        teacher: teacher.id,
                    })}
                    submitLabel="Save changes"
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

EditTeacher.layout = {
    breadcrumbs: [
        {
            title: 'Teachers',
            href: TeacherController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
