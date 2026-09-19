import { Form, Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UserForm from './user-form';

type ProfileOption = { id: number; label: string };

type Props = {
    user: {
        id: number;
        username: string;
        email: string | null;
        role: string;
        is_active: boolean;
    };
    profiles: {
        teacher: ProfileOption[];
        parent: ProfileOption[];
        student: ProfileOption[];
    };
    current_profile_ids: {
        teacher: number | null;
        parent: number | null;
        student: number | null;
    };
};

export default function EditUser({
    user,
    profiles,
    current_profile_ids,
}: Props) {
    return (
        <>
            <Head title={`Edit ${user.username}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit user"
                    description="Account credentials and role. Deactivate instead of deleting."
                />

                <UserForm
                    action={UserController.update.form({ user: user.id })}
                    submitLabel="Save changes"
                    defaults={{
                        username: user.username,
                        email: user.email,
                        role: user.role,
                        is_active: user.is_active,
                    }}
                    profiles={profiles}
                    currentProfileIds={current_profile_ids}
                    showPassword={false}
                    showActive
                />

                <Heading variant="small" title="Reset password" />

                <Form
                    {...UserController.updatePassword.form({ user: user.id })}
                    className="space-y-6"
                    resetOnSuccess={['password']}
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    placeholder="New password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Reset password
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
