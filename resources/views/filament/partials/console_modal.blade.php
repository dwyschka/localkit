<x-filament::modal id="rtsp-console-modal" width="4xl">
    <x-slot name="heading">
        <div class="flex items-center space-x-2">
            <x-filament::icon icon="heroicon-o-command-line" class="h-6 w-6 text-primary-500 inline" />
            <span>Terminal Execution Log</span>
        </div>
    </x-slot>

    <x-slot name="description">
        Output of the Telnet session and configuration updates
    </x-slot>

    <div class="rounded-xl bg-gray-950 p-4 border border-gray-800 shadow-2xl font-mono text-xs">
        <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-800">
            <div class="flex items-center space-x-2">
                <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>
                <span class="w-3 h-3 rounded-full bg-yellow-500 inline-block"></span>
                <span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span>
                <span class="text-gray-400 font-semibold ml-2">petkit &bull; telnet console</span>
            </div>
            <span class="text-xs text-gray-500">raptor.conf</span>
        </div>
        <pre class="text-green-400 whitespace-pre-wrap leading-relaxed overflow-x-auto max-h-96 select-all font-mono">{{ $this->telnetConsoleLog ?? 'No logs recorded.' }}</pre>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end w-full">
            <x-filament::button
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'rtsp-console-modal' })"
            >
                Close
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>
