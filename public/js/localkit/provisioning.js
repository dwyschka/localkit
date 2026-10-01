/*
 * BLE provisioning for PetKit devices, straight from the browser.
 *
 * A device in pairing mode advertises as "Petkit_<model>_<mac>" and accepts
 * its WiFi credentials plus the address of the server it should phone home to
 * over GATT. Pointing that address at Localkit instead of api.eu-pet.com is
 * the whole point: a device provisioned this way never talks to PetKit at all,
 * so no DNS redirect, no app account and no cloud round-trip are needed.
 *
 * This runs in the browser rather than in PHP because the container has no
 * Bluetooth adapter - and even with one, it would have to be in range of the
 * device. The laptop or phone looking at this page already is.
 *
 * Web Bluetooth needs a SECURE CONTEXT: https:// or localhost. Localkit serves
 * https on 443 with the bundled self-signed certificate, which is what the page
 * warns about when it is reached over plain http.
 *
 * PROTOCOLS. PetKit ships two chips and they speak differently. Which one a
 * device has is decided by asking for each GATT service by name - not by a
 * model table, which would be wrong the first time a known model shipped a new
 * board:
 *
 *   Ingenic  (t7, d4h, d4sh, w7h)  service 0xAAA0, write 0xAAA2, notify 0xAAA1
 *   ESP32    (t4, d3, d4)          service 0xFFFF, write 0xFF01, notify 0xFF02
 *
 * Both carry the same PetKit JSON documents ({ key, payload }); only the
 * envelope around them differs. So the conversation below is written once and
 * handed a transport.
 */

// ---------------------------------------------------------------- constants

const SVC_INGENIC = '0000aaa0-0000-1000-8000-00805f9b34fb';
const CHR_INGENIC_NOTIFY = '0000aaa1-0000-1000-8000-00805f9b34fb';
const CHR_INGENIC_WRITE = '0000aaa2-0000-1000-8000-00805f9b34fb';

const SVC_BLUFI = '0000ffff-0000-1000-8000-00805f9b34fb';
const CHR_BLUFI_WRITE = '0000ff01-0000-1000-8000-00805f9b34fb';
const CHR_BLUFI_NOTIFY = '0000ff02-0000-1000-8000-00805f9b34fb';

// Ingenic envelope. The length field counts the JSON plus the two CRC bytes.
const PK_MAGIC = [0xfa, 0xfc, 0xfd, 0x46];
const PK_TYPE_WRITE = 0x18;
const PK_TAIL = 0xfb;

// BLUFI header bits, as in ESP-IDF's btc_blufi_prf.h.
const BLUFI_TYPE_DATA = 0x01;
const BLUFI_SUB_CUSTOM = 0x13; // the subtype PetKit's JSON travels in
const BLUFI_SUB_WIFI_STATUS = 0x0f;
const BLUFI_SUB_ERROR = 0x12;
const BLUFI_FLAG_FRAGMENT = 0x10;
// PetKit's ESP32 firmware reassembles custom data in 12-byte pieces.
const BLUFI_FRAGMENT = 12;

/*
 * Key 112 is the join report. The device counts up through these while it
 * brings the radio up, joins, and reaches the server it was just given.
 * Naming them matters: "the WiFi password is wrong" is actionable, "state 3"
 * is not.
 */
const JOIN_STATES = {
    0: 'starting up',
    1: 'looking for the network',
    2: 'connecting to the network',
    3: 'the WiFi password was rejected',
    4: 'that WiFi network was not found',
    5: 'could not connect to the WiFi',
    6: 'on WiFi, now connecting to the server',
    7: 'connected to the server',
    8: 'could not reach the server',
    9: 'connecting to MQTT',
    10: 'online',
};
// State 7 is already a success: the device has reached Localkit and will sign
// up. Waiting for 10 (MQTT) can take another two minutes and adds nothing.
const JOIN_DONE = [7, 10];
const JOIN_FATAL = [4, 5, 8];
// Not fatal: an ESP32 reports 3 and then joins anyway with the same
// credentials. Worth saying out loud, not worth aborting over.
const JOIN_WARN = [3];

/*
 * Models that advertise as "Petkit_..." but have no WiFi at all. They appear
 * in the browser's device chooser because the filter is a name prefix, and
 * picking one would otherwise end in a bare GATT error. These are paired from
 * the litter box or feeder that relays for them instead (see Bluetooth
 * Devices).
 */
const ACCESSORY_PREFIXES = ['Petkit_W5', 'Petkit_W4', 'Petkit_CTW2', 'Petkit_CTW3', 'Petkit_K2', 'Petkit_K3'];

/*
 * Per-transport pacing. The Ingenic figures are the intervals PetKit's own app
 * leaves between steps; firmware that is walked through faster has been seen to
 * drop the session, so they are not rounded. ESP32 firmware does not care and
 * polls at a flat second.
 */
const PACING = {
    ingenic: {
        identifyTimeout: 5000,
        afterIdentify: 1045,
        credentialsTimeout: 6000,
        firstPoll: 3340,
        pollEvery: 3000,
        // Ask a second time before believing "joined", the way the app does.
        confirmAfter: 5780,
        beforeComplete: 1030,
        joinTimeout: 90000,
        // How long one early state may last before it is called out. Well
        // short of joinTimeout, so the operator hears about it while there is
        // still time to act on it.
        stallAfter: 25000,
    },
    blufi: {
        identifyTimeout: 8000,
        afterIdentify: 0,
        credentialsTimeout: 10000,
        firstPoll: 1000,
        pollEvery: 1000,
        confirmAfter: 0,
        beforeComplete: 0,
        joinTimeout: 120000,
        stallAfter: 25000,
    },
};

// ------------------------------------------------------------------- helpers

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/*
 * A DataView's own bytes, and only those. `view.buffer` is the whole
 * underlying ArrayBuffer, which is allowed to be longer than the view and to
 * start before it. Chrome happens to hand each notification its own buffer
 * today, so reading it whole works - right up until it does not, on some other
 * browser, with nothing in the log to explain it.
 */
function bytesOf(view) {
    if (view instanceof Uint8Array) {
        return view;
    }

    return view.byteLength === undefined
        ? new Uint8Array(view)
        : new Uint8Array(view.buffer, view.byteOffset, view.byteLength);
}

/** CRC-16/CCITT-FALSE: poly 0x1021, init 0xFFFF, no reflection, no final xor. */
function crc16(bytes) {
    let crc = 0xffff;

    for (const byte of bytes) {
        crc ^= byte << 8;

        for (let bit = 0; bit < 8; bit++) {
            crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
        }
    }

    return crc;
}

/**
 * One inbound PetKit document, or null. Accepts the Ingenic envelope as well
 * as bare JSON, because the ESP32 path carries the identical document without
 * one.
 */
function parseDocument(input) {
    const bytes = bytesOf(input);

    if (bytes.length >= 11 && PK_MAGIC.every((magic, i) => bytes[i] === magic)) {
        const length = bytes[6] | (bytes[7] << 8);

        // `length` includes the trailing CRC. Try that first; fall back to
        // stripping the 8-byte header and the CRC + tail, for firmware that
        // fills the field in differently.
        for (const end of [8 + length - 2, bytes.length - 3]) {
            try {
                return JSON.parse(new TextDecoder().decode(bytes.slice(8, end)));
            } catch (error) {
                /* try the next interpretation */
            }
        }

        return null;
    }

    try {
        return JSON.parse(new TextDecoder().decode(bytes));
    } catch (error) {
        return null;
    }
}

function describeJoin(payload) {
    if (! payload || payload.state === undefined) {
        return 'no status reported yet';
    }

    const text = JOIN_STATES[payload.state] ?? `state ${payload.state}`;

    return payload.code === undefined ? text : `${text} (code ${payload.code})`;
}

const hasJoined = (payload) => !! payload && JOIN_DONE.includes(payload.state);
const hasFailed = (payload) => !! payload && JOIN_FATAL.includes(payload.state);
const hasWarned = (payload) => !! payload && JOIN_WARN.includes(payload.state);

// ---------------------------------------------------------------- transports

/**
 * Pick the characteristic for a job. Asking by UUID is the normal case; the
 * property fallback is for firmware that exposes the service under the
 * expected UUID but not the characteristics.
 */
/*
 * The device answers every poll with the same document until something about
 * its state changes, so printing each one buries the line that matters under
 * dozens of identical ones. Only a reply that differs from the last one for
 * that key is logged.
 */
function replyLogger(log) {
    const seen = {};

    return (key, payload) => {
        const rendered = JSON.stringify(payload ?? {});

        if (seen[key] === rendered) {
            return;
        }

        seen[key] = rendered;
        log(`device: key ${key} ${rendered}`);
    };
}

function pickCharacteristic(characteristics, uuid, ...properties) {
    return (
        characteristics.find((candidate) => candidate.uuid === uuid) ??
        characteristics.find((candidate) => properties.some((property) => candidate.properties?.[property]))
    );
}

/**
 * Ingenic transport: each document goes out in one framed write, and replies
 * arrive framed the same way on the notify characteristic.
 */
async function ingenicTransport(service, log) {
    const characteristics = await service.getCharacteristics();
    const notify = pickCharacteristic(characteristics, CHR_INGENIC_NOTIFY, 'notify', 'indicate');
    const write = pickCharacteristic(characteristics, CHR_INGENIC_WRITE, 'write', 'writeWithoutResponse');

    if (! notify || ! write) {
        const seen = characteristics
            .map((characteristic) => {
                const properties = Object.entries(characteristic.properties ?? {})
                    .filter(([, enabled]) => enabled)
                    .map(([name]) => name);

                return `${characteristic.uuid} [${properties.join(',')}]`;
            })
            .join(', ');

        throw new Error(`no usable write/notify characteristic on 0xAAA0. Seen: ${seen}`);
    }

    log(`write ${write.uuid}, notify ${notify.uuid}`);

    const replies = {};
    const logReply = replyLogger(log);

    notify.addEventListener('characteristicvaluechanged', (event) => {
        const document = parseDocument(event.target.value);

        if (! document || document.key === undefined) {
            return;
        }

        replies[document.key] = document.payload ?? {};
        logReply(document.key, document.payload);
    });

    await notify.startNotifications();

    let sequence = 0;

    const send = async (document) => {
        const json = new TextEncoder().encode(JSON.stringify(document));
        const checksum = crc16(json);
        const length = json.length + 2;

        const frame = new Uint8Array([
            ...PK_MAGIC,
            PK_TYPE_WRITE,
            sequence++ & 0xff,
            length & 0xff,
            (length >> 8) & 0xff,
            ...json,
            checksum & 0xff,
            (checksum >> 8) & 0xff,
            PK_TAIL,
        ]);

        if (write.writeValueWithResponse) {
            await write.writeValueWithResponse(frame);
        } else {
            await write.writeValue(frame);
        }
    };

    return { name: 'ingenic', send, replies };
}

/**
 * ESP32 transport. BLUFI wraps everything in a four-byte header; PetKit's
 * documents ride in the "custom data" subtype, fragmented at 12 bytes because
 * that is what the firmware reassembles.
 */
async function blufiTransport(service, log) {
    const toDevice = await service.getCharacteristic(CHR_BLUFI_WRITE);
    const fromDevice = await service.getCharacteristic(CHR_BLUFI_NOTIFY);

    const replies = {};
    const fragments = [];
    const logReply = replyLogger(log);

    fromDevice.addEventListener('characteristicvaluechanged', (event) => {
        const frame = bytesOf(event.target.value);

        if (frame.length < 4) {
            log(`device: short frame (${frame.length}B), ignored`);

            return;
        }

        const type = frame[0] & 0x03;
        const subtype = frame[0] >> 2;
        const fragmented = (frame[1] & BLUFI_FLAG_FRAGMENT) !== 0;
        const data = frame.slice(4, 4 + frame[3]);

        if (type !== BLUFI_TYPE_DATA) {
            log(`device: ignored BLUFI frame type ${type}`);

            return;
        }

        // The two status subtypes are never sent, only read: a device that
        // fails below PetKit's own layer says so here, and without them that
        // arrives as an unexplained "ignored subtype".
        if (subtype === BLUFI_SUB_WIFI_STATUS) {
            log(`device: WiFi ${data[1] === 0 ? 'connected' : `not connected (${data[1]})`}`);

            return;
        }

        if (subtype === BLUFI_SUB_ERROR) {
            log(`device: BLUFI error, code ${data[0]}`);

            return;
        }

        if (subtype !== BLUFI_SUB_CUSTOM) {
            log(`device: ignored BLUFI subtype 0x${subtype.toString(16)}`);

            return;
        }

        if (fragmented) {
            // First two bytes are the remaining length, not payload.
            fragments.push(...data.slice(2));
            log(`device: partial custom data (${data.length - 2}B)`);

            return;
        }

        fragments.push(...data);

        const whole = new Uint8Array(fragments);
        fragments.length = 0;

        const document = parseDocument(whole);

        if (! document || document.key === undefined) {
            log(`device: custom data, not a PetKit document: ${new TextDecoder().decode(whole)}`);

            return;
        }

        replies[document.key] = document.payload ?? {};
        logReply(document.key, document.payload);
    });

    await fromDevice.startNotifications();

    let sequence = 0;

    const writeFrame = async (chunk, remaining) => {
        const fragmented = remaining !== null;

        const frame = new Uint8Array([
            BLUFI_TYPE_DATA | (BLUFI_SUB_CUSTOM << 2),
            fragmented ? BLUFI_FLAG_FRAGMENT : 0x00,
            sequence++ & 0xff,
            fragmented ? chunk.length + 2 : chunk.length,
            ...(fragmented ? [remaining & 0xff, (remaining >> 8) & 0xff] : []),
            ...chunk,
        ]);

        if (toDevice.writeValueWithResponse) {
            await toDevice.writeValueWithResponse(frame);
        } else {
            await toDevice.writeValue(frame);
        }
    };

    const send = async (document) => {
        const bytes = new TextEncoder().encode(JSON.stringify(document));

        if (bytes.length <= BLUFI_FRAGMENT) {
            await writeFrame(bytes, null);

            return;
        }

        for (let offset = 0; offset < bytes.length; offset += BLUFI_FRAGMENT) {
            const chunk = bytes.slice(offset, offset + BLUFI_FRAGMENT);
            const isLast = offset + BLUFI_FRAGMENT >= bytes.length;

            // What the device reassembles against is what REMAINS including
            // this chunk - not the length of the whole document.
            await writeFrame(chunk, isLast ? null : bytes.length - offset);
        }
    };

    return { name: 'blufi', send, replies };
}

// ------------------------------------------------------------ the conversation

/**
 * Poll `condition` until it holds, the deadline passes, or the device drops
 * the connection. Returns whether it held.
 */
async function waitUntil(condition, timeout, isConnected) {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        if (condition()) {
            return true;
        }

        if (! isConnected()) {
            return false;
        }

        await sleep(250);
    }

    return condition();
}

/**
 * Walk one device through provisioning. Returns true when the device confirmed
 * it reached the server, false when it said something went wrong or went
 * silent - never "the write returned without throwing", which is true even for
 * a payload the firmware never understood.
 */
async function converse(transport, payload, language, pacing, log, isConnected, onAddress) {
    const { replies } = transport;

    log('asking the device who it is (key 110)…');
    await transport.send({ key: 110 });

    if (! (await waitUntil(() => replies[110], pacing.identifyTimeout, isConnected))) {
        log('no answer - retrying key 110 once…');
        await transport.send({ key: 110 });

        if (! (await waitUntil(() => replies[110], pacing.identifyTimeout, isConnected))) {
            log(
                isConnected()
                    ? 'the device never identified itself. Make sure it is still in pairing mode.'
                    : 'the device disconnected before identifying itself.'
            );

            return false;
        }
    }

    await sleep(pacing.afterIdentify);

    log('sending WiFi credentials and server address (key 151)…');
    await transport.send({ key: 151, payload });

    const accepted = await waitUntil(
        () => replies[151]?.state === 1 || hasFailed(replies[151]),
        pacing.credentialsTimeout,
        isConnected
    );

    if (hasFailed(replies[151])) {
        log(`the device refused the credentials: ${describeJoin(replies[151])}`);

        return false;
    }

    if (! accepted || replies[151]?.state !== 1) {
        log(
            isConnected()
                ? 'the device did not acknowledge the credentials.'
                : 'the device disconnected before acknowledging the credentials.'
        );

        return false;
    }

    log('credentials accepted - waiting for the device to join…');

    const deadline = Date.now() + pacing.joinTimeout;
    let reported = null;
    let reportedSince = Date.now();
    let stalled = false;
    let askedForDetails = false;
    let addressReported = null;
    let first = true;

    /*
     * A device that sits on one of the early states is not making progress, and
     * the join timeout is long enough that saying nothing for a minute and a
     * half reads as the wizard having hung. Each of these has a small set of
     * causes worth checking, so name them once rather than repeating the state.
     */
    const STALL_HINTS = {
        1: `still looking for "${payload.ssid}". The name is case-sensitive, the device joins `
            + '2.4 GHz networks only, and it has to be in range of the access point. '
            + 'A device that has been re-provisioned several times sometimes needs a factory '
            + 'reset before it will scan properly again.',
        2: `found "${payload.ssid}" but cannot finish connecting. Usually the password, or an `
            + 'access point refusing the device after repeated attempts.',
    };

    /*
     * Key 111 is the device's own account of the network it joined - its IP,
     * gateway, BSSID and signal. The IP is the only trustworthy source for it:
     * the address a request appears to come from is the Docker gateway's
     * whenever Localkit is reached through a published port, and the device
     * reports its IP to Localkit over MQTT, which is deliberately broken while
     * it is being provisioned.
     */
    const reportAddress = async () => {
        const ip = replies[111]?.ip;

        if (! ip || ip === addressReported) {
            return;
        }

        addressReported = ip;
        log(`device address: ${ip} (gateway ${replies[111].gateway ?? '?'})`);

        try {
            await onAddress?.(ip);
        } catch (error) {
            // Only costs the telnet check, not the provisioning.
            log(`could not record the device address: ${error.message}`);
        }
    };

    const askForDetails = async () => {
        if (askedForDetails) {
            return;
        }

        askedForDetails = true;
        await transport.send({ key: 111 });
        await sleep(250);
        await reportAddress();
    };

    while (Date.now() < deadline && isConnected()) {
        await sleep(first ? pacing.firstPoll : pacing.pollEvery);
        first = false;

        await transport.send({ key: 112 });
        await sleep(250);

        const status = replies[112];

        await reportAddress();

        // Only on change - polling every second would otherwise print the same
        // line thirty times and bury the one that matters.
        if (status?.state !== reported) {
            reported = status?.state;
            reportedSince = Date.now();
            stalled = false;
            log(`device: ${describeJoin(status)}`);

            if (hasWarned(status)) {
                log('note: a rejected password is often recovered from with the same credentials - still waiting.');
            }
        }

        if (! stalled
            && STALL_HINTS[reported]
            && Date.now() - reportedSince > pacing.stallAfter) {
            stalled = true;
            log(`the device has not moved on for ${Math.round(pacing.stallAfter / 1000)}s - ${STALL_HINTS[reported]}`);
        }

        if (hasFailed(status)) {
            log(`the device gave up: ${describeJoin(status)}`);

            return false;
        }

        if (hasJoined(status)) {
            if (pacing.confirmAfter) {
                // Ask once more before believing it. A device that reports 7
                // and then falls back has been seen; the app confirms too.
                await sleep(pacing.confirmAfter);
                await transport.send({ key: 112 });
                await sleep(pacing.beforeComplete);

                if (! hasJoined(replies[112])) {
                    log(`the device fell back: ${describeJoin(replies[112])}`);

                    continue;
                }
            }

            await askForDetails();

            await transport.send({ key: 114, payload: { language } });
            log('device joined - sending completion (key 101)…');
            await transport.send({ key: 101 });

            return true;
        }

        // State 6 means it is on WiFi and reaching for the server. Key 111
        // makes it report what it actually got, which is the only thing that
        // distinguishes "wrong server address" from "server unreachable".
        if (status?.state >= 6) {
            await askForDetails();
        }
    }

    log(
        isConnected()
            ? `the device did not report joining in time (last status: ${describeJoin(replies[112])}).`
            : `the device disconnected before joining (last status: ${describeJoin(replies[112])}).`
    );

    return false;
}

// -------------------------------------------------------------- public entry

/**
 * Whether this page can provision at all, and why not when it cannot.
 *
 * THE SECURE-CONTEXT CHECK COMES FIRST, deliberately. Web Bluetooth is a
 * secure-context-only API, so on plain http the browser does not expose
 * `navigator.bluetooth` AT ALL - Chrome looks exactly like Firefox does.
 * Testing for the API first therefore tells people on a perfectly capable
 * browser that their browser is the problem.
 */
function readiness() {
    if (! window.isSecureContext) {
        return {
            ready: false,
            reason:
                'Web Bluetooth only works on a secure page, and this one is plain HTTP. ' +
                'Open Localkit over HTTPS (the container serves it on port 443) or via localhost. ' +
                'You will need Chrome or Edge as well - an HTTP page cannot tell whether you have one, ' +
                'because the browser hides Web Bluetooth entirely until the page is secure.',
        };
    }

    if (! navigator.bluetooth?.requestDevice) {
        return {
            ready: false,
            reason:
                'This browser has no Web Bluetooth. Use Chrome or Edge, on desktop or Android - ' +
                'Firefox, Safari and everything on iOS cannot provision.',
        };
    }

    return { ready: true, reason: '' };
}

/**
 * Advisory notes about the server URL the device is about to be given. These
 * never block: the URL may well be right and the check merely cautious.
 */
function reviewServerUrl(value) {
    const notes = [];

    if (/\.local(?=[:/]|$)/i.test(value)) {
        notes.push(
            'This URL uses a .local mDNS hostname. Most PetKit devices cannot resolve mDNS - ' +
                'use this host’s IP address instead.'
        );
    }

    try {
        const url = new URL(value);
        const port = url.port || (url.protocol === 'https:' ? '443' : '80');

        if (port !== '80') {
            notes.push(
                `The device is being told to use port ${port}. ESP32 models (t4, d3, d4) only ever ` +
                    'talk to port 80 - publish Localkit on 80 for those.'
            );
        }

        if (url.protocol === 'https:') {
            notes.push('Devices speak plain HTTP to the API. Use an http:// URL here.');
        }

        if (! url.pathname.endsWith('/')) {
            notes.push(
                'The device appends paths to this URL verbatim, so it should end in a slash ' +
                    '(PetKit’s own value is http://api.eu-pet.com/6/).'
            );
        }
    } catch (error) {
        // Still being typed.
    }

    return notes;
}

/**
 * The MAC out of an advertised name like "Petkit_D4SH_a4c138a66d88", or null.
 *
 * The trailing run of hex is taken rather than a fixed field, because the
 * segment count varies by model and only the address is wanted. Six to twelve
 * hex digits keeps it from mistaking a model token (D4SH, W7H) for a MAC.
 */
function macOf(name) {
    const match = /([0-9a-fA-F]{6,12})$/.exec(name || '');

    return match ? match[1].toLowerCase() : null;
}

/**
 * The same URL over http. Host, port and path are kept; only the scheme is
 * rewritten, and a value with no scheme at all is given one. Returns the input
 * unchanged only when it cannot be parsed even after prefixing - at which point
 * the device will reject it anyway and the log shows what was sent.
 */
function forceHttp(url) {
    const value = (url || '').trim();

    try {
        const parsed = new URL(value);
        parsed.protocol = 'http:';

        return parsed.toString();
    } catch (error) {
        // No scheme - the URL constructor needs one to parse a host at all.
        if (! /^[a-z]+:\/\//i.test(value)) {
            try {
                return new URL('http://' + value).toString();
            } catch (inner) {
                return value;
            }
        }

        return value;
    }
}

/**
 * Provision one device: pick it, decide which protocol it speaks, and hand it
 * its configuration.
 *
 * @param {object}   config             ssid, password, server, timezone, zone,
 *                                      an optional onDeviceSelected(mac),
 *                                      called with '' when the name had none,
 *                                      and an optional onDeviceAddress(ip),
 *                                      called once the device reports the
 *                                      network it joined
 *                                      called once a provisionable device is
 *                                      chosen
 * @param {function} log                receives one line of progress at a time
 * @returns {Promise<boolean>}          whether the device confirmed
 */
async function provision(config, log) {
    let server = null;

    const device = await navigator.bluetooth.requestDevice({
        filters: [{ namePrefix: 'Petkit' }],
        optionalServices: [SVC_INGENIC, SVC_BLUFI],
    });

    const name = device.name || device.id || '(unnamed)';
    log(`selected: ${name}`);

    if (ACCESSORY_PREFIXES.some((prefix) => name.startsWith(prefix))) {
        log(
            `${name} is a Bluetooth-only accessory - it has no WiFi to configure. ` +
                'Pair it from the litter box or feeder that relays for it instead.'
        );

        return false;
    }

    /*
     * Tell the caller a device has been picked, before anything is written to
     * it, so the run is on record well before the device reboots and signs up -
     * that record is what lets its sign-up be recognised as this provisioning.
     *
     * Always, even when the name carried no MAC: the advertised name is
     * truncated to fit the advertising packet and does not always reach the
     * address, and the caller has a way to recognise the device that does not
     * need one. Reporting nothing here would leave it with neither.
     */
    const mac = macOf(name);

    if (mac) {
        log(`device MAC: ${mac}`);
    } else {
        log('the advertised name carries no MAC - identifying by timing instead');
    }

    try {
        await config.onDeviceSelected?.(mac || '');
    } catch (error) {
        // A failed record only costs the telnet auto-start, not the
        // provisioning itself - so note it and carry on.
        log(`could not record the device for telnet: ${error.message}`);
    }

    try {
        log('connecting…');
        server = await device.gatt.connect();

        let live = true;
        device.addEventListener('gattserverdisconnected', () => {
            live = false;
        });

        /*
         * Ask for each service BY NAME rather than enumerating. The
         * enumeration returns what the browser has already discovered and
         * cached for this device, which is not always the full set - a device
         * that provisions fine through a direct getPrimaryService() can be
         * missing from the list. So the list is only used to write the error
         * message, where an incomplete answer costs nothing.
         */
        const open = async (uuid) => {
            try {
                return await server.getPrimaryService(uuid);
            } catch (error) {
                return null;
            }
        };

        const ingenic = await open(SVC_INGENIC);
        const blufi = ingenic ? null : await open(SVC_BLUFI);

        if (! ingenic && ! blufi) {
            let seen = [];

            try {
                seen = (await server.getPrimaryServices()).map((service) => service.uuid);
            } catch (error) {
                seen = [`(could not be listed: ${error.name})`];
            }

            log(
                'This device offers neither PetKit provisioning service (0xAAA0 or 0xFFFF), so there is ' +
                    'nothing here that can configure it. Older models without Bluetooth setup are pointed ' +
                    `at Localkit with a DNS redirect instead. Services seen: ${seen.join(', ') || 'none'}`
            );

            return false;
        }

        log(`protocol: ${ingenic ? 'PetKit / Ingenic (0xAAA0)' : 'BLUFI / ESP32 (0xFFFF)'}`);

        const transport = ingenic
            ? await ingenicTransport(ingenic, log)
            : await blufiTransport(blufi, log);

        /*
         * The key-151 payload, field for field as PetKit's app sends it.
         *
         * `hide` is a constant, not anything about the network. `locale` is a
         * TIME ZONE NAME ("Europe/Berlin"), not a language - the device stores
         * it and echoes it straight back in dev_signup as
         * `timezone=2.0&locale=Europe/Berlin`. `timezone` beside it is hours
         * east of UTC as a string, and it is the only thing that sets the
         * device's clock offset: with nothing here it runs on UTC and burns
         * UTC into its video watermarks.
         *
         * apiServers/ipServers are the two addresses the device phones home
         * to. Both point at Localkit; PetKit uses a hostname for one and a raw
         * IP for the other, but nothing requires that.
         *
         * The scheme is forced to http. The device speaks plain HTTP to the API
         * and cannot present or verify a certificate for a LAN address, so an
         * https URL here would leave it unable to phone home at all - a mistake
         * that only shows up minutes later as a device that never signs up. It
         * is corrected silently rather than refused, because there is one right
         * answer and no reason to make the operator retype it.
         */
        const offset = Number(config.timezone);
        const apiServer = forceHttp(config.server);

        if (apiServer !== (config.server || '').trim()) {
            log(`server address sent as ${apiServer} (devices only speak plain HTTP)`);
        }

        const payload = {
            ssid: config.ssid,
            pwd: config.password,
            hide: 1,
            locale: config.zone || Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            // One decimal is what PetKit's app sends and what the device
            // reports back ("&timezone=%.1f"), so whole and half hours go out
            // in exactly that shape. The quarter-hour zones - Nepal at +5:45,
            // India at +5:30, Chatham at +12:45 - do not survive it, and the
            // device parses the field with a plain %f, so those keep their
            // second decimal rather than being rounded to the wrong minute.
            timezone: Number.isInteger(offset * 10) ? offset.toFixed(1) : String(offset),
            apiServers: [apiServer],
            ipServers: [apiServer],
        };

        const language = (navigator.language || 'en_US').replace('-', '_');
        const pacing = transport.name === 'ingenic' ? PACING.ingenic : PACING.blufi;

        return await converse(
            transport,
            payload,
            language,
            pacing,
            log,
            () => live,
            config.onDeviceAddress
        );
    } finally {
        try {
            server?.disconnect();
        } catch (error) {
            /* it may already be gone */
        }
    }
}

window.LocalkitProvisioning = { provision, readiness, reviewServerUrl, forceHttp, macOf };
