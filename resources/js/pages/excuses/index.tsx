import { router, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import ExcuseReviewController from '@/actions/App/Http/Controllers/Excuses/ExcuseReviewController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { STATUS_BADGES } from '@/lib/attendance';
import { EXCUSE_STATUS_BADGES } from '@/lib/excuses';

type DayState = {
    date: string;
    status: string | null;
};

type ExcuseRow = {
    id: number;
    student_name: string;
    class_name: string | null;
    type: string;
    start_date: string;
    end_date: string;
    reason: string;
    has_attachment: boolean;
    attachment_url: string | null;
    status: string;
    review_note: string | null;
    submitted_at: string | null;
    days: DayState[];
};

type Paginator = {
    data: ExcuseRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    excuses: Paginator;
    can_review: boolean;
};

type ReviewTarget = {
    excuse: ExcuseRow;
    action: 'approve' | 'reject';
};

const TEXTAREA_CLASS =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

function StatusBadge({ status }: { status: string }) {
    const { t } = useTranslation();

    return (
        <Badge variant={EXCUSE_STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {t(`excuses.statuses.${status}`)}
        </Badge>
    );
}

export default function Excuses({ excuses, can_review }: Props) {
    const { t } = useTranslation();
    const [target, setTarget] = useState<ReviewTarget | null>(null);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const closeDialog = () => {
        setTarget(null);
        setNote('');
    };

    const resolve = () => {
        if (target === null) {
            return;
        }

        const url =
            target.action === 'approve'
                ? ExcuseReviewController.approve({ excuse: target.excuse.id })
                      .url
                : ExcuseReviewController.reject({ excuse: target.excuse.id })
                      .url;

        setProcessing(true);
        router.put(
            url,
            { review_note: note || null },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    closeDialog();
                },
            },
        );
    };

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('excuses.index.title'),
            },
        ],
    });

    return (
        <>
            <Head title={t('excuses.index.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('excuses.index.title')}
                    description={t('excuses.index.description')}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.student')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.dates')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.category')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.reason')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t(
                                        'excuses.index.columns.attendance_in_range',
                                    )}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.proof')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.status')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('excuses.index.columns.review_note')}
                                </th>
                                {can_review && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        {t('excuses.index.columns.actions')}
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {excuses.data.map((excuse) => (
                                <tr key={excuse.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <span className="font-medium">
                                            {excuse.student_name}
                                        </span>
                                        {excuse.class_name && (
                                            <span className="text-muted-foreground block text-xs">
                                                {excuse.class_name}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {excuse.start_date === excuse.end_date
                                            ? excuse.start_date
                                            : `${excuse.start_date} – ${excuse.end_date}`}
                                    </td>
                                    <td className="px-4 py-3">
                                        {t(`excuses.types.${excuse.type}`)}
                                    </td>
                                    <td
                                        className="max-w-48 truncate px-4 py-3"
                                        title={excuse.reason}
                                    >
                                        {excuse.reason}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {excuse.days.map((day) => (
                                                <span
                                                    key={day.date}
                                                    title={`${day.date}: ${
                                                        day.status
                                                            ? t(
                                                                  `attendance.statuses.${day.status}`,
                                                              )
                                                            : t(
                                                                  'attendance.index.no_record',
                                                              )
                                                    }`}
                                                >
                                                    <Badge
                                                        variant={
                                                            day.status
                                                                ? (STATUS_BADGES[
                                                                      day.status
                                                                  ]?.variant ??
                                                                  'secondary')
                                                                : 'outline'
                                                        }
                                                    >
                                                        {day.date.slice(8)}
                                                    </Badge>
                                                </span>
                                            ))}
                                            {excuse.days.length === 0 && (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {excuse.attachment_url ? (
                                            <a
                                                href={excuse.attachment_url}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {t('excuses.index.download')}
                                            </a>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={excuse.status} />
                                    </td>
                                    <td className="text-muted-foreground max-w-40 px-4 py-3">
                                        {excuse.review_note ?? '—'}
                                    </td>
                                    {can_review && (
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            {excuse.status === 'pending' ? (
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        onClick={() => {
                                                            setNote('');
                                                            setTarget({
                                                                excuse,
                                                                action: 'approve',
                                                            });
                                                        }}
                                                    >
                                                        {t(
                                                            'excuses.index.approve',
                                                        )}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() => {
                                                            setNote('');
                                                            setTarget({
                                                                excuse,
                                                                action: 'reject',
                                                            });
                                                        }}
                                                    >
                                                        {t(
                                                            'excuses.index.reject',
                                                        )}
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}

                            {excuses.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={can_review ? 9 : 8}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('excuses.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: excuses.current_page,
                            last: excuses.last_page,
                        })}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!excuses.prev_page_url}
                        >
                            <Link href={excuses.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!excuses.next_page_url}
                        >
                            <Link href={excuses.next_page_url ?? '#'}>
                                {t('common.next')}
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={target !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeDialog();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {target?.action === 'approve'
                                ? t('excuses.index.approve_title')
                                : t('excuses.index.reject_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {target?.action === 'approve'
                                ? t('excuses.index.approve_description', {
                                      name: target.excuse.student_name,
                                      start: target.excuse.start_date,
                                      end: target.excuse.end_date,
                                      type: t(
                                          `excuses.types.${target.excuse.type}`,
                                      ),
                                  })
                                : t('excuses.index.reject_description', {
                                      name: target?.excuse.student_name ?? '',
                                  })}{' '}
                            {t('excuses.index.review_note_visible')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="review_note">
                            {t('excuses.index.review_note_optional')}
                        </Label>
                        <textarea
                            id="review_note"
                            rows={3}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder={t(
                                'excuses.index.review_note_placeholder',
                            )}
                            className={TEXTAREA_CLASS}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={closeDialog}
                            disabled={processing}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            variant={
                                target?.action === 'approve'
                                    ? 'default'
                                    : 'destructive'
                            }
                            onClick={resolve}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {target?.action === 'approve'
                                ? t('excuses.index.approve')
                                : t('excuses.index.reject')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
