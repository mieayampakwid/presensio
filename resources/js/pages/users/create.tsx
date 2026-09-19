import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
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
    return (
        <>
            <Head title="Create user" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Create user"
                    description="Provision a new account and optionally link it to a teacher, guardian, or student profile."
                />

                <UserForm
                    action={UserController.store.form()}
                    submitLabel="Create user"
                    defaults={{ username: '', email: null, role: 'teacher' }}
                    profiles={profiles}
                    showPassword
                    showActive={false}
                />
            </div>
        </>
    );
}
