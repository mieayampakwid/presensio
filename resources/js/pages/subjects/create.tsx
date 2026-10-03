import { Head } from '@inertiajs/react';
import SubjectController from '@/actions/App/Http/Controllers/Subjects/SubjectController';
import Heading from '@/components/heading';
import SubjectForm from './subject-form';

type GroupOption = {
    value: string;
    label: string;
};

type Props = {
    groups: GroupOption[];
};

export default function CreateSubject({ groups }: Props) {
    return (
        <>
            <Head title="Create Subject" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create Subject"
                    description="Tambahkan mata pelajaran baru ke katalog kurikulum sekolah."
                />

                <SubjectForm
                    action={{
                        action: SubjectController.store.url(),
                        method: 'post',
                    }}
                    submitLabel="Create Subject"
                    defaults={{
                        name: '',
                        code: '',
                        group: groups[0]?.value ?? 'general',
                        sort_order: 0,
                        description: '',
                        is_active: true,
                    }}
                    groups={groups}
                />
            </div>
        </>
    );
}

CreateSubject.layout = {
    breadcrumbs: [
        {
            title: 'Subjects',
            href: SubjectController.index().url,
        },
        {
            title: 'Create',
        },
    ],
};
