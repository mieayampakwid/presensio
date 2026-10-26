export type ScannerCamera = { start(): Promise<void>; stop(): Promise<void> };
export type Decoder = {
    start(
        onFrame: (qr: string | null) => void,
        onEnded: () => void,
    ): Promise<void>;
    stop(): Promise<void>;
};
export type DecoderFactory = (elementId: string) => Promise<Decoder>;

export function releaseCameraVideos(videos: Iterable<HTMLVideoElement>): void {
    for (const video of videos) {
        const stream = video.srcObject as MediaStream | null;
        for (const track of stream?.getTracks() ?? []) track.stop();
        video.pause();
        video.srcObject = null;
        video.remove();
    }
}

async function browserDecoder(elementId: string): Promise<Decoder> {
    if (!globalThis.isSecureContext) throw new Error('insecure');
    if (!navigator.mediaDevices?.getUserMedia) throw new Error('unsupported');
    const { Html5Qrcode, Html5QrcodeSupportedFormats } =
        await import('html5-qrcode');
    const scanner = new Html5Qrcode(elementId, {
        formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
        verbose: false,
    });
    let tracks: MediaStreamTrack[] = [];
    let ended: (() => void) | null = null;
    return {
        async start(onFrame, onEnded) {
            await scanner.start(
                { facingMode: 'user' },
                { fps: 10 },
                (text) => onFrame(text),
                () => onFrame(null),
            );
            const video = document
                .getElementById(elementId)
                ?.querySelector('video');
            tracks =
                (video?.srcObject as MediaStream | null)?.getTracks() ?? [];
            ended = onEnded;
            for (const track of tracks)
                track.addEventListener('ended', onEnded);
        },
        async stop() {
            const videos = Array.from(
                document.getElementById(elementId)?.querySelectorAll('video') ??
                    [],
            );
            for (const track of tracks) {
                if (ended) track.removeEventListener('ended', ended);
            }
            try {
                if (scanner.isScanning) await scanner.stop();
            } finally {
                for (const track of tracks) track.stop();
                tracks = [];
                releaseCameraVideos(videos);
                if (!scanner.isScanning) scanner.clear();
            }
        },
    };
}

export function createScannerCamera(
    elementId: string,
    onFrame: (qr: string | null) => void,
    onEnded: () => void,
    decoderFactory: DecoderFactory = browserDecoder,
): ScannerCamera {
    let generation = 0;
    let decoder: Decoder | null = null;
    let starting: Promise<void> | null = null;
    return {
        start() {
            if (starting) return starting;
            if (decoder) return Promise.resolve();
            const current = ++generation;
            const pending = (async () => {
                const instance = await decoderFactory(elementId);
                if (current !== generation) {
                    await instance.stop();
                    return;
                }
                decoder = instance;
                try {
                    await instance.start(
                        (qr) => {
                            if (current === generation) onFrame(qr);
                        },
                        () => {
                            if (current === generation) onEnded();
                        },
                    );
                    if (current !== generation) await instance.stop();
                } catch (error) {
                    if (decoder === instance) decoder = null;
                    await instance.stop().catch(() => {});
                    throw error;
                }
            })();
            starting = pending.finally(() => {
                starting = null;
            });
            return starting;
        },
        async stop() {
            generation++;
            const instance = decoder;
            decoder = null;
            if (instance && !starting) await instance.stop();
        },
    };
}
