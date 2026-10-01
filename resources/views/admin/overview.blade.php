@extends('admin.layouts.app')

@section('title', 'Fleet Operations Center')

@section('container_class', 'w-full max-w-[1700px]')

@section('content')
<div class="space-y-8">
    <!-- Header with Quick Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Fleet Operations Center</h1>
            <p class="text-sm text-slate-400 mt-1">Windows Desktops &bull; WebRTC Live Screen Streaming &bull; Android Tablet Viewers</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.mappings.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold border border-slate-700 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                <span>Manage Mappings</span>
            </a>
            <button onclick="document.getElementById('createDeviceModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>Provision Device</span>
            </button>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Desktops -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-violet-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Windows Desktops</span>
                <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center text-violet-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-white">{{ $totalDesktops }}</div>
            <div class="text-xs text-slate-400 mt-1">
                <span class="text-emerald-400 font-semibold"><span id="stat-online-desktops">{{ $onlineDesktops }}</span> Online</span> &bull; <span class="text-indigo-400 font-semibold"><span id="stat-streaming-desktops">{{ $streamingDesktops }}</span> Streaming</span>
            </div>
        </div>

        <!-- Android Tablets -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-cyan-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Android Tablets</span>
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-white">{{ $totalTablets }}</div>
            <div class="text-xs text-slate-400 mt-1">
                <span class="text-emerald-400 font-semibold"><span id="stat-online-tablets">{{ $onlineTablets }}</span> Online Viewers</span>
            </div>
        </div>

        <!-- Active Live Sessions -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-emerald-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Active WebRTC Streams</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-emerald-400"><span id="stat-active-sessions">{{ $activeSessionsCount }}</span></div>
            <div class="text-xs text-slate-500 mt-1">Real-time live peer connections</div>
        </div>

        <!-- Authorized Mappings -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-indigo-400 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider">Authorized Mappings</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                </div>
            </div>
            <div class="text-3xl font-extrabold text-indigo-400">{{ $mappingsCount }}</div>
            <div class="text-xs text-slate-500 mt-1">Desktop ↔ Tablet pairs</div>
        </div>
    </div>

    <!-- Active WebRTC Sessions Real-time Table (Auto-updates without page refresh) -->
    <div id="active-sessions-container" class="{{ $activeSessions->isEmpty() ? 'hidden ' : '' }}bg-slate-900/60 border border-emerald-500/30 rounded-2xl p-6 backdrop-blur-sm shadow-xl transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                <h2 class="text-base font-bold text-white">Active WebRTC Live Streams</h2>
                <span class="text-[10px] text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full font-mono">Zero Refresh Realtime</span>
            </div>
            <span id="active-sessions-badge" class="text-xs text-emerald-400 font-mono bg-emerald-500/10 px-2.5 py-1 rounded-full border border-emerald-500/20">
                {{ $activeSessions->count() }} ACTIVE
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="pb-3">Tablet Viewer</th>
                        <th class="pb-3">Desktop Stream Source</th>
                        <th class="pb-3">Stream Status</th>
                        <th class="pb-3">Started At (IST)</th>
                        <th class="pb-3">Active Duration</th>
                    </tr>
                </thead>
                <tbody id="active-sessions-tbody" class="divide-y divide-slate-800/60 font-mono text-xs">
                    @foreach ($activeSessions as $session)
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3.5">
                                <div class="font-bold text-cyan-400 font-mono">{{ $session->tablet?->device_identifier ?? 'Unknown Tablet' }}</div>
                                <div class="text-slate-400 text-[11px] font-sans">{{ $session->tablet?->name }}</div>
                            </td>
                            <td class="py-3.5">
                                <div class="font-bold text-violet-400 font-mono">{{ $session->desktop?->device_identifier ?? 'Unknown Desktop' }}</div>
                                <div class="text-slate-400 text-[11px] font-sans">{{ $session->desktop?->name }}</div>
                            </td>
                            <td class="py-3.5">
                                @if ($session->status === 'connected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-sans">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> LIVE
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20 font-sans">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Connecting...
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 text-slate-300 font-sans text-xs">
                                {{ $session->started_at?->timezone('Asia/Kolkata')->format('h:i:s A') }}
                            </td>
                            <td class="py-3.5 font-sans text-xs font-semibold">
                                <span class="live-duration-timer text-emerald-400 font-mono font-bold" data-started-at="{{ $session->started_at?->timestamp }}">
                                    {{ $session->started_at ? gmdate('H:i:s', max(0, time() - $session->started_at->timestamp)) : '00:00:00' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Windows Desktops Fleet Table -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-sm">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span class="text-violet-400">🖥️</span> Windows Desktops Fleet (Screen + Audio Sources)
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Physical Windows PCs running Python 3.11 streaming daemon.</p>
            </div>
            <span class="text-xs text-slate-400 font-semibold">{{ $desktops->count() }} Desktops</span>
        </div>

        @if ($desktops->isEmpty())
            <div class="py-8 text-center text-slate-500 text-sm">
                No Windows Desktops registered. Provision a desktop to start streaming.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="pb-3">Desktop PC</th>
                            <th class="pb-3">Connection Status</th>
                            <th class="pb-3">Stream Status</th>
                            <th class="pb-3">Active Viewers</th>
                            <th class="pb-3">Authorized Tablets</th>
                            <th class="pb-3">Last Seen</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach ($desktops as $desktop)
                            <tr class="hover:bg-slate-800/30 transition-colors" data-device-id="{{ $desktop->id }}" data-device-uuid="{{ $desktop->uuid }}">
                                <td class="py-3.5">
                                    <div class="font-bold text-white font-mono flex items-center gap-2">
                                        {{ $desktop->device_identifier }}
                                    </div>
                                    <div class="text-xs text-slate-400">{{ $desktop->name }} &bull; <span class="text-slate-500">{{ $desktop->location }}</span></div>
                                </td>
                                <td class="py-3.5 device-status-badge">
                                    @if ($desktop->status === 'online')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
                                        </span>
                                    @elseif ($desktop->status === 'disabled')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Disabled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 device-stream-status-badge">
                                    @if ($desktop->stream_status === 'streaming')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                            🎥 STREAMING
                                        </span>
                                    @elseif ($desktop->status === 'online')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-400 bg-slate-800">
                                            IDLE
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-500">OFFLINE</span>
                                    @endif
                                </td>
                                <td class="py-3.5">
                                    <span class="device-viewers-count font-mono font-bold text-xs px-2.5 py-1 rounded-lg {{ $desktop->active_viewers_count > 0 ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-400' }}">
                                        {{ $desktop->active_viewers_count }} Viewer{{ $desktop->active_viewers_count == 1 ? '' : 's' }}
                                    </span>
                                </td>
                                <td class="py-3.5">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($desktop->assignedTablets as $tablet)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-cyan-500/10 text-cyan-300 border border-cyan-500/20" title="{{ $tablet->name }}">
                                                {{ $tablet->device_identifier }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-500 italic">None assigned</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="py-3.5 text-xs text-slate-400 device-last-seen">
                                    {{ $desktop->last_seen_at ? $desktop->last_seen_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="py-3.5 text-right space-x-2">
                                    <form action="{{ route('admin.devices.pair_ticket', $desktop->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 bg-indigo-500/10 px-2.5 py-1 rounded-lg border border-indigo-500/20" title="Issue 6-char pairing ticket">
                                            Pair Ticket
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.devices.show', $desktop->id) }}" class="text-slate-400 hover:text-white text-xs font-semibold">Details &rarr;</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Android Tablets Fleet Table -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 backdrop-blur-sm">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <span class="text-cyan-400">📱</span> Android Tablets Fleet (Client Viewers)
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Tablets authorized to view assigned desktop feeds.</p>
            </div>
            <span class="text-xs text-slate-400 font-semibold">{{ $tablets->count() }} Tablets</span>
        </div>

        @if ($tablets->isEmpty())
            <div class="py-8 text-center text-slate-500 text-sm">
                No Android Tablets registered yet.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="pb-3">Tablet Device</th>
                            <th class="pb-3">Connection Status</th>
                            <th class="pb-3">Currently Viewing</th>
                            <th class="pb-3">Assigned Desktops</th>
                            <th class="pb-3">Last Seen</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach ($tablets as $tablet)
                            <tr class="hover:bg-slate-800/30 transition-colors" data-device-id="{{ $tablet->id }}" data-device-uuid="{{ $tablet->uuid }}">
                                <td class="py-3.5">
                                    <div class="font-bold text-white font-mono flex items-center gap-2">
                                        {{ $tablet->device_identifier }}
                                    </div>
                                    <div class="text-xs text-slate-400">{{ $tablet->name }} &bull; <span class="text-slate-500">{{ $tablet->location }}</span></div>
                                </td>
                                <td class="py-3.5 device-status-badge">
                                    @if ($tablet->status === 'online')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
                                        </span>
                                    @elseif ($tablet->status === 'disabled')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Disabled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 device-viewing-status">
                                    @if ($tablet->activeViewingSession && $tablet->activeViewingSession->desktop)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                            {{ $tablet->activeViewingSession->desktop->device_identifier }}
                                            <span class="text-emerald-300 font-semibold font-mono text-[11px] ml-1">(<span class="live-duration-timer" data-started-at="{{ $tablet->activeViewingSession->started_at?->timestamp }}">00:00:00</span>)</span>
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-500 italic">Not Streaming</span>
                                    @endif
                                </td>
                                <td class="py-3.5">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($tablet->assignedDesktops as $desktop)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-violet-500/10 text-violet-300 border border-violet-500/20" title="{{ $desktop->name }}">
                                                {{ $desktop->device_identifier }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-500 italic">No desktops assigned</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="py-3.5 text-xs text-slate-400 device-last-seen">
                                    {{ $tablet->last_seen_at ? $tablet->last_seen_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="py-3.5 text-right space-x-1.5">
                                    <button type="button" onclick="copyDirectTabletLink('{{ $tablet->device_identifier }}')"
                                        class="text-xs font-semibold text-cyan-300 hover:text-white bg-cyan-600/20 hover:bg-cyan-600/30 px-2.5 py-1 rounded-lg border border-cyan-500/30 transition-all inline-flex items-center gap-1 shadow-sm active:scale-95" title="Copy Fixed 1-Click Link for Client">
                                        📋 Copy Link
                                    </button>
                                    <a href="/t/{{ $tablet->device_identifier }}" target="_blank"
                                        class="text-xs font-semibold text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 px-2 py-1 rounded-lg border border-slate-700 transition-colors inline-flex items-center gap-1" title="Open Tablet Feed in New Tab">
                                        🔗 Open
                                    </a>
                                    <form action="{{ route('admin.devices.pair_ticket', $tablet->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-slate-400 hover:text-slate-200 bg-slate-800/80 px-2 py-1 rounded-lg border border-slate-700" title="Issue 6-char pairing ticket">
                                            Ticket
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.devices.show', $tablet->id) }}" class="text-slate-400 hover:text-white text-xs font-semibold">Details &rarr;</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Modal: Provision Device -->
<div id="createDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-5">
            <h3 class="text-lg font-bold text-white">Provision New Fleet Device</h3>
            <button onclick="document.getElementById('createDeviceModal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.devices.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Device Type</label>
                <select name="device_type" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="desktop">Windows Desktop (Screen Source)</option>
                    <option value="tablet" selected>Android Tablet (Client Viewer)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Device Identifier (Optional)</label>
                <input type="text" name="device_identifier" placeholder="e.g. DESK-001 or TAB-001 (auto if blank)"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Friendly Name</label>
                <input type="text" name="name" required placeholder="e.g. Trading Floor PC 01 / ICU Tablet A"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Physical Location</label>
                <input type="text" name="location" required placeholder="e.g. Building A, Floor 2"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-4 flex justify-end space-x-3 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('createDeviceModal').classList.add('hidden')"
                    class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold shadow-lg shadow-indigo-600/20">
                    Provision Device
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Realtime Zero-Refresh Telemetry Sync for Overview Dashboard
    (function () {
        async function syncOverviewStats() {
            try {
                const res = await fetch('{{ route("admin.devices.realtime_status") }}');
                if (!res.ok) return;
                const data = await res.json();
                if (data.success && Array.isArray(data.devices)) {
                    let onlineDesktops = 0;
                    let streamingDesktops = 0;
                    let onlineTablets = 0;

                    data.devices.forEach(d => {
                        if (d.device_type === 'desktop') {
                            if (d.status === 'online') onlineDesktops++;
                            if (d.stream_status === 'streaming') streamingDesktops++;
                        } else if (d.device_type === 'tablet') {
                            if (d.status === 'online') onlineTablets++;
                        }

                        // Update row badges
                        const row = document.querySelector(`tr[data-device-id="${d.id}"]`) || 
                                    document.querySelector(`tr[data-device-uuid="${d.uuid}"]`);
                        if (row) {
                            const badge = row.querySelector('.device-status-badge');
                            const streamBadge = row.querySelector('.device-stream-status-badge');
                            const lastSeen = row.querySelector('.device-last-seen');
                            const viewers = row.querySelector('.device-viewers-count');

                            if (badge) {
                                if (d.status === 'online') {
                                    badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Online</span>`;
                                } else if (d.status === 'disabled') {
                                    badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20"><span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Disabled</span>`;
                                } else {
                                    badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700"><span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline</span>`;
                                }
                            }

                            if (streamBadge) {
                                if (d.stream_status === 'streaming') {
                                    streamBadge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">🎥 STREAMING</span>`;
                                } else if (d.status === 'online') {
                                    streamBadge.innerHTML = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-400 bg-slate-800">IDLE</span>`;
                                } else {
                                    streamBadge.innerHTML = `<span class="text-xs text-slate-500">OFFLINE</span>`;
                                }
                            }

                            if (lastSeen && d.last_seen_human) {
                                lastSeen.innerText = d.last_seen_human;
                            }

                            if (viewers && typeof d.active_viewers_count !== 'undefined') {
                                viewers.innerText = `${d.active_viewers_count} Viewer${d.active_viewers_count === 1 ? '' : 's'}`;
                            }
                        }
                    });

                    // Update Top Counters
                    const elOnlineDesk = document.getElementById('stat-online-desktops');
                    const elStreamDesk = document.getElementById('stat-streaming-desktops');
                    const elOnlineTab = document.getElementById('stat-online-tablets');
                    const elActiveSess = document.getElementById('stat-active-sessions');

                    if (elOnlineDesk) elOnlineDesk.innerText = onlineDesktops;
                    if (elStreamDesk) elStreamDesk.innerText = streamingDesktops;
                    if (elOnlineTab) elOnlineTab.innerText = onlineTablets;
                    if (elActiveSess && data.active_sessions) elActiveSess.innerText = data.active_sessions.length;
                }
            } catch (e) {
                // Ignore transient network errors
            }
        }

        setInterval(syncOverviewStats, 2000);
    })();
</script>
@endsection
