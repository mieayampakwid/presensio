import { Form, Head, setLayoutProps } from '@inertiajs/react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    token: string;
    extension: string;
    headers: string[];
    sampleRows: string[][];
    guessedMapping: Record<string, string>;
    autoCreateClasses: boolean;
};

const FIELDS: { key: string; labelKey: string; required?: boolean }[] = [
    {
        key: 'full_name',
        labelKey: 'students.import.fields.full_name',
        required: true,
    },
    { key: 'nickname', labelKey: 'students.import.fields.nickname' },
    { key: 'dob', labelKey: 'students.import.fields.dob' },
    {
        key: 'student_number',
        labelKey: 'students.import.fields.student_number',
    },
    { key: 'gender', labelKey: 'students.import.fields.gender' },
    { key: 'birth_place', labelKey: 'students.import.fields.birth_place' },
    { key: 'religion', labelKey: 'students.import.fields.religion' },
    { key: 'address', labelKey: 'students.import.fields.address' },
    { key: 'class', labelKey: 'students.import.fields.class', required: true },
    { key: 'guardian_name', labelKey: 'students.import.fields.guardian_name' },
    {
        key: 'guardian_phone',
        labelKey: 'students.import.fields.guardian_phone',
    },
    {
        key: 'guardian_work',
        labelKey: 'students.import.fields.guardian_work',
    },
    {
        key: 'guardian_address',
        labelKey: 'students.import.fields.guardian_address',
    },
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
                title: t('students.import.map.title'),
            },
        ],
    });

    return (
        <>
            <Head title={t('students.import.map.head')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('students.import.map.title')}
                    description={t('students.import.map.description')}
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
                            <input type="hidden" name="token" value={token} />

                            {FIELDS.map((field) => (
                                <div key={field.key} className="grid gap-2">
                                    <Label htmlFor={`mapping_${field.key}`}>
                                        {t(field.labelKey)}
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
                                            {t('students.import.map.ignored')}
                                        </option>
                                        {headers.map((header) => (
                                            <option key={header} value={header}>
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
                                    className="border-input dark:bg-input/30 accent-primary size-4 shrink-0 rounded-[4px] border shadow-xs outline-none"
                                />
                                <Label htmlFor="auto_create_classes">
                                    {t('students.import.auto_create_classes')}
                                </Label>
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('students.import.map.preview')}
                                </Button>
                                <Button variant="outline" asChild>
                                    <a href={StudentController.index().url}>
                                        {t('common.cancel')}
                                    </a>
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                <p className="text-muted-foreground max-w-xl text-sm">
                    {t('students.import.map.file_type', { extension })}
                </p>
            </div>
        </>
    );
}
