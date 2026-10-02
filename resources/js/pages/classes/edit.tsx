import { Head } from '@inertiajs/react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import Heading from '@/components/heading';
import ClassForm from './class-form';

type TeacherOption = {
    id: number;
    name: string;
};

type ClassRow = {
    id: number;
    name: string;
    grade_level: number;
    curriculum: string;
    teacher_id: number | null;
};

type Props = {
    class: ClassRow;
    teachers: TeacherOption[];
};

export default function EditClass({ class: schoolClass, teachers }: Props) {
    return (
        <>
            <Head title={`Edit ${schoolClass.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit class"
                    description="Reassigning the homeroom teacher moves them off their previous class when the 1:1 rule is on."
                />

                <ClassForm
                    action={SchoolClassController.update.form({
                        school_class: schoolClass.id,
                    })}
                    submitLabel="Save changes"
                    defaults={{
                        name: schoolClass.name,
                        gradeLevel: schoolClass.grade_level,
                        curriculum: typeof schoolClass.curriculum === 'string' ? schoolClass.curriculum : 'merdeka',
                        teacherId: schoolClass.teacher_id,
                    }}
                    teachers={teachers}
                />
            </div>
        </>
    );
}

EditClass.layout = {
    breadcrumbs: [
        {
            title: 'Classes',
            href: SchoolClassController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
