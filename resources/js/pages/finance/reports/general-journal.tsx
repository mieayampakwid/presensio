import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type Line = {
    id: number;
    account_code: string;
    account_name: string;
    debit: string;
    credit: string;
    student_name: string | null;
    memo: string | null;
};

type Entry = {
    id: number;
    number: string;
    entry_date: string;
    description: string;
    source_type: string;
    source_type_label: string;
    author: string | null;
    total_debit: string;
    total_credit: string;
    lines: Line[];
};

type Props = {
    report: {
        entries: Entry[];
        total_debit: string;
        total_credit: string;
        from: string | null;
        to: string | null;
    };
};

export default function GeneralJournalReportPage({ report }: Props) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('finance.common.finance'), href: '/finance/accounts' },
            { title: t('finance.reports.general_journal.title') },
        ],
    });

    const [from, setFrom] = useState(report.from ?? '');
    const [to, setTo] = useState(report.to ?? '');

    const applyFilter = () => {
        router.get(
            '/finance/reports/general-journal',
            { from, to },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={t('finance.reports.general_journal.title')} />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('finance.reports.general_journal.title')}
                        description={t(
                            'finance.reports.general_journal.description',
                        )}
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/general-journal?format=csv&from=${from}&to=${to}`}
                            download
                        >
                            <Download className="mr-2 h-4 w-4" />{' '}
                            {t('finance.common.export_csv')}
                        </a>
                    </Button>
                </div>

                {/* Filter */}
                <div className="bg-card flex flex-wrap items-end gap-4 rounded-lg border p-4">
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

                {/* Table */}
                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.common.date')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.journals.table.number')} &{' '}
                                        {t('finance.common.description')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('finance.common.account')}
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
                                {report.entries.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="text-muted-foreground px-4 py-8 text-center"
                                        >
                                            {t(
                                                'finance.reports.general_journal.empty',
                                            )}
                                        </td>
                                    </tr>
                                ) : (
                                    report.entries.map((entry) => (
                                        <Fragment key={entry.id}>
                                            <tr className="bg-muted/10 border-t font-medium">
                                                <td className="text-muted-foreground px-4 py-2 text-xs whitespace-nowrap">
                                                    {entry.entry_date}
                                                </td>
                                                <td
                                                    colSpan={4}
                                                    className="px-4 py-2"
                                                >
                                                    <span className="text-primary mr-2 font-mono">
                                                        {entry.number}
                                                    </span>
                                                    <span>
                                                        {entry.description}
                                                    </span>
                                                </td>
                                            </tr>
                                            {entry.lines.map((line) => (
                                                <tr
                                                    key={line.id}
                                                    className="border-muted/30 border-t"
                                                >
                                                    <td></td>
                                                    <td className="text-muted-foreground px-4 py-1.5 text-xs">
                                                        {line.memo && (
                                                            <span>
                                                                ↳ {line.memo}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-1.5">
                                                        <span className="text-muted-foreground mr-2 font-mono text-xs">
                                                            {line.account_code}
                                                        </span>
                                                        <span>
                                                            {line.account_name}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-1.5 text-right font-mono text-xs">
                                                        {Number(line.debit) > 0
                                                            ? Number(
                                                                  line.debit,
                                                              ).toLocaleString(
                                                                  locale,
                                                              )
                                                            : '-'}
                                                    </td>
                                                    <td className="px-4 py-1.5 text-right font-mono text-xs">
                                                        {Number(line.credit) > 0
                                                            ? Number(
                                                                  line.credit,
                                                              ).toLocaleString(
                                                                  locale,
                                                              )
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </Fragment>
                                    ))
                                )}
                            </tbody>
                            <tfoot className="bg-muted/50 border-t-2 font-mono text-sm font-bold">
                                <tr>
                                    <td
                                        colSpan={3}
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
