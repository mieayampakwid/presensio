import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    Calendar,
    Clock,
    Save,
    Sliders,
    Info,
    Check,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useGridNavigation } from '@/hooks/use-grid-navigation';

type AssessmentItem = {
    id: number;
    name: string;
    kind: 'formative' | 'summative_scope' | 'summative_final';
    assessed_on: string;
    max_score: number;
    released_at: string | null;
    sort_order: number;
    learning_objectives: Array<{
        id: number;
        code: string;
        description: string;
    }>;
};

type CellData = {
    score: number | null;
    remedial: number | null;
    notes: string | null;
};

type StudentRow = {
    id: number;
    name: string;
    student_number: string;
    gender?: string;
    cells: Record<number, CellData>;
    live_score: number | null;
    status: string;
    complete: boolean;
};

type SemesterItem = {
    id: number;
    name: string;
    number: number;
    starts_at: string;
    ends_at: string;
};

type CourseInfo = {
    id: number;
    class_name: string;
    grade_level: number;
    subject_name: string;
    subject_code: string;
    teacher_name?: string;
    passing_threshold: string;
};

type Props = {
    course: CourseInfo;
    semester: SemesterItem;
    semesters: SemesterItem[];
    is_finalized: boolean;
    weights: { scope: number; final: number };
    assessments: AssessmentItem[];
    students: StudentRow[];
    canWrite: boolean;
};

export default function GradebookShow({
    course,
    semester,
    semesters,
    is_finalized,
    weights,
    assessments,
    students,
    canWrite,
}: Props) {
    const threshold = parseFloat(course.passing_threshold) || 75;

    // Local score edit state: studentId -> assessmentId -> { score, remedial, notes }
    const [localCells, setLocalCells] = useState<
        Record<number, Record<number, CellData>>
    >(() => {
        const initial: Record<number, Record<number, CellData>> = {};
        for (const student of students) {
            initial[student.id] = {};
            for (const [assessmentId, cell] of Object.entries(student.cells)) {
                initial[student.id][parseInt(assessmentId, 10)] = { ...cell };
            }
        }
        return initial;
    });

    const [isSaving, setIsSaving] = useState(false);
    const [isWeightsOpen, setIsWeightsOpen] = useState(false);
    const [weightScope, setWeightScope] = useState(weights.scope.toString());
    const [weightFinal, setWeightFinal] = useState(weights.final.toString());
    const [saveMessage, setSaveMessage] = useState<string | null>(null);

    // Group assessments by kind
    const groupedAssessments = useMemo(() => {
        return {
            formative: assessments.filter((a) => a.kind === 'formative'),
            summativeScope: assessments.filter(
                (a) => a.kind === 'summative_scope',
            ),
            summativeFinal: assessments.filter(
                (a) => a.kind === 'summative_final',
            ),
        };
    }, [assessments]);

    const orderedAssessments = useMemo(() => {
        return [
            ...groupedAssessments.summativeScope,
            ...groupedAssessments.summativeFinal,
            ...groupedAssessments.formative,
        ];
    }, [groupedAssessments]);

    // Grid navigation hook
    const { handleKeyDown } = useGridNavigation(
        students.length,
        orderedAssessments.length * 2,
    );

    const handleSemesterChange = (semesterId: string) => {
        router.get(
            `/courses/${course.id}/gradebook`,
            { semester: semesterId },
            { preserveState: false },
        );
    };

    const handleCellChange = (
        studentId: number,
        assessmentId: number,
        field: 'score' | 'remedial' | 'notes',
        value: string,
    ) => {
        setLocalCells((prev) => {
            const studentMap = prev[studentId] ? { ...prev[studentId] } : {};
            const existingCell = studentMap[assessmentId] || {
                score: null,
                remedial: null,
                notes: null,
            };

            let parsed: number | null = null;
            if (field === 'notes') {
                existingCell.notes = value || null;
            } else {
                parsed = value === '' ? null : parseFloat(value);
                if (field === 'score') {
                    existingCell.score = isNaN(parsed as number)
                        ? null
                        : parsed;
                } else if (field === 'remedial') {
                    existingCell.remedial = isNaN(parsed as number)
                        ? null
                        : parsed;
                }
            }

            studentMap[assessmentId] = existingCell;
            return { ...prev, [studentId]: studentMap };
        });
    };

    const handleSaveBatch = () => {
        setIsSaving(true);
        setSaveMessage(null);

        const rows: Array<{
            student_id: number;
            assessment_id: number;
            score: number | null;
            remedial_score: number | null;
            notes: string | null;
        }> = [];

        for (const [studentIdStr, cellMap] of Object.entries(localCells)) {
            const studentId = parseInt(studentIdStr, 10);
            for (const [assessmentIdStr, cell] of Object.entries(cellMap)) {
                rows.push({
                    student_id: studentId,
                    assessment_id: parseInt(assessmentIdStr, 10),
                    score: cell.score,
                    remedial_score: cell.remedial,
                    notes: cell.notes,
                });
            }
        }

        router.post(
            `/courses/${course.id}/scores`,
            { scores: rows },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                    setSaveMessage('Nilai berhasil disimpan.');
                },
                onError: (err) => {
                    setIsSaving(false);
                    const msg = Object.values(err)[0] as string;
                    setSaveMessage(msg || 'Gagal menyimpan nilai.');
                },
            },
        );
    };

    const handleSaveWeights = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(
            `/courses/${course.id}/weights`,
            {
                semester_id: semester.id,
                scope_weight: parseFloat(weightScope),
                final_weight: parseFloat(weightFinal),
            },
            {
                onSuccess: () => {
                    setIsWeightsOpen(false);
                },
            },
        );
    };

    const handleReleaseAssessment = (assessmentId: number) => {
        if (!confirm('Rilis nilai asesmen ini ke siswa dan orang tua?')) return;

        router.post(
            `/courses/${course.id}/assessments/${assessmentId}/release`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head
                title={`Buku Nilai - ${course.subject_name} (${course.class_name})`}
            />

            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Link
                                href="/courses"
                                className="flex items-center gap-1 hover:underline"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali
                            </Link>
                            <span>•</span>
                            <Link
                                href={`/courses/${course.id}/assessments?semester=${semester.id}`}
                                className="hover:underline"
                            >
                                Kelola Asesmen
                            </Link>
                            <span>•</span>
                            <Link
                                href={`/courses/${course.id}/results?semester=${semester.id}`}
                                className="hover:underline"
                            >
                                Capaian Rapor
                            </Link>
                            <span>•</span>
                            <span>{course.class_name}</span>
                            <span>•</span>
                            <span>KKTP: {course.passing_threshold}</span>
                            <span>•</span>
                            <span>
                                Bobot: Lingkup {weights.scope}% / Akhir{' '}
                                {weights.final}%
                            </span>
                        </div>
                        <Heading
                            title={`Buku Nilai: ${course.subject_name}`}
                            description={`Grid penilaian Kurikulum Merdeka semester ${semester.name}`}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={`/courses/${course.id}/results?semester=${semester.id}`}
                            >
                                <Award className="mr-1.5 h-4 w-4" />
                                <span>Capaian Rapor</span>
                            </Link>
                        </Button>
                        {canWrite && !is_finalized && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsWeightsOpen(true)}
                                >
                                    <Sliders className="mr-2 h-4 w-4" />
                                    Atur Bobot
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSaveBatch}
                                    disabled={isSaving}
                                >
                                    <Save className="mr-2 h-4 w-4" />
                                    {isSaving
                                        ? 'Menyimpan...'
                                        : 'Simpan Semua Nilai'}
                                </Button>
                            </>
                        )}
                        {is_finalized && (
                            <Badge
                                variant="secondary"
                                className="bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                            >
                                Sudah Difinalisasi (Terkunci)
                            </Badge>
                        )}
                    </div>
                </div>

                {saveMessage && (
                    <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-300">
                        <Info className="h-4 w-4" />
                        <p>{saveMessage}</p>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-auto"
                            onClick={() => setSaveMessage(null)}
                        >
                            Tutup
                        </Button>
                    </div>
                )}

                {/* Filter and stats banner */}
                <div className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Calendar className="text-muted-foreground h-5 w-5" />
                        <div>
                            <p className="text-sm font-medium">
                                Semester {semester.name}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {students.length} Siswa • {assessments.length}{' '}
                                Asesmen
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="w-48">
                            <Select
                                value={String(semester.id)}
                                onValueChange={handleSemesterChange}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((s) => (
                                        <SelectItem
                                            key={s.id}
                                            value={String(s.id)}
                                        >
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* Spreadsheet Gradebook Grid */}
                {orderedAssessments.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center">
                        Belum ada asesmen untuk semester ini. Tambahkan asesmen
                        terlebih dahulu di menu{' '}
                        <Link
                            href={`/courses/${course.id}/assessments?semester=${semester.id}`}
                            className="text-primary font-semibold underline"
                        >
                            Kelola Asesmen
                        </Link>
                        .
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead className="bg-muted/70">
                                {/* Top header: Groups */}
                                <tr className="border-b">
                                    <th
                                        className="bg-muted/90 sticky left-0 z-20 border-r px-3 py-2 font-semibold"
                                        colSpan={2}
                                    >
                                        Data Siswa
                                    </th>

                                    {groupedAssessments.summativeScope.length >
                                        0 && (
                                        <th
                                            className="border-r bg-purple-50/50 px-3 py-2 text-center font-semibold text-purple-700 dark:bg-purple-950/20 dark:text-purple-300"
                                            colSpan={
                                                groupedAssessments
                                                    .summativeScope.length * 2
                                            }
                                        >
                                            Sumatif Lingkup Materi (
                                            {weights.scope}%)
                                        </th>
                                    )}

                                    {groupedAssessments.summativeFinal.length >
                                        0 && (
                                        <th
                                            className="border-r bg-emerald-50/50 px-3 py-2 text-center font-semibold text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300"
                                            colSpan={
                                                groupedAssessments
                                                    .summativeFinal.length * 2
                                            }
                                        >
                                            Sumatif Akhir Semester (
                                            {weights.final}%)
                                        </th>
                                    )}

                                    {groupedAssessments.formative.length >
                                        0 && (
                                        <th
                                            className="border-r bg-blue-50/50 px-3 py-2 text-center font-semibold text-blue-700 dark:bg-blue-950/20 dark:text-blue-300"
                                            colSpan={
                                                groupedAssessments.formative
                                                    .length
                                            }
                                        >
                                            Formatif (Umpan Balik)
                                        </th>
                                    )}

                                    <th
                                        className="px-3 py-2 text-center font-semibold"
                                        colSpan={3}
                                    >
                                        Ringkasan Nilai
                                    </th>
                                </tr>

                                {/* Second header: Individual assessments */}
                                <tr className="text-muted-foreground border-b">
                                    <th className="bg-muted/90 sticky left-0 z-20 w-12 border-r px-3 py-2 font-medium">
                                        NIS
                                    </th>
                                    <th className="bg-muted/90 sticky left-12 z-20 min-w-[160px] border-r px-3 py-2 font-medium">
                                        Nama Lengkap
                                    </th>

                                    {orderedAssessments.map((a) => {
                                        const isSummative =
                                            a.kind !== 'formative';
                                        return isSummative ? (
                                            <th
                                                key={a.id}
                                                colSpan={2}
                                                className="min-w-[140px] border-r px-2 py-2"
                                            >
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="text-foreground truncate font-semibold">
                                                        {a.name}
                                                    </span>
                                                    <div className="flex items-center justify-between text-[10px]">
                                                        <span>
                                                            Maks: {a.max_score}
                                                        </span>
                                                        {a.released_at ? (
                                                            <span className="font-medium text-emerald-600">
                                                                Dirilis
                                                            </span>
                                                        ) : (
                                                            canWrite && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        handleReleaseAssessment(
                                                                            a.id,
                                                                        )
                                                                    }
                                                                    className="text-primary font-medium hover:underline"
                                                                >
                                                                    Rilis
                                                                </button>
                                                            )
                                                        )}
                                                    </div>
                                                </div>
                                            </th>
                                        ) : (
                                            <th
                                                key={a.id}
                                                className="min-w-[70px] border-r px-2 py-2"
                                            >
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="text-foreground truncate font-semibold">
                                                        {a.name}
                                                    </span>
                                                    <span className="text-[10px]">
                                                        Maks: {a.max_score}
                                                    </span>
                                                </div>
                                            </th>
                                        );
                                    })}

                                    <th className="min-w-[70px] border-r px-2 py-2 text-center font-medium">
                                        Nilai Rapor
                                    </th>
                                    <th className="min-w-[80px] border-r px-2 py-2 text-center font-medium">
                                        Status
                                    </th>
                                    <th className="min-w-[80px] px-2 py-2 text-center font-medium">
                                        Kelengkapan
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {students.map((student, rowIndex) => {
                                    let colIndex = 0;
                                    return (
                                        <tr
                                            key={student.id}
                                            className="hover:bg-muted/40"
                                        >
                                            <td className="bg-background sticky left-0 z-10 border-r px-3 py-1.5 font-mono text-[11px]">
                                                {student.student_number}
                                            </td>
                                            <td className="bg-background sticky left-12 z-10 max-w-[200px] truncate border-r px-3 py-1.5 font-medium">
                                                {student.name}
                                            </td>

                                            {orderedAssessments.map((a) => {
                                                const isEligible =
                                                    a.id in student.cells;
                                                const cellData = localCells[
                                                    student.id
                                                ]?.[a.id] || {
                                                    score: null,
                                                    remedial: null,
                                                    notes: null,
                                                };
                                                const scoreVal = cellData.score;
                                                const showRemedial =
                                                    isEligible &&
                                                    a.kind !== 'formative' &&
                                                    scoreVal !== null &&
                                                    scoreVal < threshold;

                                                const colIdxCurrent = colIndex;
                                                colIndex +=
                                                    a.kind !== 'formative'
                                                        ? 2
                                                        : 1;

                                                if (!isEligible) {
                                                    return (
                                                        <td
                                                            key={a.id}
                                                            colSpan={
                                                                a.kind !==
                                                                'formative'
                                                                    ? 2
                                                                    : 1
                                                            }
                                                            className="text-muted-foreground/50 bg-muted/20 border-r px-2 py-1.5 text-center"
                                                        >
                                                            -
                                                        </td>
                                                    );
                                                }

                                                if (a.kind === 'formative') {
                                                    return (
                                                        <td
                                                            key={a.id}
                                                            className="border-r p-1"
                                                        >
                                                            <Input
                                                                type="number"
                                                                step="any"
                                                                data-row={
                                                                    rowIndex
                                                                }
                                                                data-col={
                                                                    colIdxCurrent
                                                                }
                                                                onKeyDown={
                                                                    handleKeyDown
                                                                }
                                                                disabled={
                                                                    !canWrite ||
                                                                    is_finalized
                                                                }
                                                                className="h-7 px-1 text-center text-xs"
                                                                value={
                                                                    cellData.score !==
                                                                    null
                                                                        ? cellData.score
                                                                        : ''
                                                                }
                                                                onChange={(e) =>
                                                                    handleCellChange(
                                                                        student.id,
                                                                        a.id,
                                                                        'score',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    );
                                                }

                                                return (
                                                    <td
                                                        key={a.id}
                                                        colSpan={2}
                                                        className="border-r p-1"
                                                    >
                                                        <div className="flex items-center gap-1">
                                                            <Input
                                                                type="number"
                                                                step="any"
                                                                data-row={
                                                                    rowIndex
                                                                }
                                                                data-col={
                                                                    colIdxCurrent
                                                                }
                                                                onKeyDown={
                                                                    handleKeyDown
                                                                }
                                                                disabled={
                                                                    !canWrite ||
                                                                    is_finalized
                                                                }
                                                                className="h-7 flex-1 px-1 text-center text-xs"
                                                                placeholder="Nilai"
                                                                value={
                                                                    cellData.score !==
                                                                    null
                                                                        ? cellData.score
                                                                        : ''
                                                                }
                                                                onChange={(e) =>
                                                                    handleCellChange(
                                                                        student.id,
                                                                        a.id,
                                                                        'score',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            {showRemedial && (
                                                                <Input
                                                                    type="number"
                                                                    step="any"
                                                                    data-row={
                                                                        rowIndex
                                                                    }
                                                                    data-col={
                                                                        colIdxCurrent +
                                                                        1
                                                                    }
                                                                    onKeyDown={
                                                                        handleKeyDown
                                                                    }
                                                                    disabled={
                                                                        !canWrite ||
                                                                        is_finalized
                                                                    }
                                                                    className="h-7 w-14 border-amber-300 bg-amber-50/50 px-1 text-center text-xs dark:border-amber-800 dark:bg-amber-950/20"
                                                                    placeholder="Remedial"
                                                                    value={
                                                                        cellData.remedial !==
                                                                        null
                                                                            ? cellData.remedial
                                                                            : ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleCellChange(
                                                                            student.id,
                                                                            a.id,
                                                                            'remedial',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        </div>
                                                    </td>
                                                );
                                            })}

                                            {/* Summary */}
                                            <td className="border-r px-2 py-1.5 text-center text-sm font-bold">
                                                {student.live_score !== null
                                                    ? student.live_score
                                                    : '-'}
                                            </td>
                                            <td className="border-r px-2 py-1.5 text-center">
                                                <Badge
                                                    variant={
                                                        student.status ===
                                                        'Tuntas'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className={
                                                        student.status ===
                                                        'Tuntas'
                                                            ? 'bg-emerald-600 text-white'
                                                            : ''
                                                    }
                                                >
                                                    {student.status}
                                                </Badge>
                                            </td>
                                            <td className="px-2 py-1.5 text-center">
                                                {student.complete ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-500 text-emerald-600"
                                                    >
                                                        <Check className="mr-1 h-3 w-3" />{' '}
                                                        Lengkap
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-400 text-amber-600"
                                                    >
                                                        <Clock className="mr-1 h-3 w-3" />{' '}
                                                        Belum
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Weights Dialog */}
            <Dialog open={isWeightsOpen} onOpenChange={setIsWeightsOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Atur Bobot Penilaian Semester</DialogTitle>
                        <DialogDescription>
                            Tentukan proporsi nilai sumatif lingkup materi dan
                            sumatif akhir semester.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSaveWeights} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="weight-scope">
                                Bobot Sumatif Lingkup Materi (%)
                            </Label>
                            <Input
                                id="weight-scope"
                                type="number"
                                step="1"
                                min="0"
                                value={weightScope}
                                onChange={(e) => setWeightScope(e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="weight-final">
                                Bobot Sumatif Akhir Semester (%)
                            </Label>
                            <Input
                                id="weight-final"
                                type="number"
                                step="1"
                                min="0"
                                value={weightFinal}
                                onChange={(e) => setWeightFinal(e.target.value)}
                                required
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsWeightsOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit">Simpan Bobot</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

GradebookShow.layout = {
    breadcrumbs: [
        {
            title: 'Buku Nilai',
            href: '/courses',
        },
    ],
};
