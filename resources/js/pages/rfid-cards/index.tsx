import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2, Undo2 } from 'lucide-react';
import { useState } from 'react';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type CardRow = {
    id: number;
    rfid_number: string;
    student: { id: number; full_name: string } | null;
};

type StudentOption = {
    id: number;
    full_name: string;
};

type EmployeeOption = {
    id: number;
    name: string;
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
    students?: StudentOption[];
    employees?: EmployeeOption[];
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function RfidCardsIndex({ cards, filters, students }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingCard, setEditingCard] = useState<CardRow | null>(null);
    const [deleting, setDeleting] = useState<CardRow | null>(null);

    const openCreate = () => {
        if (!students) {
            router.reload({ only: ['students', 'employees'] });
        }
        setIsCreateOpen(true);
    };

    const openEdit = (card: CardRow) => {
        if (!students) {
            router.reload({ only: ['students', 'employees'] });
        }
        setEditingCard(card);
    };
    const destroy = (card: CardRow) => {
        router.delete(RfidCardController.destroy({ rfid_card: card.id }).url, {
            preserveScroll: true,
        });
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

                    <Button onClick={openCreate}>Create card</Button>
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
                                                onClick={() => openEdit(card)}
                                            >
                                                <Pencil className="h-4 w-4" />
                                                <span className="sr-only">
                                                    Edit
                                                </span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    setDeleting(card)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                <span className="sr-only">
                                                    Delete
                                                </span>
                                            </Button>
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
            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete card</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete card "
                        {deleting?.rfid_number}"?
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleting) {
                                    destroy(deleting);
                                    setDeleting(null);
                                }
                            }}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create card</DialogTitle>
                        <DialogDescription>
                            Register a new RFID card number and optionally
                            assign it to a student.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...RfidCardController.store.form()}
                        onSuccess={() => setIsCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-rfid_number">
                                        Card number
                                    </Label>
                                    <Input
                                        id="create-rfid_number"
                                        name="rfid_number"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="Number printed on the card"
                                    />
                                    <InputError message={errors.rfid_number} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-student_id">
                                        Assigned student
                                    </Label>
                                    <select
                                        id="create-student_id"
                                        name="student_id"
                                        defaultValue=""
                                        className={SELECT_CLASS}
                                    >
                                        <option value="">
                                            Spare (not assigned)
                                        </option>
                                        {(students ?? []).map((student) => (
                                            <option
                                                key={student.id}
                                                value={student.id}
                                            >
                                                {student.full_name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.student_id} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <Button
                                        variant="secondary"
                                        type="button"
                                        onClick={() => setIsCreateOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Create card
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingCard !== null}
                onOpenChange={(open) => !open && setEditingCard(null)}
            >
                <DialogContent>
                    {editingCard && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Edit card {editingCard.rfid_number}
                                </DialogTitle>
                                <DialogDescription>
                                    Change the assignment or return the card to
                                    the spare pool.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                key={editingCard.id}
                                {...RfidCardController.update.form({
                                    rfid_card: editingCard.id,
                                })}
                                onSuccess={() => setEditingCard(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-rfid_number">
                                                Card number
                                            </Label>
                                            <Input
                                                id="edit-rfid_number"
                                                name="rfid_number"
                                                defaultValue={
                                                    editingCard.rfid_number
                                                }
                                                required
                                                autoComplete="off"
                                            />
                                            <InputError
                                                message={errors.rfid_number}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-student_id">
                                                Assigned student
                                            </Label>
                                            <select
                                                id="edit-student_id"
                                                name="student_id"
                                                defaultValue={
                                                    editingCard.student?.id
                                                        ? String(
                                                              editingCard
                                                                  .student.id,
                                                          )
                                                        : ''
                                                }
                                                className={SELECT_CLASS}
                                            >
                                                <option value="">
                                                    Spare (not assigned)
                                                </option>
                                                {(students ?? []).map(
                                                    (student) => (
                                                        <option
                                                            key={student.id}
                                                            value={student.id}
                                                        >
                                                            {student.full_name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <InputError
                                                message={errors.student_id}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() =>
                                                    setEditingCard(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Save changes
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
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
