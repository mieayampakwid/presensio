import { Head, router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import BillGenerationController from '@/actions/App/Http/Controllers/Fees/BillGenerationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Summary = { created: number; skipped: number; discounted: number };

type Props = {
    academicYear: { id: number; name: string } | null;
    feeTypes: {
        id: number;
        name: string;
        code: string;
        is_recurring: boolean;
        default_amount: string;
    }[];
    classes: { id: number; name: string; grade_level: number }[];
};

type FormData = {
    fee_type_id: string;
    target: 'class' | 'grade_level' | 'all';
    class_id: string;
    grade_level: string;
    amount: string;
    from_month: string;
    to_month: string;
    due_day: string;
    due_date: string;
};

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

export default function GenerateBills({
    academicYear,
    feeTypes,
    classes,
}: Props) {
    const http = useHttp<FormData, Summary>({
        fee_type_id: '',
        target: 'all',
        class_id: '',
        grade_level: '',
        amount: '',
        from_month: '',
        to_month: '',
        due_day: '10',
        due_date: '',
    });
    const [summary, setSummary] = useState<Summary | null>(null);
    const [committing, setCommitting] = useState(false);

    const feeType = feeTypes.find(
        (type) => String(type.id) === http.data.fee_type_id,
    );
    const gradeLevels = [...new Set(classes.map((c) => c.grade_level))];

    const change = <K extends keyof FormData>(key: K, value: FormData[K]) => {
        http.setData((data) => ({ ...data, [key]: value }));
        setSummary(null);
    };

    const preview = () => {
        // Validation errors surface through http.errors.
        http.post(BillGenerationController.preview.url())
            .then(setSummary)
            .catch(() => setSummary(null));
    };

    const commit = () => {
        router.post(BillGenerationController.store.url(), http.data, {
            onStart: () => setCommitting(true),
            onFinish: () => setCommitting(false),
            onSuccess: () => setSummary(null),
            onError: (errors) => http.setError(errors),
        });
    };

    return (
        <>
            <Head title="Generate Tagihan" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="Generate Tagihan"
                    description={
                        academicYear
                            ? `Buat tagihan massal untuk siswa terdaftar tahun ajaran ${academicYear.name}.`
                            : 'Tidak ada tahun ajaran aktif.'
                    }
                />

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="fee_type_id">Jenis Biaya</Label>
                        <select
                            id="fee_type_id"
                            value={http.data.fee_type_id}
                            onChange={(event) => {
                                const next = feeTypes.find(
                                    (type) =>
                                        String(type.id) === event.target.value,
                                );
                                change('fee_type_id', event.target.value);
                                http.setData(
                                    'amount',
                                    next ? next.default_amount : '',
                                );
                            }}
                            className={selectClass}
                        >
                            <option value="" disabled>
                                Pilih jenis biaya...
                            </option>
                            {feeTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.code} - {type.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={http.errors.fee_type_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="target">Sasaran</Label>
                        <select
                            id="target"
                            value={http.data.target}
                            onChange={(event) =>
                                change(
                                    'target',
                                    event.target.value as FormData['target'],
                                )
                            }
                            className={selectClass}
                        >
                            <option value="all">Semua siswa aktif</option>
                            <option value="grade_level">Satu tingkat</option>
                            <option value="class">Satu kelas</option>
                        </select>
                    </div>

                    {http.data.target === 'class' && (
                        <div className="grid gap-2">
                            <Label htmlFor="class_id">Kelas</Label>
                            <select
                                id="class_id"
                                value={http.data.class_id}
                                onChange={(event) =>
                                    change('class_id', event.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="" disabled>
                                    Pilih kelas...
                                </option>
                                {classes.map((schoolClass) => (
                                    <option
                                        key={schoolClass.id}
                                        value={schoolClass.id}
                                    >
                                        {schoolClass.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={http.errors.class_id} />
                        </div>
                    )}

                    {http.data.target === 'grade_level' && (
                        <div className="grid gap-2">
                            <Label htmlFor="grade_level">Tingkat</Label>
                            <select
                                id="grade_level"
                                value={http.data.grade_level}
                                onChange={(event) =>
                                    change('grade_level', event.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="" disabled>
                                    Pilih tingkat...
                                </option>
                                {gradeLevels.map((level) => (
                                    <option key={level} value={level}>
                                        Tingkat {level}
                                    </option>
                                ))}
                            </select>
                            <InputError message={http.errors.grade_level} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="amount">Nominal (Rp)</Label>
                        <Input
                            id="amount"
                            type="number"
                            min={1}
                            step="0.01"
                            value={http.data.amount}
                            onChange={(event) =>
                                change('amount', event.target.value)
                            }
                        />
                        <InputError message={http.errors.amount} />
                    </div>

                    {feeType?.is_recurring && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="from_month">Dari Bulan</Label>
                                <Input
                                    id="from_month"
                                    type="month"
                                    value={http.data.from_month}
                                    onChange={(event) =>
                                        change('from_month', event.target.value)
                                    }
                                />
                                <InputError message={http.errors.from_month} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to_month">Sampai Bulan</Label>
                                <Input
                                    id="to_month"
                                    type="month"
                                    value={http.data.to_month}
                                    onChange={(event) =>
                                        change('to_month', event.target.value)
                                    }
                                />
                                <InputError message={http.errors.to_month} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="due_day">
                                    Tanggal Jatuh Tempo
                                </Label>
                                <Input
                                    id="due_day"
                                    type="number"
                                    min={1}
                                    max={31}
                                    value={http.data.due_day}
                                    onChange={(event) =>
                                        change('due_day', event.target.value)
                                    }
                                />
                                <InputError message={http.errors.due_day} />
                            </div>
                        </div>
                    )}

                    {feeType && !feeType.is_recurring && (
                        <div className="grid gap-2">
                            <Label htmlFor="due_date">Jatuh Tempo</Label>
                            <Input
                                id="due_date"
                                type="date"
                                value={http.data.due_date}
                                onChange={(event) =>
                                    change('due_date', event.target.value)
                                }
                            />
                            <InputError message={http.errors.due_date} />
                        </div>
                    )}
                </div>

                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={http.processing || !feeType || !academicYear}
                        onClick={preview}
                    >
                        {http.processing && <Spinner />}
                        Pratinjau
                    </Button>
                </div>

                {summary && (
                    <div className="space-y-4 rounded-lg border p-4">
                        <dl className="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <dt className="text-muted-foreground text-sm">
                                    Akan dibuat
                                </dt>
                                <dd className="text-2xl font-semibold tabular-nums">
                                    {summary.created}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">
                                    Dilewati
                                </dt>
                                <dd className="text-2xl font-semibold tabular-nums">
                                    {summary.skipped}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-sm">
                                    Berdiskon
                                </dt>
                                <dd className="text-2xl font-semibold tabular-nums">
                                    {summary.discounted}
                                </dd>
                            </div>
                        </dl>
                        <Button
                            type="button"
                            disabled={committing || summary.created === 0}
                            onClick={commit}
                        >
                            {committing && <Spinner />}
                            Buat Tagihan
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
