import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Info, Plus, Trash2 } from 'lucide-react';
import React from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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

type AccountOption = {
    id: number;
    code: string;
    name: string;
    type: string;
    normal_balance: string;
    label: string;
};

type StudentOption = {
    id: number;
    name: string;
    nis: string;
    label: string;
};

type LineInput = {
    account_id: number | '';
    debit: string;
    credit: string;
    student_id: number | '' | null;
    memo: string;
};

type Props = {
    journal: {
        id: number;
        entry_date: string;
        description: string;
        has_attachment: boolean;
        lines: LineInput[];
    } | null;
    accounts: AccountOption[];
    students: StudentOption[];
};

export default function JournalForm({ journal, accounts, students }: Props) {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Keuangan',
                href: '/finance/accounts',
            },
            {
                title: 'Jurnal Manual',
                href: '/finance/journals',
            },
            {
                title: 'Formulir Jurnal',
            },
        ],
    });

    const { t, locale } = useTranslation();

    const isEditing = journal !== null;

    const defaultLines: LineInput[] = isEditing
        ? journal.lines
        : [
              {
                  account_id: '',
                  debit: '',
                  credit: '',
                  student_id: null,
                  memo: '',
              },
              {
                  account_id: '',
                  debit: '',
                  credit: '',
                  student_id: null,
                  memo: '',
              },
          ];

    const form = useForm({
        entry_date:
            journal?.entry_date ?? new Date().toISOString().slice(0, 10),
        description: journal?.description ?? '',
        attachment: null as File | null,
        action: 'post',
        lines: defaultLines,
    });

    const addLine = () => {
        form.setData('lines', [
            ...form.data.lines,
            {
                account_id: '',
                debit: '',
                credit: '',
                student_id: null,
                memo: '',
            },
        ]);
    };

    const removeLine = (index: number) => {
        if (form.data.lines.length <= 2) return;
        const newLines = form.data.lines.filter((_, i) => i !== index);
        form.setData('lines', newLines);
    };

    const updateLine = (index: number, field: keyof LineInput, value: any) => {
        const newLines = [...form.data.lines];
        newLines[index] = { ...newLines[index], [field]: value };

        // If updating debit, and debit > 0, reset credit to 0
        if (field === 'debit' && Number(value) > 0) {
            newLines[index].credit = '';
        }
        // If updating credit, and credit > 0, reset debit to 0
        if (field === 'credit' && Number(value) > 0) {
            newLines[index].debit = '';
        }

        form.setData('lines', newLines);
    };

    // Calculate totals
    const totalDebit = form.data.lines.reduce(
        (sum, line) => sum + (Number(line.debit) || 0),
        0,
    );
    const totalCredit = form.data.lines.reduce(
        (sum, line) => sum + (Number(line.credit) || 0),
        0,
    );
    const diff = Math.abs(totalDebit - totalCredit);
    const isBalanced = totalDebit > 0 && diff === 0;

    /**
     * The server validates each line as `lines.<index>.<field>`, so the errors
     * bag is read by string key instead of a top-level form field.
     */
    const lineError = (key: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[key];

    const handleSubmit = (actionType: 'draft' | 'post') => {
        form.setData('action', actionType);

        if (isEditing) {
            form.put(`/finance/journals/${journal.id}`);
        } else {
            form.post('/finance/journals');
        }
    };

    return (
        <>
            <Head
                title={
                    isEditing
                        ? t('finance.journals.form.title_edit')
                        : t('finance.journals.form.title_create')
                }
            />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={
                            isEditing
                                ? t('finance.journals.form.title_edit')
                                : t('finance.journals.form.title_create')
                        }
                        description="Input ayat jurnal umum debit dan kredit untuk operasional atau saldo awal."
                    />

                    <Button variant="outline" asChild>
                        <Link href="/finance/journals">
                            {t('finance.journals.show.back')}
                        </Link>
                    </Button>
                </div>

                {/* Opening Balance Tip */}
                <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/70 p-4 text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300">
                    <Info className="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                    <div className="space-y-1 text-xs">
                        <span className="font-semibold">
                            {t('finance.journals.form.tip_title', {
                                defaultValue:
                                    'Petunjuk Saldo Awal (Opening Balance):',
                            })}
                        </span>
                        <p>
                            {t('finance.journals.form.tip_body', {
                                defaultValue:
                                    'Untuk mencatat saldo awal buku besar sebelum tanggal go-live, gunakan tanggal H-1 (misal 30 Juni 2026). Masukkan aset/kas di sisi Debit, dan akun lawan di sisi Kredit menggunakan 3-1000 Aset Neto Tanpa Pembatasan. Saldo piutang per-siswa akan diatur melalui modul tagihan siswa.',
                            })}
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        handleSubmit('post');
                    }}
                    className="space-y-6"
                >
                    {/* Header info */}
                    <div className="bg-card grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label htmlFor="entry_date">
                                {t('finance.journals.form.date')}
                            </Label>
                            <Input
                                id="entry_date"
                                type="date"
                                value={form.data.entry_date}
                                onChange={(e) =>
                                    form.setData('entry_date', e.target.value)
                                }
                                required
                            />
                            {form.errors.entry_date && (
                                <p className="text-destructive text-xs">
                                    {form.errors.entry_date}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="description">
                                {t('finance.journals.form.memo')}
                            </Label>
                            <Input
                                id="description"
                                placeholder={t(
                                    'finance.journals.form.memo_placeholder',
                                )}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                required
                            />
                            {form.errors.description && (
                                <p className="text-destructive text-xs">
                                    {form.errors.description}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2 md:col-span-3">
                            <Label htmlFor="attachment">
                                {t('finance.journals.form.attachment')}
                            </Label>
                            <Input
                                id="attachment"
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                onChange={(e) =>
                                    form.setData(
                                        'attachment',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                {
                                    'Format: JPG, PNG, atau PDF (maks. 5 MB). Disimpan di private storage.'
                                }
                            </p>
                            {form.errors.attachment && (
                                <p className="text-destructive text-xs">
                                    {form.errors.attachment}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Lines Table */}
                    <div className="bg-card overflow-hidden rounded-lg border">
                        <div className="bg-muted/30 flex items-center justify-between border-b p-4">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-semibold">
                                    {t('finance.journals.form.lines_title')}
                                </span>
                                <Badge variant="outline">
                                    {form.data.lines.length}{' '}
                                    {t('finance.journals.form.lines', {
                                        defaultValue: 'Baris',
                                    })}
                                </Badge>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={addLine}
                            >
                                <Plus className="mr-1 h-4 w-4" />{' '}
                                {t('finance.journals.form.add_line')}
                            </Button>
                        </div>

                        {lineError('lines') && (
                            <p className="text-destructive px-4 pt-3 text-xs">
                                {lineError('lines')}
                            </p>
                        )}

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="w-12 px-3 py-2 text-center font-medium">
                                            #
                                        </th>
                                        <th className="w-72 px-3 py-2 font-medium">
                                            {t('finance.common.account')}
                                        </th>
                                        <th className="w-40 px-3 py-2 text-right font-medium">
                                            {t('finance.common.debit')}
                                        </th>
                                        <th className="w-40 px-3 py-2 text-right font-medium">
                                            {t('finance.common.credit')}
                                        </th>
                                        <th className="w-48 px-3 py-2 font-medium">
                                            {t('finance.common.student')}{' '}
                                            {'(Subledger)'}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('finance.common.description')}
                                        </th>
                                        <th className="w-12 px-3 py-2 text-center font-medium"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.lines.map((line, idx) => (
                                        <tr key={idx} className="border-t">
                                            <td className="text-muted-foreground px-3 py-2 text-center font-mono text-xs">
                                                {idx + 1}
                                            </td>

                                            <td className="px-3 py-2">
                                                <Select
                                                    value={
                                                        line.account_id
                                                            ? String(
                                                                  line.account_id,
                                                              )
                                                            : ''
                                                    }
                                                    onValueChange={(val) =>
                                                        updateLine(
                                                            idx,
                                                            'account_id',
                                                            Number(val),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="w-full text-xs">
                                                        <SelectValue placeholder="Pilih akun..." />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60">
                                                        {accounts.map((acc) => (
                                                            <SelectItem
                                                                key={acc.id}
                                                                value={String(
                                                                    acc.id,
                                                                )}
                                                                className="text-xs"
                                                            >
                                                                {acc.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {lineError(
                                                    `lines.${idx}.account_id`,
                                                ) && (
                                                    <p className="text-destructive mt-1 text-xs">
                                                        {lineError(
                                                            `lines.${idx}.account_id`,
                                                        )}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-3 py-2">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="0"
                                                    className="text-right font-mono text-xs"
                                                    value={line.debit}
                                                    onChange={(e) =>
                                                        updateLine(
                                                            idx,
                                                            'debit',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {lineError(
                                                    `lines.${idx}.debit`,
                                                ) && (
                                                    <p className="text-destructive mt-1 text-xs">
                                                        {lineError(
                                                            `lines.${idx}.debit`,
                                                        )}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-3 py-2">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="0"
                                                    className="text-right font-mono text-xs"
                                                    value={line.credit}
                                                    onChange={(e) =>
                                                        updateLine(
                                                            idx,
                                                            'credit',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {lineError(
                                                    `lines.${idx}.credit`,
                                                ) && (
                                                    <p className="text-destructive mt-1 text-xs">
                                                        {lineError(
                                                            `lines.${idx}.credit`,
                                                        )}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-3 py-2">
                                                <Select
                                                    value={
                                                        line.student_id
                                                            ? String(
                                                                  line.student_id,
                                                              )
                                                            : 'none'
                                                    }
                                                    onValueChange={(val) =>
                                                        updateLine(
                                                            idx,
                                                            'student_id',
                                                            val === 'none'
                                                                ? null
                                                                : Number(val),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="w-full text-xs">
                                                        <SelectValue placeholder="Opsional..." />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60">
                                                        <SelectItem value="none">
                                                            {'- Tidak ada -'}
                                                        </SelectItem>
                                                        {students.map((s) => (
                                                            <SelectItem
                                                                key={s.id}
                                                                value={String(
                                                                    s.id,
                                                                )}
                                                                className="text-xs"
                                                            >
                                                                {s.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {lineError(
                                                    `lines.${idx}.student_id`,
                                                ) && (
                                                    <p className="text-destructive mt-1 text-xs">
                                                        {lineError(
                                                            `lines.${idx}.student_id`,
                                                        )}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-3 py-2">
                                                <Input
                                                    placeholder="Catatan..."
                                                    className="text-xs"
                                                    value={line.memo}
                                                    onChange={(e) =>
                                                        updateLine(
                                                            idx,
                                                            'memo',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {lineError(
                                                    `lines.${idx}.memo`,
                                                ) && (
                                                    <p className="text-destructive mt-1 text-xs">
                                                        {lineError(
                                                            `lines.${idx}.memo`,
                                                        )}
                                                    </p>
                                                )}
                                            </td>

                                            <td className="px-3 py-2 text-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={
                                                        form.data.lines
                                                            .length <= 2
                                                    }
                                                    onClick={() =>
                                                        removeLine(idx)
                                                    }
                                                    className="text-destructive h-8 w-8"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Balance Summary Footer */}
                        <div className="bg-muted/40 flex flex-col items-center justify-between gap-4 border-t p-4 sm:flex-row">
                            <div className="flex items-center gap-2">
                                {isBalanced ? (
                                    <div className="flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-600 dark:bg-emerald-950/40">
                                        <CheckCircle2 className="mr-1.5 h-4 w-4" />{' '}
                                        {t('finance.common.balanced')}
                                    </div>
                                ) : (
                                    <div className="text-destructive bg-destructive/10 border-destructive/20 flex items-center rounded-full border px-3 py-1.5 text-xs font-medium">
                                        <AlertCircle className="mr-1.5 h-4 w-4" />
                                        {t('finance.common.difference')}:{' '}
                                        {`Rp ${diff.toLocaleString(locale)}`}
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center gap-6 font-mono text-sm">
                                <div>
                                    <span className="text-muted-foreground mr-2">
                                        {t(
                                            'finance.reports.trial_balance.total_debit',
                                        )}
                                    </span>
                                    <span className="font-semibold">
                                        {`Rp ${totalDebit.toLocaleString(locale)}`}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground mr-2">
                                        {t(
                                            'finance.reports.trial_balance.total_credit',
                                        )}
                                    </span>
                                    <span className="font-semibold">
                                        {`Rp ${totalCredit.toLocaleString(locale)}`}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Submit Buttons */}
                    <div className="flex items-center justify-end gap-3 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => handleSubmit('draft')}
                        >
                            {t('finance.journals.form.save_draft')}
                        </Button>

                        <Button
                            type="button"
                            disabled={form.processing || !isBalanced}
                            onClick={() => handleSubmit('post')}
                        >
                            {t('finance.journals.form.post')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
