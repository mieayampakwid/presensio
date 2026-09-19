import { Head } from '@inertiajs/react';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import Heading from '@/components/heading';
import GuardianForm from './guardian-form';

export default function CreateGuardian() {
    return (
        <>
            <Head title="Create guardian" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create guardian"
                    description="The phone number identifies the guardian — children sharing it share the guardian record."
                />

                <GuardianForm
                    action={GuardianController.store.form()}
                    submitLabel="Create guardian"
                    defaults={{
                        name: '',
                        phoneNumber: '',
                        work: null,
                        address: null,
                    }}
                />
            </div>
        </>
    );
}

CreateGuardian.layout = {
    breadcrumbs: [
        {
            title: 'Guardians',
            href: GuardianController.index().url,
        },
        {
            title: 'Create',
        },
    ],
};
