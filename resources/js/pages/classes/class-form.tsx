import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type TeacherOption = {
    id: number;
    name: string;
};

type ClassFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        name: string;
        gradeLevel: number;
        curriculum: string;
        teacherId: number | null;
    };
    teachers: TeacherOption[];
};

// Native <select> is used deliberately: the Radix Select primitive doesn't
// submit values with native form posts (Inertia <Form> serializes DOM inputs).
export default function ClassForm({
    action,
    submitLabel,
    defaults,
    teachers,
}: ClassFormProps) {
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={defaults.name}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="e.g. Kelas 1A"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="grade_level">Grade Level (1–12)</Label>
                            <select
                                id="grade_level"
                                name="grade_level"
                                defaultValue={defaults.gradeLevel > 0 ? defaults.gradeLevel : ''}
                                required
                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            >
                                <option value="" disabled>Pilih tingkat kelas</option>
                                {Array.from({ length: 12 }, (_, i) => i + 1).map((lvl) => (
                                    <option key={lvl} value={lvl}>
                                        Kelas {lvl}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.grade_level} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="curriculum">Curriculum</Label>
                            <select
                                id="curriculum"
                                name="curriculum"
                                defaultValue={defaults.curriculum || 'merdeka'}
                                required
                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            >
                                <option value="merdeka">Kurikulum Merdeka</option>
                            </select>
                            <InputError message={errors.curriculum} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="teacher_id">
                            Homeroom teacher (optional)
                        </Label>
                        <select
                            id="teacher_id"
                            name="teacher_id"
                            defaultValue={defaults.teacherId ?? ''}
                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        >
                            <option value="">No homeroom teacher</option>
                            {teachers.map((teacher) => (
                                <option key={teacher.id} value={teacher.id}>
                                    {teacher.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.teacher_id} />
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
