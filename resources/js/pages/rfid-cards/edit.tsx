import { Head } from '@inertiajs/react';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import Heading from '@/components/heading';
import CardForm from './card-form';

type StudentOption = {
    id: number;
    full_name: string;
};

type CardRow = {
    id: number;
    rfid_number: string;
    student_id: number | null;
};

type Props = {
    card: CardRow;
    students: StudentOption[];
};

export default function EditCard({ card, students }: Props) {
    return (
        <>
            <Head title={`Edit card ${card.rfid_number}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit card"
                    description="Change the assignment or return the card to the spare pool."
                />

                <CardForm
                    action={RfidCardController.update.form({
                        rfid_card: card.id,
                    })}
                    submitLabel="Save changes"
                    defaults={{
                        rfidNumber: card.rfid_number,
                        studentId: card.student_id ? String(card.student_id) : '',
                    }}
                    students={students}
                />
            </div>
        </>
    );
}

EditCard.layout = {
    breadcrumbs: [
        {
            title: 'RFID Cards',
            href: RfidCardController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
