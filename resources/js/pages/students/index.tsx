import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

type StudentRow = {
    id: number;
    full_name: string;
    student_number: string | null;
    school_class: { id: number; name: string } | null;
};

type Paginator = {
    data: StudentRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    students: Paginator;
    filters: { search: string };
};

export default function StudentsIndex({ students, filters }: Props) {
    const [deleting, setDeleting] = useState<StudentRow | null>(null);
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('students.index.title'),
                href: StudentController.index().url,
            },
        ],
    });

    const destroy = (student: StudentRow) => {
        router.delete(StudentController.destroy({ student: student.id }).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={t('students.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('students.index.title')}
                        description={t('students.index.description')}
                    />

                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={StudentImportController.create().url}>
                                {t('students.index.import')}
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={StudentController.create().url}>
                                {t('students.index.create')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder={t('students.index.search_placeholder')}
                    />
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('common.name')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('students.index.columns.student_number')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('students.index.columns.class')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.map((student) => (
                                <tr key={student.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {student.full_name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {student.student_number ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {student.school_class ? (
                                            <Badge variant="secondary">
                                                {student.school_class.name}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        StudentController.edit({
                                                            student: student.id,
                                                        }).url
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t('common.edit')}
                                                    </span>
                                                </Link>
                                            </Button>

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    setDeleting(student)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t('common.delete')}
                                                </span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {students.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('students.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: students.current_page,
                            last: students.last_page,
                        })}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!students.prev_page_url}
                        >
                            <Link href={students.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!students.next_page_url}
                        >
                            <Link href={students.next_page_url ?? '#'}>
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
                    <DialogTitle>{t('students.delete.title')}</DialogTitle>
                    <DialogDescription>
                        {t('students.delete.description', {
                            name: deleting?.full_name ?? '',
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
                            {t('common.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
