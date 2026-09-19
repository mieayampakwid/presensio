import { Head } from '@inertiajs/react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import Heading from '@/components/heading';
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

type StudentRow = {
    id: number;
    full_name: string;
    nickname: string | null;
    dob: string;
    student_number: string | null;
    class_id: number | null;
};

type Props = {
    student: StudentRow;
    classes: ClassOption[];
    guardians: GuardianOption[];
    current_guardian_ids: number[];
};

export default function EditStudent({
    student,
    classes,
    guardians,
    current_guardian_ids,
}: Props) {
    return (
        <>
            <Head title={`Edit ${student.full_name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit student"
                    description="Moving to another class replaces enrollment; attendance history stays untouched."
                />

                <StudentForm
                    action={StudentController.update.form({
                        student: student.id,
                    })}
                    submitLabel="Save changes"
                    defaults={{
                        fullName: student.full_name,
                        nickname: student.nickname ?? '',
                        dob: student.dob,
                        studentNumber: student.student_number ?? '',
                        classId: student.class_id ? String(student.class_id) : '',
                    }}
                    classes={classes}
                    guardians={guardians}
                    currentGuardianIds={current_guardian_ids}
                />
            </div>
        </>
    );
}

EditStudent.layout = {
    breadcrumbs: [
        {
            title: 'Students',
            href: StudentController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
