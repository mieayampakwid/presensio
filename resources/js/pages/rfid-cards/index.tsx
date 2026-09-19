import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2, Undo2 } from 'lucide-react';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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

type CardRow = {
    id: number;
    rfid_number: string;
    student: { id: number; full_name: string } | null;
};

type Paginator = {
    data: CardRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    cards: Paginator;
    filters: { search: string };
};

export default function RfidCardsIndex({ cards, filters }: Props) {
    const destroy = (card: CardRow) => {
        router.delete(
            RfidCardController.destroy({ rfid_card: card.id }).url,
            { preserveScroll: true },
        );
    };

    const revoke = (card: CardRow) => {
        router.put(
            RfidCardController.revoke({ rfid_card: card.id }).url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="RFID Cards" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="RFID Cards"
                        description="Registered scan cards; assign them to students for attendance."
                    />

                    <Button asChild>
                        <Link href={RfidCardController.create().url}>
                            Create card
                        </Link>
                    </Button>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search by card or student…"
                    />
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Card number
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Assigned to
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {cards.data.map((card) => (
                                <tr key={card.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {card.rfid_number}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {card.student?.full_name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {card.student ? (
                                            <Badge>Assigned</Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Spare
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            {card.student && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Revoke"
                                                    onClick={() => revoke(card)}
                                                >
                                                    <Undo2 className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Revoke
                                                    </span>
                                                </Button>
                                            )}

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={RfidCardController.edit(
                                                        {
                                                            rfid_card:
                                                                card.id,
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
                                                        Delete card
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Are you sure you want
                                                        to delete card "
                                                        {card.rfid_number}"?
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
                                                                        card,
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

                            {cards.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No cards found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {cards.current_page} of {cards.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!cards.prev_page_url}
                        >
                            <Link href={cards.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!cards.next_page_url}
                        >
                            <Link href={cards.next_page_url ?? '#'}>
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

RfidCardsIndex.layout = {
    breadcrumbs: [
        {
            title: 'RFID Cards',
            href: RfidCardController.index().url,
        },
    ],
};
