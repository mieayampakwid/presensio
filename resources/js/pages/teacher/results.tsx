import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    Edit2,
    FileSpreadsheet,
    Lock,
    RotateCcw,
    Save,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type LearningObjectiveItem = {
    id: number;
    code: string;
    description: string;
};

type StudentRow = {
    id: number;
    name: string;
    student_number: string | null;
    gender?: string;
    live_score: number | null;
    final_score: number | null;
    tp_results: Record<string | number, number>;
    draft_description: string;
    description: string;
    description_edited: boolean;
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
    name: string;
    code: string;
    class_id: number;
    class_name: string;
    passing_threshold: number;
};

type Props = {
    course: CourseInfo;
    semester: SemesterItem;
    semesters: SemesterItem[];
    is_finalized: boolean;
    finalized_at: string | null;
    learning_objectives: LearningObjectiveItem[];
    students: StudentRow[];
    canWrite: boolean;
};

export default function CourseResults({
    course,
    semester,
    semesters,
    is_finalized,
    finalized_at: _finalizedAt,
    learning_objectives: _learningObjectives,
    students,
    canWrite,
}: Props) {
    const [selectedStudent, setSelectedStudent] = useState<StudentRow | null>(
        null,
    );
    const [editedText, setEditedText] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const handleOpenEdit = (student: StudentRow) => {
        setSelectedStudent(student);
        setEditedText(student.description);
        setErrorMessage(null);
    };

    const handleSaveDescription = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!selectedStudent) return;

        setIsSubmitting(true);
        setErrorMessage(null);

        router.put(
            `/courses/${course.id}/results/${selectedStudent.id}`,
            {
                semester_id: semester.id,
                description: editedText,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedStudent(null);
                    setIsSubmitting(false);
                },
                onError: (err) => {
                    setIsSubmitting(false);
                    const msg = Object.values(err)[0] as string;
                    setErrorMessage(msg || 'Gagal menyimpan deskripsi.');
                },
            },
        );
    };

    const handleResetToDraft = () => {
        if (!selectedStudent) return;
        setEditedText(selectedStudent.draft_description);
    };

    const handleSemesterChange = (newSemesterId: string) => {
        router.get(
            `/courses/${course.id}/results`,
            { semester: newSemesterId },
            { preserveState: false },
        );
    };

    return (
        <>
            <Head
                title={`Hasil & Capaian - ${course.name} (${course.class_name})`}
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
                                href={`/courses/${course.id}/gradebook?semester=${semester.id}`}
                                className="hover:underline"
                            >
                                Buku Nilai
                            </Link>
                            <span>•</span>
                            <Link
                                href={`/courses/${course.id}/assessments?semester=${semester.id}`}
                                className="hover:underline"
                            >
                                Kelola Asesmen
                            </Link>
                            <span>•</span>
                            <span>{course.class_name}</span>
                            <span>•</span>
                            <span>KKTP: {course.passing_threshold}</span>
                        </div>
                        <div className="mt-1 flex items-center gap-3">
                            <Heading
                                title={`Hasil & Capaian: ${course.name}`}
                                description={`Deskripsi capaian kompetensi siswa untuk semester ${semester.name}`}
                            />
                            {is_finalized ? (
                                <Badge
                                    variant="default"
                                    className="bg-emerald-600 text-white"
                                >
                                    <Lock className="mr-1 h-3 w-3" />
                                    Difinalisasi
                                </Badge>
                            ) : (
                                <Badge variant="outline">Terbuka (Draf)</Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Semester Selector */}
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

                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={`/courses/${course.id}/gradebook?semester=${semester.id}`}
                            >
                                <FileSpreadsheet className="mr-1.5 h-4 w-4" />
                                Buku Nilai
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Finalized Banner if applicable */}
                {is_finalized && (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-50/50 p-4 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-300">
                        <Lock className="h-5 w-5 shrink-0" />
                        <div>
                            <div className="font-medium">
                                Mata pelajaran ini telah difinalisasi
                            </div>
                            <div className="text-sm opacity-90">
                                Nilai akhir dan deskripsi capaian telah
                                dibekukan dan menjadi data acuan rapor.
                            </div>
                        </div>
                    </div>
                )}

                {/* Students Results Table */}
                <div className="rounded-md border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted text-muted-foreground font-medium">
                            <tr className="border-b">
                                <th className="w-12 px-4 py-3">No</th>
                                <th className="min-w-[180px] px-4 py-3">
                                    Siswa
                                </th>
                                <th className="w-28 px-4 py-3 text-center">
                                    Nilai Akhir
                                </th>
                                <th className="w-28 px-4 py-3 text-center">
                                    Status
                                </th>
                                <th className="px-4 py-3">
                                    Deskripsi Capaian Kompetensi
                                </th>
                                {canWrite && !is_finalized && (
                                    <th className="w-24 px-4 py-3 text-right">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {students.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Tidak ada siswa terdaftar dalam kelas
                                        ini pada semester yang dipilih.
                                    </td>
                                </tr>
                            ) : (
                                students.map((student, idx) => {
                                    const effectiveScore =
                                        student.final_score ??
                                        student.live_score;
                                    const isPassing =
                                        effectiveScore !== null &&
                                        effectiveScore >=
                                            course.passing_threshold;

                                    return (
                                        <tr
                                            key={student.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <td className="text-muted-foreground px-4 py-3 font-mono">
                                                {idx + 1}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {student.name}
                                                </div>
                                                <div className="text-muted-foreground font-mono text-xs">
                                                    {student.student_number ||
                                                        '-'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {effectiveScore !== null ? (
                                                    <span className="font-mono text-base font-bold">
                                                        {effectiveScore}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs italic">
                                                        Belum ada
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {effectiveScore !== null ? (
                                                    <Badge
                                                        variant={
                                                            isPassing
                                                                ? 'default'
                                                                : 'destructive'
                                                        }
                                                        className={
                                                            isPassing
                                                                ? 'bg-emerald-600 text-white'
                                                                : ''
                                                        }
                                                    >
                                                        {isPassing
                                                            ? 'Tuntas'
                                                            : 'Belum Tuntas'}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        -
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="space-y-1.5">
                                                    <p className="text-foreground leading-relaxed">
                                                        {student.description || (
                                                            <span className="text-muted-foreground italic">
                                                                Belum ada
                                                                deskripsi
                                                                (memerlukan data
                                                                asesmen sumatif
                                                                lingkup materi).
                                                            </span>
                                                        )}
                                                    </p>
                                                    <div className="flex items-center gap-2">
                                                        {student.description_edited ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="text-[11px]"
                                                            >
                                                                Disesuaikan Guru
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-muted-foreground text-[11px]"
                                                            >
                                                                <Sparkles className="mr-1 h-3 w-3" />
                                                                Otomatis
                                                                (Sistem)
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            {canWrite && !is_finalized && (
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleOpenEdit(
                                                                student,
                                                            )
                                                        }
                                                    >
                                                        <Edit2 className="mr-1 h-3.5 w-3.5" />
                                                        Edit
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Edit Description Dialog */}
            <Dialog
                open={selectedStudent !== null}
                onOpenChange={(open) => !open && setSelectedStudent(null)}
            >
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Edit Deskripsi Capaian</DialogTitle>
                        <DialogDescription>
                            Sesuaikan catatan deskripsi capaian kompetensi untuk{' '}
                            <strong>{selectedStudent?.name}</strong>.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={handleSaveDescription}
                        className="space-y-4"
                    >
                        {errorMessage && (
                            <div className="bg-destructive/15 text-destructive rounded-md p-3 text-sm">
                                {errorMessage}
                            </div>
                        )}

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label
                                    htmlFor="description-textarea"
                                    className="text-sm font-medium"
                                >
                                    Teks Deskripsi
                                </label>
                                <span className="text-muted-foreground text-xs">
                                    {editedText.length}/2000 karakter
                                </span>
                            </div>
                            <textarea
                                id="description-textarea"
                                value={editedText}
                                onChange={(e) => setEditedText(e.target.value)}
                                rows={5}
                                maxLength={2000}
                                className="border-input placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:outline-none"
                                placeholder="Tuliskan deskripsi capaian kompetensi..."
                                required
                            />
                        </div>

                        {selectedStudent?.draft_description && (
                            <div className="bg-muted/50 rounded-md border p-3 text-xs">
                                <div className="text-muted-foreground mb-1 font-medium">
                                    Draf Otomatis Sistem:
                                </div>
                                <p className="text-foreground italic">
                                    "{selectedStudent.draft_description}"
                                </p>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    onClick={handleResetToDraft}
                                    className="mt-1 h-auto p-0 text-xs"
                                >
                                    <RotateCcw className="mr-1 h-3 w-3" />
                                    Gunakan teks draf ini
                                </Button>
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedStudent(null)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={isSubmitting}>
                                <Save className="mr-1.5 h-4 w-4" />
                                Simpan Deskripsi
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

CourseResults.layout = {
    breadcrumbs: [
        {
            title: 'Hasil & Capaian',
            href: '/courses',
        },
    ],
};
