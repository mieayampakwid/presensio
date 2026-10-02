import { Head, Form, Link } from '@inertiajs/react';
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
            <Head title={`Semesters — ${academic_year.name}`} />

            <div className="mx-auto max-w-4xl space-y-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={AcademicYearController.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to Academic Years
                        </Link>
                    </Button>
                </div>

                <Heading
                    title={`Semester Boundaries — ${academic_year.name}`}
                    description={`Year span: ${academic_year.starts_at} to ${academic_year.ends_at}. Semesters must be contiguous and fill the academic year.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Adjust Semesters</CardTitle>
                        <CardDescription>
                            Semester Ganjil begins on the academic year start
                            date. Semester Genap must begin the day after
                            Semester Ganjil ends and conclude on the academic
                            year end date.
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
                                                Semester 1 (Ganjil)
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
                                                    Start Date
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
                                                    Locked to academic year
                                                    start
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
                                                    End Date
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
                                                Semester 2 (Genap)
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
                                                    Start Date
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
                                                    End Date
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
                                                    Locked to academic year end
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
                                            Save Semester Boundaries
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
