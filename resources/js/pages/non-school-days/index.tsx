import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Trash2 } from 'lucide-react';
import { useState } from 'react';
import NonSchoolDayController from '@/actions/App/Http/Controllers/Calendar/NonSchoolDayController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Day = {
    id: number;
    date: string;
    name: string;
    source: 'sync' | 'manual';
};

type Paginator = {
    data: Day[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

export default function NonSchoolDaysIndex({ days }: { days: Paginator }) {
    const { t } = useTranslation();
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<Day | null>(null);

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('non_school_days.index.title'),
                href: NonSchoolDayController.index().url,
            },
        ],
    });
    const destroy = (day: Day) => {
        router.delete(
            NonSchoolDayController.destroy({ non_school_day: day.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('non_school_days.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('non_school_days.index.title')}
                        description={t('non_school_days.index.description')}
                    />

                    <Button onClick={() => setIsCreateOpen(true)}>
                        {t('non_school_days.index.add')}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('non_school_days.index.columns.date')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('non_school_days.index.columns.name')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('non_school_days.index.columns.source')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {days.data.map((day) => (
                                <tr key={day.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {day.date}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {day.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {day.source === 'sync' ? (
                                            <Badge variant="secondary">
                                                {t(
                                                    'non_school_days.source.sync',
                                                )}
                                            </Badge>
                                        ) : (
                                            <Badge>
                                                {t(
                                                    'non_school_days.source.manual',
                                                )}
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            onClick={() => setDeleting(day)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                            <span className="sr-only">
                                                {t(
                                                    'non_school_days.index.delete',
                                                )}
                                            </span>
                                        </Button>
                                    </td>
                                </tr>
                            ))}

                            {days.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('non_school_days.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: days.current_page,
                            last: days.last_page,
                        })}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!days.prev_page_url}
                        >
                            <Link href={days.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!days.next_page_url}
                        >
                            <Link href={days.next_page_url ?? '#'}>
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
                    <DialogTitle>
                        {t('non_school_days.index.remove_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('non_school_days.index.remove_description', {
                            date: deleting?.date ?? '',
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
                            {t('non_school_days.index.remove_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('non_school_days.form.create_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('non_school_days.form.create_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...NonSchoolDayController.store.form()}
                        onSuccess={() => setIsCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-date">
                                        {t('non_school_days.form.date')}
                                    </Label>
                                    <Input
                                        id="create-date"
                                        type="date"
                                        name="date"
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-name">
                                        {t('non_school_days.form.name')}
                                    </Label>
                                    <Input
                                        id="create-name"
                                        name="name"
                                        required
                                        maxLength={255}
                                        placeholder={t(
                                            'non_school_days.form.name_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.name} />
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
                                        {t(
                                            'non_school_days.form.submit_create',
                                        )}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
