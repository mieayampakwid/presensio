import { Form, Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, UserRoundX } from 'lucide-react';
import GuardianExcuseController from '@/actions/App/Http/Controllers/Excuses/GuardianExcuseController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { EXCUSE_STATUS_BADGES, EXCUSE_TYPE_LABELS } from '@/lib/excuses';

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const TEXTAREA_CLASS =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

type Child = {
    id: number;
    full_name: string;
    class_name: string | null;
};

type ExcuseRow = {
    id: number;
    child_name: string;
    class_name: string | null;
    type: string;
    start_date: string;
    end_date: string;
    reason: string;
    has_attachment: boolean;
    attachment_url: string | null;
    status: string;
    review_note: string | null;
    submitted_at: string | null;
};

type Paginator = {
    data: ExcuseRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    children: Child[];
    excuses: Paginator | null;
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={EXCUSE_STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {EXCUSE_STATUS_BADGES[status]?.label ?? status}
        </Badge>
    );
}

export default function MyExcuses({ children, excuses }: Props) {
    return (
        <>
            <Head title="My Excuses" />

            <div className="space-y-6 p-4">
                <Heading
                    title="My Excuses"
                    description="Submit sick or leave excuses for your children. An administrator reviews every submission."
                />

                {excuses === null ? (
                    <Card className="max-w-md">
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                            <UserRoundX className="size-10" />
                            <p className="text-foreground text-sm font-medium">
                                No guardian profile linked
                            </p>
                            <p className="text-sm">
                                Your account is not linked to a guardian
                                profile yet. Ask the school office to connect
                                it.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle>New excuse</CardTitle>
                                <CardDescription>
                                    One child and one date range per
                                    submission. Attach proof such as a
                                    doctor's note or invitation (JPG, PNG, or
                                    PDF).
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...GuardianExcuseController.store.form()}
                                    className="space-y-6"
                                >
                                    {({ processing, errors }) => (
                                        <div className="space-y-6">
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="student_id">
                                                        Child
                                                    </Label>
                                                    <select
                                                        id="student_id"
                                                        name="student_id"
                                                        defaultValue=""
                                                        required
                                                        className={SELECT_CLASS}
                                                    >
                                                        <option value="" disabled>
                                                            Select a child…
                                                        </option>
                                                        {children.map(
                                                            (child) => (
                                                                <option
                                                                    key={
                                                                        child.id
                                                                    }
                                                                    value={
                                                                        child.id
                                                                    }
                                                                >
                                                                    {
                                                                        child.full_name
                                                                    }
                                                                    {child.class_name
                                                                        ? ` — ${child.class_name}`
                                                                        : ''}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                    <InputError
                                                        message={
                                                            errors.student_id
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="type">
                                                        Category
                                                    </Label>
                                                    <select
                                                        id="type"
                                                        name="type"
                                                        defaultValue="sick"
                                                        required
                                                        className={SELECT_CLASS}
                                                    >
                                                        <option value="sick">
                                                            Sick (Sakit)
                                                        </option>
                                                        <option value="leave">
                                                            Leave (Izin)
                                                        </option>
                                                    </select>
                                                    <InputError
                                                        message={errors.type}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="start_date">
                                                        First day
                                                    </Label>
                                                    <Input
                                                        id="start_date"
                                                        name="start_date"
                                                        type="date"
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.start_date
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="end_date">
                                                        Last day
                                                    </Label>
                                                    <Input
                                                        id="end_date"
                                                        name="end_date"
                                                        type="date"
                                                        required
                                                    />
                                                    <InputError
                                                        message={errors.end_date}
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="reason">
                                                    Reason
                                                </Label>
                                                <textarea
                                                    id="reason"
                                                    name="reason"
                                                    rows={3}
                                                    required
                                                    placeholder="Tell the school why your child will be absent…"
                                                    className={TEXTAREA_CLASS}
                                                />
                                                <InputError
                                                    message={errors.reason}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="attachment">
                                                    Proof (optional)
                                                </Label>
                                                <Input
                                                    id="attachment"
                                                    name="attachment"
                                                    type="file"
                                                    accept=".jpg,.jpeg,.png,.pdf"
                                                />
                                                <InputError
                                                    message={errors.attachment}
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Submit excuse
                                            </Button>
                                        </div>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Child
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Dates
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Category
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Reason
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Proof
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Review note
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {excuses.data.map((excuse) => (
                                        <tr
                                            key={excuse.id}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {excuse.child_name}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {excuse.start_date ===
                                                excuse.end_date
                                                    ? excuse.start_date
                                                    : `${excuse.start_date} – ${excuse.end_date}`}
                                            </td>
                                            <td className="px-4 py-3">
                                                {EXCUSE_TYPE_LABELS[
                                                    excuse.type
                                                ] ?? excuse.type}
                                            </td>
                                            <td
                                                className="max-w-48 truncate px-4 py-3"
                                                title={excuse.reason}
                                            >
                                                {excuse.reason}
                                            </td>
                                            <td className="px-4 py-3">
                                                {excuse.attachment_url ? (
                                                    <a
                                                        href={
                                                            excuse.attachment_url
                                                        }
                                                        className="text-primary underline-offset-4 hover:underline"
                                                    >
                                                        Download
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={excuse.status}
                                                />
                                            </td>
                                            <td className="text-muted-foreground max-w-48 px-4 py-3">
                                                {excuse.review_note ?? '—'}
                                            </td>
                                        </tr>
                                    ))}

                                    {excuses.data.length === 0 && (
                                        <tr className="border-t">
                                            <td
                                                colSpan={7}
                                                className="text-muted-foreground px-4 py-8 text-center"
                                            >
                                                No excuses submitted yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">
                                Page {excuses.current_page} of{' '}
                                {excuses.last_page}
                            </p>

                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    disabled={!excuses.prev_page_url}
                                >
                                    <Link href={excuses.prev_page_url ?? '#'}>
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    disabled={!excuses.next_page_url}
                                >
                                    <Link href={excuses.next_page_url ?? '#'}>
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

MyExcuses.layout = {
    breadcrumbs: [
        {
            title: 'My Excuses',
        },
    ],
};
