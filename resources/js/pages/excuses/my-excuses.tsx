import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
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
import { useTranslation } from '@/hooks/use-translation';
import { EXCUSE_STATUS_BADGES } from '@/lib/excuses';

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
    const { t } = useTranslation();

    return (
        <Badge variant={EXCUSE_STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {t(`excuses.statuses.${status}`)}
        </Badge>
    );
}

export default function MyExcuses({ children, excuses }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('excuses.my_excuses.title'),
            },
        ],
    });

    return (
        <>
            <Head title={t('excuses.my_excuses.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('excuses.my_excuses.title')}
                    description={t('excuses.my_excuses.description')}
                />

                {excuses === null ? (
                    <Card className="max-w-md">
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                            <UserRoundX className="size-10" />
                            <p className="text-foreground text-sm font-medium">
                                {t('excuses.my_excuses.no_profile_title')}
                            </p>
                            <p className="text-sm">
                                {t('excuses.my_excuses.no_profile_description')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle>
                                    {t('excuses.my_excuses.new_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('excuses.my_excuses.new_description')}
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
                                                        {t(
                                                            'excuses.my_excuses.child',
                                                        )}
                                                    </Label>
                                                    <select
                                                        id="student_id"
                                                        name="student_id"
                                                        defaultValue=""
                                                        required
                                                        className={SELECT_CLASS}
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            {t(
                                                                'excuses.my_excuses.child_placeholder',
                                                            )}
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
                                                        {t(
                                                            'excuses.my_excuses.category',
                                                        )}
                                                    </Label>
                                                    <select
                                                        id="type"
                                                        name="type"
                                                        defaultValue="sick"
                                                        required
                                                        className={SELECT_CLASS}
                                                    >
                                                        <option value="sick">
                                                            {t(
                                                                'excuses.types.sick',
                                                            )}
                                                        </option>
                                                        <option value="leave">
                                                            {t(
                                                                'excuses.types.leave',
                                                            )}
                                                        </option>
                                                    </select>
                                                    <InputError
                                                        message={errors.type}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="start_date">
                                                        {t(
                                                            'excuses.my_excuses.first_day',
                                                        )}
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
                                                        {t(
                                                            'excuses.my_excuses.last_day',
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id="end_date"
                                                        name="end_date"
                                                        type="date"
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.end_date
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="reason">
                                                    {t(
                                                        'excuses.my_excuses.reason',
                                                    )}
                                                </Label>
                                                <textarea
                                                    id="reason"
                                                    name="reason"
                                                    rows={3}
                                                    required
                                                    placeholder={t(
                                                        'excuses.my_excuses.reason_placeholder',
                                                    )}
                                                    className={TEXTAREA_CLASS}
                                                />
                                                <InputError
                                                    message={errors.reason}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="attachment">
                                                    {t(
                                                        'excuses.my_excuses.proof_optional',
                                                    )}
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
                                                {t('excuses.my_excuses.submit')}
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
                                            {t(
                                                'excuses.my_excuses.columns.child',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'excuses.my_excuses.columns.dates',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'excuses.my_excuses.columns.category',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'excuses.my_excuses.columns.reason',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'excuses.my_excuses.columns.proof',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'excuses.my_excuses.columns.status',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'excuses.my_excuses.columns.review_note',
                                            )}
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
                                                {t(
                                                    `excuses.types.${excuse.type}`,
                                                )}
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
                                                        {t(
                                                            'excuses.index.download',
                                                        )}
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
                                                {t('excuses.my_excuses.empty')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">
                                {t('common.page_of', {
                                    current: excuses.current_page,
                                    last: excuses.last_page,
                                })}
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
                                        {t('common.previous')}
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    disabled={!excuses.next_page_url}
                                >
                                    <Link href={excuses.next_page_url ?? '#'}>
                                        {t('common.next')}
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
