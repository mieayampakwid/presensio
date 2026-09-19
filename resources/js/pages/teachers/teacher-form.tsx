import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type TeacherFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        name: string;
        teacherNumber: string | null;
        phoneNumber: string | null;
    };
};

export default function TeacherForm({
    action,
    submitLabel,
    defaults,
}: TeacherFormProps) {
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
                        <Label htmlFor="teacher_number">
                            Teacher number (NIP/NUPTK, optional)
                        </Label>
                        <Input
                            id="teacher_number"
                            name="teacher_number"
                            defaultValue={defaults.teacherNumber ?? ''}
                            autoComplete="off"
                            placeholder="e.g. 197501012000031002"
                        />
                        <InputError message={errors.teacher_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone_number">
                            Phone number (optional)
                        </Label>
                        <Input
                            id="phone_number"
                            name="phone_number"
                            defaultValue={defaults.phoneNumber ?? ''}
                            autoComplete="off"
                            placeholder="e.g. +6281234567890"
                        />
                        <InputError message={errors.phone_number} />
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
