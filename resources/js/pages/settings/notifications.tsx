import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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
            <Head title="Pengaturan Notifikasi" />

            <h1 className="sr-only">Pengaturan Notifikasi</h1>

            <form onSubmit={submit} className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Pengaturan Notifikasi"
                        description="Atur saluran pengiriman WhatsApp & Email, kuota gateway, dan jam tenang sekolah."
                    />

                    <div className="bg-muted flex items-center gap-3 rounded-lg border px-4 py-2 text-sm">
                        <span className="text-muted-foreground">
                            Penggunaan WA Hari Ini:
                        </span>
                        <span className="text-foreground font-semibold">
                            {today_whatsapp_usage} /{' '}
                            {data.whatsapp_daily_quota !== ''
                                ? data.whatsapp_daily_quota
                                : '∞'}{' '}
                            pesan
                        </span>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="whatsapp_daily_quota">
                            Kuota Harian WhatsApp
                        </Label>
                        <Input
                            id="whatsapp_daily_quota"
                            type="number"
                            min={1}
                            placeholder="Kosongkan untuk tanpa batas"
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
                            Batas maksimal pesan WhatsApp non-kritis per hari.
                        </span>
                        <InputError message={errors.whatsapp_daily_quota} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="bill_reminder_days_before">
                            Pengingat Tagihan (Hari Sebelum Jatuh Tempo)
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
                            Kirim notifikasi pengingat SPP N hari sebelum
                            tanggal jatuh tempo.
                        </span>
                        <InputError
                            message={errors.bill_reminder_days_before}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quiet_hours_start">
                            Mulai Jam Tenang (WIB)
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
                            Pesan tidak mendesak ditunda pengirimannya mulai jam
                            ini.
                        </span>
                        <InputError message={errors.quiet_hours_start} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quiet_hours_end">
                            Selesai Jam Tenang (WIB)
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
                            Pesan yang tertunda dikirimkan saat jam tenang
                            berakhir.
                        </span>
                        <InputError message={errors.quiet_hours_end} />
                    </div>
                </div>

                <div className="space-y-4">
                    <h3 className="text-foreground text-base font-semibold">
                        Saluran per Jenis Notifikasi
                    </h3>
                    <div className="border-border overflow-hidden rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted text-muted-foreground border-b text-xs font-medium uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        Jenis Notifikasi
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        In-App
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        WhatsApp
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Email
                                    </th>
                                    <th className="px-4 py-3">Kebijakan</th>
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
                                                    ✓ Aktif
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
                                                            Kuota
                                                        </span>
                                                    )}
                                                    {item.quiet_hours_bound && (
                                                        <span className="bg-muted rounded px-1.5 py-0.5">
                                                            Jam Tenang
                                                        </span>
                                                    )}
                                                    {item.opt_out_allowed && (
                                                        <span className="bg-muted rounded px-1.5 py-0.5">
                                                            Bisa Opt-out
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
                        Simpan Pengaturan
                    </Button>

                    {recentlySuccessful && (
                        <span className="text-muted-foreground text-sm">
                            Tersimpan.
                        </span>
                    )}
                </div>
            </form>
        </>
    );
}
