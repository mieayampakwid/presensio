import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    GraduationCap,
    Info,
    MessageSquare,
    UserRound,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type AssessmentItem = {
    id: number;
    name: string;
    kind: 'formative' | 'summative_scope' | 'summative_final';
    assessed_on: string;
    max_score: number;
    score: number | null;
    remedial_score: number | null;
    notes: string | null;
};

type SubjectItem = {
    id: number;
    name: string;
    code: string;
    teacher_name: string | null;
    passing_threshold: number;
    assessments: AssessmentItem[];
};

type SemesterItem = {
    id: number;
    name: string;
    number: number;
    starts_at: string;
    ends_at: string;
};

type StudentInfo = {
    id: number;
    name: string;
    student_number: string | null;
};

type ClassInfo = {
    id: number;
    name: string;
    grade_level: number;
};

type Props = {
    student: StudentInfo;
    school_class: ClassInfo | null;
    semester: SemesterItem;
    semesters: SemesterItem[];
    subjects: SubjectItem[];
};

export default function ChildGrades({
    student,
    school_class,
    semester,
    semesters,
    subjects,
}: Props) {
    const handleSemesterChange = (newSemesterId: string) => {
        router.get(
            `/children/${student.id}/grades`,
            { semester: newSemesterId },
            { preserveState: false },
        );
    };

    const renderKindBadge = (kind: AssessmentItem['kind']) => {
        switch (kind) {
            case 'formative':
                return (
                    <Badge
                        variant="outline"
                        className="border-blue-400 text-blue-600 dark:text-blue-400"
                    >
                        Formatif
                    </Badge>
                );
            case 'summative_scope':
                return (
                    <Badge
                        variant="secondary"
                        className="bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                    >
                        Sumatif Lingkup Materi
                    </Badge>
                );
            case 'summative_final':
                return (
                    <Badge
                        variant="default"
                        className="bg-emerald-600 text-white"
                    >
                        Sumatif Akhir
                    </Badge>
                );
        }
    };

    return (
        <>
            <Head
                title={`Nilai Ananda ${student.name} - Semester ${semester.name}`}
            />

            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Link
                                href="/dashboard"
                                className="flex items-center gap-1 hover:underline"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Dashboard
                            </Link>
                            <span>•</span>
                            <span className="flex items-center gap-1">
                                <GraduationCap className="h-4 w-4" />
                                {school_class
                                    ? `Kelas ${school_class.name}`
                                    : 'Siswa'}
                            </span>
                        </div>
                        <Heading
                            title={`Nilai Asesmen: ${student.name}`}
                            description={`Hasil asesmen yang telah dirilis oleh guru untuk semester ${semester.name}`}
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

                {/* Information Note */}
                <div className="bg-muted/30 text-muted-foreground flex items-start gap-2.5 rounded-lg border p-3 text-xs">
                    <Info className="text-primary h-4 w-4 shrink-0" />
                    <p>
                        Halaman ini menampilkan nilai dari tugas dan asesmen
                        yang telah diperiksa dan dirilis oleh guru mata
                        pelajaran. Nilai akhir rapor semester akan tersedia
                        secara resmi pada saat pembagian rapor digital.
                    </p>
                </div>

                {/* Subjects List */}
                {subjects.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center">
                        Belum ada mata pelajaran terdaftar untuk semester ini.
                    </div>
                ) : (
                    <div className="space-y-6">
                        {subjects.map((sub) => (
                            <Card key={sub.id} className="overflow-hidden">
                                <CardHeader className="bg-muted/30 border-b pb-3">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <CardTitle className="text-base font-semibold">
                                                {sub.name}
                                            </CardTitle>
                                            <CardDescription className="flex items-center gap-2 text-xs">
                                                {sub.code && (
                                                    <span>{sub.code}</span>
                                                )}
                                                {sub.teacher_name && (
                                                    <span className="flex items-center gap-1">
                                                        <UserRound className="h-3 w-3" />
                                                        Guru: {sub.teacher_name}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className="w-fit text-xs font-normal"
                                        >
                                            KKTP:{' '}
                                            <strong className="text-foreground ml-1">
                                                {sub.passing_threshold}
                                            </strong>
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-0">
                                    {sub.assessments.length === 0 ? (
                                        <div className="text-muted-foreground p-6 text-center text-xs italic">
                                            Belum ada nilai asesmen yang dirilis
                                            oleh guru pada mata pelajaran ini.
                                        </div>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead className="bg-muted/20 text-muted-foreground text-xs font-medium">
                                                    <tr className="border-b">
                                                        <th className="px-4 py-2.5">
                                                            Nama Asesmen
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Jenis
                                                        </th>
                                                        <th className="px-3 py-2.5">
                                                            Tanggal
                                                        </th>
                                                        <th className="px-3 py-2.5 text-center">
                                                            Nilai
                                                        </th>
                                                        <th className="px-3 py-2.5 text-center">
                                                            Remedial
                                                        </th>
                                                        <th className="px-4 py-2.5">
                                                            Catatan Guru
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y text-xs">
                                                    {sub.assessments.map(
                                                        (assessment) => {
                                                            const isPassing =
                                                                assessment.score !==
                                                                    null &&
                                                                assessment.score >=
                                                                    sub.passing_threshold;

                                                            return (
                                                                <tr
                                                                    key={
                                                                        assessment.id
                                                                    }
                                                                    className="hover:bg-muted/40"
                                                                >
                                                                    <td className="text-foreground px-4 py-3 font-medium">
                                                                        {
                                                                            assessment.name
                                                                        }
                                                                    </td>
                                                                    <td className="px-3 py-3">
                                                                        {renderKindBadge(
                                                                            assessment.kind,
                                                                        )}
                                                                    </td>
                                                                    <td className="text-muted-foreground px-3 py-3 font-mono">
                                                                        {
                                                                            assessment.assessed_on
                                                                        }
                                                                    </td>
                                                                    <td className="px-3 py-3 text-center">
                                                                        {assessment.score !==
                                                                        null ? (
                                                                            <span
                                                                                className={`font-mono text-sm font-bold ${
                                                                                    isPassing
                                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                                        : 'text-destructive'
                                                                                }`}
                                                                            >
                                                                                {
                                                                                    assessment.score
                                                                                }
                                                                                <span className="text-muted-foreground text-[10px] font-normal">
                                                                                    /
                                                                                    {
                                                                                        assessment.max_score
                                                                                    }
                                                                                </span>
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-muted-foreground italic">
                                                                                -
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-3 text-center font-mono">
                                                                        {assessment.remedial_score !==
                                                                        null ? (
                                                                            <span className="text-foreground font-semibold">
                                                                                {
                                                                                    assessment.remedial_score
                                                                                }
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-muted-foreground">
                                                                                -
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        {assessment.notes ? (
                                                                            <div className="text-foreground flex items-center gap-1.5 italic">
                                                                                <MessageSquare className="text-muted-foreground h-3 w-3 shrink-0" />
                                                                                <span>
                                                                                    "
                                                                                    {
                                                                                        assessment.notes
                                                                                    }
                                                                                    "
                                                                                </span>
                                                                            </div>
                                                                        ) : (
                                                                            <span className="text-muted-foreground">
                                                                                -
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            );
                                                        },
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ChildGrades.layout = {
    breadcrumbs: [
        {
            title: 'Nilai Siswa',
            href: '/dashboard',
        },
    ],
};
