import { Head } from '@inertiajs/react';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import Heading from '@/components/heading';
import TeacherForm from './teacher-form';

export default function CreateTeacher() {
    return (
        <>
            <Head title="Create teacher" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create teacher"
                    description="Master data for homeroom assignment. A login account can be linked later."
                />

                <TeacherForm
                    action={TeacherController.store.form()}
                    submitLabel="Create teacher"
                    defaults={{ name: '', teacherNumber: null, phoneNumber: null }}
                />
            </div>
        </>
    );
}

CreateTeacher.layout = {
    breadcrumbs: [
        {
            title: 'Teachers',
            href: TeacherController.index().url,
        },
        {
            title: 'Create',
        },
    ],
};
