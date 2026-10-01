@extends('admin.layouts.app')

@section('title', $device->device_identifier . ' - ' . $device->name)

@section('content')
<div class="space-y-8">
    <!-- Breadcrumb & Navigation -->
    <div class="flex items-center space-x-2 text-xs font-semibold text-slate-400">
        <a href="{{ route('admin.devices.index') }}" class="hover:text-white transition-colors">Devices</a>
        <span>/</span>
        <span class="text-indigo-400 font-mono">{{ $device->device_identifier }}</span>
    </div>

    <!-- Device Header Banner -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 backdrop-blur-sm flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div class="flex items-start space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr {{ $device->isDesktop() ? 'from-violet-600 to-indigo-600' : 'from-cyan-600 to-blue-600' }} flex items-center justify-center text-white shadow-xl ring-4 ring-indigo-500/10 flex-shrink-0">
                @if ($device->isDesktop())
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                @else
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-extrabold text-white font-mono">{{ $device->device_identifier }}</h1>
                    <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase tracking-wider {{ $device->isDesktop() ? 'bg-violet-500/20 text-violet-300 border border-violet-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' }}">
                        {{ $device->device_type }}
                    </span>
                    <div id="device-show-status-{{ $device->id }}">
                        @if ($device->status === 'online')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Online
                            </span>
                        @elseif ($device->status === 'warning')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Warning
                            </span>
                        @elseif ($device->status === 'pending_pair')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Pending Pair
                            </span>
                        @elseif ($device->status === 'disabled')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                <span class="w-2 h-2 rounded-full bg-rose-400"></span> Disabled
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                <span class="w-2 h-2 rounded-full bg-slate-500"></span> Offline
                            </span>
                        @endif
                    </div>
                </div>
                <div class="text-sm font-semibold text-slate-300 mt-1">{{ $device->name }}</div>
                <div class="text-xs text-slate-400 flex items-center gap-3 mt-0.5">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {{ $device->location }}
                    </span>
                    <span>&bull;</span>
                    <span>Last Seen: <span id="device-show-last-seen-{{ $device->id }}" class="text-slate-300 font-medium">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}</span></span>
                </div>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="flex items-center flex-wrap gap-3">
            <button onclick="document.getElementById('editDeviceModal').classList.remove('hidden')"
                class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition-colors">
                Edit Details
            </button>

            <form action="{{ route('admin.devices.toggle', $device->id) }}" method="POST">
                @csrf
                <button type="submit"
                    class="px-4 py-2 rounded-xl text-xs font-semibold transition-colors {{ $device->status === 'disabled' ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-slate-800 hover:bg-rose-950/60 text-rose-400 border border-rose-800/40' }}">
                    {{ $device->status === 'disabled' ? 'Enable Device' : 'Disable Device' }}
                </button>
            </form>

            <form action="{{ route('admin.devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this device?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-500 hover:text-rose-400 hover:bg-rose-950/20 transition-colors" title="Delete Device">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Fixed 1-Click Tablet Client Link (Zero Setup) -->
    @if ($device->isTablet())
        <div class="bg-gradient-to-r from-cyan-950/50 via-slate-900 to-indigo-950/40 border border-cyan-500/40 rounded-2xl p-6 shadow-xl relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-48 h-48 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 relative z-10">
                <div class="space-y-3 flex-1">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 font-bold text-base">
                            📱
                        </span>
                        <div>
                            <h2 class="text-base font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                                <span>Fixed 1-Click Client Link</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Permanent &bull; No Code Needed</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Send this link to the client. When clicked on their tablet or browser, it bypasses the pairing code screen and begins streaming the desktop immediately.
                            </p>
                        </div>
                    </div>

                    <!-- Direct Link Input Box & Copy Buttons -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                        <div class="relative flex-1">
                            <input id="directTabletLinkInput" type="text" readonly
                                value="{{ url('/t/' . $device->device_identifier) }}"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-cyan-500/40 text-cyan-300 font-mono text-sm shadow-inner focus:outline-none focus:ring-2 focus:ring-cyan-500">
                        </div>
                        <button type="button" onclick="copyDirectTabletLink('{{ $device->device_identifier }}')"
                            class="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold transition-all shadow-lg shadow-cyan-600/30 active:scale-95 flex items-center justify-center gap-1.5 flex-shrink-0">
                            <span>📋 Copy 1-Click Link</span>
                        </button>
                        <a href="{{ url('/t/' . $device->device_identifier) }}" target="_blank"
                            class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700 text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 flex-shrink-0">
                            <span>🔗 Open Stream</span>
                        </a>
                    </div>

                    <!-- Remote Desktop Feed Switcher -->
                    <div class="pt-3 border-t border-slate-800/80">
                        <form action="{{ route('admin.devices.assign_desktop', $device->id) }}" method="POST" class="flex flex-col sm:flex-row sm:items-center gap-3">
                            @csrf
                            <div class="text-xs font-semibold text-slate-300 flex items-center gap-1.5 flex-shrink-0">
                                <span class="text-violet-400">🖥️</span>
                                <span>Currently Streaming Desktop:</span>
                            </div>

                            @php
                                $currentDesktop = $device->assignedDesktops->first();
                            @endphp

                            <select name="desktop_id" required class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white text-xs font-mono focus:ring-2 focus:ring-indigo-500">
                                @forelse ($availableDesktops as $d)
                                    <option value="{{ $d->id }}" {{ $currentDesktop && $currentDesktop->id === $d->id ? 'selected' : '' }}>
                                        {{ $d->device_identifier }} - {{ $d->name }} ({{ $d->location }}) {{ $d->status === 'online' ? '🟢 Online' : '⚪ Offline' }}
                                    </option>
                                @empty
                                    <option value="" disabled>No Desktops Available</option>
                                @endforelse
                            </select>

                            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md active:scale-95 flex items-center justify-center gap-1 flex-shrink-0">
                                <span>🔄 Switch Desktop Feed</span>
                            </button>
                        </form>
                        <p class="text-[11px] text-slate-400 mt-1.5">
                            ⚡ When you switch the desktop here, the tablet seamlessly switches live feed in ~1-2 seconds without the client refreshing the page.
                        </p>
                    </div>
                </div>

                <!-- Direct QR Code -->
                <div class="flex flex-col items-center justify-center p-3 bg-slate-950/80 rounded-2xl border border-cyan-500/30 flex-shrink-0 self-center lg:self-auto shadow-inner">
                    <canvas id="directTabletQrCanvas" class="w-28 h-28 rounded-lg bg-white p-1.5"></canvas>
                    <span class="text-[10px] text-cyan-300/80 font-mono mt-2 uppercase tracking-wider">Scan to Watch Live</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Active Pairing Section (Highlighted Card) -->
    @php
        $activeTicket = $device->latestActivePairingTicket;
    @endphp
    <div class="bg-gradient-to-r from-indigo-950/40 via-slate-900 to-slate-900 border border-indigo-500/30 rounded-2xl p-6 shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full {{ $activeTicket ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span>
                    <h2 class="text-base font-bold text-white uppercase tracking-wider">{{ $device->isTablet() ? 'Fallback 6-Digit Pairing Ticket' : 'Desktop Agent Pairing Ticket' }}</h2>
                </div>
                <p class="text-xs text-slate-400 mt-1 max-w-xl">
                    {{ $device->isTablet() ? 'Optional fallback pairing code if direct 1-click link is not used.' : 'Generate an ephemeral pairing code for desktop-agent client_gui_setup.py.' }}
                </p>

                @if ($activeTicket)
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <div class="bg-slate-950 px-5 py-3 rounded-xl border border-indigo-500/50 shadow-inner flex items-center space-x-3">
                            <span class="text-xs text-indigo-400 font-semibold uppercase tracking-wider">Pairing Code:</span>
                            <span class="text-2xl font-extrabold text-white font-mono tracking-widest selection:bg-indigo-600">{{ $activeTicket->pairing_code }}</span>
                        </div>
                        <div class="text-xs text-slate-400">
                            Expires in <span class="text-indigo-300 font-semibold">{{ $activeTicket->expires_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @else
                    <div class="mt-4 text-xs text-slate-500 font-medium">
                        No active pairing code. Generate one below to pair a tablet or desktop agent.
                    </div>
                @endif
            </div>

            <!-- Action Button & QR -->
            <div class="flex items-center gap-4 flex-shrink-0">
                @if ($activeTicket)
                    <div class="bg-white p-2 rounded-xl shadow-lg border border-slate-700 hidden sm:block">
                        <canvas id="pairingQrCanvas" class="w-24 h-24"></canvas>
                    </div>
                @endif
                <form action="{{ route('admin.devices.pair_ticket', $device->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/20 transition-all active:scale-95 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                        <span>{{ $activeTicket ? 'Regenerate Code' : 'Generate Pairing Code' }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Two-Column Grid: Device Info & Audit Trail -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Device Details & Credentials -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Hardware & Stream Status Card -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-sm shadow-xl">
                <h2 class="text-base font-bold text-white mb-4">Device Properties</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                        <dt class="text-slate-500 font-semibold uppercase tracking-wider text-[10px]">Device UUID</dt>
                        <dd class="text-slate-200 font-mono mt-1 select-all">{{ $device->uuid }}</dd>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                        <dt class="text-slate-500 font-semibold uppercase tracking-wider text-[10px]">Device Type</dt>
                        <dd class="text-indigo-400 font-bold uppercase mt-1">{{ $device->device_type }}</dd>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                        <dt class="text-slate-500 font-semibold uppercase tracking-wider text-[10px]">Streaming Status</dt>
                        <dd class="text-slate-200 font-semibold mt-1 uppercase">{{ $device->stream_status }}</dd>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                        <dt class="text-slate-500 font-semibold uppercase tracking-wider text-[10px]">Active Viewers</dt>
                        <dd class="text-slate-200 font-semibold mt-1">{{ $device->active_viewers_count }} connected</dd>
                    </div>
                </dl>
            </div>

            <!-- Device Credentials History -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-sm">
                <h2 class="text-base font-bold text-white mb-2">Device Permanent Tokens</h2>
                <p class="text-xs text-slate-400 mb-4">Cryptographic bearer credentials (only SHA-256 hashes are stored).</p>

                @if ($device->credentials->isEmpty())
                    <div class="text-xs text-slate-500 py-3">No permanent credentials generated yet.</div>
                @else
                    <div class="space-y-2">
                        @foreach ($device->credentials as $cred)
                            <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between text-xs font-mono">
                                <div>
                                    <span class="text-slate-300">{{ $cred->token_prefix }}...</span>
                                    <span class="text-slate-500 ml-2">Issued: {{ $cred->created_at->format('M d, Y') }}</span>
                                </div>
                                <div>
                                    @if ($cred->isRevoked())
                                        <span class="text-rose-400 font-semibold">Revoked</span>
                                    @else
                                        <span class="text-emerald-400 font-semibold">&bull; Active</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Right: Audit Trail -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-sm h-fit">
            <h2 class="text-base font-bold text-white mb-1">Device Audit Trail</h2>
            <p class="text-xs text-slate-400 mb-4">Complete security & lifecycle events.</p>

            @if ($device->auditLogs->isEmpty())
                <div class="py-8 text-center text-slate-500 text-xs">No audit logs for this device yet.</div>
            @else
                <div class="space-y-3">
                    @foreach ($device->auditLogs as $log)
                        <div class="flex items-start space-x-2.5 text-xs pb-3 border-b border-slate-800/50 last:border-0">
                            <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 {{ $log->severity === 'warning' ? 'bg-amber-400' : 'bg-indigo-400' }}"></div>
                            <div class="flex-1">
                                <div class="font-bold text-slate-200 uppercase tracking-wide text-[11px]">
                                    {{ str_replace('_', ' ', $log->event_type) }}
                                </div>
                                <div class="text-slate-400 text-[11px] mt-0.5">
                                    {{ $log->created_at->format('M d, Y H:i:s') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal: Edit Device -->
<div id="editDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-5">
            <h3 class="text-lg font-bold text-white">Edit Device Details</h3>
            <button onclick="document.getElementById('editDeviceModal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.devices.update', $device->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Device Identifier / Link ID</label>
                <input type="text" name="device_identifier" value="{{ $device->device_identifier }}" required
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-sm focus:ring-2 focus:ring-indigo-500">
                <p class="text-[11px] text-slate-500 mt-1">Changes direct 1-click URL (e.g. /t/{{ $device->device_identifier }}).</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Friendly Name</label>
                <input type="text" name="name" value="{{ $device->name }}" required
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Physical Location</label>
                <input type="text" name="location" value="{{ $device->location }}" required
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-4 flex justify-end space-x-3 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('editDeviceModal').classList.add('hidden')"
                    class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold shadow-lg shadow-indigo-600/20">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin-assets/js/qrious.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if ($device->isTablet())
            const directCanvas = document.getElementById('directTabletQrCanvas');
            if (directCanvas) {
                new QRious({
                    element: directCanvas,
                    value: "{{ url('/t/' . $device->device_identifier) }}",
                    size: 150,
                    level: 'H'
                });
            }
        @endif

        @if ($activeTicket)
            const qrCanvas = document.getElementById('pairingQrCanvas');
            if (qrCanvas) {
                new QRious({
                    element: qrCanvas,
                    value: "{{ url('/tablet?code=' . $activeTicket->pairing_code) }}",
                    size: 130,
                    level: 'M'
                });
            }
        @endif
    });
</script>
@endpush
