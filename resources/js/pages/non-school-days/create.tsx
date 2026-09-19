import { Head, Form } from '@inertiajs/react';
import NonSchoolDayController from '@/actions/App/Http/Controllers/Calendar/NonSchoolDayController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function CreateNonSchoolDay() {
    return (
        <>
            <Head title="Add non-school day" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Add non-school day"
                    description="Declare a school closure. Only one entry per date is allowed."
                />

                <Form
                    {...NonSchoolDayController.store.form()}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="grid max-w-sm gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="date">Date</Label>
                                <Input
                                    id="date"
                                    type="date"
                                    name="date"
                                    required
                                />
                                <InputError message={errors.date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxLength={255}
                                    placeholder="e.g. Teacher training day"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Add date
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateNonSchoolDay.layout = {
    breadcrumbs: [
        {
            title: 'Non-School Days',
            href: NonSchoolDayController.index().url,
        },
        {
            title: 'Add',
        },
    ],
};
