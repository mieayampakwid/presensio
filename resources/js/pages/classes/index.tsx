import { index as classAnnouncements } from '@/routes/classes/announcements';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    CalendarRange,
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
import ClassTimetableViewController from '@/actions/App/Http/Controllers/Timetable/ClassTimetableViewController';
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
import { useTranslation } from '@/hooks/use-translation';

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
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('classes.index.title'),
                href: SchoolClassController.index().url,
            },
        ],
    });

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
            <Head title={t('classes.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('classes.index.title')}
                        description={t('classes.index.description')}
                    />

                    {can.manage && (
                        <Button onClick={openCreate}>
                            {t('classes.index.create')}
                        </Button>
                    )}
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="year_id">
                            {t('classes.index.academic_year')}
                        </Label>
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
                                    {year.is_active
                                        ? t('classes.index.active_suffix')
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="search">
                            {t('classes.index.search')}
                        </Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder={t('classes.index.search_placeholder')}
                            onChange={submitFilters}
                        />
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('classes.index.columns.name')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('classes.index.columns.grade')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('classes.index.columns.curriculum')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t(
                                        'classes.index.columns.homeroom_teacher',
                                    )}
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
                                                {t(
                                                    'classes.index.grade_label',
                                                    {
                                                        level: schoolClass.grade_level,
                                                    },
                                                )}
                                            </span>
                                        ) : (
                                            <span className="font-medium text-amber-600">
                                                {t(
                                                    'classes.index.grade_not_set',
                                                )}
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
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title={t(
                                                    'classes.index.view_timetable',
                                                )}
                                            >
                                                <Link
                                                    href={
                                                        ClassTimetableViewController.show(
                                                            {
                                                                school_class:
                                                                    schoolClass.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <CalendarRange className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t(
                                                            'classes.index.view_timetable',
                                                        )}
                                                    </span>
                                                </Link>
                                            </Button>
                                            {can.manage && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    title={t(
                                                        'classes.index.edit_timetable',
                                                    )}
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
                                                            {t(
                                                                'classes.index.edit_timetable',
                                                            )}
                                                        </span>
                                                    </Link>
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title={t(
                                                    'classes.index.subjects_title',
                                                )}
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
                                                        {t(
                                                            'classes.index.subjects',
                                                        )}
                                                    </span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title={t(
                                                    'classes.index.report_card',
                                                )}
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
                                                        {t(
                                                            'classes.index.report_card',
                                                        )}
                                                    </span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title={t(
                                                    'classes.index.grade_ledger',
                                                )}
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
                                                        {t(
                                                            'classes.index.grade_ledger',
                                                        )}
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
                                                        {t(
                                                            'classes.index.announcements',
                                                        )}
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
                                                            {t('common.edit')}
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
                                                            {t(
                                                                'classes.delete.confirm',
                                                            )}
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
                                        {t('classes.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: classes.current_page,
                            last: classes.last_page,
                        })}
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
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!classes.next_page_url}
                        >
                            <Link href={classes.next_page_url ?? '#'}>
                                {t('common.next')}
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
                    <DialogTitle>{t('classes.delete.title')}</DialogTitle>
                    <DialogDescription>
                        {t('classes.delete.description', {
                            name: deleting?.name ?? '',
                        })}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setDeleting(null)}
                        >
                            {t('common.cancel')}
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
                            {t('classes.delete.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('classes.create.title')}</DialogTitle>
                        <DialogDescription>
                            {t('classes.create.description')}
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
                                    <Label htmlFor="create-name">
                                        {t('classes.form.name')}
                                    </Label>
                                    <Input
                                        id="create-name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder={t(
                                            'classes.form.name_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-grade_level">
                                            {t('classes.form.grade_level')}
                                        </Label>
                                        <select
                                            id="create-grade_level"
                                            name="grade_level"
                                            required
                                            defaultValue=""
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="" disabled>
                                                {t(
                                                    'classes.form.grade_placeholder',
                                                )}
                                            </option>
                                            {Array.from(
                                                { length: 12 },
                                                (_, i) => i + 1,
                                            ).map((lvl) => (
                                                <option key={lvl} value={lvl}>
                                                    {t(
                                                        'classes.index.grade_label',
                                                        { level: lvl },
                                                    )}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.grade_level}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="create-curriculum">
                                            {t('classes.form.curriculum')}
                                        </Label>
                                        <select
                                            id="create-curriculum"
                                            name="curriculum"
                                            defaultValue="merdeka"
                                            required
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="merdeka">
                                                {t(
                                                    'classes.curriculums.merdeka',
                                                )}
                                            </option>
                                        </select>
                                        <InputError
                                            message={errors.curriculum}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-teacher_id">
                                        {t('classes.form.homeroom_teacher')}
                                    </Label>
                                    <select
                                        id="create-teacher_id"
                                        name="teacher_id"
                                        defaultValue=""
                                        className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    >
                                        <option value="">
                                            {t(
                                                'classes.form.no_homeroom_teacher',
                                            )}
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
                                        {t('common.cancel')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {t('classes.form.submit_create')}
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
                                    {t('classes.edit.title', {
                                        name: editingClass.name,
                                    })}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('classes.edit.description')}
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
                                                {t('classes.form.name')}
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
                                                    {t(
                                                        'classes.form.grade_level',
                                                    )}
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
                                                        {t(
                                                            'classes.form.grade_placeholder',
                                                        )}
                                                    </option>
                                                    {Array.from(
                                                        { length: 12 },
                                                        (_, i) => i + 1,
                                                    ).map((lvl) => (
                                                        <option
                                                            key={lvl}
                                                            value={lvl}
                                                        >
                                                            {t(
                                                                'classes.index.grade_label',
                                                                { level: lvl },
                                                            )}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError
                                                    message={errors.grade_level}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-curriculum">
                                                    {t(
                                                        'classes.form.curriculum',
                                                    )}
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
                                                        {t(
                                                            'classes.curriculums.merdeka',
                                                        )}
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.curriculum}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-teacher_id">
                                                {t(
                                                    'classes.form.homeroom_teacher',
                                                )}
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
                                                    {t(
                                                        'classes.form.no_homeroom_teacher',
                                                    )}
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
                                                {t('common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t(
                                                    'classes.form.submit_update',
                                                )}
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
