import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import UserForm from './user-form';

type ProfileOption = { id: number; label: string };

type Props = {
    profiles: {
        teacher: ProfileOption[];
        parent: ProfileOption[];
        student: ProfileOption[];
    };
};

export default function CreateUser({ profiles }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('users.create.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('users.create.title')}
                    description={t('users.create.description')}
                />

                <UserForm
                    action={UserController.store.form()}
                    submitLabel={t('users.form.create_submit')}
                    defaults={{
                        username: '',
                        email: null,
                        roles: ['teacher'],
                        role: 'teacher',
                    }}
                    profiles={profiles}
                    showPassword
                    showActive={false}
                />
            </div>
        </>
    );
}
