import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Plus,
    Trash2,
    Edit2,
    BookOpen,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Objective = {
    id: number;
    code: string;
    description: string;
};

type AssessmentItem = {
    id: number;
    class_subject_id: number;
    semester_id: number;
    name: string;
    kind: 'formative' | 'summative_scope' | 'summative_final';
    assessed_on: string;
    max_score: string;
    released_at: string | null;
    sort_order: number;
    scores_count: number;
    learning_objectives: Objective[];
};

type SemesterItem = {
    id: number;
    number: number;
    name: string;
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
    currentSemester: SemesterItem;
    semesters: SemesterItem[];
    assessments: AssessmentItem[];
    objectives: Objective[];
    canWrite: boolean;
};

export default function AssessmentsIndex({
    course,
    currentSemester,
    semesters,
    assessments,
    objectives,
    canWrite,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingAssessment, setEditingAssessment] =
        useState<AssessmentItem | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const createForm = useForm({
        semester_id: currentSemester.id,
        name: '',
        kind: 'summative_scope',
        assessed_on: '',
        max_score: '100',
        sort_order: 0,
        objective_ids: [] as number[],
    });

    const editForm = useForm({
        name: '',
        kind: 'summative_scope',
        assessed_on: '',
        max_score: '100',
        sort_order: 0,
        objective_ids: [] as number[],
    });

    const handleSemesterChange = (semesterId: string) => {
        router.get(
            `/courses/${course.id}/assessments`,
            { semester: semesterId },
            { preserveState: true },
        );
    };

    const handleCreateSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        createForm.post(`/courses/${course.id}/assessments`, {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset(
                    'name',
                    'assessed_on',
                    'sort_order',
                    'objective_ids',
                );
            },
            onError: (err) => {
                const first = Object.values(err)[0] as string;
                setErrorMessage(first || 'Gagal menyimpan asesmen.');
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!editingAssessment) return;

        editForm.put(
            `/courses/${course.id}/assessments/${editingAssessment.id}`,
            {
                onSuccess: () => {
                    setEditingAssessment(null);
                },
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    setErrorMessage(first || 'Gagal memperbarui asesmen.');
                },
            },
        );
    };

    const handleDelete = (assessment: AssessmentItem) => {
        let confirmFlag = false;
        if (assessment.scores_count > 0) {
            if (
                !confirm(
                    `Asesmen ini sudah memiliki ${assessment.scores_count} nilai tersimpan. Tetap hapus seluruh nilai dan asesmen?`,
                )
            ) {
                return;
            }
            confirmFlag = true;
        } else {
            if (!confirm(`Hapus asesmen "${assessment.name}"?`)) return;
        }

        router.delete(`/courses/${course.id}/assessments/${assessment.id}`, {
            data: { confirm: confirmFlag },
            onError: (errors) => {
                const msg = Object.values(errors)[0] as string;
                setErrorMessage(msg || 'Gagal menghapus asesmen.');
            },
        });
    };

    const toggleCreateObjective = (objectiveId: number) => {
        const current = [...createForm.data.objective_ids];
        const idx = current.indexOf(objectiveId);
        if (idx > -1) {
            current.splice(idx, 1);
        } else {
            current.push(objectiveId);
        }
        createForm.setData('objective_ids', current);
    };

    const toggleEditObjective = (objectiveId: number) => {
        const current = [...editForm.data.objective_ids];
        const idx = current.indexOf(objectiveId);
        if (idx > -1) {
            current.splice(idx, 1);
        } else {
            current.push(objectiveId);
        }
        editForm.setData('objective_ids', current);
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
                title={`Asesmen - ${course.subject_name} (${course.class_name})`}
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
                                Kembali ke Kursus
                            </Link>
                            <span>•</span>
                            <span>{course.class_name}</span>
                            <span>•</span>
                            <span>KKTP: {course.passing_threshold}</span>
                        </div>
                        <Heading
                            title={`Daftar Asesmen: ${course.subject_name}`}
                            description={`Kelola asesmen formatif dan sumatif semester ${currentSemester.name}`}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={`/courses/${course.id}/gradebook?semester=${currentSemester.id}`}
                        >
                            <Button variant="outline">
                                <FileSpreadsheet className="mr-2 h-4 w-4" />
                                Buku Nilai
                            </Button>
                        </Link>
                        {canWrite && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Asesmen
                            </Button>
                        )}
                    </div>
                </div>

                {errorMessage && (
                    <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-300">
                        <AlertCircle className="h-5 w-5 shrink-0" />
                        <p>{errorMessage}</p>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-auto"
                            onClick={() => setErrorMessage(null)}
                        >
                            Tutup
                        </Button>
                    </div>
                )}

                {/* Semester filter */}
                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-3">
                        <Calendar className="text-muted-foreground h-5 w-5" />
                        <div>
                            <p className="text-sm font-medium">
                                Semester Aktif
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {currentSemester.starts_at} s.d.{' '}
                                {currentSemester.ends_at}
                            </p>
                        </div>
                    </div>
                    <div className="w-48">
                        <Select
                            value={String(currentSemester.id)}
                            onValueChange={handleSemesterChange}
                        >
                            <SelectTrigger>
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

                {/* Table */}
                {assessments.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <BookOpen className="text-muted-foreground/60 mx-auto h-12 w-12" />
                        <h3 className="mt-4 text-base font-semibold">
                            Belum Ada Asesmen
                        </h3>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {canWrite
                                ? 'Tambahkan asesmen baru untuk mencatat nilai formatif atau sumatif pada semester ini.'
                                : 'Belum ada asesmen yang dibuat untuk semester ini.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground border-b text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Urutan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Nama Asesmen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tanggal
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Skor Maks
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tujuan Pembelajaran
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status Rilis
                                    </th>
                                    {canWrite && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {assessments.map((a) => (
                                    <tr
                                        key={a.id}
                                        className="hover:bg-muted/50"
                                    >
                                        <td className="text-muted-foreground px-4 py-3">
                                            {a.sort_order}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {a.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {renderKindBadge(a.kind)}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {a.assessed_on}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.max_score}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.learning_objectives.length ===
                                            0 ? (
                                                <span className="text-muted-foreground text-xs">
                                                    -
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {a.learning_objectives.map(
                                                        (tp) => (
                                                            <Badge
                                                                key={tp.id}
                                                                variant="outline"
                                                                className="text-xs"
                                                            >
                                                                {tp.code}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.released_at ? (
                                                <div className="flex items-center gap-1 text-xs text-emerald-600">
                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                    <span>Dirilis</span>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-1 text-xs text-amber-600">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    <span>Draf</span>
                                                </div>
                                            )}
                                        </td>
                                        {canWrite && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditingAssessment(
                                                                a,
                                                            );
                                                            editForm.setData({
                                                                name: a.name,
                                                                kind: a.kind,
                                                                assessed_on:
                                                                    a.assessed_on,
                                                                max_score:
                                                                    a.max_score,
                                                                sort_order:
                                                                    a.sort_order,
                                                                objective_ids:
                                                                    a.learning_objectives.map(
                                                                        (tp) =>
                                                                            tp.id,
                                                                    ),
                                                            });
                                                        }}
                                                    >
                                                        <Edit2 className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() =>
                                                            handleDelete(a)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Tambah Asesmen Baru</DialogTitle>
                        <DialogDescription>
                            Tentukan jenis asesmen dan tautkan tujuan
                            pembelajaran yang dinilai.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="create-name">Nama Asesmen</Label>
                            <Input
                                id="create-name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="Contoh: Sumatif Lingkup Materi: Bilangan Cacah"
                                required
                            />
                            {createForm.errors.name && (
                                <p className="text-xs text-red-500">
                                    {createForm.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label>Jenis Asesmen</Label>
                                <Select
                                    value={createForm.data.kind}
                                    onValueChange={(
                                        val:
                                            | 'formative'
                                            | 'summative_scope'
                                            | 'summative_final',
                                    ) => createForm.setData('kind', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="formative">
                                            Formatif (Umpan Balik)
                                        </SelectItem>
                                        <SelectItem value="summative_scope">
                                            Sumatif Lingkup Materi
                                        </SelectItem>
                                        <SelectItem value="summative_final">
                                            Sumatif Akhir Semester
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="create-date">
                                    Tanggal Asesmen
                                </Label>
                                <Input
                                    id="create-date"
                                    type="date"
                                    value={createForm.data.assessed_on}
                                    min={currentSemester.starts_at}
                                    max={currentSemester.ends_at}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'assessed_on',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {createForm.errors.assessed_on && (
                                    <p className="text-xs text-red-500">
                                        {createForm.errors.assessed_on}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="create-max">
                                    Skor Maksimal
                                </Label>
                                <Input
                                    id="create-max"
                                    type="number"
                                    step="0.01"
                                    value={createForm.data.max_score}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'max_score',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {createForm.errors.max_score && (
                                    <p className="text-xs text-red-500">
                                        {createForm.errors.max_score}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="create-sort">
                                    Urutan Tampilan
                                </Label>
                                <Input
                                    id="create-sort"
                                    type="number"
                                    value={createForm.data.sort_order}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'sort_order',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                />
                            </div>
                        </div>

                        {/* Objectives linking */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Tautkan Tujuan Pembelajaran (TP)</Label>
                                {createForm.data.kind === 'summative_scope' && (
                                    <span className="text-xs font-medium text-amber-600">
                                        *Wajib untuk sumatif lingkup materi
                                    </span>
                                )}
                            </div>
                            {objectives.length === 0 ? (
                                <p className="text-muted-foreground text-xs">
                                    Belum ada TP yang dibuat untuk mata
                                    pelajaran dan tingkat kelas ini.
                                </p>
                            ) : (
                                <div className="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3">
                                    {objectives.map((tp) => (
                                        <div
                                            key={tp.id}
                                            className="flex items-start gap-2"
                                        >
                                            <Checkbox
                                                id={`create-tp-${tp.id}`}
                                                checked={createForm.data.objective_ids.includes(
                                                    tp.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleCreateObjective(tp.id)
                                                }
                                            />
                                            <label
                                                htmlFor={`create-tp-${tp.id}`}
                                                className="flex-1 cursor-pointer text-xs leading-none"
                                            >
                                                <span className="font-semibold">
                                                    {tp.code}:
                                                </span>{' '}
                                                {tp.description}
                                            </label>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {createForm.errors.objective_ids && (
                                <p className="text-xs text-red-500">
                                    {createForm.errors.objective_ids}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                Simpan Asesmen
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog
                open={!!editingAssessment}
                onOpenChange={(open) => !open && setEditingAssessment(null)}
            >
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Ubah Asesmen</DialogTitle>
                        <DialogDescription>
                            Perbarui detail atau tautan tujuan pembelajaran
                            asesmen ini.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-name">Nama Asesmen</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(e) =>
                                    editForm.setData('name', e.target.value)
                                }
                                required
                            />
                            {editForm.errors.name && (
                                <p className="text-xs text-red-500">
                                    {editForm.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label>Jenis Asesmen</Label>
                                <Select
                                    value={editForm.data.kind}
                                    onValueChange={(
                                        val:
                                            | 'formative'
                                            | 'summative_scope'
                                            | 'summative_final',
                                    ) => editForm.setData('kind', val)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="formative">
                                            Formatif (Umpan Balik)
                                        </SelectItem>
                                        <SelectItem value="summative_scope">
                                            Sumatif Lingkup Materi
                                        </SelectItem>
                                        <SelectItem value="summative_final">
                                            Sumatif Akhir Semester
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="edit-date">
                                    Tanggal Asesmen
                                </Label>
                                <Input
                                    id="edit-date"
                                    type="date"
                                    value={editForm.data.assessed_on}
                                    min={currentSemester.starts_at}
                                    max={currentSemester.ends_at}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'assessed_on',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {editForm.errors.assessed_on && (
                                    <p className="text-xs text-red-500">
                                        {editForm.errors.assessed_on}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="edit-max">Skor Maksimal</Label>
                                <Input
                                    id="edit-max"
                                    type="number"
                                    step="0.01"
                                    value={editForm.data.max_score}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'max_score',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {editForm.errors.max_score && (
                                    <p className="text-xs text-red-500">
                                        {editForm.errors.max_score}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="edit-sort">
                                    Urutan Tampilan
                                </Label>
                                <Input
                                    id="edit-sort"
                                    type="number"
                                    value={editForm.data.sort_order}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'sort_order',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
                                />
                            </div>
                        </div>

                        {/* Objectives linking */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Tautkan Tujuan Pembelajaran (TP)</Label>
                                {editForm.data.kind === 'summative_scope' && (
                                    <span className="text-xs font-medium text-amber-600">
                                        *Wajib untuk sumatif lingkup materi
                                    </span>
                                )}
                            </div>
                            {objectives.length === 0 ? (
                                <p className="text-muted-foreground text-xs">
                                    Belum ada TP yang dibuat untuk mata
                                    pelajaran dan tingkat kelas ini.
                                </p>
                            ) : (
                                <div className="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3">
                                    {objectives.map((tp) => (
                                        <div
                                            key={tp.id}
                                            className="flex items-start gap-2"
                                        >
                                            <Checkbox
                                                id={`edit-tp-${tp.id}`}
                                                checked={editForm.data.objective_ids.includes(
                                                    tp.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleEditObjective(tp.id)
                                                }
                                            />
                                            <label
                                                htmlFor={`edit-tp-${tp.id}`}
                                                className="flex-1 cursor-pointer text-xs leading-none"
                                            >
                                                <span className="font-semibold">
                                                    {tp.code}:
                                                </span>{' '}
                                                {tp.description}
                                            </label>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {editForm.errors.objective_ids && (
                                <p className="text-xs text-red-500">
                                    {editForm.errors.objective_ids}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingAssessment(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                Perbarui
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AssessmentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Asesmen',
            href: '/courses',
        },
    ],
};
