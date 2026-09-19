import { Head, router } from '@inertiajs/react';
import { UserRoundX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';
import type { StudentQr } from '@/types/attendance';

type Props = {
    qr: StudentQr | null;
};

const ROTATE_AFTER_MS = 25000;

export default function MyQr({ qr }: Props) {
    const { resolvedAppearance } = useAppearance();
    const [now, setNow] = useState(() => Date.now());
    const rotatedFor = useRef<string | null>(null);

    useEffect(() => {
        if (!qr) {
            return;
        }

        const tick = setInterval(() => setNow(Date.now()), 1000);
        const rotate = setInterval(() => {
            router.reload({ only: ['qr'] });
        }, ROTATE_AFTER_MS);

        return () => {
            clearInterval(tick);
            clearInterval(rotate);
        };
    }, [qr?.token]);

    const secondsLeft = qr
        ? Math.max(
              0,
              Math.floor((new Date(qr.expires_at).getTime() - now) / 1000),
          )
        : 0;

    // Safety net: never keep an expired code on screen, even if the
    // rotation interval was somehow missed.
    useEffect(() => {
        if (qr && secondsLeft <= 0 && rotatedFor.current !== qr.token) {
            rotatedFor.current = qr.token;
            router.reload({ only: ['qr'] });
        }
    }, [secondsLeft, qr]);

    return (
        <>
            <Head title="My QR" />

            <div className="space-y-6 p-4">
                <Heading
                    title="My QR"
                    description="Show this code at the school scanner to check in."
                />

                {qr ? (
                    <Card className="max-w-md">
                        <CardHeader className="items-center">
                            <CardTitle>Attendance code</CardTitle>
                            <CardDescription>
                                Scan times are recorded in the{' '}
                                {qr.school_timezone} school timezone.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-4">
                            <div className="border-border aspect-square w-64 rounded-lg border">
                                <div
                                    className="h-full w-full rounded-lg bg-white p-3 [&_svg]:size-full"
                                    dangerouslySetInnerHTML={{
                                        __html: qr.svg,
                                    }}
                                    style={{
                                        filter:
                                            resolvedAppearance === 'dark'
                                                ? 'invert(1) brightness(1.5)'
                                                : undefined,
                                    }}
                                />
                            </div>
                            <Badge
                                variant={
                                    secondsLeft > 0 ? 'default' : 'secondary'
                                }
                            >
                                {secondsLeft > 0
                                    ? `Expires in ${secondsLeft}s`
                                    : 'Refreshing…'}
                            </Badge>
                            <p className="text-muted-foreground text-center text-sm">
                                The code renews itself automatically — just keep
                                this page open.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="max-w-md">
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                            <UserRoundX className="size-10" />
                            <p className="text-foreground text-sm font-medium">
                                No student profile linked
                            </p>
                            <p className="text-sm">
                                Your account is not linked to a student profile
                                yet. Ask the school office to connect it.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

MyQr.layout = {
    breadcrumbs: [
        {
            title: 'My QR',
        },
    ],
};
