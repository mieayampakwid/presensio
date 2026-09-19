import { Head } from '@inertiajs/react';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import Heading from '@/components/heading';
import CardForm from './card-form';

type StudentOption = {
    id: number;
    full_name: string;
};

type Props = {
    students: StudentOption[];
};

export default function CreateCard({ students }: Props) {
    return (
        <>
            <Head title="Create card" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create card"
                    description="Register an RFID card, optionally assigning it to a student."
                />

                <CardForm
                    action={RfidCardController.store.form()}
                    submitLabel="Create card"
                    defaults={{ rfidNumber: '', studentId: '' }}
                    students={students}
                />
            </div>
        </>
    );
}

CreateCard.layout = {
    breadcrumbs: [
        {
            title: 'RFID Cards',
            href: RfidCardController.index().url,
        },
        {
            title: 'Create',
        },
    ],
};
