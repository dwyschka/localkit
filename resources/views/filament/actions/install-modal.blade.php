@php
$localIp = trim((string) config('petkit.local_ip'));
$initialIp = $defaultIp;
$exampleIp = !empty($defaultIp) ? $defaultIp : (!empty($localIp) && $localIp !== '127.0.0.1' ? $localIp : '192.168.1.150');

if (empty($initialIp)) {
    if (!empty($localIp) && $localIp !== '127.0.0.1') {
        $parts = explode('.', $localIp);
        if (count($parts) >= 4) {
            $initialIp = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.';
        } elseif (count($parts) === 3) {
            $initialIp = implode('.', $parts) . '.';
        } else {
            $initialIp = rtrim($localIp, '.') . '.';
        }
    } else {
        $initialIp = '';
    }
}
@endphp

<style>
    .lk-installer {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .lk-installer__top {
        display: flex;
        align-items: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .lk-installer__input-group {
        flex: 1 1 240px;
    }

    .lk-installer__label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        margin-bottom: 0.375rem;
        color: var(--gray-700, #374151);
    }

    :is(.dark) .lk-installer__label {
        color: var(--gray-300, #d1d5db);
    }

    .lk-installer__actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .lk-installer__terminal-card {
        border-radius: 0.375rem;
        border: 1px solid #27272a;
        background: #09090b;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        display: flex;
        flex-direction: column;
        width: 100%;
        aspect-ratio: 16 / 9;
        min-height: 280px;
    }

    .lk-installer__terminal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.4rem 0.75rem;
        background: #18181b;
        border-bottom: 1px solid #27272a;
        user-select: none;
        flex-shrink: 0;
    }

    .lk-installer__linux-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #a1a1aa;
        font-size: 0.75rem;
        font-family: ui-monospace, "Ubuntu Mono", "DejaVu Sans Mono", monospace;
    }

    .lk-installer__linux-icon {
        color: #22c55e;
        font-weight: 700;
        font-size: 0.8125rem;
    }

    .lk-installer__header-right {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .lk-installer__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.6875rem;
        font-weight: 600;
        font-family: ui-monospace, "Ubuntu Mono", monospace;
        background: #27272a;
        color: #a1a1aa;
    }

    .lk-installer__badge--running {
        background: #1e3a5f !important;
        color: #60a5fa !important;
    }

    .lk-installer__badge--success {
        background: #14532d !important;
        color: #4ade80 !important;
    }

    .lk-installer__badge--error {
        background: #7f1d1d !important;
        color: #f87171 !important;
    }

    .lk-installer__header-btn {
        background: #27272a;
        border: 1px solid #3f3f46;
        color: #a1a1aa;
        font-size: 0.6875rem;
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        user-select: none;
    }

    .lk-installer__header-btn:hover:not(:disabled) {
        background: #3f3f46;
        color: #fafafa;
    }

    .lk-installer__header-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .lk-installer__linux-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #71717a;
        font-size: 0.75rem;
        font-family: ui-monospace, monospace;
        margin-left: 0.25rem;
    }

    .lk-installer__pre {
        margin: 0;
        padding: 0.875rem 1rem;
        flex: 1 1 0;
        min-height: 0;
        height: 100%;
        overflow-y: auto;
        font-family: ui-monospace, "Ubuntu Mono", "DejaVu Sans Mono", Menlo, Consolas, monospace;
        font-size: 0.8125rem;
        line-height: 1.55;
        white-space: pre-wrap;
        word-break: break-all;
        color: #f4f4f5;
        background: #09090b;
        scrollbar-width: thin;
        scrollbar-color: #3f3f46 #09090b;
    }

    .lk-installer__pre::-webkit-scrollbar {
        width: 8px;
    }

    .lk-installer__pre::-webkit-scrollbar-track {
        background: #09090b;
    }

    .lk-installer__pre::-webkit-scrollbar-thumb {
        background-color: #3f3f46;
        border-radius: 3px;
    }
</style>

<div
    x-data="{
        ip: '{{ $initialIp }}',
        status: 'ready',
        statusText: 'Ready',
        running: false,
        copied: false,
        copyTimeout: null,
        controller: null,
        output: '// Ready. Enter target device IP and click \'Run Installer\'.\n',

        init() {
            this.focusInput();
            this.$nextTick(() => this.focusInput());
            setTimeout(() => this.focusInput(), 60);
            setTimeout(() => this.focusInput(), 200);
        },

        focusInput() {
            const input = this.$refs.ipInput;
            if (input) {
                input.focus();
                const len = input.value ? input.value.length : 0;
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(len, len);
                }
            }
        },

        append(text) {
            if (this.output.startsWith('// Ready')) {
                this.output = '';
            }
            this.output += text;
            this.$nextTick(() => {
                if (this.$refs.terminal) {
                    this.$refs.terminal.scrollTop = this.$refs.terminal.scrollHeight;
                }
            });
        },

        get formattedOutput() {
            return this.ansiToHtml(this.output);
        },

        ansiToHtml(str) {
            if (!str) return '';
            let s = str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const colors = {
                '0': null,
                '1': 'font-weight:600;',
                '2': 'opacity:0.75;',
                '3': 'font-style:italic;',
                '4': 'text-decoration:underline;',
                '30': 'color:#71717a;',
                '31': 'color:#ef4444;',
                '32': 'color:#22c55e;',
                '33': 'color:#eab308;',
                '34': 'color:#3b82f6;',
                '35': 'color:#a855f7;',
                '36': 'color:#06b6d4;',
                '37': 'color:#f4f4f5;',
                '90': 'color:#a1a1aa;',
                '91': 'color:#f87171;',
                '92': 'color:#4ade80;',
                '93': 'color:#fde047;',
                '94': 'color:#60a5fa;',
                '95': 'color:#c084fc;',
                '96': 'color:#22d3ee;',
                '97': 'color:#ffffff;'
            };
            let parts = s.split(/(?:\x1b|\u001b)?\[([0-9;]+)?m/);
            if (parts.length === 1) return s;
            let res = '';
            let curStyle = '';
            for (let i = 0; i < parts.length; i += 2) {
                if (curStyle) {
                    res += '&lt;span style=&quot;' + curStyle + '&quot;&gt;' + parts[i] + '&lt;/span&gt;';
                } else {
                    res += parts[i];
                }
                if (i + 1 < parts.length) {
                    const code = parts[i + 1] || '0';
                    if (code === '0') {
                        curStyle = '';
                    } else if (colors[code]) {
                        curStyle = colors[code];
                    }
                }
            }
            return res;
        },

        stripAnsi(str) {
            if (!str) return '';
            return str.replace(/(?:\x1b|\u001b)?\[(?:\d+(?:;\d+)*)?m/g, '');
        },

        async copy() {
            try {
                const textToCopy = this.stripAnsi(this.output);
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(textToCopy);
                } else {
                    const textArea = document.createElement('textarea');
                    textArea.value = textToCopy;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    document.execCommand('copy');
                    textArea.remove();
                }
                this.copied = true;
                if (this.copyTimeout) clearTimeout(this.copyTimeout);
                this.copyTimeout = setTimeout(() => { this.copied = false; }, 2000);
            } catch (e) {
                console.error('Failed to copy text: ', e);
            }
        },

        clear() {
            if (this.running) return;
            this.output = '// Ready. Enter target device IP and click \'Run Installer\'.\n';
            this.status = 'ready';
            this.statusText = 'Ready';
        },

        async run() {
            if (this.running) return;
            const targetIp = this.ip.trim();
            this.output = '';

            if (!targetIp) {
                this.append('[LocalKit] Error: Target IPv4 address is required.\n');
                this.status = 'error';
                this.statusText = 'Failed';
                return;
            }

            this.running = true;
            this.status = 'running';
            this.statusText = 'Connecting...';

            this.controller = new AbortController();
            const url = '{{ route('installer.run') }}';

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json, text/plain, */*',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ ip: targetIp }),
                    credentials: 'same-origin',
                    signal: this.controller.signal,
                });

                const contentType = resp.headers.get('content-type') || '';

                if (!resp.ok) {
                    let errMsg = `${resp.status} ${resp.statusText}`.trim();
                    if (contentType.includes('application/json')) {
                        try {
                            const data = await resp.json();
                            if (data.errors && typeof data.errors === 'object') {
                                errMsg = Object.values(data.errors).flat().join(' ');
                            } else if (data.message) {
                                errMsg = data.message;
                            }
                        } catch (_) {}
                    } else {
                        try {
                            const txt = await resp.text();
                            if (txt && !txt.startsWith('<!DOCTYPE') && !txt.startsWith('<html')) {
                                errMsg = txt;
                            }
                        } catch (_) {}
                    }
                    this.append('[LocalKit] Error: ' + errMsg + '\n');
                    this.status = 'error';
                    this.statusText = 'Failed';
                    return;
                }

                if (resp.redirected || contentType.includes('text/html')) {
                    this.append('[LocalKit] Error: Unexpected redirect or HTML response (session may have expired).\n');
                    this.status = 'error';
                    this.statusText = 'Failed';
                    return;
                }

                this.statusText = 'Installing...';
                const reader = resp.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    const chunk = decoder.decode(value, { stream: true });
                    this.append(chunk);
                }

                const leftover = decoder.decode();
                if (leftover) {
                    this.append(leftover);
                }

                const hasCredsError = this.output.includes('Telnet credentials not configured');
                const hasExitError = this.output.includes('[LocalKit-Exit-Error:');
                const hasGeneralError = /(^|\n)\[LocalKit\] Error:/.test(this.output) ||
                                       hasCredsError ||
                                       /wget:\s*(server returned error|can't connect|bad address)/i.test(this.output) ||
                                       /Firmware mismatch:/i.test(this.output);

                if (hasExitError) {
                    const match = this.output.match(/\[LocalKit-Exit-Error:\s*(\d+)\]/);
                    const exitCode = match ? match[1] : '1';
                    this.append('\n[LocalKit] Error: Command exited with status ' + exitCode + '\n');
                }

                if (hasExitError || hasGeneralError) {
                    this.append('\n[LocalKit] Execution failed.\n');
                    this.status = 'error';
                    this.statusText = 'Failed';

                    if (hasCredsError) {
                        this.notify('Telnet credentials not configured', 'Set DEVICE_TELNET_USERNAME and DEVICE_TELNET_PASSWORD in your .env file.', 'danger');
                    } else {
                        this.notify('Device installation failed', 'Check the terminal output for error details.', 'danger');
                    }
                } else {
                    this.append('\n[LocalKit] Execution completed.\n');
                    this.status = 'success';
                    this.statusText = 'Completed';
                    this.notify('Device installation completed', 'LocalKit was installed successfully on ' + this.ip + '.', 'success');
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    this.append('\n[LocalKit] Disconnected from installer output.\n');
                    this.append('[LocalKit] Note: The installation process on the device may still be running.\n');
                    this.status = 'error';
                    this.statusText = 'Disconnected';
                } else {
                    this.append('\n[LocalKit] Error: ' + err.message + '\n');
                    this.status = 'error';
                    this.statusText = 'Error';
                    this.notify('Installation error', err.message, 'danger');
                }
            } finally {
                this.running = false;
                this.controller = null;
            }
        },

        notify(title, body = null, status = 'danger') {
            try {
                if (typeof FilamentNotification !== 'undefined') {
                    const notif = new FilamentNotification().title(title);
                    if (body) notif.body(body);
                    if (status === 'success') notif.success();
                    else if (status === 'warning') notif.warning();
                    else notif.danger();
                    notif.send();
                }
            } catch (_) {}
        },

        stop() {
            if (this.controller) {
                this.controller.abort();
            }
        }
    }"
    class="lk-installer"
    @keydown.enter.prevent="if (!running) run()">
    <!-- Top Configuration Bar -->
    <div class="lk-installer__top">
        <div class="lk-installer__input-group">
            <label for="installer-ip" class="lk-installer__label">
                Target Device IPv4
            </label>
            <x-filament::input.wrapper prefix-icon="heroicon-m-globe-alt">
                <x-filament::input
                    id="installer-ip"
                    x-ref="ipInput"
                    type="text"
                    x-model="ip"
                    x-bind:disabled="running"
                    @keydown.enter.prevent.stop="if (!running) run()"
                    placeholder="e.g. {{ $exampleIp }}"
                    autofocus
                    autocomplete="off"
                    style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace;" />
            </x-filament::input.wrapper>
        </div>

        <div class="lk-installer__actions">
            <x-filament::button
                type="button"
                icon="heroicon-m-play"
                color="primary"
                x-show="!running"
                @click="run()">
                Run Installer
            </x-filament::button>

            <x-filament::button
                type="button"
                icon="heroicon-m-stop"
                color="danger"
                x-show="running"
                @click="stop()">
                Disconnect
            </x-filament::button>
        </div>
    </div>

    <!-- Terminal Window Container (Linux style) -->
    <div class="lk-installer__terminal-card">
        <!-- Terminal Header Bar -->
        <div class="lk-installer__terminal-header">
            <div class="lk-installer__linux-title">
                <span class="lk-installer__linux-icon">&gt;_</span>
                <span>root@petkit-device:~# (sh)</span>
            </div>

            <div class="lk-installer__header-right">
                <span
                    class="lk-installer__badge"
                    :class="{
                        'lk-installer__badge--running': status === 'running',
                        'lk-installer__badge--success': status === 'success',
                        'lk-installer__badge--error': status === 'error'
                    }"
                    x-text="statusText"></span>
                <button
                    type="button"
                    title="Copy console output"
                    class="lk-installer__header-btn"
                    @click="copy()">
                    <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                </button>
                <button
                    type="button"
                    title="Clear console"
                    class="lk-installer__header-btn"
                    x-bind:disabled="running"
                    @click="clear()">
                    Clear
                </button>
                <div class="lk-installer__linux-controls">
                    <span>─</span>
                    <span>□</span>
                    <span>✕</span>
                </div>
            </div>
        </div>

        <!-- Terminal Output Area -->
        <pre
            x-ref="terminal"
            class="lk-installer__pre"
            x-html="formattedOutput"></pre>
    </div>
</div>
