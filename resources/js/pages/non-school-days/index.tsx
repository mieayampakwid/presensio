import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Trash2 } from 'lucide-react';
import NonSchoolDayController from '@/actions/App/Http/Controllers/Calendar/NonSchoolDayController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Day = {
    id: number;
    date: string;
    name: string;
    source: 'sync' | 'manual';
};

type Paginator = {
    data: Day[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

export default function NonSchoolDaysIndex({ days }: { days: Paginator }) {
    const destroy = (day: Day) => {
        router.delete(
            NonSchoolDayController.destroy({ non_school_day: day.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Non-School Days" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Non-School Days"
                        description="Holidays and school closures — the sweep and scanner respect these."
                    />

                    <Button asChild>
                        <Link href={NonSchoolDayController.create().url}>
                            Add date
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Date
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Source
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {days.data.map((day) => (
                                <tr key={day.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {day.date}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {day.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {day.source === 'sync' ? (
                                            <Badge variant="secondary">
                                                Sync
                                            </Badge>
                                        ) : (
                                            <Badge>Manual</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Dialog>
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Delete
                                                    </span>
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogTitle>
                                                    Delete non-school day
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Remove {day.date} — "
                                                    {day.name}"? Note: a synced
                                                    date deleted while the
                                                    national feed still lists it
                                                    will re-import at the next
                                                    weekly sync.
                                                </DialogDescription>
                                                <DialogFooter className="gap-2">
                                                    <DialogClose asChild>
                                                        <Button variant="secondary">
                                                            Cancel
                                                        </Button>
                                                    </DialogClose>
                                                    <DialogClose asChild>
                                                        <Button
                                                            variant="destructive"
                                                            onClick={() =>
                                                                destroy(day)
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    </DialogClose>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </td>
                                </tr>
                            ))}

                            {days.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No non-school days recorded.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {days.current_page} of {days.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!days.prev_page_url}
                        >
                            <Link href={days.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!days.next_page_url}
                        >
                            <Link href={days.next_page_url ?? '#'}>
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

NonSchoolDaysIndex.layout = {
    breadcrumbs: [
        {
            title: 'Non-School Days',
            href: NonSchoolDayController.index().url,
        },
    ],
};
