@extends('admin.layouts.app')

@section('title', 'Devices Inventory')

@section('content')
<div class="space-y-6">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Devices Inventory</h1>
            <p class="text-sm text-slate-400 mt-1">Manage Desktops and Tablets registered across the fleet.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="openTabletLinksModal()" class="px-4 py-2.5 rounded-xl bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 hover:text-white text-sm font-bold border border-cyan-500/30 transition-all flex items-center gap-2 shadow-md active:scale-95">
                <span>📱 All Tablet Links</span>
            </button>
            <a href="/downloads/RemoteMonitor-Setup.exe" download class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-sm font-semibold border border-slate-700 transition-all flex items-center gap-2 shadow-md">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download Setup (.EXE)</span>
            </a>
            <a href="/downloads/RemoteMonitor-1Click-Setup.bat" download class="px-4 py-2.5 rounded-xl bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 hover:text-white text-sm font-semibold border border-amber-500/30 transition-all flex items-center gap-2 shadow-md" title="Windows 11 Direct Launcher - Never blocked by Smart App Control">
                <span>⚡ 1-Click Launcher (.BAT)</span>
            </a>
            <button onclick="document.getElementById('createDeviceModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2 active:scale-95 self-start sm:self-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>Register Device</span>
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-200 shadow-xl space-y-1">
            <div class="font-bold text-sm text-rose-300 flex items-center gap-2">
                <span>⚠️</span> Failed to register device:
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 text-rose-200/90">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('direct_link'))
        <div class="p-4 rounded-2xl bg-cyan-950/60 border border-cyan-500/40 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📱</span>
                <div>
                    <div class="font-bold text-sm text-cyan-300">1-Click Tablet Link Ready for {{ session('tablet_identifier') }}</div>
                    <div class="text-xs text-slate-300 font-mono mt-0.5">{{ session('direct_link') }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <button type="button" onclick="copyDirectTabletLink('{{ session('tablet_identifier') }}')"
                    class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold transition-all shadow-md active:scale-95">
                    📋 Copy 1-Click Link
                </button>
                <a href="{{ session('direct_link') }}" target="_blank"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700 transition-colors">
                    🔗 Open
                </a>
            </div>
        </div>
    @endif

    <!-- Filter & Search Toolbar -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 backdrop-blur-sm">
        <form method="GET" action="{{ route('admin.devices.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by Device ID, Name, or Location..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <svg class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="w-full sm:w-48">
                <select name="status" onchange="this.form.submit()" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="pending_pair" {{ request('status') === 'pending_pair' ? 'selected' : '' }}>Pending Pair</option>
                    <option value="disabled" {{ request('status') === 'disabled' ? 'selected' : '' }}>Disabled</option>
                </select>
            </div>
            @if (request('search') || request('status'))
                <a href="{{ route('admin.devices.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold text-center">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Devices Table -->
    <div id="devicesTableCard" class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-sm">
        @if ($devices->isEmpty())
            <div id="emptyDevicesNotice" class="py-16 text-center text-slate-500 text-sm">
                No matching devices found. Click <strong>"Register Device"</strong> to add one.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-5">Device ID</th>
                            <th class="py-3.5 px-4">Type</th>
                            <th class="py-3.5 px-4">Display Name</th>
                            <th class="py-3.5 px-4">Location</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4">Last Seen</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="devicesTableBody" class="divide-y divide-slate-800/60">
                        @foreach ($devices as $device)
                            <tr class="hover:bg-slate-800/30 transition-colors" data-device-id="{{ $device->id }}" data-device-uuid="{{ $device->uuid }}" data-device-identifier="{{ $device->device_identifier }}">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-white font-mono text-base">{{ $device->device_identifier }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ substr($device->uuid, 0, 8) }}...</div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider {{ $device->isDesktop() ? 'bg-violet-500/20 text-violet-300 border border-violet-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' }}">
                                        {{ $device->device_type }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 font-semibold text-slate-200">
                                    {{ $device->name }}
                                </td>
                                <td class="py-4 px-4 text-slate-300">
                                    {{ $device->location }}
                                </td>
                                <td class="py-4 px-4 device-status-badge">
                                    @if ($device->status === 'online')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
                                        </span>
                                    @elseif ($device->status === 'warning')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Warning
                                        </span>
                                    @elseif ($device->status === 'pending_pair')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Pending Pair
                                        </span>
                                    @elseif ($device->status === 'disabled')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Disabled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-slate-400 text-xs device-last-seen">
                                    {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="py-4 px-5 text-right space-x-1.5">
                                    @if ($device->isTablet())
                                        <button type="button" onclick="copyDirectTabletLink('{{ $device->device_identifier }}')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 hover:text-white text-xs font-semibold border border-cyan-500/30 transition-all shadow-sm active:scale-95" title="Copy Fixed 1-Click Link for Client (No Code Needed)">
                                            📋 Copy Link
                                        </button>
                                        <a href="/t/{{ $device->device_identifier }}" target="_blank"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors" title="Open Tablet Stream in New Tab">
                                            🔗 Open
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.devices.show', $device->id) }}"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700 transition-colors">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($devices->hasPages())
                <div class="p-4 border-t border-slate-800">
                    {{ $devices->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<!-- Modal: Register Device -->
<div id="createDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-5">
            <h3 class="text-lg font-bold text-white">Register New Device</h3>
            <button onclick="document.getElementById('createDeviceModal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.devices.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Device Type</label>
                <select id="modalDeviceType" name="device_type" onchange="toggleDeviceTypeFields(this.value)" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="tablet">Tablet (Remote Viewer Client - 1-Click Link)</option>
                    <option value="desktop">Desktop (Screen & Audio Streaming Source)</option>
                </select>
            </div>

            <div id="modalDesktopAssignGroup" class="p-3.5 rounded-xl bg-cyan-950/40 border border-cyan-500/30 space-y-2">
                <label class="block text-xs font-bold text-cyan-300 uppercase tracking-wider">Assign to Desktop Feed (Optional)</label>
                <select name="assigned_desktop_id" class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-cyan-500/40 text-white text-xs font-mono focus:ring-2 focus:ring-cyan-500">
                    <option value="">-- Choose Desktop Feed --</option>
                    @foreach ($availableDesktops as $desk)
                        <option value="{{ $desk->id }}">{{ $desk->device_identifier }} - {{ $desk->name }} ({{ $desk->location }})</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-cyan-200/80">
                    ⚡ The tablet will immediately stream this desktop on 1-click launch. You can switch feeds anytime from Admin.
                </p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Device Identifier / Vanity Link (Optional)</label>
                <input type="text" name="device_identifier" placeholder="e.g. TAB-003 or CLIENT-A (auto-generated if blank)"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Friendly Name</label>
                <input type="text" name="name" required placeholder="e.g. Client Inspection Tablet 1"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Physical Location</label>
                <input type="text" name="location" required placeholder="e.g. Pune Inspection Bay 1"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-4 flex justify-end space-x-3 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('createDeviceModal').classList.add('hidden')"
                    class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold shadow-lg shadow-indigo-600/20">
                    Register Device &amp; Generate Link
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleDeviceTypeFields(type) {
        const group = document.getElementById('modalDesktopAssignGroup');
        if (group) {
            group.style.display = (type === 'tablet') ? 'block' : 'none';
        }
    }

    function buildStatusBadge(status) {
        if (status === 'online') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Online
            </span>`;
        } else if (status === 'warning') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Warning
            </span>`;
        } else if (status === 'pending_pair') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Pending Pair
            </span>`;
        } else if (status === 'disabled') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Disabled
            </span>`;
        } else {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
            </span>`;
        }
    }

    function buildDeviceRowHtml(d) {
        const isDesktop = (d.device_type === 'desktop');
        const typeBadge = isDesktop 
            ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-violet-500/20 text-violet-300 border border-violet-500/30">desktop</span>`
            : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">tablet</span>`;

        let actionHtml = '';
        if (!isDesktop) {
            actionHtml = `
                <button type="button" onclick="copyDirectTabletLink('${d.identifier}')"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 hover:text-white text-xs font-semibold border border-cyan-500/30 transition-all shadow-sm active:scale-95" title="Copy Fixed 1-Click Link for Client">
                    📋 Copy Link
                </button>
                <a href="/t/${d.identifier}" target="_blank"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors" title="Open Tablet Stream in New Tab">
                    🔗 Open
                </a>
            `;
        }
        actionHtml += `
            <a href="/admin/devices/${d.id}"
                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700 transition-colors">
                Details
            </a>
        `;

        const shortUuid = (d.uuid || '').substring(0, 8);

        return `
            <tr class="hover:bg-slate-800/30 transition-all duration-300 bg-indigo-950/20" data-device-id="${d.id}" data-device-uuid="${d.uuid}" data-device-identifier="${d.identifier}">
                <td class="py-4 px-5">
                    <div class="font-bold text-white font-mono text-base">${d.identifier}</div>
                    <div class="text-[11px] text-slate-500 font-mono">${shortUuid}...</div>
                </td>
                <td class="py-4 px-4">
                    ${typeBadge}
                </td>
                <td class="py-4 px-4 font-semibold text-slate-200">
                    ${d.name || ''}
                </td>
                <td class="py-4 px-4 text-slate-300">
                    ${d.location || 'Fleet'}
                </td>
                <td class="py-4 px-4 device-status-badge">
                    ${buildStatusBadge(d.status)}
                </td>
                <td class="py-4 px-4 text-slate-400 text-xs device-last-seen">
                    ${d.last_seen_human || 'Just now'}
                </td>
                <td class="py-4 px-5 text-right space-x-1.5">
                    ${actionHtml}
                </td>
            </tr>
        `;
    }

    // High-frequency Realtime Presence Status Sync (Zero Refresh + Live Auto-Discovery)
    (function () {
        let isSyncing = false;

        async function syncDeviceStatuses() {
            if (isSyncing) return;
            isSyncing = true;
            try {
                const res = await fetch('{{ route("admin.devices.realtime_status") }}');
                if (!res.ok) return;
                const data = await res.json();
                if (data.success && Array.isArray(data.devices)) {
                    const tableContainer = document.getElementById('devicesTableCard');
                    let tbody = document.getElementById('devicesTableBody');

                    // If table was initially empty and devices now exist
                    if ((!tbody || document.getElementById('emptyDevicesNotice')) && data.devices.length > 0 && tableContainer) {
                        tableContainer.innerHTML = `
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-800 bg-slate-950/40 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                            <th class="py-3.5 px-5">Device ID</th>
                                            <th class="py-3.5 px-4">Type</th>
                                            <th class="py-3.5 px-4">Display Name</th>
                                            <th class="py-3.5 px-4">Location</th>
                                            <th class="py-3.5 px-4">Status</th>
                                            <th class="py-3.5 px-4">Last Seen</th>
                                            <th class="py-3.5 px-5 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="devicesTableBody" class="divide-y divide-slate-800/60">
                                    </tbody>
                                </table>
                            </div>
                        `;
                        tbody = document.getElementById('devicesTableBody');
                    }

                    if (tbody) {
                        data.devices.forEach(d => {
                            let row = document.querySelector(`tr[data-device-id="${d.id}"]`) || 
                                      document.querySelector(`tr[data-device-uuid="${d.uuid}"]`) ||
                                      document.querySelector(`tr[data-device-identifier="${d.identifier}"]`);
                            
                            if (!row) {
                                // Dynamically insert newly registered / auto-enrolled device!
                                tbody.insertAdjacentHTML('afterbegin', buildDeviceRowHtml(d));
                            } else {
                                // Update existing row
                                const badgeContainer = row.querySelector('.device-status-badge');
                                const lastSeenEl = row.querySelector('.device-last-seen');

                                if (badgeContainer) {
                                    badgeContainer.innerHTML = buildStatusBadge(d.status);
                                }
                                if (lastSeenEl && d.last_seen_human) {
                                    lastSeenEl.innerText = d.last_seen_human;
                                }
                            }
                        });
                    }
                }
            } catch (e) {
                // Ignore transient network errors
            } finally {
                isSyncing = false;
            }
        }

        // Poll every 1.5 seconds for instant zero-refresh detection
        setInterval(syncDeviceStatuses, 1500);
    })();
</script>
@endsection
