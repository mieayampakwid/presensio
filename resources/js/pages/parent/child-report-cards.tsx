import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import ReportCardView, {
    type ReportCardPayload,
} from '@/components/report-card-view';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index,
    pdf,
} from '@/actions/App/Http/Controllers/Parent/ChildReportCardController';

type ChildOption = { id: number; name: string };

type SemesterSummary = {
    id: number;
    name: string;
    number: number;
    version: number;
    published_at: string;
};

type Props = {
    child: ChildOption;
    students: ChildOption[];
    semesters: SemesterSummary[];
    selected_semester_id: number | null;
    payload: ReportCardPayload | null;
};

export default function ChildReportCards({
    child,
    students,
    semesters,
    selected_semester_id,
    payload,
}: Props) {
    const [semesterId, setSemesterId] = useState<number | null>(
        selected_semester_id,
    );

    const switchChild = (studentId: string) => {
        router.get(
            index.url({ student: Number(studentId) }),
            {},
            { preserveState: false },
        );
    };

    const switchSemester = (newSemesterId: string) => {
        const id = Number(newSemesterId);
        setSemesterId(id);
        router.get(
            index.url({ student: child.id }),
            { semester: id },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={`Rapor ${child.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={`Rapor: ${child.name}`}
                    description="Arsip rapor semester yang sudah diterbitkan"
                />

                {students.length > 1 && (
                    <div className="flex items-center gap-3">
                        <Label htmlFor="child">Pilih Anak</Label>
                        <Select
                            value={String(child.id)}
                            onValueChange={switchChild}
                        >
                            <SelectTrigger id="child" className="w-[220px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {students.map((student) => (
                                    <SelectItem
                                        key={student.id}
                                        value={String(student.id)}
                                    >
                                        {student.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {semesters.length === 0 ? (
                    <Card className="text-muted-foreground p-6 text-sm">
                        Belum ada rapor yang diterbitkan untuk anak ini.
                    </Card>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-3">
                            <Label htmlFor="semester">Semester</Label>
                            <Select
                                value={String(semesterId ?? '')}
                                onValueChange={switchSemester}
                            >
                                <SelectTrigger
                                    id="semester"
                                    className="w-[220px]"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((semester) => (
                                        <SelectItem
                                            key={semester.id}
                                            value={String(semester.id)}
                                        >
                                            {semester.name} · Versi{' '}
                                            {semester.version}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {semesterId !== null && (
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={pdf.url({
                                            student: child.id,
                                            semester: semesterId,
                                        })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Unduh PDF
                                    </a>
                                </Button>
                            )}
                        </div>

                        {payload !== null && (
                            <Card className="p-6">
                                <ReportCardView payload={payload} />
                            </Card>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

ChildReportCards.layout = {
    breadcrumbs: [
        {
            title: 'Rapor Anak',
            href: '/children',
        },
    ],
};
