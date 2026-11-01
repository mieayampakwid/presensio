import { Form, Head, setLayoutProps } from '@inertiajs/react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

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
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('students.index.title'),
                href: StudentController.index().url,
            },
            {
                title: t('students.import.crumb'),
                href: StudentImportController.create().url,
            },
            {
                title: t('students.import.preview.title'),
            },
        ],
    });

    return (
        <>
            <Head title={t('students.import.preview.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('students.import.preview.title')}
                    description={t('students.import.preview.description')}
                />

                <div className="flex flex-wrap items-center gap-4">
                    <Badge variant="secondary">
                        {t('students.import.preview.ready', {
                            count: validCount,
                        })}
                    </Badge>
                    <Badge
                        variant={errors.length > 0 ? 'destructive' : 'outline'}
                    >
                        {t('students.import.preview.with_errors', {
                            count: errors.length,
                        })}
                    </Badge>
                    <span className="text-muted-foreground text-sm">
                        {t('students.import.preview.file_type', { extension })}
                        {options.auto_create_classes
                            ? ` · ${t('students.import.preview.auto_create_on')}`
                            : ''}
                    </span>
                </div>

                {errors.length > 0 && (
                    <div className="border-destructive/40 bg-destructive/5 rounded-lg border p-4">
                        <p className="text-destructive mb-2 text-sm font-medium">
                            {t('students.import.preview.skipped')}
                        </p>
                        <ul className="text-destructive list-disc space-y-1 pl-5 text-sm">
                            {errors.map((error) => (
                                <li key={error.row}>
                                    {t('students.import.preview.row_error', {
                                        row: error.row,
                                        errors: error.errors.join(' '),
                                    })}
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
                                    {t(
                                        'students.import.preview.columns.full_name',
                                    )}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('students.import.preview.columns.dob')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('students.import.preview.columns.nis')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('students.import.preview.columns.class')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t(
                                        'students.import.preview.columns.guardian',
                                    )}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="border-t">
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
                                        {t('students.import.preview.empty')}
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
                            <input type="hidden" name="token" value={token} />
                            {Object.entries(mapping).map(([field, header]) => (
                                <input
                                    key={field}
                                    type="hidden"
                                    name={`mapping[${field}]`}
                                    value={header}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="auto_create_classes"
                                value={options.auto_create_classes ? '1' : '0'}
                            />
                            <Button
                                type="submit"
                                disabled={processing || validCount === 0}
                            >
                                {processing
                                    ? t('students.import.preview.importing')
                                    : t('students.import.preview.run', {
                                          count: validCount,
                                      })}
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={StudentImportController.create().url}>
                                    {t('students.import.preview.start_over')}
                                </a>
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
