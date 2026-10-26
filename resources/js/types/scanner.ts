export type ScanResponse =
    | {
          outcome: 'error';
          detail: 'error_expired_token' | 'error_unknown_credential';
      }
    | {
          outcome: 'checked_in' | 'checked_out' | 'ignored';
          detail: string;
          student_name: string | null;
          subject_type: 'student' | 'employee';
          attendance_status: string | null;
      };

export type ScannerLogEntry = { receivedAt: number; response: ScanResponse };
export type ScannerState =
    | 'idle'
    | 'ready'
    | 'processing'
    | 'waiting-removal'
    | 'paused'
    | 'unauthorized'
    | 'throttled';
export type ScannerSessionState = {
    state: ScannerState;
    pending: boolean;
    retryAt: number;
};
export type ScannerSession = {
    start(): boolean;
    stop(): void;
    observe(qrPresent: boolean): boolean;
    settle(
        result: 'confirmed' | 'unconfirmed' | 'unauthorized' | 'throttled',
        retryAfterMs?: number,
    ): void;
    resume(): boolean;
    snapshot(): ScannerSessionState;
};
