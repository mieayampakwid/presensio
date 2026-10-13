import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, GraduationCap, Lock, Unlock } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type SubjectItem = {
    id: number;
    name: string;
    code: string;
    teacher_name: string | null;
    passing_threshold: number;
    is_finalized: boolean;
    finalized_at: string | null;
};

type ScoreCell = {
    score: number | null;
    is_final: boolean;
    below_threshold: boolean;
};

type StudentRow = {
    id: number;
    name: string;
    student_number: string | null;
    gender?: string;
    scores: Record<number, ScoreCell>;
};

type SemesterItem = {
    id: number;
    name: string;
    number: number;
    starts_at: string;
    ends_at: string;
};

type ClassInfo = {
    id: number;
    name: string;
    grade_level: number;
};

type Props = {
    school_class: ClassInfo;
    semester: SemesterItem;
    semesters: SemesterItem[];
    subjects: SubjectItem[];
    students: StudentRow[];
};

export default function ClassGrades({
    school_class,
    semester,
    semesters,
    subjects,
    students,
}: Props) {
    const handleSemesterChange = (newSemesterId: string) => {
        router.get(
            `/classes/${school_class.id}/grades`,
            { semester: newSemesterId },
            { preserveState: false },
        );
    };

    return (
        <>
            <Head
                title={`Leger Nilai - ${school_class.name} (${semester.name})`}
            />

            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Link
                                href="/classes"
                                className="flex items-center gap-1 hover:underline"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali ke Kelas
                            </Link>
                            <span>•</span>
                            <span className="flex items-center gap-1">
                                <GraduationCap className="h-4 w-4" />
                                Kelas {school_class.name} (Tingkat{' '}
                                {school_class.grade_level})
                            </span>
                        </div>
                        <Heading
                            title={`Leger Nilai: Kelas ${school_class.name}`}
                            description={`Rekapitulasi nilai seluruh mata pelajaran untuk semester ${semester.name}`}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Calendar className="text-muted-foreground h-4 w-4" />
                        <Select
                            value={String(semester.id)}
                            onValueChange={handleSemesterChange}
                        >
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Pilih Semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {semesters.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Ledger Grid */}
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted text-muted-foreground font-medium">
                            <tr className="border-b">
                                <th className="bg-muted sticky left-0 z-20 w-12 px-4 py-3">
                                    No
                                </th>
                                <th className="bg-muted sticky left-12 z-20 min-w-[200px] px-4 py-3 shadow-[1px_0_0_0_var(--color-border)]">
                                    Nama Siswa
                                </th>
                                {subjects.map((sub) => (
                                    <th
                                        key={sub.id}
                                        className="min-w-[130px] px-3 py-2 text-center"
                                    >
                                        <div className="text-foreground font-semibold">
                                            {sub.code || sub.name}
                                        </div>
                                        <div className="text-muted-foreground text-[11px]">
                                            KKTP: {sub.passing_threshold}
                                        </div>
                                        <div className="mt-1 flex justify-center">
                                            {sub.is_finalized ? (
                                                <Badge
                                                    variant="default"
                                                    className="bg-emerald-600 px-1.5 py-0 text-[10px] text-white"
                                                >
                                                    <Lock className="mr-0.5 h-2.5 w-2.5" />
                                                    Final
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="px-1.5 py-0 text-[10px]"
                                                >
                                                    <Unlock className="mr-0.5 h-2.5 w-2.5" />
                                                    Draf
                                                </Badge>
                                            )}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {students.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={subjects.length + 2}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Tidak ada siswa aktif terdaftar di kelas
                                        ini pada semester terpilih.
                                    </td>
                                </tr>
                            ) : (
                                students.map((student, idx) => (
                                    <tr
                                        key={student.id}
                                        className="hover:bg-muted/50"
                                    >
                                        <td className="bg-background text-muted-foreground sticky left-0 px-4 py-2.5 font-mono">
                                            {idx + 1}
                                        </td>
                                        <td className="bg-background sticky left-12 px-4 py-2.5 shadow-[1px_0_0_0_var(--color-border)]">
                                            <div className="font-medium">
                                                {student.name}
                                            </div>
                                            <div className="text-muted-foreground font-mono text-xs">
                                                {student.student_number || '-'}
                                            </div>
                                        </td>
                                        {subjects.map((sub) => {
                                            const cell = student.scores[sub.id];
                                            const hasScore =
                                                cell && cell.score !== null;
                                            const isBelow =
                                                cell?.below_threshold;

                                            return (
                                                <td
                                                    key={sub.id}
                                                    className="px-3 py-2.5 text-center font-mono"
                                                >
                                                    {hasScore ? (
                                                        <span
                                                            className={`inline-block rounded px-2 py-0.5 font-semibold ${
                                                                isBelow
                                                                    ? 'bg-destructive/15 text-destructive font-bold'
                                                                    : 'text-foreground'
                                                            }`}
                                                        >
                                                            {cell.score}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs italic">
                                                            -
                                                        </span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ClassGrades.layout = {
    breadcrumbs: [
        {
            title: 'Leger Nilai',
            href: '/classes',
        },
    ],
};
