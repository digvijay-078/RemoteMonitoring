@extends('admin.layouts.app')

@section('title', 'Session Logs & Reports')

@section('container_class', 'w-full max-w-[1700px]')

@section('content')
<div class="space-y-6">
    <!-- Header with Export Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                <span>Session Logs & Reports</span>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">IST (UTC+5:30)</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">Real-time tablet connection logs, exact start/end timestamps, session duration, and audit reports.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.sessions.export', request()->query()) }}" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold shadow-lg shadow-emerald-600/20 transition-all flex items-center gap-2 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <span>Download Excel / CSV</span>
            </a>
            <a href="{{ route('admin.sessions.index') }}" class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold border border-slate-700 transition-colors flex items-center gap-2" title="Reset Filters">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            </a>
        </div>
    </div>

    <!-- Stat KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-emerald-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Live Active Sessions</span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            </div>
            <div class="text-3xl font-extrabold text-emerald-400"><span id="kpi-active-sessions">{{ $activeSessionsCount }}</span></div>
            <div class="text-xs text-slate-500 mt-1">Tablets actively monitoring right now</div>
        </div>

        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-indigo-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Sessions Today</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </div>
            <div class="text-3xl font-extrabold text-indigo-400"><span id="kpi-today-sessions">{{ $todaySessionsCount }}</span></div>
            <div class="text-xs text-slate-500 mt-1">Recorded on {{ now()->format('d M Y') }}</div>
        </div>

        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
            <div class="flex items-center justify-between text-cyan-400 mb-2">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Recorded Sessions</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <div class="text-3xl font-extrabold text-white"><span id="kpi-total-sessions">{{ $totalSessionsCount }}</span></div>
            <div class="text-xs text-slate-500 mt-1">Historic audit database entries</div>
        </div>
    </div>

    <!-- Filter Bar Form -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 backdrop-blur-sm">
        <form method="GET" action="{{ route('admin.sessions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-slate-400 mb-1">Search ID or Device</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, ID or code..." class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- Tablet Filter -->
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Tablet Viewer</label>
                <select name="tablet_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    <option value="">All Tablets</option>
                    @foreach($tablets as $t)
                        <option value="{{ $t->id }}" {{ request('tablet_id') == $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->device_identifier }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Desktop Filter -->
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Desktop PC</label>
                <select name="desktop_id" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    <option value="">All Desktops</option>
                    @foreach($desktops as $d)
                        <option value="{{ $d->id }}" {{ request('desktop_id') == $d->id ? 'selected' : '' }}>{{ $d->name }} ({{ $d->device_identifier }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                <select name="status" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="connected" {{ request('status') == 'connected' ? 'selected' : '' }}>Connected / Active</option>
                    <option value="terminated" {{ request('status') == 'terminated' ? 'selected' : '' }}>Completed / Terminated</option>
                    <option value="initiating" {{ request('status') == 'initiating' ? 'selected' : '' }}>Initiating</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <!-- Filter Button -->
            <div>
                <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition-colors flex items-center justify-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                    <span>Apply Filter</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Sessions Table -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-sm shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider bg-slate-950/40">
                        <th class="px-5 py-3.5">Session ID</th>
                        <th class="px-5 py-3.5">Tablet Viewer</th>
                        <th class="px-5 py-3.5">Monitored Desktop</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5">Connect Time (IST)</th>
                        <th class="px-5 py-3.5">Disconnect Time (IST)</th>
                        <th class="px-5 py-3.5">Duration</th>
                        <th class="px-5 py-3.5">Reason</th>
                    </tr>
                </thead>
                <tbody id="sessions-tbody" class="divide-y divide-slate-800/60 text-xs">
                    @forelse($sessions as $session)
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <!-- Session ID -->
                            <td class="px-5 py-4 font-mono text-slate-400 text-[11px]">
                                <span title="{{ $session->session_id }}">{{ Str::limit($session->session_id, 12, '...') }}</span>
                            </td>

                            <!-- Tablet -->
                            <td class="px-5 py-4">
                                @if($session->tablet)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-md bg-cyan-500/10 flex items-center justify-center text-cyan-400 text-xs">📱</div>
                                        <div>
                                            <div class="font-semibold text-white">{{ $session->tablet->name }}</div>
                                            <div class="text-[10px] font-mono text-cyan-400/80">{{ $session->tablet->device_identifier }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-500">Deleted Tablet</span>
                                @endif
                            </td>

                            <!-- Desktop -->
                            <td class="px-5 py-4">
                                @if($session->desktop)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-md bg-violet-500/10 flex items-center justify-center text-violet-400 text-xs">🖥️</div>
                                        <div>
                                            <div class="font-semibold text-white">{{ $session->desktop->name }}</div>
                                            <div class="text-[10px] font-mono text-violet-400/80">{{ $session->desktop->device_identifier }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-500">Deleted Desktop</span>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-4">
                                @if($session->status === 'connected' && !$session->ended_at)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        LIVE ACTIVE
                                    </span>
                                @elseif($session->status === 'terminated')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-800 text-slate-300 border border-slate-700">
                                        Completed
                                    </span>
                                @elseif($session->status === 'initiating')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        Connecting
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        {{ ucfirst($session->status) }}
                                    </span>
                                @endif
                            </td>

                            <!-- Connect Time (IST) -->
                            <td class="px-5 py-4 font-mono text-slate-300">
                                {{ $session->connected_at_ist ?: ($session->started_at_ist ?: '—') }}
                            </td>

                            <!-- Disconnect Time (IST) -->
                            <td class="px-5 py-4 font-mono text-slate-400">
                                @if($session->ended_at_ist)
                                    {{ $session->ended_at_ist }}
                                @elseif($session->status === 'connected')
                                    <span class="text-emerald-400 font-sans font-medium">Still Connected</span>
                                @else
                                    —
                                @endif
                            </td>

                            <!-- Duration -->
                            <td class="px-5 py-4 font-semibold text-white session-duration-cell">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    @if($session->status === 'connected' && !$session->ended_at)
                                        <span class="live-duration-timer text-emerald-400 font-mono font-bold" data-started-at="{{ $session->started_at?->timestamp }}">
                                            {{ $session->duration_human }}
                                        </span>
                                    @else
                                        <span>{{ $session->duration_human }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Reason -->
                            <td class="px-5 py-4 text-slate-400 text-[11px]">
                                {{ $session->termination_reason ?: 'Normal Session' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                <div class="text-3xl mb-2">📋</div>
                                <div class="text-sm font-medium text-slate-400">No session logs match your criteria</div>
                                <div class="text-xs text-slate-600 mt-1">Sessions are automatically recorded when tablets connect to desktop streams.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sessions->hasPages())
            <div class="px-5 py-3 border-t border-slate-800/80 bg-slate-950/20">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const queryParams = new URLSearchParams(window.location.search);
        if (queryParams.get('page') && parseInt(queryParams.get('page')) > 1) {
            return;
        }

        async function pollSessions() {
            try {
                const res = await fetch('/admin/sessions/realtime?' + queryParams.toString());
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success) return;

                if (data.kpis) {
                    const elAct = document.getElementById('kpi-active-sessions');
                    if (elAct) elAct.textContent = data.kpis.active_count;
                    const elTod = document.getElementById('kpi-today-sessions');
                    if (elTod) elTod.textContent = data.kpis.today_count;
                    const elTot = document.getElementById('kpi-total-sessions');
                    if (elTot) elTot.textContent = data.kpis.total_count;
                }

                const tbody = document.getElementById('sessions-tbody');
                if (tbody && Array.isArray(data.sessions) && data.sessions.length > 0) {
                    tbody.innerHTML = data.sessions.map(s => `
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-4 font-mono text-slate-400 text-[11px]">
                                <span title="${s.session_id}">${s.session_id_short}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-md bg-cyan-500/10 flex items-center justify-center text-cyan-400 text-xs">📱</div>
                                    <div>
                                        <div class="font-semibold text-white">${s.tablet_name}</div>
                                        <div class="text-[10px] font-mono text-cyan-400/80">${s.tablet_identifier}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-md bg-violet-500/10 flex items-center justify-center text-violet-400 text-xs">🖥️</div>
                                    <div>
                                        <div class="font-semibold text-white">${s.desktop_name}</div>
                                        <div class="text-[10px] font-mono text-violet-400/80">${s.desktop_identifier}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                ${s.is_active ? `
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        LIVE ACTIVE
                                    </span>
                                ` : (s.status === 'terminated' ? `
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-800 text-slate-300 border border-slate-700">
                                        Completed
                                    </span>
                                ` : `
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        ${s.status}
                                    </span>
                                `)}
                            </td>
                            <td class="px-5 py-4 font-mono text-slate-300">
                                ${s.connect_time_ist}
                            </td>
                            <td class="px-5 py-4 font-mono text-slate-400">
                                ${s.is_active ? `<span class="text-emerald-400 font-sans font-medium">Still Connected</span>` : s.disconnect_time_ist}
                            </td>
                            <td class="px-5 py-4 font-semibold text-white session-duration-cell">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    ${s.is_active && s.started_at_ts ? `
                                        <span class="live-duration-timer text-emerald-400 font-mono font-bold" data-started-at="${s.started_at_ts}">${s.duration_human}</span>
                                    ` : `<span>${s.duration_human}</span>`}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-400 text-[11px]">
                                ${s.termination_reason}
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (e) {}
        }

        setInterval(pollSessions, 4000);
    })();
</script>
@endpush
