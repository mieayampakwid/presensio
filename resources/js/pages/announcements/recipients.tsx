import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import Reader from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import { useTranslation } from '@/hooks/use-translation';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Announcement, PageData } from './types';
type Row = {
    id: number;
    name: string;
    guardians: {
        name: string;
        has_account: boolean;
        read: boolean;
        acknowledged: boolean;
    }[];
    read: boolean;
    acknowledged: boolean;
};
type Report = {
    kind: string;
    as_of: string;
    totals: {
        total: number;
        read: number;
        acknowledged: number;
        accountless_guardians: number;
    };
    rows: PageData<Row>;
    filter: string;
};
export default function Recipients({
    announcement,
    recipientReport: r,
}: {
    announcement: Announcement;
    recipientReport: Report;
}) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('announcements.title'), href: '/announcements' },
            { title: announcement.title, href: Reader.show(announcement.id) },
            { title: t('announcements.show.recipient_status') },
        ],
    });

    return (
        <>
            <Head title={t('announcements.show.recipient_status')} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Link
                    className="text-sm underline underline-offset-4"
                    href={Reader.show(announcement.id)}
                >
                    {t('announcements.recipients.back')}
                </Link>
                <Heading
                    title={t('announcements.show.recipient_status')}
                    description={`${announcement.title} · Keanggotaan saat ini per ${r.as_of}`}
                />
                <div className="flex flex-wrap gap-x-8 gap-y-3 rounded-lg border p-4 text-sm">
                    <p>
                        {t('announcements.recipients.total')}{' '}
                        <strong>{r.totals.total}</strong>
                    </p>
                    <p>
                        {t('announcements.recipients.already_read')}{' '}
                        <strong>{r.totals.read}</strong>
                    </p>
                    <p>
                        {t('announcements.recipients.already_confirmed')}{' '}
                        <strong>{r.totals.acknowledged}</strong>
                    </p>
                    <p>
                        {t('announcements.recipients.guardians_no_account')}{' '}
                        <strong>{r.totals.accountless_guardians}</strong>
                    </p>
                </div>
                <div className="max-w-xs space-y-2">
                    <Label htmlFor="recipient-status">
                        {t('announcements.recipients.filter_status')}
                    </Label>
                    <select
                        id="recipient-status"
                        className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                        value={r.filter}
                        onChange={(e) =>
                            router.get(
                                Reader.recipients.url(announcement.id),
                                e.target.value
                                    ? { status: e.target.value }
                                    : {},
                            )
                        }
                    >
                        <option value="">
                            {t('announcements.recipients.all')}
                        </option>
                        <option value="unread">
                            {t('announcements.recipients.not_read')}
                        </option>
                        <option value="unacknowledged">
                            {t('announcements.recipients.not_confirmed')}
                        </option>
                    </select>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-4">
                                    {r.kind === 'students'
                                        ? 'Siswa'
                                        : 'Pengguna'}
                                </th>
                                {r.kind === 'students' && (
                                    <th className="p-4">
                                        {t(
                                            'announcements.recipients.guardians',
                                        )}
                                    </th>
                                )}
                                <th className="p-4">
                                    {t('announcements.recipients.read')}
                                </th>
                                <th className="p-4">
                                    {t('announcements.recipients.confirmation')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {r.rows.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="p-4 font-medium">
                                        {row.name}
                                    </td>
                                    {r.kind === 'students' && (
                                        <td className="p-4">
                                            {row.guardians.length
                                                ? row.guardians.map((g, i) => (
                                                      <p key={i}>
                                                          {g.name}
                                                          {!g.has_account && (
                                                              <span className="text-muted-foreground">
                                                                  {' '}
                                                                  {t(
                                                                      'announcements.recipients.no_active_account',
                                                                  )}
                                                              </span>
                                                          )}
                                                      </p>
                                                  ))
                                                : t(
                                                      'announcements.no_linked_guardian',
                                                  )}
                                        </td>
                                    )}
                                    <td className="p-4">
                                        {row.read
                                            ? t('common.yes')
                                            : t('common.no')}
                                    </td>
                                    <td className="p-4">
                                        {row.acknowledged
                                            ? t('common.yes')
                                            : t('common.no')}
                                    </td>
                                </tr>
                            ))}
                            {r.rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground p-8 text-center"
                                    >
                                        {t('announcements.recipients.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span>
                        {t('common.page_of', {
                            current: r.rows.current_page,
                            last: r.rows.last_page,
                        })}
                    </span>
                    <div className="flex gap-2">
                        {r.rows.prev_page_url && (
                            <Button asChild variant="outline">
                                <Link href={r.rows.prev_page_url}>
                                    {t('common.previous')}
                                </Link>
                            </Button>
                        )}
                        {r.rows.next_page_url && (
                            <Button asChild variant="outline">
                                <Link href={r.rows.next_page_url}>
                                    {t('common.next')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
