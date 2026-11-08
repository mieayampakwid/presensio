import { Head, router, setLayoutProps } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type AccountRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    normal_balance: string;
    sum_debit: string;
    sum_credit: string;
    debit_balance: string;
    credit_balance: string;
};

type Props = {
    report: {
        accounts: AccountRow[];
        total_debit: string;
        total_credit: string;
        difference: string;
        is_balanced: boolean;
        as_of: string | null;
    };
};

export default function TrialBalanceReportPage({ report }: Props) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('finance.common.finance'), href: '/finance/accounts' },
            { title: t('finance.reports.trial_balance.title') },
        ],
    });

    const [asOf, setAsOf] = useState(report.as_of ?? '');

    const applyFilter = () => {
        router.get(
            '/finance/reports/trial-balance',
            { as_of: asOf },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={t('finance.reports.trial_balance.title')} />

            <div className="mx-auto max-w-5xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('finance.reports.trial_balance.title')}
                        description={t(
                            'finance.reports.trial_balance.description',
                        )}
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/trial-balance?format=csv&as_of=${asOf}`}
                            download
                        >
                            <Download className="mr-2 h-4 w-4" />{' '}
                            {t('finance.common.export_csv')}
                        </a>
                    </Button>
                </div>

                {/* Filter and Status */}
                <div className="bg-card flex flex-col justify-between gap-4 rounded-lg border p-4 sm:flex-row sm:items-center">
                    <div className="flex items-end gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="as_of">
                                {t('finance.reports.trial_balance.as_of', {
                                    defaultValue: 'Posisi Per Tanggal',
                                })}
                            </Label>
                            <Input
                                id="as_of"
                                type="date"
                                value={asOf}
                                onChange={(e) => setAsOf(e.target.value)}
                            />
                        </div>
                        <Button onClick={applyFilter}>
                            {t('finance.common.filter')}
                        </Button>
                    </div>

                    <div>
                        {report.is_balanced ? (
                            <div className="flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40">
                                <CheckCircle2 className="mr-1.5 h-4 w-4" />{' '}
                                {t('finance.reports.trial_balance.balanced')}
                            </div>
                        ) : (
                            <div className="text-destructive bg-destructive/10 border-destructive/20 flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold">
                                <AlertCircle className="mr-1.5 h-4 w-4" />
                                {t('finance.common.difference')}:{' '}
                                {`Rp ${Number(report.difference).toLocaleString(locale)}`}
                            </div>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.accounts.table.code')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.accounts.table.name')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t(
                                            'finance.accounts.table.classification',
                                        )}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t(
                                            'finance.accounts.fields.normal_balance',
                                            { defaultValue: 'Saldo Normal' },
                                        )}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {t('finance.common.debit')}
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        {t('finance.common.credit')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.accounts.map((acc) => (
                                    <tr
                                        key={acc.id}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-2 font-mono font-medium">
                                            {acc.code}
                                        </td>
                                        <td className="px-4 py-2 font-medium">
                                            {acc.name}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge
                                                variant="outline"
                                                className="text-xs uppercase"
                                            >
                                                {acc.type}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2 font-mono text-xs uppercase">
                                            {acc.normal_balance}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {Number(acc.debit_balance) > 0
                                                ? Number(
                                                      acc.debit_balance,
                                                  ).toLocaleString(locale)
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {Number(acc.credit_balance) > 0
                                                ? Number(
                                                      acc.credit_balance,
                                                  ).toLocaleString(locale)
                                                : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-muted/60 border-t-2 font-mono text-sm font-bold">
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-3 text-right"
                                    >
                                        {t('finance.common.total')}:
                                    </td>
                                    <td className="px-4 py-3 text-right text-blue-700 dark:text-blue-400">
                                        {`Rp ${Number(report.total_debit).toLocaleString(locale)}`}
                                    </td>
                                    <td className="px-4 py-3 text-right text-amber-700 dark:text-amber-400">
                                        {`Rp ${Number(report.total_credit).toLocaleString(locale)}`}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
