import { http, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ScannerController from '@/actions/App/Http/Controllers/Attendance/ScannerController';
import { createScannerCamera } from '@/lib/scanner-camera';
import type { ScannerCamera } from '@/lib/scanner-camera';
import { appendScannerResult, scannerFeedback } from '@/lib/scanner-feedback';
import { createScannerSession } from '@/lib/scanner-session';
import { submitScannerCredential } from '@/lib/scanner-transport';
import type { ScanResponse, ScannerLogEntry } from '@/types/scanner';

export function useScanner() {
    const [session] = useState(() =>
        createScannerSession(() => performance.now()),
    );
    const [view, setView] = useState(session.snapshot());
    const [starting, setStarting] = useState(false);
    const [active, setActive] = useState(false);
    const [errorKey, setErrorKey] = useState<string | null>(null);
    const [result, setResult] = useState<ScanResponse | null>(null);
    const [log, setLog] = useState<ScannerLogEntry[]>([]);
    const [remaining, setRemaining] = useState(0);
    const mounted = useRef(false);
    const generation = useRef(0);
    const startupPending = useRef(false);
    const camera = useRef<ScannerCamera | null>(null);
    const request = useRef<AbortController | null>(null);
    const audio = useRef<AudioContext | null>(null);
    const resultTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const requestTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const stop = useCallback(async () => {
        generation.current++;
        session.stop();
        request.current?.abort();
        if (resultTimer.current) clearTimeout(resultTimer.current);
        if (requestTimer.current) clearTimeout(requestTimer.current);
        if (mounted.current) {
            setActive(false);
            setView(session.snapshot());
            setResult(null);
            setLog([]);
        }
        await camera.current?.stop().catch(() => {});
    }, [session]);

    useEffect(() => {
        mounted.current = true;
        const hidden = () => {
            if (document.hidden) void stop();
        };
        document.addEventListener('visibilitychange', hidden);
        const unsubscribe = router.on('before', () => {
            void stop();
        });
        return () => {
            mounted.current = false;
            document.removeEventListener('visibilitychange', hidden);
            unsubscribe();
            void stop();
            void audio.current?.close().catch(() => {});
        };
    }, [stop]);

    useEffect(() => {
        if (view.state !== 'throttled') return;
        const tick = () =>
            setRemaining(
                Math.max(
                    0,
                    Math.ceil((view.retryAt - performance.now()) / 1000),
                ),
            );
        const timer = setInterval(tick, 250);
        return () => clearInterval(timer);
    }, [view.state, view.retryAt]);

    function feedback(response: ScanResponse) {
        setResult(response);
        setLog((entries) =>
            appendScannerResult(entries, { receivedAt: Date.now(), response }),
        );
        if (resultTimer.current) clearTimeout(resultTimer.current);
        resultTimer.current = setTimeout(() => {
            if (mounted.current) setResult(null);
        }, 4000);
        const sound = scannerFeedback(response).sound;
        const context = audio.current;
        if (sound && context?.state === 'running') {
            try {
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                oscillator.frequency.value =
                    sound === 'error' ? 180 : sound === 'warning' ? 440 : 880;
                oscillator.type = sound === 'error' ? 'square' : 'sine';
                gain.gain.setValueAtTime(0.08, context.currentTime);
                gain.gain.exponentialRampToValueAtTime(
                    0.001,
                    context.currentTime + 0.18,
                );
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start();
                oscillator.stop(context.currentTime + 0.18);
                oscillator.onended = () => {
                    oscillator.disconnect();
                    gain.disconnect();
                };
            } catch {
                /* Visual feedback remains authoritative when audio is unavailable. */
            }
        }
    }

    async function submit(credential: string, current: number) {
        const controller = new AbortController();
        request.current = controller;
        requestTimer.current = setTimeout(() => controller.abort(), 15000);
        const submission = await submitScannerCredential(
            http.getClient(),
            ScannerController.store.url(),
            credential,
            controller.signal,
        );
        if (requestTimer.current) clearTimeout(requestTimer.current);
        request.current = null;
        if (!mounted.current || current !== generation.current) {
            session.settle('unconfirmed');
            if (mounted.current) setView(session.snapshot());
            return;
        }
        if (submission.kind === 'confirmed') {
            session.settle('confirmed');
            feedback(submission.response);
            setErrorKey(null);
        } else if (submission.kind === 'validation') {
            session.settle('confirmed');
            setErrorKey('scanner.validation');
        } else if (submission.kind === 'throttled') {
            session.settle('throttled', submission.retryAfterMs);
            setRemaining(Math.ceil(submission.retryAfterMs / 1000));
            setErrorKey('scanner.throttle');
        } else if (submission.kind === 'unauthorized') {
            session.settle('unauthorized');
            setErrorKey('scanner.unauthorized');
            setLog([]);
            setResult(null);
            setActive(false);
            await camera.current?.stop().catch(() => {});
        } else {
            session.settle('unconfirmed');
            setErrorKey('scanner.unconfirmed');
        }
        if (mounted.current) setView(session.snapshot());
    }

    async function start() {
        if (startupPending.current || !session.start()) return;
        startupPending.current = true;
        const current = ++generation.current;
        setStarting(true);
        setErrorKey(null);
        setView(session.snapshot());
        try {
            audio.current ??= new AudioContext();
            void audio.current.resume().catch(() => {});
        } catch {
            /* Camera scanning does not require Web Audio. */
        }
        camera.current ??= createScannerCamera(
            'scanner-camera',
            (credential) => {
                if (!mounted.current) return;
                const shouldSubmit = session.observe(credential !== null);
                setView(session.snapshot());
                if (shouldSubmit && credential !== null)
                    void submit(credential, generation.current);
            },
            () => {
                void stop();
                if (mounted.current) setErrorKey('scanner.camera_ended');
            },
        );
        try {
            await camera.current.start();
            if (mounted.current && generation.current === current)
                setActive(true);
        } catch (error) {
            if (mounted.current && generation.current === current) {
                session.stop();
                setActive(false);
                setView(session.snapshot());
                const message = String(error);
                setErrorKey(
                    message.includes('insecure')
                        ? 'scanner.insecure'
                        : message.includes('unsupported')
                          ? 'scanner.unsupported'
                          : /NotAllowed|PermissionDenied|Permission denied/i.test(
                                  message,
                              )
                            ? 'scanner.camera_denied'
                            : /NotFound|DevicesNotFound/i.test(message)
                              ? 'scanner.no_camera'
                              : 'scanner.camera_unavailable',
                );
            }
        } finally {
            startupPending.current = false;
            if (mounted.current) setStarting(false);
        }
    }

    function resume() {
        if (session.resume()) {
            setErrorKey(null);
            setView(session.snapshot());
        }
    }

    async function toggleFullscreen() {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else
                await document
                    .getElementById('scanner-workspace')
                    ?.requestFullscreen();
        } catch {
            /* Fullscreen is optional. */
        }
    }

    return {
        state: view.state,
        pending: view.pending,
        starting,
        active,
        errorKey,
        result,
        log,
        remaining,
        start,
        stop,
        resume,
        toggleFullscreen,
    };
}
