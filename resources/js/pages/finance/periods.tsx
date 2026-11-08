import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Info, Lock, Unlock } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import PeriodController from '@/actions/App/Http/Controllers/Finance/PeriodController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type PeriodRow = {
    period: string;
    status: 'open' | 'closed' | 'locked';
    is_open: boolean;
    closed_at: string | null;
    closed_by: string | null;
};

type ClosableYear = {
    fiscal_year_start: string;
    label: string;
    is_closable: boolean;
    reason: string | null;
};

type Props = {
    canManage: boolean;
    periods: PeriodRow[];
    closableYears: ClosableYear[];
};

export default function PeriodsIndex({
    canManage,
    periods,
    closableYears,
}: Props) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('finance.common.finance'), href: '/finance/accounts' },
            { title: t('finance.periods.title') },
        ],
    });

    const [closingYear, setClosingYear] = useState<ClosableYear | null>(null);
    const [reopeningPeriod, setReopeningPeriod] = useState<PeriodRow | null>(
        null,
    );
    const [closingPeriod, setClosingPeriod] = useState<PeriodRow | null>(null);

    const reopenForm = useForm({
        reason: '',
    });

    const handleCloseConfirm = () => {
        if (!canManage || !closingPeriod) return;
        router.post(
            PeriodController.close.url(closingPeriod.period),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setClosingPeriod(null),
            },
        );
    };

    const handleCloseYearConfirm = () => {
        if (!canManage || !closingYear) return;
        router.post(
            PeriodController.closeYear.url(),
            { fiscal_year_start: closingYear.fiscal_year_start },
            {
                preserveScroll: true,
                onSuccess: () => setClosingYear(null),
            },
        );
    };

    const handleReopenSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!canManage || !reopeningPeriod) return;
        reopenForm.post(PeriodController.reopen.url(reopeningPeriod.period), {
            preserveScroll: true,
            onSuccess: () => {
                setReopeningPeriod(null);
                reopenForm.reset();
            },
        });
    };

    return (
        <>
            <Head title={t('finance.periods.title')} />

            <div className="mx-auto max-w-5xl space-y-6 p-4">
                <Heading
                    title={t('finance.periods.title')}
                    description={t('finance.periods.description')}
                />

                {/* Preconditions Notice */}
                <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/70 p-4 text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300">
                    <Info className="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                    <div className="space-y-1 text-xs">
                        <span className="font-semibold">
                            {t('finance.periods.preconditions_title', {
                                defaultValue: 'Prasyarat Tutup Buku Bulanan:',
                            })}
                        </span>
                        <p>
                            {t('finance.periods.preconditions_body', {
                                defaultValue:
                                    'Sebelum periode ditutup, sistem akan memverifikasi bahwa Neraca Saldo (Trial Balance) per akhir bulan tersebut seimbang (Total Debit = Total Kredit). Setelah ditutup, transaksi baru atau pembalikan tidak dapat dimasukkan ke tanggal periode ini.',
                            })}
                        </p>
                    </div>
                </div>

                {canManage && closableYears.length > 0 && (
                    <div className="bg-card space-y-3 rounded-lg border p-4">
                        <div>
                            <h2 className="font-semibold">
                                {t('finance.periods.close_year')}
                            </h2>
                            <p className="text-muted-foreground text-xs">
                                {t('finance.periods.year_end_dialog.message')}
                            </p>
                        </div>
                        {closableYears.map((year) => (
                            <div
                                key={year.fiscal_year_start}
                                className="flex items-center justify-between gap-3 border-t pt-3"
                            >
                                <div>
                                    <div className="font-mono font-bold">
                                        {year.label}
                                    </div>
                                    {year.reason && (
                                        <div className="text-muted-foreground text-xs">
                                            {year.reason}
                                        </div>
                                    )}
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!year.is_closable}
                                    onClick={() => setClosingYear(year)}
                                >
                                    <Lock className="mr-1 h-3.5 w-3.5" />{' '}
                                    {t('finance.periods.close_year')}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}

                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.periods.table.period')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.periods.table.status')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.periods.table.closed')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.periods.table.closed_by')}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {t('finance.common.actions')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {periods.map((p) => (
                                    <tr
                                        key={p.period}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-3 font-mono text-base font-bold">
                                            {p.period}
                                        </td>
                                        <td className="px-4 py-3">
                                            {p.status === 'locked' ? (
                                                <Badge
                                                    variant="default"
                                                    className="bg-red-800"
                                                >
                                                    <Lock className="mr-1 h-3 w-3" />{' '}
                                                    {t(
                                                        'finance.periods.table.locked',
                                                    )}
                                                </Badge>
                                            ) : p.is_open ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30"
                                                >
                                                    <Unlock className="mr-1 h-3 w-3" />{' '}
                                                    {t(
                                                        'finance.periods.table.open',
                                                    )}
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="default"
                                                    className="bg-slate-700"
                                                >
                                                    <Lock className="mr-1 h-3 w-3" />{' '}
                                                    {t(
                                                        'finance.periods.table.closed',
                                                    )}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 text-xs whitespace-nowrap">
                                            {p.closed_at
                                                ? new Date(
                                                      p.closed_at,
                                                  ).toLocaleDateString(locale, {
                                                      year: 'numeric',
                                                      month: 'short',
                                                      day: 'numeric',
                                                      hour: '2-digit',
                                                      minute: '2-digit',
                                                  })
                                                : '-'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 text-xs">
                                            {p.closed_by ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {!canManage ||
                                            p.status ===
                                                'locked' ? null : p.is_open ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setClosingPeriod(p)
                                                    }
                                                >
                                                    <Lock className="mr-1 h-3.5 w-3.5" />{' '}
                                                    {t(
                                                        'finance.periods.table.close',
                                                    )}
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setReopeningPeriod(p)
                                                    }
                                                >
                                                    <Unlock className="mr-1 h-3.5 w-3.5" />{' '}
                                                    {t(
                                                        'finance.periods.table.reopen',
                                                    )}
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Close Confirm Dialog */}
            <Dialog
                open={canManage && closingPeriod !== null}
                onOpenChange={(open) => !open && setClosingPeriod(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('finance.periods.close_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('finance.periods.close_dialog.message', {
                                period: closingPeriod?.period ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setClosingPeriod(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={handleCloseConfirm}>
                            {t('finance.periods.close_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Year-End Close Dialog */}
            <Dialog
                open={canManage && closingYear !== null}
                onOpenChange={(open) => !open && setClosingYear(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('finance.periods.year_end_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('finance.periods.year_end_dialog.message')}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setClosingYear(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button onClick={handleCloseYearConfirm}>
                            {t('finance.periods.year_end_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reopen Dialog */}
            <Dialog
                open={canManage && reopeningPeriod !== null}
                onOpenChange={(open) => !open && setReopeningPeriod(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('finance.periods.reopen_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('finance.periods.reopen_dialog.message', {
                                period: reopeningPeriod?.period ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleReopenSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="reopen-reason">
                                {t('finance.periods.reopen_dialog.reason')}
                            </Label>
                            <Input
                                id="reopen-reason"
                                placeholder={t(
                                    'finance.periods.reopen_dialog.reason_placeholder',
                                )}
                                value={reopenForm.data.reason}
                                onChange={(e) =>
                                    reopenForm.setData('reason', e.target.value)
                                }
                                required
                            />
                            {reopenForm.errors.reason && (
                                <p className="text-destructive text-xs">
                                    {reopenForm.errors.reason}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setReopeningPeriod(null)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={reopenForm.processing}
                            >
                                {t('finance.periods.reopen_dialog.confirm')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
