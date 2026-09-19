import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import GuardianContactController from '@/actions/App/Http/Controllers/Settings/GuardianContactController';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type Guardian = {
    id: number;
    name: string;
    phone_number: string;
    work: string | null;
    address: string | null;
};

type PageProps = {
    auth: Auth;
    guardian: Guardian | null;
};

export default function Profile() {
    const { auth, guardian } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Account"
                    description="Your account details. Username and role are managed by the school administrator."
                />

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <p className="text-sm leading-none font-medium">Username</p>
                        <p className="text-muted-foreground text-sm">
                            {auth.user.username}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <p className="text-sm leading-none font-medium">Email</p>
                        <p className="text-muted-foreground text-sm">
                            {auth.user.email ?? '—'}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <p className="text-sm leading-none font-medium">Role</p>
                        <div>
                            <Badge variant="outline" className="capitalize">
                                {auth.user.role}
                            </Badge>
                        </div>
                    </div>
                </div>

                {guardian && <GuardianContactSection guardian={guardian} />}

                <p className="text-muted-foreground text-sm">
                    Change your password from the{' '}
                    <span className="text-foreground font-medium">Security</span>{' '}
                    tab.
                </p>
            </div>
        </>
    );
}

function GuardianContactSection({ guardian }: { guardian: Guardian }) {
    return (
        <Form
            {...GuardianContactController.update.form()}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Contact details"
                        description={`Absence notifications for your children go to these details. Your name (${guardian.name}) is managed by the school.`}
                    />

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="phone_number">Phone number</Label>
                            <Input
                                id="phone_number"
                                name="phone_number"
                                defaultValue={guardian.phone_number}
                                required
                                autoComplete="tel"
                            />
                            <InputError message={errors.phone_number} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="work">Occupation (optional)</Label>
                            <Input
                                id="work"
                                name="work"
                                defaultValue={guardian.work ?? ''}
                                autoComplete="off"
                            />
                            <InputError message={errors.work} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address">Address (optional)</Label>
                            <textarea
                                id="address"
                                name="address"
                                defaultValue={guardian.address ?? ''}
                                rows={3}
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            />
                            <InputError message={errors.address} />
                        </div>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Save contact details
                    </Button>
                </div>
            )}
        </Form>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
