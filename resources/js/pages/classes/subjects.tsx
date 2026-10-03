import { Head, Link, router, Form } from '@inertiajs/react';
import { ChevronLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ClassSubjectController from '@/actions/App/Http/Controllers/Classes/ClassSubjectController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type SubjectOption = {
    id: number;
    code: string;
    name: string;
    group: string;
};

type TeacherOption = {
    id: number;
    name: string;
};

type ClassSubjectRow = {
    id: number;
    class_id: number;
    subject_id: number;
    teacher_id: number;
    passing_threshold: string;
    subject?: {
        id: number;
        code: string;
        name: string;
        group: string;
        sort_order: number;
        is_active: boolean;
    };
    teacher?: {
        id: number;
        name: string;
    };
};

type SchoolClassData = {
    id: number;
    name: string;
    grade_level: number;
    curriculum: string;
    academic_year?: {
        id: number;
        name: string;
        is_active: boolean;
    };
    teacher?: {
        id: number;
        name: string;
    } | null;
};

type Props = {
    schoolClass: SchoolClassData;
    classSubjects: ClassSubjectRow[];
    subjects: SubjectOption[];
    teachers: TeacherOption[];
    defaultPassingThreshold: string;
};

export default function ClassSubjects({
    schoolClass,
    classSubjects,
    subjects,
    teachers,
    defaultPassingThreshold,
}: Props) {
    const [editingItem, setEditingItem] = useState<ClassSubjectRow | null>(null);

    const isActiveYear = schoolClass.academic_year?.is_active ?? true;

    const assignedSubjectIds = new Set(classSubjects.map((cs) => cs.subject_id));
    const unassignedSubjects = subjects.filter((s) => !assignedSubjectIds.has(s.id));

    const destroy = (item: ClassSubjectRow) => {
        router.delete(
            ClassSubjectController.destroy.url({
                school_class: schoolClass.id,
                class_subject: item.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Mata Pelajaran & Guru — ${schoolClass.name}`} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={SchoolClassController.index().url}>
                                    <ChevronLeft className="h-4 w-4" />
                                    Classes
                                </Link>
                            </Button>
                        </div>
                        <Heading
                            title={`Mata Pelajaran & Guru — ${schoolClass.name}`}
                            description={`Tahun Ajaran: ${schoolClass.academic_year?.name ?? '-'} | Wali Kelas: ${schoolClass.teacher?.name ?? 'Belum ditentukan'}`}
                        />
                    </div>
                </div>

                {!isActiveYear && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/50 dark:text-amber-200">
                        Kelas ini berada pada tahun ajaran yang tidak aktif. Penugasan mata pelajaran bersifat hanya-baca (read-only).
                    </div>
                )}

                {isActiveYear && unassignedSubjects.length > 0 && (
                    <div className="rounded-lg border bg-card p-4 shadow-xs">
                        <h3 className="mb-4 text-base font-semibold">
                            Tambah Penugasan Mata Pelajaran
                        </h3>
                        <Form
                            action={ClassSubjectController.store.url({
                                school_class: schoolClass.id,
                            })}
                            method="post"
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <div className="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="subject_id">Mata Pelajaran</Label>
                                        <select
                                            id="subject_id"
                                            name="subject_id"
                                            required
                                            defaultValue=""
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="" disabled>
                                                Pilih mata pelajaran
                                            </option>
                                            {unassignedSubjects.map((subject) => (
                                                <option key={subject.id} value={subject.id}>
                                                    {subject.name} ({subject.code})
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.subject_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="teacher_id">Guru Pengajar</Label>
                                        <select
                                            id="teacher_id"
                                            name="teacher_id"
                                            required
                                            defaultValue=""
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="" disabled>
                                                Pilih guru pengajar
                                            </option>
                                            {teachers.map((teacher) => (
                                                <option key={teacher.id} value={teacher.id}>
                                                    {teacher.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.teacher_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="passing_threshold">
                                            KKTP (Passing Threshold)
                                        </Label>
                                        <Input
                                            id="passing_threshold"
                                            name="passing_threshold"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            defaultValue={defaultPassingThreshold}
                                            required
                                        />
                                        <InputError message={errors.passing_threshold} />
                                    </div>

                                    <div>
                                        <Button type="submit" disabled={processing} className="w-full">
                                            {processing && <Spinner className="mr-2" />}
                                            <Plus className="mr-1 h-4 w-4" />
                                            Tugaskan
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Kode</th>
                                <th className="px-4 py-3 font-medium">Mata Pelajaran</th>
                                <th className="px-4 py-3 font-medium">Kelompok</th>
                                <th className="px-4 py-3 font-medium">Guru Pengajar</th>
                                <th className="px-4 py-3 font-medium">KKTP</th>
                                {isActiveYear && (
                                    <th className="px-4 py-3 text-right font-medium">Aksi</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {classSubjects.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-4 py-3 font-mono font-medium">
                                        {item.subject?.code}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {item.subject?.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.subject?.group}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {item.teacher?.name ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.passing_threshold}
                                    </td>
                                    {isActiveYear && (
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => setEditingItem(item)}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Button>

                                                <Dialog>
                                                    <DialogTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive hover:text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                            <span className="sr-only">Hapus</span>
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogTitle>
                                                            Hapus Penugasan
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Hapus penugasan {item.subject?.name} dari {schoolClass.name}?
                                                        </DialogDescription>
                                                        <DialogFooter className="gap-2">
                                                            <DialogClose asChild>
                                                                <Button variant="outline">
                                                                    Batal
                                                                </Button>
                                                            </DialogClose>
                                                            <DialogClose asChild>
                                                                <Button
                                                                    variant="destructive"
                                                                    onClick={() => destroy(item)}
                                                                >
                                                                    Hapus
                                                                </Button>
                                                            </DialogClose>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}

                            {classSubjects.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={isActiveYear ? 6 : 5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Belum ada mata pelajaran yang ditugaskan ke kelas ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {editingItem && (
                    <Dialog open={true} onOpenChange={(open) => !open && setEditingItem(null)}>
                        <DialogContent>
                            <DialogTitle>
                                Edit Penugasan — {editingItem.subject?.name}
                            </DialogTitle>
                            <DialogDescription>
                                Perbarui guru pengajar atau ambang batas kelulusan (KKTP).
                            </DialogDescription>

                            <Form
                                action={ClassSubjectController.update.url({
                                    school_class: schoolClass.id,
                                    class_subject: editingItem.id,
                                })}
                                method="put"
                                onSuccess={() => setEditingItem(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <div className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit_teacher_id">Guru Pengajar</Label>
                                            <select
                                                id="edit_teacher_id"
                                                name="teacher_id"
                                                required
                                                defaultValue={editingItem.teacher_id}
                                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            >
                                                {teachers.map((teacher) => (
                                                    <option key={teacher.id} value={teacher.id}>
                                                        {teacher.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.teacher_id} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit_passing_threshold">
                                                KKTP (Passing Threshold)
                                            </Label>
                                            <Input
                                                id="edit_passing_threshold"
                                                name="passing_threshold"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                defaultValue={editingItem.passing_threshold}
                                                required
                                            />
                                            <InputError message={errors.passing_threshold} />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setEditingItem(null)}
                                            >
                                                Batal
                                            </Button>
                                            <Button type="submit" disabled={processing}>
                                                {processing && <Spinner className="mr-2" />}
                                                Simpan
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </>
    );
}

ClassSubjects.layout = {
    breadcrumbs: [
        {
            title: 'Classes',
            href: SchoolClassController.index().url,
        },
        {
            title: 'Subjects & Teachers',
        },
    ],
};
