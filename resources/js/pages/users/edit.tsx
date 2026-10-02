import { AuditHistory, type AuditLogEntry } from '@/components/audit-history';
import { Form, Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { UserRole } from '@/types';
import UserForm from './user-form';

type ProfileOption = { id: number; label: string };

type Props = {
    user: {
        id: number;
        username: string;
        email: string | null;
        role: string;
        roles?: UserRole[];
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
    audit_logs?: AuditLogEntry[];
};

export default function EditUser({
    user,
    profiles,
    current_profile_ids,
    audit_logs,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('users.edit.head', { username: user.username })} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('users.edit.title')}
                    description={t('users.edit.description')}
                />

                <UserForm
                    action={UserController.update.form({ user: user.id })}
                    submitLabel={t('users.edit.submit')}
                    defaults={{
                        username: user.username,
                        email: user.email,
                        role: user.role,
                        roles: user.roles,
                        is_active: user.is_active,
                    }}
                    profiles={profiles}
                    currentProfileIds={current_profile_ids}
                    showPassword={false}
                    showActive
                />

                <Heading
                    variant="small"
                    title={t('users.reset_password.title')}
                />

                <Form
                    {...UserController.updatePassword.form({ user: user.id })}
                    className="space-y-6"
                    resetOnSuccess={['password']}
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('users.reset_password.label')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    placeholder={t(
                                        'users.reset_password.placeholder',
                                    )}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {t('users.reset_password.submit')}
                            </Button>
                        </div>
                    )}
                </Form>

                <AuditHistory entries={audit_logs ?? []} />
            </div>
        </>
    );
}
