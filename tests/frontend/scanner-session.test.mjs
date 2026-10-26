import test from 'node:test';
import assert from 'node:assert/strict';
import { createScannerSession } from '../../resources/js/lib/scanner-session.ts';
import {
    scannerFeedback,
    appendScannerResult,
} from '../../resources/js/lib/scanner-feedback.ts';

function fixture() {
    let now = 0;
    return {
        scanner: createScannerSession(() => now),
        at: (value) => {
            now = value;
        },
    };
}

void test('slow response and rotating visible QR never rearm', () => {
    const { scanner, at } = fixture();
    assert.equal(scanner.start(), true);
    assert.equal(scanner.observe(true), true);
    at(3000);
    assert.equal(scanner.observe(true), false);
    scanner.settle('confirmed');
    at(90000);
    assert.equal(scanner.observe(true), false);
    scanner.observe(false);
    at(91000);
    assert.equal(scanner.observe(false), false);
    assert.equal(scanner.observe(true), true);
});

void test('cooldown is global across different people and absence needs a full second', () => {
    const { scanner, at } = fixture();
    scanner.start();
    scanner.observe(true);
    scanner.settle('confirmed');
    scanner.observe(false);
    at(999);
    assert.equal(scanner.observe(true), false);
    scanner.observe(false);
    at(1999);
    scanner.observe(false);
    assert.equal(scanner.observe(true), false);
    at(2000);
    assert.equal(scanner.observe(true), true);
});

void test('lost response pauses without replay', () => {
    const { scanner, at } = fixture();
    scanner.start();
    scanner.observe(true);
    scanner.settle('unconfirmed');
    at(10000);
    scanner.observe(false);
    at(11000);
    assert.equal(scanner.observe(true), false);
    assert.equal(scanner.resume(), true);
    assert.equal(scanner.observe(true), false);
    scanner.observe(false);
    at(12000);
    scanner.observe(false);
    assert.equal(scanner.observe(true), true);
});

void test('unauthorized cannot resume without restart', () => {
    const { scanner } = fixture();
    scanner.start();
    scanner.observe(true);
    scanner.settle('unauthorized');
    assert.equal(scanner.resume(), false);
    assert.equal(scanner.observe(true), false);
    assert.equal(scanner.snapshot().state, 'unauthorized');
});

void test('throttle requires delay and explicit resume', () => {
    const { scanner, at } = fixture();
    scanner.start();
    scanner.observe(true);
    scanner.settle('throttled', 5000);
    at(4999);
    assert.equal(scanner.resume(), false);
    at(5000);
    assert.equal(scanner.observe(true), false);
    assert.equal(scanner.resume(), true);
    assert.equal(scanner.observe(true), false);
});

void test('stop while pending cannot create a second request', () => {
    const { scanner, at } = fixture();
    scanner.start();
    scanner.observe(true);
    scanner.stop();
    at(5000);
    assert.equal(scanner.start(), false);
    assert.equal(scanner.observe(true), false);
    scanner.settle('confirmed');
    assert.equal(scanner.snapshot().state, 'idle');
    assert.equal(scanner.start(), true);
    assert.equal(scanner.observe(true), false);
});

void test('absent upgrade uses attendance status and covers ignored and errors', () => {
    const result = {
        outcome: 'checked_in',
        detail: 'absent_upgraded',
        student_name: 'Siswa',
        subject_type: 'student',
        attendance_status: 'present',
    };
    assert.equal(scannerFeedback(result).tone, 'success');
    assert.equal(
        scannerFeedback({ ...result, attendance_status: 'late' }).tone,
        'warning',
    );
    assert.equal(
        scannerFeedback({
            ...result,
            outcome: 'ignored',
            detail: 'ignored_inactive',
        }).messageKey,
        'scanner.inactive',
    );
    assert.equal(
        scannerFeedback({ outcome: 'error', detail: 'error_expired_token' })
            .tone,
        'error',
    );
});

void test('log holds only fifty normalized results newest first', () => {
    let log = [];
    for (let i = 0; i < 51; i++)
        log = appendScannerResult(log, {
            receivedAt: i,
            response: { outcome: 'error', detail: 'error_unknown_credential' },
        });
    assert.equal(log.length, 50);
    assert.equal(log[0].receivedAt, 50);
    assert.equal(log[49].receivedAt, 1);
    assert.equal(JSON.stringify(log).includes('credential":"'), false);
});
