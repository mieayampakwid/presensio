import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type GuardianFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        name: string;
        phoneNumber: string;
        work: string | null;
        address: string | null;
    };
};

export default function GuardianForm({
    action,
    submitLabel,
    defaults,
}: GuardianFormProps) {
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={defaults.name}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="Full name"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone_number">Phone number</Label>
                        <Input
                            id="phone_number"
                            name="phone_number"
                            defaultValue={defaults.phoneNumber}
                            required
                            autoComplete="off"
                            placeholder="e.g. +6281234567890"
                        />
                        <InputError message={errors.phone_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="work">Occupation (optional)</Label>
                        <Input
                            id="work"
                            name="work"
                            defaultValue={defaults.work ?? ''}
                            autoComplete="off"
                        />
                        <InputError message={errors.work} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Address (optional)</Label>
                        <textarea
                            id="address"
                            name="address"
                            defaultValue={defaults.address ?? ''}
                            rows={3}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                        <InputError message={errors.address} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            )}
        </Form>
    );
}
