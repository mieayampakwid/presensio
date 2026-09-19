import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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
        role: string;
        is_active?: boolean;
    };
    profiles: ProfileOptions;
    currentProfileIds?: CurrentProfileIds;
    showPassword: boolean;
    showActive: boolean;
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

// Native <select> and <input type="checkbox"> are used deliberately: the
// Radix Select and Checkbox primitives don't submit values with native
// form posts (Inertia <Form> serializes DOM inputs).
export default function UserForm({
    action,
    submitLabel,
    defaults,
    profiles,
    currentProfileIds,
    showPassword,
    showActive,
}: UserFormProps) {
    const [role, setRole] = useState(defaults.role);
    const [profileId, setProfileId] = useState(
        defaults.role !== 'admin'
            ? String(currentProfileIds?.[defaults.role as keyof CurrentProfileIds] ?? '')
            : '',
    );

    const changeRole = (nextRole: string) => {
        setRole(nextRole);
        setProfileId(
            nextRole === 'admin'
                ? ''
                : String(
                      currentProfileIds?.[
                          nextRole as keyof CurrentProfileIds
                      ] ?? '',
                  ),
        );
    };

    const profileOptions =
        role === 'admin' ? [] : profiles[role as keyof ProfileOptions] ?? [];

    return (
        <Form
            {...action}
            className="space-y-6"
            resetOnSuccess={showPassword ? ['password'] : undefined}
        >
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            name="username"
                            defaultValue={defaults.username}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="NIS / NIP / NIK"
                        />
                        <InputError message={errors.username} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email (optional)</Label>
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
                        <Label htmlFor="role">Role</Label>
                        <select
                            id="role"
                            name="role"
                            value={role}
                            onChange={(event) => changeRole(event.target.value)}
                            className={SELECT_CLASS}
                        >
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                        </select>
                        <InputError message={errors.role} />
                    </div>

                    {role !== 'admin' && (
                        <div className="grid gap-2">
                            <Label htmlFor="profile_id">Linked profile</Label>
                            <select
                                id="profile_id"
                                name="profile_id"
                                value={profileId}
                                onChange={(event) =>
                                    setProfileId(event.target.value)
                                }
                                className={SELECT_CLASS}
                            >
                                <option value="">Not linked</option>
                                {profileOptions.map((profile) => (
                                    <option
                                        key={profile.id}
                                        value={profile.id}
                                    >
                                        {profile.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.profile_id} />
                        </div>
                    )}

                    {showActive && (
                        <div className="flex items-center gap-3">
                            {/* Hidden input first so unchecked submits "0";
                                FormData keeps the last occurrence per name. */}
                            <input
                                type="hidden"
                                name="is_active"
                                value="0"
                            />
                            <input
                                id="is_active"
                                type="checkbox"
                                name="is_active"
                                value="1"
                                defaultChecked={defaults.is_active ?? true}
                                className="border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <Label htmlFor="is_active">Active</Label>
                            <InputError message={errors.is_active} />
                        </div>
                    )}

                    {showPassword && (
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                placeholder="Password"
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
