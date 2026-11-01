import { Head, Form, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import AcademicYearController from '@/actions/App/Http/Controllers/AcademicYears/AcademicYearController';
import SemesterController from '@/actions/App/Http/Controllers/AcademicYears/SemesterController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Semester = {
    id: number;
    number: number;
    name: string;
    starts_at: string;
    ends_at: string;
};

type AcademicYear = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
};

type Props = {
    academic_year: AcademicYear;
    semesters: Semester[];
};

export default function SemestersPage({ academic_year, semesters }: Props) {
    const ganjil = semesters.find((s) => s.number === 1) ?? {
        id: 0,
        number: 1,
        name: 'Semester Ganjil',
        starts_at: academic_year.starts_at,
        ends_at: '',
    };
    const genap = semesters.find((s) => s.number === 2) ?? {
        id: 0,
        number: 2,
        name: 'Semester Genap',
        starts_at: '',
        ends_at: academic_year.ends_at,
    };

    const [ganjilEnd, setGanjilEnd] = useState(ganjil.ends_at);
    const [genapStart, setGenapStart] = useState(genap.starts_at);
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('academic_years.index.title'),
                href: AcademicYearController.index().url,
            },
            {
                title: t('academic_years.semesters.title', {
                    name: academic_year.name,
                }),
            },
        ],
    });

    const handleGanjilEndChange = (newEnd: string) => {
        setGanjilEnd(newEnd);
        if (newEnd) {
            const d = new Date(newEnd);
            d.setDate(d.getDate() + 1);
            setGenapStart(d.toISOString().split('T')[0]);
        }
    };

    return (
        <>
            <Head
                title={t('academic_years.semesters.title', {
                    name: academic_year.name,
                })}
            />

            <div className="mx-auto max-w-4xl space-y-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={AcademicYearController.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                            {t('academic_years.semesters.back')}
                        </Link>
                    </Button>
                </div>

                <Heading
                    title={t('academic_years.semesters.heading', {
                        name: academic_year.name,
                    })}
                    description={t('academic_years.semesters.description', {
                        starts: academic_year.starts_at,
                        ends: academic_year.ends_at,
                    })}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('academic_years.semesters.adjust_heading')}
                        </CardTitle>
                        <CardDescription>
                            {t('academic_years.semesters.adjust_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...SemesterController.update.form({
                                academic_year: academic_year.id,
                            })}
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <InputError
                                        message={
                                            errors['semesters'] as
                                                | string
                                                | undefined
                                        }
                                    />

                                    <div className="grid gap-6 md:grid-cols-2">
                                        <div className="space-y-4 rounded-lg border p-4">
                                            <h3 className="font-semibold">
                                                {t(
                                                    'academic_years.semesters.semester_one',
                                                )}
                                            </h3>
                                            <input
                                                type="hidden"
                                                name="semesters[0][number]"
                                                value={1}
                                            />
                                            <input
                                                type="hidden"
                                                name="semesters[0][starts_at]"
                                                value={academic_year.starts_at}
                                            />
                                            <div className="space-y-2">
                                                <Label htmlFor="ganjil_start">
                                                    {t(
                                                        'academic_years.semesters.start_date',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="ganjil_start"
                                                    type="date"
                                                    value={
                                                        academic_year.starts_at
                                                    }
                                                    disabled
                                                    className="bg-muted"
                                                />
                                                <p className="text-muted-foreground text-xs">
                                                    {t(
                                                        'academic_years.semesters.locked_start',
                                                    )}
                                                </p>
                                                <InputError
                                                    message={
                                                        errors[
                                                            'semesters.0.starts_at'
                                                        ] as string | undefined
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="ganjil_end">
                                                    {t(
                                                        'academic_years.semesters.end_date',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="ganjil_end"
                                                    type="date"
                                                    name="semesters[0][ends_at]"
                                                    value={ganjilEnd}
                                                    onChange={(e) =>
                                                        handleGanjilEndChange(
                                                            e.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            'semesters.0.ends_at'
                                                        ] as string | undefined
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-4 rounded-lg border p-4">
                                            <h3 className="font-semibold">
                                                {t(
                                                    'academic_years.semesters.semester_two',
                                                )}
                                            </h3>
                                            <input
                                                type="hidden"
                                                name="semesters[1][number]"
                                                value={2}
                                            />
                                            <input
                                                type="hidden"
                                                name="semesters[1][ends_at]"
                                                value={academic_year.ends_at}
                                            />
                                            <div className="space-y-2">
                                                <Label htmlFor="genap_start">
                                                    {t(
                                                        'academic_years.semesters.start_date',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="genap_start"
                                                    type="date"
                                                    name="semesters[1][starts_at]"
                                                    value={genapStart}
                                                    onChange={(e) =>
                                                        setGenapStart(
                                                            e.target.value,
                                                        )
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            'semesters.1.starts_at'
                                                        ] as string | undefined
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="genap_end">
                                                    {t(
                                                        'academic_years.semesters.end_date',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="genap_end"
                                                    type="date"
                                                    value={
                                                        academic_year.ends_at
                                                    }
                                                    disabled
                                                    className="bg-muted"
                                                />
                                                <p className="text-muted-foreground text-xs">
                                                    {t(
                                                        'academic_years.semesters.locked_end',
                                                    )}
                                                </p>
                                                <InputError
                                                    message={
                                                        errors[
                                                            'semesters.1.ends_at'
                                                        ] as string | undefined
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t(
                                                'academic_years.semesters.submit',
                                            )}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
