import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import OverpaymentController from '@/actions/App/Http/Controllers/Fees/OverpaymentController';
import ManualJournalController from '@/actions/App/Http/Controllers/Finance/ManualJournalController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

type CashLine = {
    id: number;
    entry_date: string;
    number: string;
    journal_entry_id: number;
    overpayment_id: number | null;
    description: string;
    debit: string;
    credit: string;
    running_balance: string;
    student_name: string | null;
    memo: string | null;
};

type CashAccountOption = {
    id: number;
    code: string;
    name: string;
    label: string;
};

type Props = {
    report: {
        account: { id: number; code: string; name: string } | null;
        cash_accounts: CashAccountOption[];
        opening_balance: string;
        period_debits: string;
        period_credits: string;
        closing_balance: string;
        lines: CashLine[];
        from: string | null;
        to: string | null;
    };
};

export default function CashBookReportPage({ report }: Props) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('finance.common.finance'), href: '/finance/accounts' },
            { title: t('finance.reports.cash_book.title') },
        ],
    });

    const [accountId, setAccountId] = useState<string>(
        report.account ? String(report.account.id) : '',
    );
    const [from, setFrom] = useState(report.from ?? '');
    const [to, setTo] = useState(report.to ?? '');

    const applyFilter = () => {
        router.get(
            '/finance/reports/cash-book',
            {
                account_id: accountId,
                from,
                to,
            },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={t('finance.reports.cash_book.title')} />

            <div className="mx-auto max-w-5xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('finance.reports.cash_book.title')}
                        description={t('finance.reports.cash_book.description')}
                    />

                    {report.account && (
                        <Button variant="outline" asChild>
                            <a
                                href={`/finance/reports/cash-book?format=csv&account_id=${accountId}&from=${from}&to=${to}`}
                                download
                            >
                                <Download className="mr-2 h-4 w-4" />{' '}
                                {t('finance.common.export_csv')}
                            </a>
                        </Button>
                    )}
                </div>

                {/* Filter */}
                <div className="bg-card grid grid-cols-1 items-end gap-4 rounded-lg border p-4 sm:grid-cols-4">
                    <div className="space-y-1">
                        <Label htmlFor="cash_account">
                            {t('finance.reports.cash_book.select_account')}
                        </Label>
                        <Select value={accountId} onValueChange={setAccountId}>
                            <SelectTrigger id="cash_account">
                                <SelectValue placeholder="Pilih rekening" />
                            </SelectTrigger>
                            <SelectContent>
                                {report.cash_accounts.map((acc) => (
                                    <SelectItem
                                        key={acc.id}
                                        value={String(acc.id)}
                                    >
                                        {acc.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="from">
                            {t('fees.common.from_date')}
                        </Label>
                        <Input
                            id="from"
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="to">{t('fees.common.to_date')}</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>

                    <Button onClick={applyFilter}>
                        {t('finance.common.filter')}
                    </Button>
                </div>

                {/* Ledger Card */}
                {!report.account ? (
                    <div className="text-muted-foreground bg-card rounded-lg border p-8 text-center">
                        {t('finance.reports.cash_book.empty')}
                    </div>
                ) : (
                    <div className="bg-card overflow-hidden rounded-lg border">
                        <div className="bg-muted/40 flex flex-col justify-between gap-2 border-b p-4 sm:flex-row sm:items-center">
                            <div>
                                <span className="mr-2 font-mono text-base font-bold">
                                    {report.account.code}
                                </span>
                                <span className="text-base font-semibold">
                                    {report.account.name}
                                </span>
                            </div>

                            <div className="space-x-4 font-mono text-xs">
                                <span>
                                    {t(
                                        'finance.reports.cash_book.initial_balance',
                                    )}{' '}
                                    <strong>
                                        {`Rp ${Number(report.opening_balance).toLocaleString(locale)}`}
                                    </strong>
                                </span>
                                <span>
                                    {t(
                                        'finance.reports.cash_book.final_balance',
                                    )}{' '}
                                    <strong>
                                        {`Rp ${Number(report.closing_balance).toLocaleString(locale)}`}
                                    </strong>
                                </span>
                            </div>
                        </div>

                        <div className="overflow-x-auto p-2">
                            <table className="w-full text-xs">
                                <thead className="bg-muted/30 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            {t('finance.common.date')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('finance.journals.table.number')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('finance.common.description')}
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium text-emerald-700">
                                            {'Masuk / Debit (Rp)'}
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium text-rose-700">
                                            {'Keluar / Kredit (Rp)'}
                                        </th>
                                        <th className="px-3 py-2 text-right font-bold font-medium">
                                            {'Saldo Kas (Rp)'}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {'Memo'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="bg-muted/10 text-muted-foreground border-t italic">
                                        <td className="px-3 py-1.5">
                                            {report.from ?? '-'}
                                        </td>
                                        <td className="px-3 py-1.5">-</td>
                                        <td className="px-3 py-1.5 font-medium">
                                            {'SALDO AWAL KAS'}
                                        </td>
                                        <td className="px-3 py-1.5 text-right">
                                            -
                                        </td>
                                        <td className="px-3 py-1.5 text-right">
                                            -
                                        </td>
                                        <td className="px-3 py-1.5 text-right font-mono font-medium">
                                            {Number(
                                                report.opening_balance,
                                            ).toLocaleString(locale)}
                                        </td>
                                        <td className="px-3 py-1.5">-</td>
                                    </tr>

                                    {report.lines.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="text-muted-foreground px-3 py-6 text-center italic"
                                            >
                                                {t(
                                                    'finance.reports.cash_book.empty',
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        report.lines.map((line) => (
                                            <tr
                                                key={line.id}
                                                className="hover:bg-muted/20 border-t"
                                            >
                                                <td className="px-3 py-1.5 whitespace-nowrap">
                                                    {line.entry_date}
                                                </td>
                                                <td className="text-primary px-3 py-1.5 font-mono">
                                                    <Link
                                                        className="underline"
                                                        href={ManualJournalController.show(
                                                            line.journal_entry_id,
                                                        )}
                                                    >
                                                        {line.number}
                                                    </Link>
                                                    {line.overpayment_id && (
                                                        <Link
                                                            className="ml-2 underline"
                                                            href={OverpaymentController.show(
                                                                line.overpayment_id,
                                                            )}
                                                        >
                                                            {'Kelebihan #'}
                                                            {
                                                                line.overpayment_id
                                                            }
                                                        </Link>
                                                    )}
                                                </td>
                                                <td className="max-w-xs truncate px-3 py-1.5">
                                                    {line.description}
                                                </td>
                                                <td className="px-3 py-1.5 text-right font-mono text-emerald-700">
                                                    {Number(line.debit) > 0
                                                        ? Number(
                                                              line.debit,
                                                          ).toLocaleString(
                                                              locale,
                                                          )
                                                        : '-'}
                                                </td>
                                                <td className="px-3 py-1.5 text-right font-mono text-rose-700">
                                                    {Number(line.credit) > 0
                                                        ? Number(
                                                              line.credit,
                                                          ).toLocaleString(
                                                              locale,
                                                          )
                                                        : '-'}
                                                </td>
                                                <td className="px-3 py-1.5 text-right font-mono font-semibold">
                                                    {Number(
                                                        line.running_balance,
                                                    ).toLocaleString(locale)}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-1.5">
                                                    {line.memo ?? '-'}
                                                </td>
                                            </tr>
                                        ))
                                    )}

                                    <tr className="bg-muted/40 border-t-2 font-mono font-semibold">
                                        <td
                                            colSpan={3}
                                            className="px-3 py-2 text-right"
                                        >
                                            {
                                                'TOTAL PENERIMAAN / PENGELUARAN & SALDO AKHIR:'
                                            }
                                        </td>
                                        <td className="px-3 py-2 text-right text-emerald-700">
                                            {Number(
                                                report.period_debits,
                                            ).toLocaleString(locale)}
                                        </td>
                                        <td className="px-3 py-2 text-right text-rose-700">
                                            {Number(
                                                report.period_credits,
                                            ).toLocaleString(locale)}
                                        </td>
                                        <td className="px-3 py-2 text-right font-bold text-emerald-700">
                                            {Number(
                                                report.closing_balance,
                                            ).toLocaleString(locale)}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
