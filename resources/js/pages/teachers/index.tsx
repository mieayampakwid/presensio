import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import Heading from '@/components/heading';
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

type TeacherRow = {
    id: number;
    name: string;
    teacher_number: string | null;
    phone_number: string | null;
};

type Paginator = {
    data: TeacherRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    teachers: Paginator;
    filters: { search: string };
};

export default function TeachersIndex({ teachers, filters }: Props) {
    const [deleting, setDeleting] = useState<TeacherRow | null>(null);
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('teachers.index.title'),
                href: TeacherController.index().url,
            },
        ],
    });

    const destroy = (teacher: TeacherRow) => {
        router.delete(TeacherController.destroy({ teacher: teacher.id }).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={t('teachers.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('teachers.index.title')}
                        description={t('teachers.index.description')}
                    />

                    <Button asChild>
                        <Link href={TeacherController.create().url}>
                            {t('teachers.index.create')}
                        </Link>
                    </Button>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder={t('teachers.index.search_placeholder')}
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
                                    {t('teachers.index.columns.teacher_number')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('common.phone')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {teachers.data.map((teacher) => (
                                <tr key={teacher.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {teacher.name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {teacher.teacher_number ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {teacher.phone_number ?? '—'}
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
                                                        TeacherController.edit({
                                                            teacher: teacher.id,
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
                                                    setDeleting(teacher)
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

                            {teachers.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('teachers.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: teachers.current_page,
                            last: teachers.last_page,
                        })}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!teachers.prev_page_url}
                        >
                            <Link href={teachers.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!teachers.next_page_url}
                        >
                            <Link href={teachers.next_page_url ?? '#'}>
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
                    <DialogTitle>{t('teachers.delete.title')}</DialogTitle>
                    <DialogDescription>
                        {t('teachers.delete.description', {
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
                            {t('common.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
