import { Head } from '@inertiajs/react';
import SubjectController from '@/actions/App/Http/Controllers/Subjects/SubjectController';
import Heading from '@/components/heading';
import SubjectForm from './subject-form';

type GroupOption = {
    value: string;
    label: string;
};

type SubjectData = {
    id: number;
    name: string;
    code: string;
    group: string;
    sort_order: number;
    description: string | null;
    is_active: boolean;
};

type Props = {
    subject: SubjectData;
    groups: GroupOption[];
};

export default function EditSubject({ subject, groups }: Props) {
    return (
        <>
            <Head title={`Edit ${subject.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={`Edit ${subject.name}`}
                    description="Perbarui informasi mata pelajaran dalam katalog sekolah."
                />

                <SubjectForm
                    action={{
                        action: SubjectController.update.url({
                            subject: subject.id,
                        }),
                        method: 'put',
                    }}
                    submitLabel="Save Changes"
                    defaults={{
                        name: subject.name,
                        code: subject.code,
                        group: subject.group,
                        sort_order: subject.sort_order,
                        description: subject.description ?? '',
                        is_active: subject.is_active,
                    }}
                    groups={groups}
                />
            </div>
        </>
    );
}

EditSubject.layout = {
    breadcrumbs: [
        {
            title: 'Subjects',
            href: SubjectController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
