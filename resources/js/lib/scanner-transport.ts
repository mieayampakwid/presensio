import type { ScanResponse } from '../types/scanner';

type HttpResponse = {
    status: number;
    data: string;
    headers: Record<string, string>;
};
type Client = {
    request(config: {
        method: 'post';
        url: string;
        data: { credential: string };
        headers: Record<string, string>;
        signal: AbortSignal;
    }): Promise<HttpResponse>;
};
export type ScannerSubmission =
    | { kind: 'confirmed'; response: ScanResponse }
    | { kind: 'validation' | 'unconfirmed' | 'unauthorized' }
    | { kind: 'throttled'; retryAfterMs: number };

function parseResult(value: unknown): ScanResponse | null {
    if (!value || typeof value !== 'object') return null;
    const body = value as Record<string, unknown>;
    if (
        body.outcome === 'error' &&
        (body.detail === 'error_expired_token' ||
            body.detail === 'error_unknown_credential')
    ) {
        return { outcome: 'error', detail: body.detail };
    }
    if (
        (body.outcome === 'checked_in' ||
            body.outcome === 'checked_out' ||
            body.outcome === 'ignored') &&
        typeof body.detail === 'string' &&
        (typeof body.student_name === 'string' || body.student_name === null) &&
        (body.subject_type === 'student' || body.subject_type === 'employee') &&
        (typeof body.attendance_status === 'string' ||
            body.attendance_status === null)
    ) {
        return {
            outcome: body.outcome,
            detail: body.detail,
            student_name: body.student_name,
            subject_type: body.subject_type,
            attendance_status: body.attendance_status,
        };
    }
    return null;
}

export async function submitScannerCredential(
    client: Client,
    url: string,
    credential: string,
    signal: AbortSignal,
): Promise<ScannerSubmission> {
    let response: HttpResponse;
    try {
        response = await client.request({
            method: 'post',
            url,
            data: { credential },
            headers: { Accept: 'application/json' },
            signal,
        });
    } catch (error) {
        if (!error || typeof error !== 'object' || !('response' in error))
            return { kind: 'unconfirmed' };
        response = error.response as HttpResponse;
    }
    if ([401, 403, 419].includes(response.status))
        return { kind: 'unauthorized' };
    if (response.status === 429) {
        const seconds = Number(response.headers['retry-after']);
        return {
            kind: 'throttled',
            retryAfterMs:
                Number.isFinite(seconds) && seconds >= 0
                    ? seconds * 1000
                    : 60000,
        };
    }
    if (response.status !== 200 && response.status !== 422)
        return { kind: 'unconfirmed' };
    try {
        const body: unknown = JSON.parse(response.data);
        const result = parseResult(body);
        if (
            result &&
            (response.status === 422) === (result.outcome === 'error')
        )
            return { kind: 'confirmed', response: result };
        if (
            response.status === 422 &&
            body &&
            typeof body === 'object' &&
            'errors' in body
        )
            return { kind: 'validation' };
    } catch {
        /* A transport response can be HTML after an expired session redirect. */
    }
    return { kind: 'unconfirmed' };
}
