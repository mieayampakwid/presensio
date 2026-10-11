import { Head, router, useForm } from '@inertiajs/react';
import { Copy, Plus, Target, Trash2, Edit2, AlertCircle } from 'lucide-react';
import { useState } from 'react';
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

type Objective = {
    id: number;
    academic_year_id: number;
    subject_id: number;
    grade_level: number;
    code: string;
    description: string;
    sort_order: number;
};

type AcademicYear = {
    id: number;
    name: string;
    is_active: boolean;
};

type Subject = {
    id: number;
    code: string;
    name: string;
};

type Props = {
    academicYears: AcademicYear[];
    subjects: Subject[];
    gradeLevels: number[];
    selectedYearId: number | null;
    selectedSubjectId: number | null;
    selectedGradeLevel: number | null;
    objectives: Objective[];
    canManage: boolean;
    hasPreviousYear: boolean;
};

export default function ObjectivesIndex({
    academicYears,
    subjects,
    gradeLevels,
    selectedYearId,
    selectedSubjectId,
    selectedGradeLevel,
    objectives,
    canManage,
    hasPreviousYear,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingObjective, setEditingObjective] = useState<Objective | null>(
        null,
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const createForm = useForm({
        academic_year_id: selectedYearId ?? '',
        subject_id: selectedSubjectId ?? '',
        grade_level: selectedGradeLevel ?? '',
        code: '',
        description: '',
        sort_order: 0,
    });

    const editForm = useForm({
        code: '',
        description: '',
        sort_order: 0,
    });

    const handleFilterChange = (
        key: 'academic_year_id' | 'subject_id' | 'grade_level',
        value: string,
    ) => {
        router.get(
            '/objectives',
            {
                academic_year_id:
                    key === 'academic_year_id' ? value : (selectedYearId ?? ''),
                subject_id:
                    key === 'subject_id' ? value : (selectedSubjectId ?? ''),
                grade_level:
                    key === 'grade_level' ? value : (selectedGradeLevel ?? ''),
            },
            { preserveState: true },
        );
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.setData({
            ...createForm.data,
            academic_year_id: selectedYearId ?? '',
            subject_id: selectedSubjectId ?? '',
            grade_level: selectedGradeLevel ?? '',
        });
        createForm.post('/objectives', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset('code', 'description', 'sort_order');
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingObjective) return;

        editForm.put(`/objectives/${editingObjective.id}`, {
            onSuccess: () => {
                setEditingObjective(null);
            },
        });
    };

    const handleDelete = (objective: Objective) => {
        if (!confirm(`Hapus tujuan pembelajaran ${objective.code}?`)) return;

        router.delete(`/objectives/${objective.id}`, {
            onError: (errors) => {
                const msg = Object.values(errors)[0] as string;
                setErrorMessage(msg || 'Gagal menghapus tujuan pembelajaran.');
            },
        });
    };

    const handleCopyPreviousYear = () => {
        if (
            !confirm(
                'Salin tujuan pembelajaran dari tahun ajaran sebelumnya? Kode yang sudah ada akan dilewati.',
            )
        )
            return;

        router.post(
            '/objectives/copy',
            {
                academic_year_id: selectedYearId,
                subject_id: selectedSubjectId,
                grade_level: selectedGradeLevel,
            },
            {
                onError: (errors) => {
                    const msg = Object.values(errors)[0] as string;
                    setErrorMessage(
                        msg || 'Gagal menyalin tujuan pembelajaran.',
                    );
                },
            },
        );
    };

    return (
        <>
            <Head title="Tujuan Pembelajaran" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Tujuan Pembelajaran (TP)"
                            description="Kelola tujuan pembelajaran Kurikulum Merdeka per mata pelajaran dan tingkat kelas"
                        />
                    </div>
                    {canManage && (
                        <div className="flex items-center gap-2">
                            {hasPreviousYear && (
                                <Button
                                    variant="outline"
                                    onClick={handleCopyPreviousYear}
                                >
                                    <Copy className="mr-2 h-4 w-4" />
                                    Salin dari Tahun Lalu
                                </Button>
                            )}
                            <Button
                                onClick={() => setIsCreateOpen(true)}
                                disabled={
                                    !selectedYearId ||
                                    !selectedSubjectId ||
                                    !selectedGradeLevel
                                }
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah TP
                            </Button>
                        </div>
                    )}
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

                {/* Filters */}
                <div className="grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-3">
                    <div className="space-y-1.5">
                        <Label>Tahun Ajaran</Label>
                        <Select
                            value={selectedYearId ? String(selectedYearId) : ''}
                            onValueChange={(val) =>
                                handleFilterChange('academic_year_id', val)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih Tahun Ajaran" />
                            </SelectTrigger>
                            <SelectContent>
                                {academicYears.map((y) => (
                                    <SelectItem key={y.id} value={String(y.id)}>
                                        {y.name} {y.is_active && '(Aktif)'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Mata Pelajaran</Label>
                        <Select
                            value={
                                selectedSubjectId
                                    ? String(selectedSubjectId)
                                    : ''
                            }
                            onValueChange={(val) =>
                                handleFilterChange('subject_id', val)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih Mata Pelajaran" />
                            </SelectTrigger>
                            <SelectContent>
                                {subjects.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name} ({s.code})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Tingkat Kelas</Label>
                        <Select
                            value={
                                selectedGradeLevel
                                    ? String(selectedGradeLevel)
                                    : ''
                            }
                            onValueChange={(val) =>
                                handleFilterChange('grade_level', val)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih Tingkat" />
                            </SelectTrigger>
                            <SelectContent>
                                {gradeLevels.map((lvl) => (
                                    <SelectItem key={lvl} value={String(lvl)}>
                                        Kelas {lvl}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Content */}
                {!selectedYearId ||
                !selectedSubjectId ||
                !selectedGradeLevel ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center">
                        Pilih tahun ajaran, mata pelajaran, dan tingkat kelas
                        untuk melihat tujuan pembelajaran.
                    </div>
                ) : objectives.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <Target className="text-muted-foreground/60 mx-auto h-12 w-12" />
                        <h3 className="mt-4 text-base font-semibold">
                            Belum Ada Tujuan Pembelajaran
                        </h3>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {canManage
                                ? 'Tambahkan tujuan pembelajaran untuk lingkup mata pelajaran dan tingkat kelas ini.'
                                : 'Anda tidak memiliki hak mengelola tujuan pembelajaran untuk lingkup ini.'}
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
                                        Kode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Deskripsi Tujuan Pembelajaran
                                    </th>
                                    {canManage && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {objectives.map((tp) => (
                                    <tr
                                        key={tp.id}
                                        className="hover:bg-muted/50"
                                    >
                                        <td className="text-muted-foreground px-4 py-3">
                                            {tp.sort_order}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            <Badge variant="outline">
                                                {tp.code}
                                            </Badge>
                                        </td>
                                        <td className="max-w-xl px-4 py-3">
                                            {tp.description}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditingObjective(
                                                                tp,
                                                            );
                                                            editForm.setData({
                                                                code: tp.code,
                                                                description:
                                                                    tp.description,
                                                                sort_order:
                                                                    tp.sort_order,
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
                                                            handleDelete(tp)
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
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Tujuan Pembelajaran</DialogTitle>
                        <DialogDescription>
                            Tujuan pembelajaran digunakan dalam asesmen lingkup
                            materi dan deskripsi capaian rapor.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="create-code">Kode TP</Label>
                            <Input
                                id="create-code"
                                value={createForm.data.code}
                                onChange={(e) =>
                                    createForm.setData('code', e.target.value)
                                }
                                placeholder="Contoh: TP 1"
                                required
                            />
                            {createForm.errors.code && (
                                <p className="text-xs text-red-500">
                                    {createForm.errors.code}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="create-description">
                                Deskripsi
                            </Label>
                            <textarea
                                id="create-description"
                                className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                value={createForm.data.description}
                                onChange={(
                                    e: React.ChangeEvent<HTMLTextAreaElement>,
                                ) =>
                                    createForm.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                                placeholder="Contoh: Memahami konsep pecahan dan representasinya"
                                maxLength={500}
                                rows={4}
                                required
                            />
                            {createForm.errors.description && (
                                <p className="text-xs text-red-500">
                                    {createForm.errors.description}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="create-sort">Urutan</Label>
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
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog
                open={!!editingObjective}
                onOpenChange={(open) => !open && setEditingObjective(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ubah Tujuan Pembelajaran</DialogTitle>
                        <DialogDescription>
                            Perbarui kode atau deskripsi tujuan pembelajaran.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-code">Kode TP</Label>
                            <Input
                                id="edit-code"
                                value={editForm.data.code}
                                onChange={(e) =>
                                    editForm.setData('code', e.target.value)
                                }
                                required
                            />
                            {editForm.errors.code && (
                                <p className="text-xs text-red-500">
                                    {editForm.errors.code}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="edit-description">Deskripsi</Label>
                            <textarea
                                id="edit-description"
                                className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                value={editForm.data.description}
                                onChange={(
                                    e: React.ChangeEvent<HTMLTextAreaElement>,
                                ) =>
                                    editForm.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                                maxLength={500}
                                rows={4}
                                required
                            />
                            {editForm.errors.description && (
                                <p className="text-xs text-red-500">
                                    {editForm.errors.description}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="edit-sort">Urutan</Label>
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

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingObjective(null)}
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

ObjectivesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tujuan Pembelajaran',
            href: '/objectives',
        },
    ],
};
