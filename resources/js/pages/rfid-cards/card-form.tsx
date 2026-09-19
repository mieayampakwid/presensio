import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type StudentOption = {
    id: number;
    full_name: string;
};

type CardFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        rfidNumber: string;
        studentId: string;
    };
    students: StudentOption[];
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function CardForm({
    action,
    submitLabel,
    defaults,
    students,
}: CardFormProps) {
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="rfid_number">Card number</Label>
                        <Input
                            id="rfid_number"
                            name="rfid_number"
                            defaultValue={defaults.rfidNumber}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="Number printed on the card"
                        />
                        <InputError message={errors.rfid_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="student_id">Assigned student</Label>
                        <select
                            id="student_id"
                            name="student_id"
                            defaultValue={defaults.studentId}
                            className={SELECT_CLASS}
                        >
                            <option value="">Spare (not assigned)</option>
                            {students.map((student) => (
                                <option key={student.id} value={student.id}>
                                    {student.full_name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.student_id} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            )}
        </Form>
    );
}
