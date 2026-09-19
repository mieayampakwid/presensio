import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type GuardianRow = {
    id: number;
    name: string;
    phone_number: string;
    work: string | null;
};

type Paginator = {
    data: GuardianRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    guardians: Paginator;
    filters: { search: string };
};

export default function GuardiansIndex({ guardians, filters }: Props) {
    const destroy = (guardian: GuardianRow) => {
        router.delete(
            GuardianController.destroy({ guardian: guardian.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Guardians" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Guardians"
                        description="Absence notifications go to these contacts; the phone number is the guardian identity."
                    />

                    <Button asChild>
                        <Link href={GuardianController.create().url}>
                            Create guardian
                        </Link>
                    </Button>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search by name or phone…"
                    />
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Phone
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Occupation
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {guardians.data.map((guardian) => (
                                <tr key={guardian.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {guardian.name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {guardian.phone_number}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {guardian.work ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={GuardianController.edit(
                                                        {
                                                            guardian:
                                                                guardian.id,
                                                        },
                                                    ).url}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Edit
                                                    </span>
                                                </Link>
                                            </Button>

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
                                                        Delete guardian
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Are you sure you want
                                                        to delete "
                                                        {guardian.name}"? Their
                                                        links to children are
                                                        removed with them.
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
                                                                    destroy(
                                                                        guardian,
                                                                    )
                                                                }
                                                            >
                                                                Delete
                                                            </Button>
                                                        </DialogClose>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {guardians.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No guardians found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {guardians.current_page} of {guardians.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!guardians.prev_page_url}
                        >
                            <Link href={guardians.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!guardians.next_page_url}
                        >
                            <Link href={guardians.next_page_url ?? '#'}>
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

GuardiansIndex.layout = {
    breadcrumbs: [
        {
            title: 'Guardians',
            href: GuardianController.index().url,
        },
    ],
};
