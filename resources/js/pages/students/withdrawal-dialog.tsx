import { useForm } from '@inertiajs/react';
import { AlertTriangle, UserMinus } from 'lucide-react';
import { useEffect, useState } from 'react';
import StudentWithdrawalController from '@/actions/App/Http/Controllers/Students/StudentWithdrawalController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type StudentTarget = {
    id: number;
    full_name: string;
    student_number: string | null;
    school_class: { id: number; name: string } | null;
    has_rfid?: boolean;
};

type PreviewData = {
    has_active_enrollment: boolean;
    class_name?: string;
    started_on?: string;
    has_rfid: boolean;
    has_user_account: boolean;
    cancelled_bills_count: number;
    review_bills_count: number;
};

type Props = {
    student: StudentTarget | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function WithdrawalDialog({
    student,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [preview, setPreview] = useState<PreviewData | null>(null);
    const [loadingPreview, setLoadingPreview] = useState(false);

    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors, reset } = useForm({
        ended_on: today,
        reason: 'transfer_out',
        notes: '',
        release_rfid: true,
        deactivate_user: true,
    });

    useEffect(() => {
        if (!open || !student) {
            setPreview(null);
            reset();
            return;
        }

        setLoadingPreview(true);
        fetch(
            StudentWithdrawalController.preview.url(
                { student: student.id },
                { query: { ended_on: data.ended_on } },
            ),
        )
            .then((res) => res.json())
            .then((data: PreviewData) => {
                setPreview(data);
                setLoadingPreview(false);
            })
            .catch(() => setLoadingPreview(false));
    }, [open, student, data.ended_on]);

    if (!student) return null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(StudentWithdrawalController.store({ student: student.id }).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <div className="text-destructive flex items-center gap-2">
                        <UserMinus className="h-5 w-5" />
                        <DialogTitle>
                            {t('students.withdrawal.title')}
                        </DialogTitle>
                    </div>
                    <DialogDescription>
                        {t('students.withdrawal.description')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="bg-muted/40 space-y-1 rounded-md border p-3 text-sm">
                        <div>
                            <span className="text-muted-foreground font-medium">
                                {t('students.withdrawal.student')}:
                            </span>{' '}
                            <span className="font-semibold">
                                {student.full_name}
                            </span>{' '}
                            {student.student_number && (
                                <span className="text-muted-foreground">
                                    ({student.student_number})
                                </span>
                            )}
                        </div>
                        {student.school_class && (
                            <div>
                                <span className="text-muted-foreground font-medium">
                                    {t('students.withdrawal.class')}:
                                </span>{' '}
                                <span className="font-semibold">
                                    {student.school_class.name}
                                </span>
                            </div>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="ended_on">
                            {t('students.withdrawal.ended_on')}
                        </Label>
                        <Input
                            id="ended_on"
                            type="date"
                            max={today}
                            min={preview?.started_on}
                            value={data.ended_on}
                            onChange={(e) =>
                                setData('ended_on', e.target.value)
                            }
                        />
                        <InputError message={errors.ended_on} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            {t('students.withdrawal.reason')}
                        </Label>
                        <select
                            id="reason"
                            className="border-input focus-visible:ring-ring flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                        >
                            <option value="transfer_out">
                                {t('students.withdrawal.reasons.transfer_out')}
                            </option>
                            <option value="dropped_out">
                                {t('students.withdrawal.reasons.dropped_out')}
                            </option>
                            <option value="expelled">
                                {t('students.withdrawal.reasons.expelled')}
                            </option>
                            <option value="deceased">
                                {t('students.withdrawal.reasons.deceased')}
                            </option>
                            <option value="other">
                                {t('students.withdrawal.reasons.other')}
                            </option>
                        </select>
                        <InputError message={errors.reason} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">
                            {t('students.withdrawal.notes')}
                        </Label>
                        <textarea
                            id="notes"
                            placeholder={t(
                                'students.withdrawal.notes_placeholder',
                            )}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                        <InputError message={errors.notes} />
                    </div>

                    {preview && preview.has_rfid && (
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="release_rfid"
                                checked={data.release_rfid}
                                onCheckedChange={(checked) =>
                                    setData('release_rfid', Boolean(checked))
                                }
                            />
                            <Label
                                htmlFor="release_rfid"
                                className="cursor-pointer text-sm font-normal"
                            >
                                {t('students.withdrawal.release_rfid')}
                            </Label>
                        </div>
                    )}

                    {preview && preview.has_user_account && (
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="deactivate_user"
                                checked={data.deactivate_user}
                                onCheckedChange={(checked) =>
                                    setData('deactivate_user', Boolean(checked))
                                }
                            />
                            <Label
                                htmlFor="deactivate_user"
                                className="cursor-pointer text-sm font-normal"
                            >
                                {t('students.withdrawal.deactivate_user')}
                            </Label>
                        </div>
                    )}

                    {preview && preview.cancelled_bills_count > 0 && (
                        <div className="text-muted-foreground text-xs">
                            {`✓ ${t('students.withdrawal.cancelled_bills_note', { count: preview.cancelled_bills_count })}`}
                        </div>
                    )}

                    {preview && preview.review_bills_count > 0 && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                {t('students.withdrawal.review_bills_warning', {
                                    count: preview.review_bills_count,
                                })}
                            </AlertDescription>
                        </Alert>
                    )}

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            {t('students.withdrawal.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing || loadingPreview}
                        >
                            {t('students.withdrawal.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
