import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { UserRole } from '@/types';

type ProfileOption = { id: number; label: string };

type ProfileOptions = {
    teacher: ProfileOption[];
    parent: ProfileOption[];
    student: ProfileOption[];
};

type CurrentProfileIds = {
    teacher: number | null;
    parent: number | null;
    student: number | null;
};

type UserFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        username: string;
        email: string | null;
        role?: string;
        roles?: UserRole[];
        is_active?: boolean;
    };
    profiles: ProfileOptions;
    currentProfileIds?: CurrentProfileIds;
    showPassword: boolean;
    showActive: boolean;
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const ALL_ROLES: UserRole[] = [
    'admin',
    'principal',
    'teacher',
    'counselor',
    'finance',
    'staff',
    'parent',
    'student',
];

export default function UserForm({
    action,
    submitLabel,
    defaults,
    profiles,
    currentProfileIds,
    showPassword,
    showActive,
}: UserFormProps) {
    const { t } = useTranslation();

    const initialRoles: UserRole[] =
        defaults.roles && defaults.roles.length > 0
            ? defaults.roles
            : defaults.role
              ? [defaults.role as UserRole]
              : ['teacher'];

    const [roles, setRoles] = useState<UserRole[]>(initialRoles);

    const activeProfileRole = roles.find((r) =>
        ['teacher', 'parent', 'student'].includes(r),
    );

    const [profileId, setProfileId] = useState(
        activeProfileRole
            ? String(
                  currentProfileIds?.[
                      activeProfileRole as keyof CurrentProfileIds
                  ] ?? '',
              )
            : '',
    );

    const toggleRole = (targetRole: UserRole) => {
        let nextRoles: UserRole[];
        if (targetRole === 'student') {
            nextRoles = ['student'];
        } else {
            const withoutStudent = roles.filter((r) => r !== 'student');
            if (withoutStudent.includes(targetRole)) {
                nextRoles = withoutStudent.filter((r) => r !== targetRole);
            } else {
                nextRoles = [...withoutStudent, targetRole];
            }
        }
        setRoles(nextRoles);

        const nextActiveProfileRole = nextRoles.find((r) =>
            ['teacher', 'parent', 'student'].includes(r),
        );
        if (nextActiveProfileRole) {
            setProfileId(
                String(
                    currentProfileIds?.[
                        nextActiveProfileRole as keyof CurrentProfileIds
                    ] ?? '',
                ),
            );
        } else {
            setProfileId('');
        }
    };

    const profileOptions = activeProfileRole
        ? (profiles[activeProfileRole as keyof ProfileOptions] ?? [])
        : [];

    return (
        <Form
            {...action}
            className="space-y-6"
            resetOnSuccess={showPassword ? ['password'] : undefined}
        >
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="username">
                            {t('users.form.username')}
                        </Label>
                        <Input
                            id="username"
                            name="username"
                            defaultValue={defaults.username}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder={t('users.form.username_placeholder')}
                        />
                        <InputError message={errors.username} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('users.form.email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            defaultValue={defaults.email ?? ''}
                            autoComplete="off"
                            placeholder="email@school.id"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('users.form.roles')}</Label>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {ALL_ROLES.map((role) => (
                                <label
                                    key={role}
                                    className="flex cursor-pointer items-center gap-2 text-sm font-normal"
                                >
                                    <input
                                        type="checkbox"
                                        name="roles[]"
                                        value={role}
                                        checked={roles.includes(role)}
                                        onChange={() => toggleRole(role)}
                                        className="text-primary focus:ring-primary size-4 rounded border-gray-300"
                                    />
                                    <span>{t(`roles.${role}`)}</span>
                                </label>
                            ))}
                        </div>
                        <input
                            type="hidden"
                            name="role"
                            value={roles[0] ?? ''}
                        />
                        <InputError message={errors.roles ?? errors.role} />
                    </div>

                    {activeProfileRole && (
                        <div className="grid gap-2">
                            <Label htmlFor="profile_id">
                                {t('users.form.linked_profile', {
                                    role: t(`roles.${activeProfileRole}`),
                                })}
                            </Label>
                            <select
                                id="profile_id"
                                name="profile_id"
                                value={profileId}
                                onChange={(event) =>
                                    setProfileId(event.target.value)
                                }
                                className={SELECT_CLASS}
                            >
                                <option value="">
                                    {t('users.form.not_linked')}
                                </option>
                                {profileOptions.map((profile) => (
                                    <option key={profile.id} value={profile.id}>
                                        {profile.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.profile_id} />
                        </div>
                    )}

                    {showActive && (
                        <div className="flex items-center gap-3">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                id="is_active"
                                type="checkbox"
                                name="is_active"
                                value="1"
                                defaultChecked={defaults.is_active ?? true}
                                className="border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <Label htmlFor="is_active">
                                {t('users.form.active')}
                            </Label>
                            <InputError message={errors.is_active} />
                        </div>
                    )}

                    {showPassword && (
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('users.form.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                placeholder={t(
                                    'users.form.password_placeholder',
                                )}
                            />
                            <InputError message={errors.password} />
                        </div>
                    )}

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            )}
        </Form>
    );
}
