{{--
    The BLE provisioning wizard.

    Rendered both as the Provisioning page and inside the "Provision Device"
    action on the device list, so it deliberately brings no section or card of
    its own - whatever includes it supplies the frame.

    The component this is included in must use InteractsWithProvisioning. Once
    the Bluetooth handshake is done the wizard polls $wire.provisioningStatus()
    through four waits - the device signing up, its first heartbeat, then telnet
    coming up - and ends on a success screen. The Bluetooth half lives in
    public/js/localkit/provisioning.js, loaded panel-wide from
    PetkitPanelProvider.
--}}
<style>
    [x-cloak] { display: none !important; }

    /* Two fields to a row - WiFi credentials on the first, where the device
       should report to on the second - collapsing to one on narrow screens. */
    .provision__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        gap: 1rem;
    }

    .provision__field label {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .provision__hint {
        margin-top: 0.375rem;
        font-size: 0.75rem;
        color: var(--gray-500);
    }

    .provision__note {
        padding: 0.625rem 0.875rem;
        border-radius: 0.5rem;
        font-size: 0.8125rem;
        line-height: 1.5;
        color: rgb(146 64 14);
        background: rgb(254 243 199);
    }

    .provision__note--stop {
        color: rgb(153 27 27);
        background: rgb(254 226 226);
    }

    .dark .provision__note {
        color: rgb(253 230 138);
        background: rgb(120 53 15 / 0.35);
    }

    .dark .provision__note--stop {
        color: rgb(254 202 202);
        background: rgb(127 29 29 / 0.35);
    }

    .provision__log {
        margin: 0;
        padding: 1rem;
        max-height: 16rem;
        overflow: auto;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.75rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
        color: var(--gray-300);
        background: var(--gray-950);
        border-radius: 0.5rem;
    }

    .provision__steps {
        /* Filament's reset strips list markers, and an unnumbered list of
           ordered steps reads as three unrelated sentences. */
        list-style: decimal outside;
        margin: 0;
        padding-left: 1.125rem;
        font-size: 0.8125rem;
        line-height: 1.7;
        color: var(--gray-500);
    }

    /* ---- wizard progress ---- */

    .provision__stepper {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .provision__step {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.5rem 0;
    }

    .provision__step-mark {
        flex: none;
        width: 1.5rem;
        height: 1.5rem;
        display: grid;
        place-items: center;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 600;
        background: var(--gray-100);
        color: var(--gray-400);
    }

    .dark .provision__step-mark {
        background: var(--gray-800);
        color: var(--gray-500);
    }

    .provision__step--done .provision__step-mark {
        background: rgb(220 252 231);
        color: rgb(22 101 52);
    }

    .provision__step--active .provision__step-mark {
        background: transparent;
    }

    .provision__step--failed .provision__step-mark {
        background: rgb(254 226 226);
        color: rgb(153 27 27);
    }

    .dark .provision__step--done .provision__step-mark {
        background: rgb(20 83 45 / 0.4);
        color: rgb(187 247 208);
    }

    .dark .provision__step--failed .provision__step-mark {
        background: rgb(127 29 29 / 0.4);
        color: rgb(254 202 202);
    }

    .provision__step-body { padding-top: 0.125rem; }

    .provision__step-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-400);
    }

    .provision__step--active .provision__step-label,
    .provision__step--done .provision__step-label,
    .provision__step--failed .provision__step-label {
        color: var(--gray-700);
    }

    .dark .provision__step--active .provision__step-label,
    .dark .provision__step--done .provision__step-label,
    .dark .provision__step--failed .provision__step-label {
        color: var(--gray-200);
    }

    .provision__step-sub {
        margin-top: 0.125rem;
        font-size: 0.75rem;
        color: var(--gray-500);
    }

    .provision__spinner {
        width: 1.05rem;
        height: 1.05rem;
        border: 2px solid var(--primary-500, #f59e0b);
        border-top-color: transparent;
        border-radius: 9999px;
        animation: provision-spin 0.7s linear infinite;
    }

    @keyframes provision-spin { to { transform: rotate(360deg); } }

    /*
     * Layout for anything x-show toggles lives in a class, never in the inline
     * style. x-show writes to the element's inline `display`, which leaves a
     * container declared inline as `display:flex` sitting at `display:block`
     * once it has been hidden and shown again - and a block container ignores
     * `gap`, so every child ends up flush against the next.
     */
    .provision__stack {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .provision__stack--progress { gap: 1.25rem; }

    .provision__row {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .provision__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .provision__install-list {
        margin: 0.5rem 0 0;
        padding-left: 1.1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        list-style: disc;
    }

    .provision__install-source {
        margin-top: 0.625rem;
        font-size: 0.75rem;
        opacity: 0.9;
    }

    .provision__install-source a { text-decoration: underline; }

    .provision__modal {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .provision__modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgb(0 0 0 / 0.5);
    }

    .provision__modal-panel {
        position: relative;
        width: min(46rem, 100%);
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding: 1.5rem;
        border-radius: 0.75rem;
        background: rgb(255 255 255);
        box-shadow: 0 20px 45px rgb(0 0 0 / 0.35);
    }

    .dark .provision__modal-panel { background: rgb(24 24 27); }

    .provision__modal-title { font-size: 1rem; font-weight: 600; }

    .provision__modal-hint { font-size: 0.8125rem; line-height: 1.5; opacity: 0.85; }

    .provision__modal-panel textarea {
        width: 100%;
        padding: 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid rgb(209 213 219);
        background: rgb(249 250 251);
        color: inherit;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.75rem;
        line-height: 1.6;
        resize: vertical;
    }

    .dark .provision__modal-panel textarea {
        border-color: rgb(255 255 255 / 0.1);
        background: rgb(255 255 255 / 0.05);
    }

    .provision__modal-actions { display: flex; gap: 0.5rem; }

    /*
     * "Start over" sits on its own row below the console, away from the
     * decisions about this run - level with those it read as one more equal
     * choice next to the destructive ones.
     */
    .provision__headline {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.75rem;
    }

    .provision__result {
        margin: 0.25rem 0 0.75rem;
        padding: 1.25rem;
        border-radius: 0.75rem;
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .provision__result--done {
        background: rgb(220 252 231);
        color: rgb(22 101 52);
    }

    .provision__result--failed {
        background: rgb(254 226 226);
        color: rgb(153 27 27);
    }

    .dark .provision__result--done {
        background: rgb(20 83 45 / 0.35);
        color: rgb(187 247 208);
    }

    .dark .provision__result--failed {
        background: rgb(127 29 29 / 0.35);
        color: rgb(254 202 202);
    }

    .provision__result-icon { font-size: 1.5rem; line-height: 1; flex: none; }
    .provision__result-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; }
    .provision__result-text { font-size: 0.8125rem; line-height: 1.5; }
    .provision__result-meta { margin-top: 0.5rem; font-size: 0.75rem; opacity: 0.85; }
</style>

<div
    x-data="{
        /* --- form fields --- */
        ssid: @js($ssid),
        password: @js($password),
        server: @js($server),
        timezone: @js($timezone),
        zone: @js($zone),

        /* --- environment gate --- */
        ready: false,
        reason: '',
        notes: [],

        /*
         * The wizard's one piece of state. 'form' shows the inputs; the four
         * running phases each wait for one thing; 'done' and 'failed' end it.
         */
        phase: 'form',
        failedStep: null,

        /* The offer to throw away the device a failed run left behind. */
        confirmDelete: false,
        deleting: false,

        /* The script editor: one command or script, run on the device. */
        scriptModal: false,
        commandInput: '',
        commandRunning: false,

        /* The install scripts offered once telnet is up. */
        installSteps: @js($installSteps),
        installing: false,
        installFinished: false,
        installDeclined: false,
        statusText: '',
        resultText: '',
        device: null,
        ip: null,
        lines: [],

        get running() {
            return ['bluetooth', 'device', 'heartbeat', 'telnet'].includes(this.phase);
        },

        init() {
            const state = window.LocalkitProvisioning.readiness();
            this.ready = state.ready;
            this.reason = state.reason;
            this.review();
        },

        review() {
            this.notes = window.LocalkitProvisioning.reviewServerUrl(this.server);
        },

        log(line) {
            this.lines.push(line);
            this.$nextTick(() => {
                const box = this.$refs.log;
                if (box) box.scrollTop = box.scrollHeight;
            });
        },

        /* The four waits, in order, as a stepper the current phase drives. */
        stepList() {
            const order = ['bluetooth', 'device', 'heartbeat', 'telnet'];
            const labels = {
                bluetooth: ['Bluetooth', 'Handing the device its configuration'],
                device: ['Device', 'Waiting for it to sign up here'],
                heartbeat: ['Wait for Heartbeat', 'Waiting for its first heartbeat'],
                telnet: ['Telnet', 'Waiting for telnet to come up'],
            };
            // Where the run currently sits. On failure the marker is the failed
            // step, so everything before it still reads as done.
            let current;
            if (this.phase === 'done') {
                current = order.length;
            } else if (this.phase === 'failed') {
                current = order.indexOf(this.failedStep);
            } else {
                current = order.indexOf(this.phase);
            }

            return order.map((key, index) => {
                let state;
                if (this.failedStep === key) {
                    state = 'failed';
                } else if (this.phase === 'done' || index < current) {
                    state = 'done';
                } else if (index === current && this.phase !== 'failed') {
                    state = 'active';
                } else {
                    state = 'pending';
                }
                return { key, label: labels[key][0], sub: labels[key][1], state };
            });
        },

        async start() {
            if (! this.ssid || ! this.password) {
                this.notes = ['WiFi name and password are both required.'];
                return;
            }

            this.lines = [];
            this.failedStep = null;
            this.confirmDelete = false;
            this.installFinished = false;
            this.installDeclined = false;
            this.device = null;
            this.ip = null;
            this.phase = 'bluetooth';
            this.statusText = 'Talking to the device over Bluetooth…';

            /*
             * Straight into the Bluetooth work, with nothing awaited first.
             * navigator.bluetooth.requestDevice() only opens its chooser while
             * the click that led here still counts as a user gesture, and a
             * Livewire round-trip is long enough to lose that - the chooser then
             * never appears and the press looks like it did nothing.
             *
             * So the run is opened server-side from onDeviceSelected instead,
             * which fires the moment a device is picked and still well before
             * any configuration reaches it.
             */
            try {
                const confirmed = await window.LocalkitProvisioning.provision({
                    ssid: this.ssid,
                    password: this.password,
                    server: this.server,
                    timezone: this.timezone,
                    zone: this.zone,
                    // Opens the run and records the device's MAC in one call:
                    // stamps the clock the heartbeat is judged against, and the
                    // address dev_serverinfo hands back - normalised exactly as
                    // the device is about to receive it.
                    onDeviceSelected: (mac) => this.$wire.beginProvisioning(
                        window.LocalkitProvisioning.forceHttp(this.server),
                        mac
                    ),
                    // The address the device reports for itself once it has
                    // joined. Nothing else knows it while provisioning is under
                    // way, and the telnet check needs somewhere to aim.
                    onDeviceAddress: (ip) => this.$wire.recordProvisionedAddress(ip),
                }, (line) => this.log(line));

                if (! confirmed) {
                    this.fail('bluetooth',
                        'The device did not confirm its configuration. The log below is what to report.');
                    return;
                }

                this.phase = 'device';
                await this.poll();
            } catch (error) {
                const known = {
                    NotFoundError: 'No device was picked. Put the device into pairing mode and try again.',
                    SecurityError: 'Bluetooth was blocked - the page has to be served over HTTPS.',
                    NotAllowedError: 'Permission denied. Allow Bluetooth for this browser in your OS settings.',
                    NetworkError: 'The connection to the device dropped. Move closer and try again.',
                };
                this.log('ERROR ' + error.name + ': ' + error.message);
                this.fail('bluetooth', known[error.name] ?? (error.name + ': ' + error.message));
            }
        },

        /*
         * Poll the server through the three remaining waits. Each phase has its
         * own patience; running past it stops with the furthest step reached
         * marked failed, rather than spinning forever.
         */
        async poll() {
            const budgets = { device: 40, heartbeat: 40, telnet: 30 }; // polls, ~3s each
            const messages = {
                device: 'Waiting for the device to sign up…',
                heartbeat: 'Wait for Heartbeat — waiting for the device to check in…',
                telnet: 'Heartbeat received. Checking telnet…',
            };

            let phase = 'device';
            let spent = 0;
            let announced = null;

            while (true) {
                if (announced !== phase) {
                    announced = phase;
                    this.log(messages[phase]);
                }
                this.statusText = messages[phase];

                await new Promise((resolve) => setTimeout(resolve, 3000));

                let status;
                try {
                    status = await this.$wire.provisioningStatus();
                } catch (error) {
                    // A transient Livewire hiccup should not end the wizard.
                    this.log('status check failed, retrying: ' + error.message);
                    continue;
                }

                // A call that resolves with nothing is the same kind of hiccup,
                // and reading through it would end the wizard on a TypeError
                // rather than on anything the operator can act on.
                if (! status || typeof status !== 'object') {
                    this.log('status check came back empty, retrying');
                    continue;
                }

                // The wizard never registered its start, so nothing here will
                // ever change. Almost always a browser still running an older
                // provisioning.js than the markup it is driving.
                if (status.phase === 'idle') {
                    this.fail('device',
                        'This page did not register the start of the run. Reload it with a hard '
                        + 'refresh (Cmd/Ctrl+Shift+R) and try again - the browser is most likely '
                        + 'still running a cached copy of the provisioning script.');
                    return;
                }

                this.device = status.device;
                this.ip = status.ip;

                if (status.phase === 'done') {
                    this.phase = 'done';
                    this.resultText = 'Telnet is up on the device. It is provisioned and reachable.';
                    this.log('telnet is reachable at ' + (status.ip || 'the device') + ' — done.');
                    return;
                }

                if (status.phase !== phase) {
                    // Advanced to the next wait; reset the budget for it.
                    phase = status.phase;
                    spent = 0;
                    this.phase = phase;
                    continue;
                }

                this.phase = phase;

                if (++spent >= (budgets[phase] ?? 40)) {
                    const why = {
                        device: 'The device has not signed up. Check that it joined the WiFi and that the '
                            + 'server address you gave it is reachable from the device.',
                        heartbeat: 'The device signed up but sent no heartbeat. It may still be booting — '
                            + 'you can keep waiting.',
                        telnet: 'Heartbeat received, but telnet never came up. The device may not support '
                            + 'the remote command, or its address could not be determined'
                            + (this.ip ? ' (tried ' + this.ip + ').' : '.'),
                    };
                    this.fail(phase, why[phase]);
                    return;
                }
            }
        },

        fail(step, message) {
            this.failedStep = step;
            this.resultText = message;
            this.phase = 'failed';
        },

        openScript() {
            this.scriptModal = true;
        },

        closeScript() {
            if (! this.commandRunning) {
                this.scriptModal = false;
            }
        },

        /*
         * Run what is in the editor, then get out of the way: the output lands
         * in the console, which is behind the overlay.
         */
        async runScript() {
            await this.runCommand();

            this.scriptModal = false;
        },

        /*
         * Run whatever was typed on the device. Echoed into the log first, so
         * the console reads as a session rather than as answers to questions
         * nobody can see.
         */
        async runCommand() {
            const command = this.commandInput.trim();

            if (! command || this.commandRunning) {
                return;
            }

            this.commandRunning = true;
            command.split('\n').forEach((line, index) => this.log((index === 0 ? '$ ' : '> ') + line));

            try {
                const result = await this.$wire.runProvisioningCommand(command);

                if (result && result.output) {
                    this.log(result.output);
                }

                if (! result || ! result.ok) {
                    this.log(`failed: ${(result && result.error) || 'no answer from the device'}`);
                } else {
                    this.commandInput = '';
                }
            } catch (error) {
                this.log(`could not reach the server: ${error.message}`);
            }

            this.commandRunning = false;
        },

        /* Whether there is still an install to offer. */
        get offerInstall() {
            return this.phase === 'done'
                && this.installSteps.length > 0
                && ! this.installing
                && ! this.installFinished
                && ! this.installDeclined;
        },

        /*
         * Run the configured install scripts on the device, one at a time, and
         * print each one's output as it comes back rather than going quiet for
         * however long the whole list takes.
         */
        async runInstall() {
            this.installing = true;
            this.log('--- running the Localkit install scripts ---');

            for (let index = 0; index < this.installSteps.length; index++) {
                const step = this.installSteps[index];

                this.statusText = `Running ${step.name} on the device…`;
                this.log(`[${index + 1}/${this.installSteps.length}] ${step.name}`
                    + (step.description ? ` - ${step.description}` : ''));
                this.log(`$ ${step.command}`);

                let result;

                try {
                    result = await this.$wire.runProvisioningInstallStep(index);
                } catch (error) {
                    this.log(`could not reach the server: ${error.message}`);
                    break;
                }

                if (result && result.output) {
                    this.log(result.output);
                }

                if (! result || ! result.ok) {
                    this.log(`${step.name} failed: ${(result && result.error) || 'no answer from the device'}`);
                    this.log('--- stopped, the remaining steps were not run ---');
                    break;
                }

                this.log(`${step.name} finished.`);
            }

            this.installing = false;
            this.installFinished = true;
            this.statusText = '';
        },

        /*
         * Throw away the device the failed run left behind. Asked for rather
         * than offered silently, and only reachable from a failure - a run that
         * finished has nothing to undo.
         */
        async deleteDevice() {
            this.deleting = true;

            try {
                const result = await this.$wire.deleteProvisionedDevice();

                if (result && result.deleted) {
                    this.log(`deleted ${result.name} from Localkit.`);
                    this.device = null;
                    this.ip = null;
                    this.resultText = 'The device was removed from Localkit. '
                        + 'Put it back into pairing mode to try again.';
                } else {
                    this.log('nothing to delete - the device is no longer there.');
                }
            } catch (error) {
                this.log(`could not delete the device: ${error.message}`);
            }

            this.deleting = false;
            this.confirmDelete = false;
        },

        keepWaiting() {
            this.failedStep = null;
            // poll() re-derives the real phase from the first status call, so
            // where we set it here only decides the first line shown.
            this.phase = this.device ? 'telnet' : 'device';
            this.poll();
        },

        reset() {
            this.phase = 'form';
            this.failedStep = null;
            this.confirmDelete = false;
            this.installFinished = false;
            this.installDeclined = false;
            this.device = null;
            this.ip = null;
            this.lines = [];
            this.resultText = '';
            this.review();
        },
    }"
    style="display:flex;flex-direction:column;gap:1rem;"
>
    {{-- ======================= form ======================= --}}
    <div x-show="phase === 'form'" class="provision__stack">
        <div x-show="! ready" x-cloak class="provision__note provision__note--stop" x-text="reason"></div>

        <ol class="provision__steps">
            <li>Put the device into pairing mode - hold its WiFi/reset button until it announces it.</li>
            <li>Fill in the network it should join and check the server address below.</li>
            <li>Press <strong>Start provisioning</strong> and pick the <code>Petkit_…</code> entry your
                browser offers.</li>
        </ol>

        <div class="provision__grid">
            <div class="provision__field">
                <label for="provision-ssid">WiFi name (SSID)</label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        id="provision-ssid"
                        x-model="ssid"
                        x-bind:disabled="! ready"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
                <p class="provision__hint">2.4&nbsp;GHz only - no PetKit device joins a 5&nbsp;GHz network.</p>
            </div>

            <div class="provision__field">
                <label for="provision-password">WiFi password</label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="password"
                        id="provision-password"
                        x-model="password"
                        x-bind:disabled="! ready"
                        autocomplete="new-password"
                    />
                </x-filament::input.wrapper>
                <p class="provision__hint">Sent straight to the device over Bluetooth; it never reaches this server.</p>
            </div>
        </div>

        <div class="provision__grid">
            <div class="provision__field">
                <label for="provision-server">Server address</label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        id="provision-server"
                        x-model="server"
                        x-on:input="review()"
                        x-bind:disabled="! ready"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
                <p class="provision__hint">
                    Where the device sends its API calls. Must be reachable from the device itself -
                    an IP address, not a <code>.local</code> name.
                </p>
            </div>

            <div class="provision__field">
                <label for="provision-timezone">Device timezone</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="provision-timezone"
                        x-model="timezone"
                        x-bind:disabled="! ready"
                    >
                        @foreach ($timezoneOptions as $value => $label)
                            <option value="{{ $value }}">
                                {{ $label }}{{ (string) $value === $timezone ? ' — detected' : '' }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <p class="provision__hint">
                    Set once, here. Without it the device runs on UTC and stamps UTC onto its video.
                </p>
            </div>
        </div>

        <template x-for="note in notes" :key="note">
            <div class="provision__note" x-text="note"></div>
        </template>

        <div>
            <x-filament::button
                icon="heroicon-m-signal"
                x-on:click="start()"
                x-bind:disabled="! ready"
            >
                Start provisioning
            </x-filament::button>
        </div>
    </div>

    {{-- ======================= progress ======================= --}}
    <div x-show="phase !== 'form'" x-cloak class="provision__stack provision__stack--progress">
        {{-- stepper --}}
        <div class="provision__stepper">
            <template x-for="step in stepList()" :key="step.key">
                <div class="provision__step" x-bind:class="'provision__step--' + step.state">
                    <div class="provision__step-mark">
                        <template x-if="step.state === 'done'"><span>✓</span></template>
                        <template x-if="step.state === 'failed'"><span>✕</span></template>
                        <template x-if="step.state === 'active'"><div class="provision__spinner"></div></template>
                        <template x-if="step.state === 'pending'"><span>•</span></template>
                    </div>
                    <div class="provision__step-body">
                        <div class="provision__step-label" x-text="step.label"></div>
                        <div class="provision__step-sub" x-text="step.sub"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- live status while running --}}
        <div x-show="running" class="provision__row">
            <div class="provision__spinner"></div>
            <span style="font-size:0.875rem;color:var(--gray-500);" x-text="statusText"></span>
        </div>

        {{-- success --}}
        <div x-show="phase === 'done'" x-cloak class="provision__result provision__result--done">
            <div class="provision__result-icon">✓</div>
            <div>
                <div class="provision__result-title">Device ready</div>
                <div class="provision__result-text" x-text="resultText"></div>
                <div class="provision__result-meta" x-show="device" x-cloak>
                    <span x-text="device ? device.name + ' (' + device.serial_number + ')' : ''"></span>
                    <template x-if="ip"><span x-text="' — telnet at ' + ip"></span></template>
                </div>
            </div>
        </div>

        {{-- failure --}}
        <div x-show="phase === 'failed'" x-cloak class="provision__result provision__result--failed">
            <div class="provision__result-icon">✕</div>
            <div>
                <div class="provision__result-title">Provisioning did not finish</div>
                <div class="provision__result-text" x-text="resultText"></div>
                <div class="provision__result-meta" x-show="device" x-cloak
                     x-text="device ? device.name + ' (' + device.serial_number + ')' : ''"></div>
            </div>
        </div>

        {{--
            The install scripts, offered once telnet is up. Named and described
            before anything runs, and never run without being asked for - these
            are root shell commands on somebody's device.
        --}}
        <div x-show="offerInstall" x-cloak class="provision__note">
            <div>Telnet is up. Run the Localkit install scripts on this device?</div>
            <ul class="provision__install-list">
                <template x-for="step in installSteps" :key="step.name">
                    <li>
                        <strong x-text="step.name"></strong>
                        <span x-show="step.description" x-text="' - ' + step.description"></span>
                    </li>
                </template>
            </ul>
            <p class="provision__install-source">
                What a device needs differs per model -
                <a href="https://localkit.io/supported-devices.html" target="_blank"
                   rel="noopener noreferrer">localkit.io/supported-devices.html</a>
                lists the scripts for each one. Every command is printed to the console below as it runs.
            </p>
        </div>

        <div x-show="offerInstall" x-cloak class="provision__actions">
            <x-filament::button icon="heroicon-m-command-line" x-on:click="runInstall()">
                Run install scripts
            </x-filament::button>
            <x-filament::button color="gray" x-on:click="installDeclined = true">
                Skip
            </x-filament::button>
        </div>

        {{--
            The offer to undo a failed run. Shown only on a failure that left a
            device behind, and worded from whether this run is what created it -
            deleting a device Localkit already knew throws away its history too.
        --}}
        <div x-show="phase === 'failed' && device" x-cloak class="provision__note">
            <template x-if="! confirmDelete">
                <span
                    x-text="device
                        ? (device.created_in_run
                            ? device.name + ' was added to Localkit by this run. Delete it and start over?'
                            : device.name + ' was already in Localkit before this run. Delete it anyway?')
                        : ''"
                ></span>
            </template>
            <template x-if="confirmDelete">
                <span
                    x-text="device
                        ? 'Delete ' + device.name + ' (' + device.serial_number + ')'
                            + (device.created_in_run
                                ? '? It was created by this run.'
                                : '? It was here before this run - its history goes with it.')
                        : ''"
                ></span>
            </template>
        </div>

        {{-- what is left to decide about a failed run --}}
        <div x-show="! running && phase === 'failed'" x-cloak class="provision__actions">
            <template x-if="failedStep === 'heartbeat' || failedStep === 'telnet'">
                <x-filament::button color="gray" x-on:click="keepWaiting()">
                    Keep waiting
                </x-filament::button>
            </template>
            <template x-if="device && ! confirmDelete">
                <x-filament::button color="danger" icon="heroicon-m-trash"
                                    x-on:click="confirmDelete = true">
                    Delete device
                </x-filament::button>
            </template>
            <template x-if="device && confirmDelete">
                <x-filament::button color="danger" icon="heroicon-m-trash"
                                    x-bind:disabled="deleting" x-on:click="deleteDevice()">
                    <span x-text="deleting ? 'Deleting…' : 'Yes, delete it'"></span>
                </x-filament::button>
            </template>
            <template x-if="device && confirmDelete">
                <x-filament::button color="gray" x-bind:disabled="deleting"
                                    x-on:click="confirmDelete = false">
                    Keep it
                </x-filament::button>
            </template>
        </div>

        {{-- log --}}
        <pre x-show="lines.length" x-cloak class="provision__log" x-ref="log" x-text="lines.join('\n')"></pre>

        {{--
            What is left to do once a run has ended. The shell is reachable
            while a device is known, because a device that did not finish is
            exactly the one worth poking at - and this is the only shell it has
            while MQTT is held down.
        --}}
        <div x-show="! running" x-cloak class="provision__headline">
            <template x-if="device">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-command-line"
                                    x-on:click="openScript()">
                    Execute Script
                </x-filament::button>
            </template>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-path" x-on:click="reset()">
                Provision another device
            </x-filament::button>
        </div>

        {{--
            The script editor. An Alpine overlay rather than a Filament modal:
            this partial is itself rendered inside one on the device list, and a
            modal within a modal is Filament's problem rather than ours to hand
            it.
        --}}
        <template x-if="scriptModal">
            <div class="provision__modal" x-on:keydown.escape.window="closeScript()">
                <div class="provision__modal-backdrop" x-on:click="closeScript()"></div>
                <div class="provision__modal-panel" role="dialog" aria-modal="true"
                     aria-label="Execute a script on the device">
                    <div class="provision__modal-title">Execute a script on the device</div>
                    <p class="provision__modal-hint">
                        Runs over telnet as root on
                        <strong x-text="device ? device.name : 'the device'"></strong><span
                            x-show="ip" x-text="' (' + ip + ')'"></span>.
                        One command or a whole script - a script is written to the device and run
                        there. The output goes to the console behind this.
                    </p>
                    <textarea
                        rows="10"
                        x-model="commandInput"
                        x-bind:disabled="commandRunning"
                        x-on:keydown.enter.meta.prevent="runScript()"
                        x-on:keydown.enter.ctrl.prevent="runScript()"
                        x-init="$nextTick(() => $el.focus())"
                        placeholder="#!/bin/sh&#10;echo hello from the device"
                    ></textarea>
                    <div class="provision__modal-actions">
                        <x-filament::button
                            icon="heroicon-m-play"
                            x-bind:disabled="commandRunning || ! commandInput.trim()"
                            x-on:click="runScript()"
                        >
                            <span x-text="commandRunning ? 'Running…' : 'Run on device'"></span>
                        </x-filament::button>
                        <x-filament::button color="gray" x-bind:disabled="commandRunning"
                                            x-on:click="closeScript()">
                            Cancel
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
