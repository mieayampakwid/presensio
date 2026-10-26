import type { ScannerSession, ScannerState } from '../types/scanner';

export function createScannerSession(now: () => number): ScannerSession {
    let state: ScannerState = 'idle';
    let running = false;
    let pending = false;
    let submitted = false;
    let needsRemoval = false;
    let absentSince: number | null = null;
    let lastStart = -Infinity;
    let retryAt = 0;

    return {
        start() {
            if (
                pending ||
                ['paused', 'unauthorized', 'throttled'].includes(state)
            )
                return false;
            running = true;
            needsRemoval = submitted;
            absentSince = null;
            state = needsRemoval ? 'waiting-removal' : 'ready';
            return true;
        },
        stop() {
            running = false;
            if (pending) state = 'paused';
            else if (!['paused', 'unauthorized', 'throttled'].includes(state))
                state = 'idle';
            absentSince = null;
        },
        observe(qrPresent) {
            if (
                !running ||
                ['paused', 'unauthorized', 'throttled'].includes(state)
            )
                return false;
            const time = now();
            if (!qrPresent) {
                absentSince ??= time;
                if (time - absentSince >= 1000) {
                    needsRemoval = false;
                    if (!pending) state = 'ready';
                }
                return false;
            }
            absentSince = null;
            if (pending || needsRemoval || time - lastStart < 2000)
                return false;
            pending = true;
            submitted = true;
            needsRemoval = true;
            lastStart = time;
            state = 'processing';
            return true;
        },
        settle(result, retryAfterMs = 0) {
            pending = false;
            if (result === 'unconfirmed') state = 'paused';
            else if (result === 'unauthorized') state = 'unauthorized';
            else if (result === 'throttled') {
                state = 'throttled';
                retryAt = now() + Math.max(0, retryAfterMs);
            } else
                state = !running
                    ? 'idle'
                    : needsRemoval
                      ? 'waiting-removal'
                      : 'ready';
        },
        resume() {
            if (
                pending ||
                state === 'unauthorized' ||
                (state === 'throttled' && now() < retryAt)
            )
                return false;
            if (state !== 'paused' && state !== 'throttled') return false;
            needsRemoval = true;
            absentSince = null;
            state = running ? 'waiting-removal' : 'idle';
            return true;
        },
        snapshot: () => ({ state, pending, retryAt }),
    };
}
