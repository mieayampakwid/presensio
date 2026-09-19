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

type Props = {
    classes: ClassOption[];
    guardians: GuardianOption[];
};

export default function CreateStudent({ classes, guardians }: Props) {
    return (
        <>
            <Head title="Create student" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create student"
                    description="Link guardians so absence notifications reach the right people."
                />

                <StudentForm
                    action={StudentController.store.form()}
                    submitLabel="Create student"
                    defaults={{
                        fullName: '',
                        nickname: '',
                        dob: '',
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

CreateStudent.layout = {
    breadcrumbs: [
        {
            title: 'Students',
            href: StudentController.index().url,
        },
        {
            title: 'Create',
        },
    ],
};
