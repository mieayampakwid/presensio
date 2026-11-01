import { Head, Link, router, Form, setLayoutProps } from '@inertiajs/react';
import {
    ChevronLeft,
    ExternalLink,
    FileSpreadsheet,
    GraduationCap,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import ClassSubjectController from '@/actions/App/Http/Controllers/Classes/ClassSubjectController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import ReportCardWorkspaceController from '@/actions/App/Http/Controllers/Teacher/ReportCardWorkspaceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

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
    const [editingItem, setEditingItem] = useState<ClassSubjectRow | null>(
        null,
    );
    const [deletingItem, setDeletingItem] = useState<ClassSubjectRow | null>(
        null,
    );
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('classes.index.title'),
                href: SchoolClassController.index().url,
            },
            { title: t('classes.subjects.breadcrumb') },
        ],
    });

    const isActiveYear = schoolClass.academic_year?.is_active ?? true;

    const assignedSubjectIds = new Set(
        classSubjects.map((cs) => cs.subject_id),
    );
    const unassignedSubjects = subjects.filter(
        (s) => !assignedSubjectIds.has(s.id),
    );

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
            <Head
                title={t('classes.subjects.title', {
                    name: schoolClass.name,
                })}
            />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={SchoolClassController.index().url}>
                                    <ChevronLeft className="h-4 w-4" />
                                    {t('classes.index.title')}
                                </Link>
                            </Button>
                        </div>
                        <Heading
                            title={t('classes.subjects.title', {
                                name: schoolClass.name,
                            })}
                            description={t('classes.subjects.description', {
                                year: schoolClass.academic_year?.name ?? '-',
                                teacher:
                                    schoolClass.teacher?.name ??
                                    t('classes.subjects.teacher_not_set'),
                            })}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={
                                    ReportCardWorkspaceController.show({
                                        school_class: schoolClass.id,
                                    }).url
                                }
                            >
                                <GraduationCap className="mr-1.5 h-4 w-4" />
                                {t('classes.subjects.report_card')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {!isActiveYear && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/50 dark:text-amber-200">
                        {t('classes.subjects.read_only_notice')}
                    </div>
                )}

                {isActiveYear && unassignedSubjects.length > 0 && (
                    <div className="bg-card rounded-lg border p-4 shadow-xs">
                        <h3 className="mb-4 text-base font-semibold">
                            {t('classes.subjects.assign_heading')}
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
                                        <Label htmlFor="subject_id">
                                            {t('classes.subjects.form.subject')}
                                        </Label>
                                        <select
                                            id="subject_id"
                                            name="subject_id"
                                            required
                                            defaultValue=""
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="" disabled>
                                                {t(
                                                    'classes.subjects.form.subject_placeholder',
                                                )}
                                            </option>
                                            {unassignedSubjects.map(
                                                (subject) => (
                                                    <option
                                                        key={subject.id}
                                                        value={subject.id}
                                                    >
                                                        {subject.name} (
                                                        {subject.code})
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={errors.subject_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="teacher_id">
                                            {t('classes.subjects.form.teacher')}
                                        </Label>
                                        <select
                                            id="teacher_id"
                                            name="teacher_id"
                                            required
                                            defaultValue=""
                                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="" disabled>
                                                {t(
                                                    'classes.subjects.form.teacher_placeholder',
                                                )}
                                            </option>
                                            {teachers.map((teacher) => (
                                                <option
                                                    key={teacher.id}
                                                    value={teacher.id}
                                                >
                                                    {teacher.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.teacher_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="passing_threshold">
                                            {t(
                                                'classes.subjects.form.passing_threshold',
                                            )}
                                        </Label>
                                        <Input
                                            id="passing_threshold"
                                            name="passing_threshold"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            defaultValue={
                                                defaultPassingThreshold
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.passing_threshold}
                                        />
                                    </div>

                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            {processing && (
                                                <Spinner className="mr-2" />
                                            )}
                                            <Plus className="mr-1 h-4 w-4" />
                                            {t('classes.subjects.form.assign')}
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
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.code')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.subject')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.group')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.teacher')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t(
                                        'classes.subjects.columns.passing_threshold',
                                    )}
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    {t('classes.subjects.columns.actions')}
                                </th>
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
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title={t(
                                                    'classes.subjects.gradebook',
                                                )}
                                            >
                                                <Link
                                                    href={`/courses/${item.id}/gradebook`}
                                                >
                                                    <FileSpreadsheet className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t(
                                                            'classes.subjects.gradebook',
                                                        )}
                                                    </span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                title={t(
                                                    'classes.subjects.class_detail',
                                                )}
                                            >
                                                <Link
                                                    href={`/courses/${item.id}`}
                                                >
                                                    <ExternalLink className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t(
                                                            'classes.subjects.class_detail',
                                                        )}
                                                    </span>
                                                </Link>
                                            </Button>
                                            {isActiveYear && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            setEditingItem(item)
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
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setDeletingItem(
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            {t(
                                                                'classes.subjects.delete.confirm',
                                                            )}
                                                        </span>
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {classSubjects.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('classes.subjects.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {editingItem && (
                    <Dialog
                        open={true}
                        onOpenChange={(open) => !open && setEditingItem(null)}
                    >
                        <DialogContent>
                            <DialogTitle>
                                {t('classes.subjects.edit.title', {
                                    subject: editingItem.subject?.name ?? '',
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('classes.subjects.edit.description')}
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
                                            <Label htmlFor="edit_teacher_id">
                                                {t(
                                                    'classes.subjects.form.teacher',
                                                )}
                                            </Label>
                                            <select
                                                id="edit_teacher_id"
                                                name="teacher_id"
                                                required
                                                defaultValue={
                                                    editingItem.teacher_id
                                                }
                                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            >
                                                {teachers.map((teacher) => (
                                                    <option
                                                        key={teacher.id}
                                                        value={teacher.id}
                                                    >
                                                        {teacher.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.teacher_id}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit_passing_threshold">
                                                {t(
                                                    'classes.subjects.form.passing_threshold',
                                                )}
                                            </Label>
                                            <Input
                                                id="edit_passing_threshold"
                                                name="passing_threshold"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                defaultValue={
                                                    editingItem.passing_threshold
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors.passing_threshold
                                                }
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingItem(null)
                                                }
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && (
                                                    <Spinner className="mr-2" />
                                                )}
                                                {t(
                                                    'classes.subjects.form.save',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}

                <Dialog
                    open={deletingItem !== null}
                    onOpenChange={(open) => !open && setDeletingItem(null)}
                >
                    <DialogContent>
                        <DialogTitle>
                            {t('classes.subjects.delete.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('classes.subjects.delete.description', {
                                subject: deletingItem?.subject?.name ?? '',
                                class: schoolClass.name,
                            })}
                        </DialogDescription>
                        <DialogFooter className="gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setDeletingItem(null)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (deletingItem) {
                                        destroy(deletingItem);
                                        setDeletingItem(null);
                                    }
                                }}
                            >
                                {t('classes.subjects.delete.confirm')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
