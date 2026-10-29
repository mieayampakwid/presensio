import { Head, router } from '@inertiajs/react';
import { CalendarCheck, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import ShiftRosterController from '@/actions/App/Http/Controllers/Staff/ShiftRosterController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { cn } from '@/lib/utils';

type RosterCell = {
    date: string;
    editable: boolean;
    source: 'roster' | 'default' | 'fallback';
    is_day_off: boolean;
    shift: {
        id: number;
        name: string;
        start_time: string;
        end_time: string;
    } | null;
};

type RosterRow = {
    employee_id: number;
    name: string;
    default_shift: { id: number; name: string } | null;
    cells: RosterCell[];
};

type ShiftOption = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    crosses_midnight: boolean;
};

type Props = {
    rows: RosterRow[];
    shifts: ShiftOption[];
    view: { start_date: string; end_date: string };
    today: string;
    can_manage: boolean;
};

const DAY_HEADERS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

/** Cycled per shift id order so colors stay stable within a view. */
const SHIFT_CELL_CLASSES = [
    'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200',
    'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
    'bg-teal-100 text-teal-900 dark:bg-teal-950 dark:text-teal-200',
];

const addDays = (dateStr: string, days: number): string => {
    const date = new Date(`${dateStr}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
};

const addMonths = (dateStr: string, months: number): string => {
    const date = new Date(`${dateStr}T00:00:00Z`);
    date.setUTCMonth(date.getUTCMonth() + months, 1);
    return date.toISOString().slice(0, 10);
};

export default function StaffRoster({
    rows,
    shifts,
    view,
    today,
    can_manage,
}: Props) {
    const { t } = useTranslation();

    const dates = rows[0]?.cells.map((cell) => cell.date) ?? [];
    const isMonthView = dates.length > 7;
    const monthAnchor = `${view.start_date.slice(0, 7)}-01`;

    const [cellTarget, setCellTarget] = useState<{
        row: RosterRow;
        cell: RosterCell;
    } | null>(null);
    const [choice, setChoice] = useState<string>('');
    const [bulkOpen, setBulkOpen] = useState(false);
    const [bulkEmployees, setBulkEmployees] = useState<number[]>([]);
    const [bulkRange, setBulkRange] = useState({
        start_date: view.start_date < today ? today : view.start_date,
        end_date: view.end_date < today ? today : view.end_date,
    });
    const [bulkShift, setBulkShift] = useState<string>('off');
    const [bulkError, setBulkError] = useState<string>('');

    const colorFor = (shiftId: number | null): string => {
        const index = shifts.findIndex((shift) => shift.id === shiftId);

        return index === -1
            ? SHIFT_CELL_CLASSES[0]
            : SHIFT_CELL_CLASSES[index % SHIFT_CELL_CLASSES.length];
    };

    const openCell = (row: RosterRow, cell: RosterCell): void => {
        setCellTarget({ row, cell });
        setChoice(
            cell.is_day_off
                ? 'off'
                : cell.shift !== null
                  ? String(cell.shift.id)
                  : 'off',
        );
    };

    const saveCell = (): void => {
        if (!cellTarget) {
            return;
        }

        const payload = {
            employee_id: cellTarget.row.employee_id,
            date: cellTarget.cell.date,
        };

        if (choice === '') {
            router.delete(ShiftRosterController.clear.url(), { data: payload });
        } else {
            router.put(ShiftRosterController.set.url(), {
                ...payload,
                shift_id: choice === 'off' ? null : Number(choice),
            });
        }

        setCellTarget(null);
    };

    const submitBulk = (): void => {
        if (bulkEmployees.length === 0) {
            setBulkError(t('shifts.roster.bulk.employees_required'));

            return;
        }

        router.post(
            ShiftRosterController.bulk.url(),
            {
                employees: bulkEmployees,
                start_date: bulkRange.start_date,
                end_date: bulkRange.end_date,
                shift_id: bulkShift === 'off' ? null : Number(bulkShift),
            },
            { onSuccess: () => setBulkOpen(false) },
        );
    };

    const navigate = (params: Record<string, string>): void => {
        router.get(ShiftRosterController.index.url(), params);
    };

    return (
        <>
            <Head title={t('shifts.roster.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('shifts.roster.title')}
                        description={t('shifts.roster.description')}
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => navigate({ week: view.start_date })}
                        >
                            {t('shifts.roster.week')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => navigate({ month: monthAnchor })}
                        >
                            {t('shifts.roster.month')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                navigate(
                                    isMonthView
                                        ? {
                                              month: addMonths(monthAnchor, -1),
                                          }
                                        : {
                                              week: addDays(
                                                  view.start_date,
                                                  -7,
                                              ),
                                          },
                                )
                            }
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => navigate({ week: today })}
                        >
                            {t('shifts.roster.today')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                navigate(
                                    isMonthView
                                        ? {
                                              month: addMonths(monthAnchor, 1),
                                          }
                                        : {
                                              week: addDays(view.start_date, 7),
                                          },
                                )
                            }
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        {can_manage && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    setBulkEmployees([]);
                                    setBulkRange({
                                        start_date:
                                            view.start_date < today
                                                ? today
                                                : view.start_date,
                                        end_date:
                                            view.end_date < today
                                                ? today
                                                : view.end_date,
                                    });
                                    setBulkShift('off');
                                    setBulkError('');
                                    setBulkOpen(true);
                                }}
                            >
                                <CalendarCheck className="mr-2 h-4 w-4" />
                                {t('shifts.roster.bulk.action')}
                            </Button>
                        )}
                    </div>
                </div>

                <p className="text-muted-foreground text-sm">
                    {t('shifts.roster.readonly_note')}
                </p>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="bg-muted/50 sticky left-0 z-10 min-w-40 border-r p-2 text-left">
                                    {t('shifts.roster.employee')}
                                </th>
                                {dates.map((date) => {
                                    const day = new Date(
                                        `${date}T00:00:00Z`,
                                    ).getUTCDay();
                                    const weekday = (day + 6) % 7;

                                    return (
                                        <th
                                            key={date}
                                            className={cn(
                                                'bg-muted/50 border-r p-2 text-center text-xs font-medium last:border-r-0',
                                                (weekday === 5 ||
                                                    weekday === 6) &&
                                                    'bg-amber-100/60 dark:bg-amber-950/40',
                                                date === today &&
                                                    'bg-primary/10',
                                            )}
                                        >
                                            <div>{DAY_HEADERS[weekday]}</div>
                                            <div className="text-base font-semibold">
                                                {date.slice(8)}
                                            </div>
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.employee_id} className="border-t">
                                    <td className="bg-card sticky left-0 z-10 border-r p-2">
                                        <div className="font-medium">
                                            {row.name}
                                        </div>
                                        {row.default_shift !== null && (
                                            <div className="text-muted-foreground text-xs">
                                                {row.default_shift.name}
                                            </div>
                                        )}
                                    </td>
                                    {row.cells.map((cell) => {
                                        const day = new Date(
                                            `${cell.date}T00:00:00Z`,
                                        ).getUTCDay();

                                        return (
                                            <td
                                                key={cell.date}
                                                className={cn(
                                                    'border-r p-1 text-center text-xs last:border-r-0',
                                                    (day === 0 || day === 6) &&
                                                        'bg-muted/30',
                                                    cell.editable &&
                                                        can_manage &&
                                                        'hover:ring-ring/50 cursor-pointer hover:ring-2',
                                                )}
                                                title={
                                                    cell.shift !== null
                                                        ? `${cell.shift.name} ${cell.shift.start_time}-${cell.shift.end_time}`
                                                        : cell.is_day_off
                                                          ? t(
                                                                'shifts.roster.day_off',
                                                            )
                                                          : t(
                                                                'shifts.roster.fallback',
                                                            )
                                                }
                                                onClick={
                                                    cell.editable && can_manage
                                                        ? () =>
                                                              openCell(
                                                                  row,
                                                                  cell,
                                                              )
                                                        : undefined
                                                }
                                            >
                                                {cell.is_day_off ? (
                                                    <span className="text-muted-foreground line-through">
                                                        {t(
                                                            'shifts.roster.day_off',
                                                        )}
                                                    </span>
                                                ) : cell.shift !== null ? (
                                                    <span
                                                        className={cn(
                                                            'block rounded p-1',
                                                            colorFor(
                                                                cell.shift.id,
                                                            ),
                                                            cell.source ===
                                                                'roster' &&
                                                                'ring-primary/50 ring-2',
                                                        )}
                                                    >
                                                        {cell.shift.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground block rounded border border-dashed p-1">
                                                        {t(
                                                            'shifts.roster.fallback',
                                                        )}
                                                    </span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={cellTarget !== null}
                onOpenChange={(open) => !open && setCellTarget(null)}
            >
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {t('shifts.roster.cell.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {cellTarget?.row.name} — {cellTarget?.cell.date}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="cell-assignment">
                            {t('shifts.roster.cell.assignment')}
                        </Label>
                        <Select
                            value={choice || 'off'}
                            onValueChange={setChoice}
                        >
                            <SelectTrigger id="cell-assignment">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {cellTarget?.cell.source === 'roster' && (
                                    <SelectItem value="">
                                        {t('shifts.roster.cell.clear')}
                                    </SelectItem>
                                )}
                                <SelectItem value="off">
                                    {t('shifts.roster.day_off')}
                                </SelectItem>
                                {shifts.map((shift) => (
                                    <SelectItem
                                        key={shift.id}
                                        value={String(shift.id)}
                                    >
                                        {shift.name} ({shift.start_time}-
                                        {shift.end_time})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {cellTarget?.cell.source === 'roster' && (
                            <p className="text-muted-foreground text-xs">
                                {t('shifts.roster.cell.clear_hint')}
                            </p>
                        )}
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">
                                {t('shifts.roster.cancel')}
                            </Button>
                        </DialogClose>
                        <Button onClick={saveCell}>
                            {t('shifts.roster.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={bulkOpen} onOpenChange={setBulkOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {t('shifts.roster.bulk.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('shifts.roster.bulk.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>{t('shifts.roster.bulk.employees')}</Label>
                            <div className="max-h-40 overflow-y-auto rounded-md border p-2">
                                {rows.map((row) => (
                                    <label
                                        key={row.employee_id}
                                        className="flex cursor-pointer items-center gap-2 p-1 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={bulkEmployees.includes(
                                                row.employee_id,
                                            )}
                                            onChange={(event) =>
                                                setBulkEmployees(
                                                    event.target.checked
                                                        ? [
                                                              ...bulkEmployees,
                                                              row.employee_id,
                                                          ]
                                                        : bulkEmployees.filter(
                                                              (id) =>
                                                                  id !==
                                                                  row.employee_id,
                                                          ),
                                                )
                                            }
                                        />
                                        {row.name}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <div className="grid gap-1">
                                <Label htmlFor="bulk-start">
                                    {t('shifts.roster.bulk.start_date')}
                                </Label>
                                <Input
                                    id="bulk-start"
                                    type="date"
                                    min={today}
                                    value={bulkRange.start_date}
                                    onChange={(event) =>
                                        setBulkRange((current) => ({
                                            ...current,
                                            start_date: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="bulk-end">
                                    {t('shifts.roster.bulk.end_date')}
                                </Label>
                                <Input
                                    id="bulk-end"
                                    type="date"
                                    min={bulkRange.start_date}
                                    value={bulkRange.end_date}
                                    onChange={(event) =>
                                        setBulkRange((current) => ({
                                            ...current,
                                            end_date: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bulk-shift">
                                {t('shifts.roster.bulk.target')}
                            </Label>
                            <Select
                                value={bulkShift}
                                onValueChange={setBulkShift}
                            >
                                <SelectTrigger id="bulk-shift">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="off">
                                        {t('shifts.roster.day_off')}
                                    </SelectItem>
                                    {shifts.map((shift) => (
                                        <SelectItem
                                            key={shift.id}
                                            value={String(shift.id)}
                                        >
                                            {shift.name} ({shift.start_time}-
                                            {shift.end_time})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {bulkError !== '' && (
                            <p className="text-destructive text-sm">
                                {bulkError}
                            </p>
                        )}
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">
                                {t('shifts.roster.cancel')}
                            </Button>
                        </DialogClose>
                        <Button onClick={submitBulk}>
                            {t('shifts.roster.bulk.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
