import { index as classAnnouncements } from '@/routes/classes/announcements';
import { Form, Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    FileSpreadsheet,
    GraduationCap,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import ClassSubjectController from '@/actions/App/Http/Controllers/Classes/ClassSubjectController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import ClassTimetableController from '@/actions/App/Http/Controllers/Timetable/ClassTimetableController';
import ReportCardWorkspaceController from '@/actions/App/Http/Controllers/Teacher/ReportCardWorkspaceController';
import ClassGradeLedgerController from '@/actions/App/Http/Controllers/Teacher/ClassGradeLedgerController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type ClassRow = {
    id: number;
    can_announce: boolean;
    name: string;
    grade_level: number;
    curriculum: string;
    teacher?: { id: number; name: string } | null;
};

type YearOption = {
    id: number;
    name: string;
    is_active: boolean;
};

type TeacherOption = {
    id: number;
    name: string;
};

type Paginator = {
    data: ClassRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    classes: Paginator;
    years: YearOption[];
    filters: { search: string; year_id: number | null };
    teachers?: TeacherOption[];
    can?: { manage: boolean };
};

export default function ClassesIndex({
    classes,
    years,
    filters,
    teachers,
    can = { manage: false },
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingClass, setEditingClass] = useState<ClassRow | null>(null);
    const [deleting, setDeleting] = useState<ClassRow | null>(null);

    const openCreate = () => {
        if (!teachers) {
            router.reload({ only: ['teachers'] });
        }
        setIsCreateOpen(true);
    };

    const openEdit = (cls: ClassRow) => {
        if (!teachers) {
            router.reload({ only: ['teachers'] });
        }
        setEditingClass(cls);
    };
    const destroy = (schoolClass: ClassRow) => {
        router.delete(
            SchoolClassController.destroy({ school_class: schoolClass.id }).url,
            { preserveScroll: true },
        );
    };

    const submitFilters = (
        event: ChangeEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    return (
        <>
            <Head title="Classes" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Classes"
                        description="Year-scoped class instances; homeroom teachers link to master data."
                    />

                    {can.manage && (
                        <Button onClick={openCreate}>Create class</Button>
                    )}
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="year_id">Academic year</Label>
                        <select
                            id="year_id"
                            name="year_id"
                            defaultValue={filters.year_id ?? ''}
                            className="border-input dark:bg-input/30 flex h-9 w-64 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none md:text-sm"
                            onChange={submitFilters}
                        >
                            {years.map((year) => (
                                <option key={year.id} value={year.id}>
                                    {year.name}
                                    {year.is_active ? ' — active' : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search by class or teacher name…"
                            onChange={submitFilters}
                        />
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Grade
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Curriculum
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Homeroom teacher
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {classes.data.map((schoolClass) => (
                                <tr key={schoolClass.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {schoolClass.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {schoolClass.grade_level > 0 ? (
                                            <span>
                                                Kelas {schoolClass.grade_level}
                                            </span>
                                        ) : (
                                            <span className="font-medium text-amber-600">
                                                Belum diatur
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 capitalize">
                                        {schoolClass.curriculum}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {schoolClass.teacher?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.manage && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    title="Jadwal Pelajaran"
                                                >
                                                    <Link
                                                        href={
                                                            ClassTimetableController.edit(
                                                                {
                                                                    school_class:
                                                                        schoolClass.id,
                                                                },
                                                            ).url
                                                        }
                                                    >
                                                        <CalendarDays className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Jadwal Pelajaran
                                                        </span>
                                                    </Link>
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title="Mata Pelajaran & Guru"
                                            >
                                                <Link
                                                    href={
                                                        ClassSubjectController.index(
                                                            {
                                                                school_class:
                                                                    schoolClass.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <BookOpen className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Mata Pelajaran
                                                    </span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title="Rapor Kelas"
                                            >
                                                <Link
                                                    href={
                                                        ReportCardWorkspaceController.show(
                                                            {
                                                                school_class:
                                                                    schoolClass.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <GraduationCap className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Rapor Kelas
                                                    </span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title="Buku Nilai Kelas"
                                            >
                                                <Link
                                                    href={
                                                        ClassGradeLedgerController.index(
                                                            {
                                                                school_class:
                                                                    schoolClass.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <FileSpreadsheet className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Buku Nilai Kelas
                                                    </span>
                                                </Link>
                                            </Button>
                                            {schoolClass.can_announce && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={classAnnouncements(
                                                            schoolClass.id,
                                                        )}
                                                    >
                                                        Pengumuman kelas
                                                    </Link>
                                                </Button>
                                            )}
                                            {can.manage && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(
                                                                schoolClass,
                                                            )
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Edit
                                                        </span>
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() =>
                                                            setDeleting(
                                                                schoolClass,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Delete
                                                        </span>
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {classes.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={3}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No classes found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {classes.current_page} of {classes.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!classes.prev_page_url}
                        >
                            <Link href={classes.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!classes.next_page_url}
                        >
                            <Link href={classes.next_page_url ?? '#'}>
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete class</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{deleting?.name}"?
                        Classes with enrolled students cannot be deleted.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleting) {
                                    destroy(deleting);
                                    setDeleting(null);
                                }
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create class</DialogTitle>
                        <DialogDescription>
                            Create a new class for the active academic year.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...SchoolClassController.store.form()}
                        onSuccess={() => setIsCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-name">Name</Label>
                                    <Input
                                        id="create-name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="e.g. Kelas 1A"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-grade_level">
                                            Grade Level (1–12)
                                        </Label>
                                        <select
                                            id="create-grade_level"
                                            name="grade_level"
                                            required
                                            defaultValue=""
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="" disabled>
                                                Pilih tingkat kelas
                                            </option>
                                            {Array.from(
                                                { length: 12 },
                                                (_, i) => i + 1,
                                            ).map((lvl) => (
                                                <option key={lvl} value={lvl}>
                                                    Kelas {lvl}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.grade_level}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="create-curriculum">
                                            Curriculum
                                        </Label>
                                        <select
                                            id="create-curriculum"
                                            name="curriculum"
                                            defaultValue="merdeka"
                                            required
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="merdeka">
                                                Kurikulum Merdeka
                                            </option>
                                        </select>
                                        <InputError
                                            message={errors.curriculum}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-teacher_id">
                                        Homeroom teacher (optional)
                                    </Label>
                                    <select
                                        id="create-teacher_id"
                                        name="teacher_id"
                                        defaultValue=""
                                        className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    >
                                        <option value="">
                                            No homeroom teacher
                                        </option>
                                        {(teachers ?? []).map((teacher) => (
                                            <option
                                                key={teacher.id}
                                                value={teacher.id}
                                            >
                                                {teacher.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.teacher_id} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <Button
                                        variant="secondary"
                                        type="button"
                                        onClick={() => setIsCreateOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Create class
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingClass !== null}
                onOpenChange={(open) => !open && setEditingClass(null)}
            >
                <DialogContent>
                    {editingClass && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Edit {editingClass.name}
                                </DialogTitle>
                                <DialogDescription>
                                    Update class details and homeroom teacher.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                key={editingClass.id}
                                {...SchoolClassController.update.form({
                                    school_class: editingClass.id,
                                })}
                                onSuccess={() => setEditingClass(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="edit-name"
                                                name="name"
                                                defaultValue={editingClass.name}
                                                required
                                                autoComplete="off"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-grade_level">
                                                    Grade Level (1–12)
                                                </Label>
                                                <select
                                                    id="edit-grade_level"
                                                    name="grade_level"
                                                    required
                                                    defaultValue={
                                                        editingClass.grade_level >
                                                        0
                                                            ? editingClass.grade_level
                                                            : ''
                                                    }
                                                    className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                                >
                                                    <option value="" disabled>
                                                        Pilih tingkat kelas
                                                    </option>
                                                    {Array.from(
                                                        { length: 12 },
                                                        (_, i) => i + 1,
                                                    ).map((lvl) => (
                                                        <option
                                                            key={lvl}
                                                            value={lvl}
                                                        >
                                                            Kelas {lvl}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError
                                                    message={errors.grade_level}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-curriculum">
                                                    Curriculum
                                                </Label>
                                                <select
                                                    id="edit-curriculum"
                                                    name="curriculum"
                                                    defaultValue={
                                                        editingClass.curriculum ||
                                                        'merdeka'
                                                    }
                                                    required
                                                    className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                                >
                                                    <option value="merdeka">
                                                        Kurikulum Merdeka
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.curriculum}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-teacher_id">
                                                Homeroom teacher (optional)
                                            </Label>
                                            <select
                                                id="edit-teacher_id"
                                                name="teacher_id"
                                                defaultValue={
                                                    editingClass.teacher?.id ??
                                                    ''
                                                }
                                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            >
                                                <option value="">
                                                    No homeroom teacher
                                                </option>
                                                {(teachers ?? []).map(
                                                    (teacher) => (
                                                        <option
                                                            key={teacher.id}
                                                            value={teacher.id}
                                                        >
                                                            {teacher.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors.teacher_id}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() =>
                                                    setEditingClass(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Save changes
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

ClassesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Classes',
            href: SchoolClassController.index().url,
        },
    ],
};
