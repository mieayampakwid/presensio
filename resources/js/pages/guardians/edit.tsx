import { Head } from '@inertiajs/react';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import Heading from '@/components/heading';
import { Label } from '@/components/ui/label';
import GuardianForm from './guardian-form';

type StudentRow = {
    id: number;
    full_name: string;
};

type GuardianRow = {
    id: number;
    name: string;
    phone_number: string;
    work: string | null;
    address: string | null;
};

type Props = {
    guardian: GuardianRow;
    students: StudentRow[];
};

export default function EditGuardian({ guardian, students }: Props) {
    return (
        <>
            <Head title={`Edit ${guardian.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit guardian"
                    description="Linked children are managed from the student records."
                />

                <GuardianForm
                    action={GuardianController.update.form({
                        guardian: guardian.id,
                    })}
                    submitLabel="Save changes"
                    defaults={{
                        name: guardian.name,
                        phoneNumber: guardian.phone_number,
                        work: guardian.work,
                        address: guardian.address,
                    }}
                />

                <div className="space-y-2">
                    <Label>Children</Label>
                    {students.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No children linked yet.
                        </p>
                    ) : (
                        <ul className="text-muted-foreground list-disc pl-5 text-sm">
                            {students.map((student) => (
                                <li key={student.id}>{student.full_name}</li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

EditGuardian.layout = {
    breadcrumbs: [
        {
            title: 'Guardians',
            href: GuardianController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
