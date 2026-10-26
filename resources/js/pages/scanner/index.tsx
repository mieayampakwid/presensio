import { Head, Link } from '@inertiajs/react';
import {
    Camera,
    CheckCircle2,
    Maximize,
    Pause,
    Play,
    QrCode,
    ScanLine,
    TriangleAlert,
} from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useScanner } from '@/hooks/use-scanner';
import { useTranslation } from '@/hooks/use-translation';
import { scannerFeedback } from '@/lib/scanner-feedback';
import { cn } from '@/lib/utils';
import { login } from '@/routes';

const tones = {
    success:
        'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100',
    warning:
        'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
    checkout:
        'border-blue-300 bg-blue-50 text-blue-950 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100',
    ignored: 'border-border bg-muted text-foreground',
    error: 'border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
};

export default function ScannerPage() {
    const scanner = useScanner();
    const { t, locale } = useTranslation();
    const state = scanner.starting ? 'starting' : scanner.state;
    const feedback = scanner.result ? scannerFeedback(scanner.result) : null;
    const subject =
        scanner.result && scanner.result.outcome !== 'error'
            ? scanner.result
            : null;
    const canResume =
        scanner.state === 'paused' || scanner.state === 'throttled';

    return (
        <div
            id="scanner-workspace"
            className="bg-background flex min-h-full flex-1 flex-col gap-6 overflow-auto p-4 md:p-6"
        >
            <Head title={t('scanner.title')} />
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('scanner.title')}
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {t('scanner.description')}
                    </p>
                </div>
                <Button
                    variant="outline"
                    onClick={() => void scanner.toggleFullscreen()}
                >
                    <Maximize />
                    {t('scanner.fullscreen')}
                </Button>
            </div>
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                <div className="flex min-w-0 flex-col gap-4">
                    <Card className="gap-0 overflow-hidden py-0">
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ScanLine className="size-5" />
                                {t('scanner.camera')}
                            </CardTitle>
                            <Badge
                                variant="secondary"
                                className="max-w-full whitespace-normal"
                            >
                                {t(`scanner.${state}`)}
                            </Badge>
                        </CardHeader>
                        <div className="relative flex min-h-72 items-center justify-center bg-black sm:min-h-96">
                            <div
                                id="scanner-camera"
                                className="w-full [&_video]:max-h-[65vh] [&_video]:w-full [&_video]:object-contain"
                            />
                            {!scanner.active && (
                                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-center text-white/70">
                                    <Camera className="size-10" />
                                    <p className="text-sm">
                                        {t(
                                            scanner.starting
                                                ? 'scanner.permission_hint'
                                                : 'scanner.camera_placeholder',
                                        )}
                                    </p>
                                </div>
                            )}
                            {scanner.active && (
                                <div
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-12 rounded-xl border-2 border-white/60 sm:inset-16"
                                />
                            )}
                        </div>
                        <CardContent className="flex flex-wrap items-center justify-between gap-4 p-5">
                            <p className="text-muted-foreground max-w-md text-sm">
                                {t('scanner.hint')}
                            </p>
                            {scanner.active || scanner.starting ? (
                                <Button
                                    variant="outline"
                                    onClick={() => void scanner.stop()}
                                >
                                    <Pause />
                                    {t(
                                        scanner.starting
                                            ? 'scanner.cancel'
                                            : 'scanner.stop',
                                    )}
                                </Button>
                            ) : (
                                scanner.state !== 'unauthorized' &&
                                !canResume && (
                                    <Button
                                        disabled={scanner.pending}
                                        onClick={() => void scanner.start()}
                                    >
                                        <Play />
                                        {t('scanner.start')}
                                    </Button>
                                )
                            )}
                        </CardContent>
                    </Card>
                    {scanner.errorKey && (
                        <Alert
                            variant={
                                scanner.state === 'unauthorized'
                                    ? 'destructive'
                                    : 'default'
                            }
                        >
                            <TriangleAlert />
                            <AlertDescription className="flex flex-col items-start gap-3">
                                <p>
                                    {t(scanner.errorKey, {
                                        seconds: scanner.remaining,
                                    })}
                                </p>
                                {canResume && (
                                    <Button
                                        variant="outline"
                                        disabled={
                                            scanner.pending ||
                                            (scanner.remaining > 0 &&
                                                scanner.state === 'throttled')
                                        }
                                        onClick={scanner.resume}
                                    >
                                        {t('scanner.resume')}
                                    </Button>
                                )}
                                {scanner.state === 'unauthorized' && (
                                    <Button asChild variant="outline">
                                        <Link href={login()}>
                                            {t('scanner.login')}
                                        </Link>
                                    </Button>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                    <div
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                        className={cn(
                            'flex min-h-28 items-center gap-4 rounded-xl border p-5',
                            feedback
                                ? tones[feedback.tone]
                                : 'border-border bg-muted/40',
                        )}
                    >
                        {feedback ? (
                            feedback.tone === 'error' ||
                            feedback.tone === 'warning' ? (
                                <TriangleAlert className="size-8 shrink-0" />
                            ) : (
                                <CheckCircle2 className="size-8 shrink-0" />
                            )
                        ) : (
                            <QrCode className="text-muted-foreground size-8 shrink-0" />
                        )}
                        <div className="min-w-0">
                            <p className="text-xl font-semibold break-words">
                                {subject?.student_name ??
                                    t(
                                        feedback
                                            ? feedback.messageKey
                                            : 'scanner.awaiting',
                                    )}
                            </p>
                            <p className="mt-1 text-sm">
                                {feedback
                                    ? t(feedback.messageKey)
                                    : t('scanner.awaiting_hint')}
                            </p>
                        </div>
                    </div>
                    <p role="status" className="sr-only">
                        {t(`scanner.${state}`)}
                    </p>
                </div>
                <Card className="min-w-0 gap-4">
                    <CardHeader>
                        <CardTitle>{t('scanner.recent')}</CardTitle>
                        <CardDescription>
                            {t('scanner.recent_hint')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {scanner.log.length === 0 ? (
                            <div className="text-muted-foreground flex min-h-40 items-center justify-center text-center text-sm">
                                {t('scanner.empty')}
                            </div>
                        ) : (
                            <ol className="max-h-[36rem] divide-y overflow-auto">
                                {scanner.log.map((entry, index) => {
                                    const item = scannerFeedback(
                                        entry.response,
                                    );
                                    const response = entry.response;
                                    return (
                                        <li
                                            key={`${entry.receivedAt}-${index}`}
                                            className="flex items-start justify-between gap-3 py-3 first:pt-0"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium break-words">
                                                    {response.outcome !==
                                                    'error'
                                                        ? response.student_name
                                                        : t(item.messageKey)}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {t(item.messageKey)}
                                                    {response.outcome !==
                                                    'error'
                                                        ? ` • ${t(`scanner.${response.subject_type}`)}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <time
                                                className="text-muted-foreground shrink-0 text-xs tabular-nums"
                                                dateTime={new Date(
                                                    entry.receivedAt,
                                                ).toISOString()}
                                            >
                                                {new Date(
                                                    entry.receivedAt,
                                                ).toLocaleTimeString(locale, {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                    second: '2-digit',
                                                })}
                                            </time>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
