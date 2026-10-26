import test from 'node:test';
import assert from 'node:assert/strict';
import { createScannerCamera } from '../../resources/js/lib/scanner-camera.ts';

function decoder() {
    let resolve;
    let frame;
    let ended;
    let live = false;
    let stops = 0;
    const pending = new Promise((done) => {
        resolve = done;
    });
    return {
        handle: {
            start: async (onFrame, onEnded) => {
                frame = onFrame;
                ended = onEnded;
                await pending;
                live = true;
            },
            stop: async () => {
                stops++;
                live = false;
            },
        },
        resolve: () => resolve(),
        emit: (value) => frame?.(value),
        end: () => ended?.(),
        live: () => live,
        stops: () => stops,
    };
}

void test('permission completing after cancellation releases the late stream', async () => {
    const fake = decoder();
    const frames = [];
    const camera = createScannerCamera(
        'camera',
        (qr) => frames.push(qr),
        () => {},
        async () => fake.handle,
    );
    const pending = camera.start();
    await Promise.resolve();
    await camera.stop();
    fake.resolve();
    await pending;
    fake.emit('private-token');
    assert.equal(fake.live(), false);
    assert.ok(fake.stops() >= 1);
    assert.deepEqual(frames, []);
});

void test('repeated start and stop creates only one live decoder', async () => {
    let created = 0;
    const fake = decoder();
    fake.resolve();
    const camera = createScannerCamera(
        'camera',
        () => {},
        () => {},
        async () => {
            created++;
            return fake.handle;
        },
    );
    await Promise.all([camera.start(), camera.start()]);
    assert.equal(created, 1);
    await camera.stop();
    assert.equal(fake.live(), false);
    await camera.start();
    assert.equal(created, 2);
    await camera.stop();
});

void test('track termination pauses and callbacks after stop are ignored', async () => {
    const fake = decoder();
    fake.resolve();
    let ended = 0;
    const frames = [];
    const camera = createScannerCamera(
        'camera',
        (qr) => frames.push(qr),
        () => {
            ended++;
        },
        async () => fake.handle,
    );
    await camera.start();
    fake.emit('one');
    fake.end();
    assert.equal(ended, 1);
    await camera.stop();
    fake.emit('two');
    fake.end();
    assert.deepEqual(frames, ['one']);
    assert.equal(ended, 1);
    assert.equal(fake.live(), false);
});
