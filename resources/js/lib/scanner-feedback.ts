import type { ScanResponse, ScannerLogEntry } from '../types/scanner';

type Feedback = {
    tone: 'success' | 'warning' | 'checkout' | 'ignored' | 'error';
    messageKey: string;
    sound: 'success' | 'warning' | 'error' | null;
};

export function scannerFeedback(result: ScanResponse): Feedback {
    if (result.outcome === 'error')
        return {
            tone: 'error',
            messageKey:
                result.detail === 'error_expired_token'
                    ? 'scanner.expired'
                    : 'scanner.unknown',
            sound: 'error',
        };
    if (result.outcome === 'checked_out')
        return {
            tone: 'checkout',
            messageKey: 'scanner.checked_out',
            sound: 'success',
        };
    if (result.outcome === 'checked_in')
        return result.attendance_status === 'late'
            ? { tone: 'warning', messageKey: 'scanner.late', sound: 'warning' }
            : {
                  tone: 'success',
                  messageKey: 'scanner.present',
                  sound: 'success',
              };
    const ignored: Record<string, string> = {
        ignored_debounce: 'scanner.debounce',
        ignored_complete: 'scanner.complete',
        ignored_excused: 'scanner.excused',
        ignored_alumni: 'scanner.alumni',
        ignored_inactive: 'scanner.inactive',
    };
    return {
        tone: 'ignored',
        messageKey: ignored[result.detail] ?? 'scanner.ignored',
        sound: null,
    };
}

export function appendScannerResult(
    log: readonly ScannerLogEntry[],
    entry: ScannerLogEntry,
): ScannerLogEntry[] {
    return [entry, ...log].slice(0, 50);
}
