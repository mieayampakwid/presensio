import { Head } from '@inertiajs/react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import Heading from '@/components/heading';
import ClassForm from './class-form';

type TeacherOption = {
    id: number;
    name: string;
};

type Props = {
    teachers: TeacherOption[];
};

export default function CreateClass({ teachers }: Props) {
    return (
        <>
            <Head title="Create class" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create class"
                    description="Enrollment happens on the student record; a class may start empty."
                />

                <ClassForm
                    action={SchoolClassController.store.form()}
                    submitLabel="Create class"
                    defaults={{ name: '', teacherId: null }}
                    teachers={teachers}
                />
            </div>
        </>
    );
}

CreateClass.layout = {
    breadcrumbs: [
        {
            title: 'Classes',
            href: SchoolClassController.index().url,
        },
        {
            title: 'Create',
        },
    ],
};
