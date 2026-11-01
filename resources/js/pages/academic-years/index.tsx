import { Head, Form, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarDays, Pencil, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import AcademicYearController from '@/actions/App/Http/Controllers/AcademicYears/AcademicYearController';
import SemesterController from '@/actions/App/Http/Controllers/AcademicYears/SemesterController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Year = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    classes_count: number;
};

type Props = {
    years: Year[];
};

export default function AcademicYearsIndex({ years }: Props) {
    const [editingYear, setEditingYear] = useState<Year | null>(null);
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('academic_years.index.title'),
                href: AcademicYearController.index().url,
            },
        ],
    });

    return (
        <>
            <Head title={t('academic_years.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('academic_years.index.title')}
                        description={t('academic_years.index.description')}
                    />

                    <Button variant="outline" asChild>
                        <Link href={AcademicYearController.rollOver().url}>
                            <RotateCcw className="h-4 w-4" />
                            {t('academic_years.index.roll_over')}
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('academic_years.index.columns.year')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('academic_years.index.columns.period')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('academic_years.index.columns.classes')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('academic_years.index.columns.status')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {years.map((year) => (
                                <tr key={year.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {year.name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {year.starts_at} — {year.ends_at}
                                    </td>
                                    <td className="px-4 py-3">
                                        {year.classes_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        {year.is_active ? (
                                            <Badge>
                                                {t(
                                                    'academic_years.index.active',
                                                )}
                                            </Badge>
                                        ) : (
                                            <Form
                                                {...AcademicYearController.activate.form(
                                                    {
                                                        academic_year: year.id,
                                                    },
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        {t(
                                                            'academic_years.index.set_active',
                                                        )}
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        SemesterController.edit(
                                                            {
                                                                academic_year:
                                                                    year.id,
                                                            },
                                                        ).url
                                                    }
                                                    title={t(
                                                        'academic_years.index.manage_semesters',
                                                    )}
                                                >
                                                    <CalendarDays className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title={t(
                                                    'academic_years.index.edit_year',
                                                )}
                                                onClick={() =>
                                                    setEditingYear(year)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t(
                                                        'academic_years.index.edit_year',
                                                    )}
                                                </span>
                                            </Button>
                                            {!year.is_active && (
                                                <Form
                                                    {...AcademicYearController.destroy.form(
                                                        {
                                                            academic_year:
                                                                year.id,
                                                        },
                                                    )}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            type="submit"
                                                            disabled={
                                                                processing ||
                                                                year.classes_count >
                                                                    0
                                                            }
                                                            title={
                                                                year.classes_count >
                                                                0
                                                                    ? t(
                                                                          'academic_years.index.delete_blocked',
                                                                      )
                                                                    : t(
                                                                          'academic_years.index.delete_title',
                                                                      )
                                                            }
                                                        >
                                                            {t(
                                                                'academic_years.index.delete',
                                                            )}
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {years.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('academic_years.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <CreateYearDialog />
                </div>
            </div>
            <Dialog
                open={editingYear !== null}
                onOpenChange={(open) => !open && setEditingYear(null)}
            >
                <DialogContent>
                    {editingYear && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('academic_years.edit.title', {
                                        name: editingYear.name,
                                    })}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('academic_years.edit.description')}
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                key={editingYear.id}
                                {...AcademicYearController.update.form({
                                    academic_year: editingYear.id,
                                })}
                                onSuccess={() => setEditingYear(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`name-${editingYear.id}`}
                                            >
                                                {t('academic_years.form.name')}
                                            </Label>
                                            <Input
                                                id={`name-${editingYear.id}`}
                                                name="name"
                                                defaultValue={editingYear.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`starts-${editingYear.id}`}
                                                >
                                                    {t(
                                                        'academic_years.form.starts_at',
                                                    )}
                                                </Label>
                                                <Input
                                                    id={`starts-${editingYear.id}`}
                                                    type="date"
                                                    name="starts_at"
                                                    defaultValue={
                                                        editingYear.starts_at
                                                    }
                                                />
                                                <InputError
                                                    message={errors.starts_at}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`ends-${editingYear.id}`}
                                                >
                                                    {t(
                                                        'academic_years.form.ends_at',
                                                    )}
                                                </Label>
                                                <Input
                                                    id={`ends-${editingYear.id}`}
                                                    type="date"
                                                    name="ends_at"
                                                    defaultValue={
                                                        editingYear.ends_at
                                                    }
                                                />
                                                <InputError
                                                    message={errors.ends_at}
                                                />
                                            </div>
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() =>
                                                    setEditingYear(null)
                                                }
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {t(
                                                    'academic_years.form.submit_update',
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

function CreateYearDialog() {
    const [open, setOpen] = useState(false);
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>{t('academic_years.index.create_year')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('academic_years.create.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('academic_years.create.description')}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AcademicYearController.store.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('academic_years.form.name')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder={t(
                                        'academic_years.form.name_placeholder',
                                    )}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">
                                        {t('academic_years.form.starts_at')}
                                    </Label>
                                    <Input
                                        id="starts_at"
                                        type="date"
                                        name="starts_at"
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">
                                        {t('academic_years.form.ends_at')}
                                    </Label>
                                    <Input
                                        id="ends_at"
                                        type="date"
                                        name="ends_at"
                                    />
                                    <InputError message={errors.ends_at} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        {t('common.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {t('academic_years.form.submit_create')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
