import { Head, useForm, setLayoutProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type CatalogItem = {
    key: string;
    label: string;
    in_app: boolean;
    default_whatsapp: boolean;
    default_email: boolean;
    whatsapp_allowed: boolean;
    email_allowed: boolean;
    opt_out_allowed: boolean;
    quota_bound: boolean;
    quiet_hours_bound: boolean;
};

type ChannelSetting = {
    whatsapp: boolean;
    email: boolean;
};

type Props = {
    channels: Record<string, ChannelSetting>;
    catalog: CatalogItem[];
    whatsapp_daily_quota: number | null;
    today_whatsapp_usage: number;
    quiet_hours_start: string;
    quiet_hours_end: string;
    bill_reminder_days_before: number;
};

export default function NotificationSettings({
    channels,
    catalog,
    whatsapp_daily_quota,
    today_whatsapp_usage,
    quiet_hours_start,
    quiet_hours_end,
    bill_reminder_days_before,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('settings.nav.notifications'),
                href: '/settings/notifications',
            },
        ],
    });
    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            notification_channels: channels,
            whatsapp_daily_quota: (whatsapp_daily_quota ?? '') as
                | number
                | string,
            quiet_hours_start,
            quiet_hours_end,
            bill_reminder_days_before,
        });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/settings/notifications');
    };

    return (
        <>
            <Head title={t('settings.notifications.page_title')} />
            <form onSubmit={submit} className="space-y-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        variant="small"
                        title={t('settings.notifications.title')}
                        description={t('settings.notifications.description')}
                    />

                    <div className="bg-muted flex items-center gap-3 rounded-lg border px-4 py-2 text-sm">
                        <span className="text-muted-foreground">
                            {t('settings.notifications.usage_today')}
                        </span>
                        <span className="text-foreground font-semibold">
                            {today_whatsapp_usage} /{' '}
                            {data.whatsapp_daily_quota !== ''
                                ? data.whatsapp_daily_quota
                                : '∞'}{' '}
                            {t('settings.notifications.messages')}
                        </span>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_daily_quota">
                            {t('settings.notifications.whatsapp_daily_quota')}
                        </Label>
                        <Input
                            id="whatsapp_daily_quota"
                            type="number"
                            min={1}
                            placeholder={t(
                                'settings.notifications.quota_placeholder',
                            )}
                            value={data.whatsapp_daily_quota}
                            onChange={(e) =>
                                setData(
                                    'whatsapp_daily_quota',
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        />
                        <span className="text-muted-foreground text-xs">
                            {t('settings.notifications.quota_hint')}
                        </span>
                        <InputError message={errors.whatsapp_daily_quota} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="bill_reminder_days_before">
                            {t('settings.notifications.bill_reminder_days')}
                        </Label>
                        <Input
                            id="bill_reminder_days_before"
                            type="number"
                            min={1}
                            max={30}
                            value={data.bill_reminder_days_before}
                            onChange={(e) =>
                                setData(
                                    'bill_reminder_days_before',
                                    Number(e.target.value),
                                )
                            }
                        />
                        <span className="text-muted-foreground text-xs">
                            {t('settings.notifications.bill_reminder_hint')}
                        </span>
                        <InputError
                            message={errors.bill_reminder_days_before}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quiet_hours_start">
                            {t('settings.notifications.quiet_hours_start')}
                        </Label>
                        <Input
                            id="quiet_hours_start"
                            type="time"
                            value={data.quiet_hours_start}
                            onChange={(e) =>
                                setData('quiet_hours_start', e.target.value)
                            }
                        />
                        <span className="text-muted-foreground text-xs">
                            {t('settings.notifications.quiet_hours_start_hint')}
                        </span>
                        <InputError message={errors.quiet_hours_start} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quiet_hours_end">
                            {t('settings.notifications.quiet_hours_end')}
                        </Label>
                        <Input
                            id="quiet_hours_end"
                            type="time"
                            value={data.quiet_hours_end}
                            onChange={(e) =>
                                setData('quiet_hours_end', e.target.value)
                            }
                        />
                        <span className="text-muted-foreground text-xs">
                            {t('settings.notifications.quiet_hours_end_hint')}
                        </span>
                        <InputError message={errors.quiet_hours_end} />
                    </div>
                </div>

                <div className="space-y-4">
                    <h3 className="text-foreground text-base font-semibold">
                        {t('settings.notifications.channels_title')}
                    </h3>
                    <div className="border-border overflow-hidden rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted text-muted-foreground border-b text-xs font-medium uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        {t('settings.notifications.table.type')}
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        {t(
                                            'settings.notifications.table.in_app',
                                        )}
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        {t(
                                            'settings.notifications.table.whatsapp',
                                        )}
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        {t(
                                            'settings.notifications.table.email',
                                        )}
                                    </th>
                                    <th className="px-4 py-3">
                                        {t(
                                            'settings.notifications.table.policy',
                                        )}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-border divide-y">
                                {catalog.map((item) => {
                                    const currentChannel = data
                                        .notification_channels[item.key] ?? {
                                        whatsapp: item.default_whatsapp,
                                        email: item.default_email,
                                    };

                                    return (
                                        <tr
                                            key={item.key}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="text-foreground font-medium">
                                                    {item.label}
                                                </div>
                                                <div className="text-muted-foreground font-mono text-xs">
                                                    {item.key}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {t(
                                                        'settings.notifications.active',
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {item.whatsapp_allowed ? (
                                                    <Checkbox
                                                        checked={
                                                            currentChannel.whatsapp
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            setData(
                                                                'notification_channels',
                                                                {
                                                                    ...data.notification_channels,
                                                                    [item.key]:
                                                                        {
                                                                            ...currentChannel,
                                                                            whatsapp:
                                                                                checked ===
                                                                                true,
                                                                        },
                                                                },
                                                            );
                                                        }}
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {item.email_allowed ? (
                                                    <Checkbox
                                                        checked={
                                                            currentChannel.email
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            setData(
                                                                'notification_channels',
                                                                {
                                                                    ...data.notification_channels,
                                                                    [item.key]:
                                                                        {
                                                                            ...currentChannel,
                                                                            email:
                                                                                checked ===
                                                                                true,
                                                                        },
                                                                },
                                                            );
                                                        }}
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 text-xs">
                                                <div className="flex flex-wrap gap-1">
                                                    {item.quota_bound && (
                                                        <span className="bg-muted rounded px-1.5 py-0.5">
                                                            {t(
                                                                'settings.notifications.policy.quota',
                                                            )}
                                                        </span>
                                                    )}
                                                    {item.quiet_hours_bound && (
                                                        <span className="bg-muted rounded px-1.5 py-0.5">
                                                            {t(
                                                                'settings.notifications.policy.quiet_hours',
                                                            )}
                                                        </span>
                                                    )}
                                                    {item.opt_out_allowed && (
                                                        <span className="bg-muted rounded px-1.5 py-0.5">
                                                            {t(
                                                                'settings.notifications.policy.opt_out',
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        {t('settings.notifications.save')}
                    </Button>

                    {recentlySuccessful && (
                        <span className="text-muted-foreground text-sm">
                            {t('settings.notifications.saved')}
                        </span>
                    )}
                </div>
            </form>
        </>
    );
}
