import { Form, Head } from '@inertiajs/react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    token: string;
    extension: string;
    headers: string[];
    sampleRows: string[][];
    guessedMapping: Record<string, string>;
    autoCreateClasses: boolean;
};

const FIELDS: { key: string; label: string; required?: boolean }[] = [
    { key: 'full_name', label: 'Full name', required: true },
    { key: 'nickname', label: 'Nickname' },
    { key: 'dob', label: 'Date of birth' },
    { key: 'student_number', label: 'Student number (NIS)' },
    { key: 'class', label: 'Class', required: true },
    { key: 'guardian_name', label: 'Guardian name' },
    { key: 'guardian_phone', label: 'Guardian phone' },
    { key: 'guardian_work', label: 'Guardian occupation' },
    { key: 'guardian_address', label: 'Guardian address' },
];

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function ImportMap({
    token,
    extension,
    headers,
    sampleRows,
    guessedMapping,
    autoCreateClasses,
}: Props) {
    return (
        <>
            <Head title="Map import columns" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Map columns"
                    description="Match the spreadsheet columns to student fields. Guesses are preselected — override anything that looks wrong."
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                {headers.map((header) => (
                                    <th
                                        key={header}
                                        className="px-4 py-3 text-left font-medium"
                                    >
                                        {header || '—'}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {sampleRows.map((row, index) => (
                                <tr key={index} className="border-t">
                                    {headers.map((header, cellIndex) => (
                                        <td
                                            key={header + cellIndex}
                                            className="px-4 py-3"
                                        >
                                            {row[cellIndex] ?? ''}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Form
                    {...StudentImportController.preview.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="grid max-w-xl gap-6">
                            <input
                                type="hidden"
                                name="token"
                                value={token}
                            />

                            {FIELDS.map((field) => (
                                <div
                                    key={field.key}
                                    className="grid gap-2"
                                >
                                    <Label
                                        htmlFor={`mapping_${field.key}`}
                                    >
                                        {field.label}
                                        {field.required && ' *'}
                                    </Label>
                                    <select
                                        id={`mapping_${field.key}`}
                                        name={`mapping[${field.key}]`}
                                        defaultValue={
                                            guessedMapping[field.key] ?? ''
                                        }
                                        className={SELECT_CLASS}
                                    >
                                        <option value="">
                                            — ignored —
                                        </option>
                                        {headers.map((header) => (
                                            <option
                                                key={header}
                                                value={header}
                                            >
                                                {header || '—'}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            errors[
                                                `mapping.${field.key}` as keyof typeof errors
                                            ]
                                        }
                                    />
                                </div>
                            ))}

                            <div className="flex items-center gap-3">
                                <input
                                    id="auto_create_classes"
                                    type="checkbox"
                                    name="auto_create_classes"
                                    value="1"
                                    defaultChecked={autoCreateClasses}
                                    className="border-input dark:bg-input/30 size-4 shrink-0 rounded-[4px] border shadow-xs outline-none accent-primary"
                                />
                                <Label htmlFor="auto_create_classes">
                                    Create classes from the file when they
                                    don't exist yet
                                </Label>
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Preview import
                                </Button>
                                <Button
                                    variant="outline"
                                    asChild
                                >
                                    <a
                                        href={
                                            StudentController.index().url
                                        }
                                    >
                                        Cancel
                                    </a>
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                <p className="text-muted-foreground max-w-xl text-sm">
                    File type: .{extension}. Dates are read day-first
                    (dd/mm/yyyy). Student numbers keep their leading zeros.
                </p>
            </div>
        </>
    );
}

ImportMap.layout = {
    breadcrumbs: [
        {
            title: 'Students',
            href: StudentController.index().url,
        },
        {
            title: 'Import',
            href: StudentImportController.create().url,
        },
        {
            title: 'Map columns',
        },
    ],
};
