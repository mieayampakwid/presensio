import { Form, Head } from '@inertiajs/react';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function ImportUpload() {
    return (
        <>
            <Head title="Import students" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Import students"
                    description="Upload a CSV or XLSX export. You will confirm the column mapping before anything is written."
                />

                <Form
                    {...StudentImportController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="grid max-w-xl gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="file">Spreadsheet file</Label>
                                <Input
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept=".csv,.txt,.xlsx"
                                    required
                                />
                                <p className="text-muted-foreground text-sm">
                                    .csv, .txt, or .xlsx — legacy .xls is not
                                    supported.
                                </p>
                                <InputError message={errors.file} />
                            </div>

                            <div className="flex items-center gap-3">
                                <input
                                    id="auto_create_classes"
                                    type="checkbox"
                                    name="auto_create_classes"
                                    value="1"
                                    className="border-input dark:bg-input/30 size-4 shrink-0 rounded-[4px] border shadow-xs outline-none accent-primary"
                                />
                                <Label htmlFor="auto_create_classes">
                                    Create classes from the file when they
                                    don't exist yet
                                </Label>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Upload
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

ImportUpload.layout = {
    breadcrumbs: [
        {
            title: 'Students',
            href: StudentController.index().url,
        },
        {
            title: 'Import',
        },
    ],
};
