import { Head, Form, Link } from '@inertiajs/react';
import { Pencil, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import AcademicYearController from '@/actions/App/Http/Controllers/AcademicYears/AcademicYearController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Year = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    classes_count: number;
};

type Props = {
    years: Year[];
};

export default function AcademicYearsIndex({ years }: Props) {
    return (
        <>
            <Head title="Academic Years" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Academic Years"
                        description="Year-scoped classes and the annual roll-over."
                    />

                    <Button variant="outline" asChild>
                        <Link href={AcademicYearController.rollOver().url}>
                            <RotateCcw className="h-4 w-4" />
                            Roll over
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Year</th>
                                <th className="px-4 py-3 text-left font-medium">Period</th>
                                <th className="px-4 py-3 text-left font-medium">Classes</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {years.map((year) => (
                                <tr key={year.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">{year.name}</td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {year.starts_at} — {year.ends_at}
                                    </td>
                                    <td className="px-4 py-3">{year.classes_count}</td>
                                    <td className="px-4 py-3">
                                        {year.is_active ? (
                                            <Badge>Active</Badge>
                                        ) : (
                                            <Form
                                                {...AcademicYearController.activate.form({
                                                    academic_year: year.id,
                                                })}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Set active
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <EditYearDialog year={year} />
                                            {!year.is_active && (
                                                <Form
                                                    {...AcademicYearController.destroy.form({
                                                        academic_year: year.id,
                                                    })}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            type="submit"
                                                            disabled={processing || year.classes_count > 0}
                                                            title={
                                                                year.classes_count > 0
                                                                    ? 'Cannot delete: classes exist in this year.'
                                                                    : 'Delete year'
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {years.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No academic years yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <CreateYearDialog />
                </div>
            </div>
        </>
    );
}

function CreateYearDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Create year</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create academic year</DialogTitle>
                    <DialogDescription>
                        New years start inactive — use roll-over or “Set active” when
                        the year begins.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AcademicYearController.store.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" placeholder="2027/2028" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Starts</Label>
                                    <Input id="starts_at" type="date" name="starts_at" />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">Ends</Label>
                                    <Input id="ends_at" type="date" name="ends_at" />
                                    <InputError message={errors.ends_at} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Create
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditYearDialog({ year }: { year: Year }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" title="Edit year">
                    <Pencil className="h-4 w-4" />
                    <span className="sr-only">Edit year</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit — {year.name}</DialogTitle>
                    <DialogDescription>
                        Date bounds seed roll-over defaults; changing them never
                        rewrites existing enrollments.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AcademicYearController.update.form({ academic_year: year.id })}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`name-${year.id}`}>Name</Label>
                                <Input
                                    id={`name-${year.id}`}
                                    name="name"
                                    defaultValue={year.name}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`starts-${year.id}`}>Starts</Label>
                                    <Input
                                        id={`starts-${year.id}`}
                                        type="date"
                                        name="starts_at"
                                        defaultValue={year.starts_at}
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`ends-${year.id}`}>Ends</Label>
                                    <Input
                                        id={`ends-${year.id}`}
                                        type="date"
                                        name="ends_at"
                                        defaultValue={year.ends_at}
                                    />
                                    <InputError message={errors.ends_at} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AcademicYearsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Academic Years',
            href: AcademicYearController.index().url,
        },
    ],
};
