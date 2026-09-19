import { Form, Head } from '@inertiajs/react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ImportError = {
    row: number;
    errors: string[];
};

type ValidRow = Record<string, string | null>;

type Props = {
    token: string;
    extension: string;
    mapping: Record<string, string>;
    options: Record<string, unknown>;
    validCount: number;
    errors: ImportError[];
    rows: ValidRow[];
};

export default function ImportPreview({
    token,
    extension,
    mapping,
    options,
    validCount,
    errors,
    rows,
}: Props) {
    return (
        <>
            <Head title="Preview import" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Preview import"
                    description="Nothing is written yet. Review the summary, then run the import — valid rows commit, broken rows are skipped and reported."
                />

                <div className="flex flex-wrap items-center gap-4">
                    <Badge variant="secondary">
                        {validCount} ready to import
                    </Badge>
                    <Badge
                        variant={errors.length > 0 ? 'destructive' : 'outline'}
                    >
                        {errors.length} with errors
                    </Badge>
                    <span className="text-muted-foreground text-sm">
                        File type: .{extension}
                        {options.auto_create_classes
                            ? ' · auto-create classes on'
                            : ''}
                    </span>
                </div>

                {errors.length > 0 && (
                    <div className="rounded-lg border border-destructive/40 bg-destructive/5 p-4">
                        <p className="text-destructive mb-2 text-sm font-medium">
                            Rows that will be skipped
                        </p>
                        <ul className="text-destructive list-disc space-y-1 pl-5 text-sm">
                            {errors.map((error) => (
                                <li key={error.row}>
                                    Row {error.row}:{' '}
                                    {error.errors.join(' ')}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Full name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Date of birth
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    NIS
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Class
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Guardian
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={index}
                                    className="border-t"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {row.full_name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.dob}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.student_number ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.class ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.guardian_name
                                            ? `${row.guardian_name} · ${row.guardian_phone}`
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Nothing to import.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Form
                    {...StudentImportController.run.form()}
                    className="flex gap-2"
                >
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="token"
                                value={token}
                            />
                            {Object.entries(mapping).map(
                                ([field, header]) => (
                                    <input
                                        key={field}
                                        type="hidden"
                                        name={`mapping[${field}]`}
                                        value={header}
                                    />
                                ),
                            )}
                            <input
                                type="hidden"
                                name="auto_create_classes"
                                value={
                                    options.auto_create_classes ? '1' : '0'
                                }
                            />
                            <Button
                                type="submit"
                                disabled={
                                    processing || validCount === 0
                                }
                            >
                                {processing
                                    ? 'Importing…'
                                    : `Import ${validCount} students`}
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        StudentImportController.create()
                                            .url
                                    }
                                >
                                    Start over
                                </a>
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ImportPreview.layout = {
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
            title: 'Preview',
        },
    ],
};
