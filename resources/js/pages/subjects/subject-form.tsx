import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type GroupOption = {
    value: string;
    label: string;
};

type SubjectFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        name: string;
        code: string;
        group: string;
        sort_order: number;
        description: string;
        is_active: boolean;
    };
    groups: GroupOption[];
};

export default function SubjectForm({
    action,
    submitLabel,
    defaults,
    groups,
}: SubjectFormProps) {
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid max-w-2xl gap-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                name="code"
                                defaultValue={defaults.code}
                                required
                                autoFocus
                                autoComplete="off"
                                placeholder="e.g. MAT"
                                maxLength={20}
                                className="uppercase"
                            />
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={defaults.name}
                                required
                                autoComplete="off"
                                placeholder="e.g. Matematika"
                                maxLength={255}
                            />
                            <InputError message={errors.name} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="group">Group</Label>
                            <select
                                id="group"
                                name="group"
                                defaultValue={defaults.group}
                                required
                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            >
                                {groups.map((group) => (
                                    <option
                                        key={group.value}
                                        value={group.value}
                                    >
                                        {group.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.group} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sort_order">Sort Order</Label>
                            <Input
                                id="sort_order"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={defaults.sort_order}
                                required
                            />
                            <InputError message={errors.sort_order} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            Description (optional)
                        </Label>
                        <textarea
                            id="description"
                            name="description"
                            defaultValue={defaults.description}
                            rows={3}
                            className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            placeholder="Deskripsi singkat mata pelajaran..."
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="hidden"
                            name="is_active"
                            value="0"
                        />
                        <input
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            defaultChecked={defaults.is_active}
                            className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <Label htmlFor="is_active" className="cursor-pointer">
                            Active (Mata pelajaran aktif)
                        </Label>
                        <InputError message={errors.is_active} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner className="mr-2" />}
                            {submitLabel}
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}
