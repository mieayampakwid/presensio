import test from 'node:test';
import assert from 'node:assert/strict';
import { submitScannerCredential } from '../../resources/js/lib/scanner-transport.ts';

const submit = (status, data, headers = {}) =>
    submitScannerCredential(
        {
            request: async () => {
                const response = {
                    status,
                    data: JSON.stringify(data),
                    headers,
                };
                if (status >= 400) throw { response };
                return response;
            },
        },
        '/scanner/scan',
        'private-token',
        new AbortController().signal,
    );

void test('credential errors are confirmed results while validation is separate', async () => {
    assert.deepEqual(
        await submit(422, { outcome: 'error', detail: 'error_expired_token' }),
        {
            kind: 'confirmed',
            response: { outcome: 'error', detail: 'error_expired_token' },
        },
    );
    assert.deepEqual(
        await submit(422, { errors: { credential: ['Invalid.'] } }),
        { kind: 'validation' },
    );
});

void test('session and permission failures halt scanning', async () => {
    for (const status of [401, 403, 419])
        assert.deepEqual(await submit(status, {}), { kind: 'unauthorized' });
});

void test('throttle preserves retry delay and network and server failures are unconfirmed', async () => {
    assert.deepEqual(await submit(429, {}, { 'retry-after': '12' }), {
        kind: 'throttled',
        retryAfterMs: 12000,
    });
    assert.deepEqual(await submit(500, {}), { kind: 'unconfirmed' });
    assert.deepEqual(
        await submitScannerCredential(
            {
                request: async () => {
                    throw new Error('network');
                },
            },
            '/scanner/scan',
            'token',
            new AbortController().signal,
        ),
        { kind: 'unconfirmed' },
    );
});

void test('a redirect HTML body cannot be mistaken for a successful attendance result', async () => {
    const result = await submitScannerCredential(
        {
            request: async () => ({
                status: 200,
                data: '<html>login</html>',
                headers: {},
            }),
        },
        '/scanner/scan',
        'token',
        new AbortController().signal,
    );
    assert.deepEqual(result, { kind: 'unconfirmed' });
});

void test('successful responses discard fields outside the scan contract', async () => {
    const result = await submit(200, {
        outcome: 'checked_in',
        detail: 'check_in',
        student_name: 'Siswa',
        subject_type: 'student',
        attendance_status: 'present',
        credential: 'must-not-retain',
    });
    assert.equal(result.kind, 'confirmed');
    assert.equal(result.response.student_name, 'Siswa');
    assert.equal('credential' in result.response, false);
});
