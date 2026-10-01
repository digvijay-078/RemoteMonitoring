<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Console') - RemoteMonitor</title>
    <!-- Tailwind CSS CDN for lightweight standalone rendering -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        code, .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full flex flex-col bg-slate-950">
    <!-- Top Navigation Bar -->
    <nav class="border-b border-slate-800/80 bg-slate-900/95 backdrop-blur-md sticky top-0 z-40">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3">
                <!-- Left Brand -->
                <div class="flex items-center gap-3 flex-shrink-0">
                    <a href="{{ route('admin.overview') }}" class="flex items-center gap-2.5 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm sm:text-base font-extrabold tracking-tight text-white flex items-center gap-1.5 whitespace-nowrap">
                                RemoteMonitor
                                <span class="text-[9px] uppercase font-bold tracking-widest px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">Admin</span>
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap hidden sm:block">Fleet Control Center</span>
                        </div>
                    </a>
                </div>

                <!-- Center Desktop Nav Links (Visible on xl+, neatly organized) -->
                <div class="hidden xl:flex items-center gap-1 bg-slate-950/40 p-1 rounded-xl border border-slate-800/60">
                    <a href="{{ route('admin.overview') }}" class="whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.overview*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Overview
                    </a>
                    <a href="{{ route('admin.control_room.index') }}" class="whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.control_room*') ? 'bg-gradient-to-r from-rose-600 to-amber-600 text-white shadow-md' : 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10' }} flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2 flex-shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                        </span>
                        <span>Live Control</span>
                    </a>
                    <a href="{{ route('admin.devices.index') }}" class="whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.devices*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Devices
                    </a>
                    <a href="{{ route('admin.mappings.index') }}" class="whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.mappings*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Authorizations
                    </a>
                    <a href="{{ route('admin.sessions.index') }}" class="whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ request()->routeIs('admin.sessions*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                        Session Logs
                    </a>
                </div>

                <!-- Right Action & Profile -->
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                    <!-- Tablet Links Action Button -->
                    <button type="button" onclick="openTabletLinksModal()" class="px-3 py-1.5 rounded-xl bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 hover:text-white border border-cyan-500/30 text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm active:scale-95 flex-shrink-0">
                        <span>📱 Tablet Links</span>
                    </button>

                    <!-- Tablet PWA Link (Hidden on very small screens) -->
                    <a href="{{ route('tablet.index') }}" target="_blank" class="hidden sm:inline-flex whitespace-nowrap px-2.5 py-1.5 rounded-xl text-xs font-semibold text-emerald-400 hover:text-white hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors items-center gap-1.5 flex-shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Tablet PWA</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    </a>

                    <!-- User Profile Badge & Logout -->
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-800 flex-shrink-0">
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div class="hidden 2xl:flex flex-col text-left leading-none max-w-[130px]">
                                <span class="text-xs font-bold text-white whitespace-nowrap truncate">{{ Auth::user()->name }}</span>
                                <span class="text-[10px] text-slate-400 uppercase mt-0.5 font-semibold">{{ Auth::user()->role }}</span>
                            </div>
                        </div>

                        <form action="{{ route('admin.logout') }}" method="POST" class="inline flex-shrink-0">
                            @csrf
                            <button type="submit" class="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors" title="Log Out">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>

                        <!-- Mobile Menu Hamburger Button -->
                        <button type="button" onclick="toggleMobileNavMenu()" class="xl:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors flex-shrink-0" title="Toggle Navigation Menu">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile / Tablet Collapsible Menu -->
            <div id="mobileNavMenu" class="hidden xl:hidden border-t border-slate-800/80 py-3 space-y-1.5 transition-all">
                <a href="{{ route('admin.overview') }}" class="block px-3 py-2 rounded-xl text-xs font-bold {{ request()->routeIs('admin.overview*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Overview
                </a>
                <a href="{{ route('admin.control_room.index') }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold {{ request()->routeIs('admin.control_room*') ? 'bg-rose-600 text-white' : 'text-amber-400 hover:bg-slate-800' }}">
                    <span>Live Control Room</span>
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                </a>
                <a href="{{ route('admin.devices.index') }}" class="block px-3 py-2 rounded-xl text-xs font-bold {{ request()->routeIs('admin.devices*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Devices Inventory
                </a>
                <a href="{{ route('admin.mappings.index') }}" class="block px-3 py-2 rounded-xl text-xs font-bold {{ request()->routeIs('admin.mappings*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Authorizations &amp; Mappings
                </a>
                <a href="{{ route('admin.sessions.index') }}" class="block px-3 py-2 rounded-xl text-xs font-bold {{ request()->routeIs('admin.sessions*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Session Logs &amp; Reports
                </a>
                <a href="{{ route('tablet.index') }}" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold text-emerald-400 hover:bg-slate-800">
                    <span>Open Tablet PWA</span>
                    <span>↗</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-1 @yield('container_class', 'max-w-7xl') w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Flash Messages -->
        @if (session('status'))
            <div class="mb-6 p-4 rounded-xl bg-indigo-950/60 border border-indigo-500/40 text-indigo-200 text-sm flex items-center justify-between shadow-lg">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-200 text-sm shadow-lg">
                <div class="font-bold flex items-center gap-2 mb-1">
                    <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Action Failed
                </div>
                <ul class="list-disc list-inside space-y-1 text-rose-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-slate-900 bg-slate-950 py-4 text-center text-xs text-slate-500">
        RemoteMonitor Phase 2 &bull; PHP 8.2 + Laravel 11 + Reverb WebSocket &bull; Realtime Fleet Monitoring
    </footer>

    <!-- Realtime WebSocket Admin Presence Integration -->
    <script src="/admin-assets/js/pusher.min.js"></script>
    <script>
        (function () {
            const isHttps = window.location.protocol === 'https:';
            const reverbKey = '{{ config("reverb.apps.apps.0.key") ?: env("REVERB_APP_KEY") }}';
            const wsHost = window.location.hostname;
            const wsPort = isHttps ? 443 : (window.location.port === '8088' ? 8088 : (parseInt('{{ env("REVERB_PORT", 8080) }}') || 8080));

            if (window.Pusher && reverbKey) {
                try {
                    const pusher = new Pusher(reverbKey, {
                        wsHost: wsHost,
                        wsPort: wsPort,
                        wssPort: wsPort,
                        forceTLS: isHttps,
                        enabledTransports: ['ws', 'wss'],
                        authEndpoint: '/broadcasting/auth',
                        auth: {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            }
                        }
                    });

                    const onStatusUpdate = function (data) {
                        window.dispatchEvent(new CustomEvent('device-status-changed', { detail: data }));
                        updateDeviceStatusUI(data);
                    };

                    const privChannel = pusher.subscribe('private-admin.devices');
                    privChannel.bind('DeviceStatusChanged', onStatusUpdate);

                    const pubChannel = pusher.subscribe('admin.devices.public');
                    pubChannel.bind('DeviceStatusChanged', onStatusUpdate);

                    pubChannel.bind('pusher:subscription_succeeded', function() {
                        console.log('[AdminRealtime] Connected to admin.devices.public via Reverb WebSocket');
                    });
                } catch (err) {
                    console.warn('[AdminRealtime] Realtime connection init error:', err);
                }
            }

            function updateDeviceStatusUI(data) {
                const devId = data.id || data.device_id || data.deviceId;
                const devUuid = data.uuid;
                // Update table rows in overview & devices list
                const matchingRows = document.querySelectorAll(`[data-device-id="${devId}"], [data-device-uuid="${devUuid}"]`);
                matchingRows.forEach(row => {
                    const statusBadge = row.querySelector('.device-status-badge');
                    if (statusBadge) {
                        statusBadge.innerHTML = renderBadgeHTML(data.status);
                    }
                    const streamBadge = row.querySelector('.device-stream-status-badge');
                    if (streamBadge) {
                        streamBadge.innerHTML = renderStreamBadgeHTML(data.stream_status, data.status);
                    }
                    const lastSeen = row.querySelector('.device-last-seen');
                    if (lastSeen && (data.last_seen_human || data.lastSeenHuman)) {
                        lastSeen.textContent = data.last_seen_human || data.lastSeenHuman;
                    }
                    const viewersCount = row.querySelector('.device-viewers-count');
                    if (viewersCount && typeof data.active_viewers_count !== 'undefined') {
                        const count = data.active_viewers_count;
                        viewersCount.className = `font-mono font-bold text-xs px-2.5 py-1 rounded-lg ${count > 0 ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-400'}`;
                        viewersCount.textContent = `${count} Viewer${count === 1 ? '' : 's'}`;
                    }
                    const viewingStatus = row.querySelector('.device-viewing-status');
                    if (viewingStatus) {
                        if (data.currently_viewing && data.currently_viewing.desktop_identifier) {
                            viewingStatus.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                ${data.currently_viewing.desktop_identifier}
                                <span class="text-emerald-300 font-semibold font-mono text-[11px] ml-1">(<span class="live-duration-timer" data-started-at="${data.currently_viewing.started_at_ts}">00:00:00</span>)</span>
                            </span>`;
                        } else {
                            viewingStatus.innerHTML = `<span class="text-xs text-slate-500 italic">Not Streaming</span>`;
                        }
                    }
                });

                // Update single device show view if present
                if (devId) {
                    const showStatus = document.getElementById(`device-show-status-${devId}`);
                    if (showStatus) {
                        showStatus.innerHTML = renderBadgeHTML(data.status);
                    }
                    const showLastSeen = document.getElementById(`device-show-last-seen-${devId}`);
                    if (showLastSeen && (data.last_seen_human || data.lastSeenHuman)) {
                        showLastSeen.textContent = data.last_seen_human || data.lastSeenHuman;
                    }
                }
            }

            function renderBadgeHTML(status) {
                if (status === 'online') {
                    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Online
                    </span>`;
                } else if (status === 'warning') {
                    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Warning (Stale)
                    </span>`;
                } else if (status === 'disabled') {
                    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Disabled
                    </span>`;
                } else if (status === 'pending_pair') {
                    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span> Pending Pair
                    </span>`;
                } else {
                    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
                    </span>`;
                }
            }

            function renderStreamBadgeHTML(streamStatus, status) {
                if (streamStatus === 'streaming') {
                    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                        🎥 STREAMING
                    </span>`;
                } else if (status === 'online') {
                    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-400 bg-slate-800">
                        Idle (Standby)
                    </span>`;
                } else {
                    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-slate-500 bg-slate-900 border border-slate-800">
                        Offline
                    </span>`;
                }
            }

            // Real-time 1-second live duration ticker for all active streams & tablets
            function updateAllLiveDurations() {
                const nowTs = Math.floor(Date.now() / 1000);
                document.querySelectorAll('.live-duration-timer').forEach(el => {
                    const startTs = parseInt(el.getAttribute('data-started-at'));
                    if (!startTs || isNaN(startTs)) return;
                    const elapsed = Math.max(0, nowTs - startTs);
                    const hrs = String(Math.floor(elapsed / 3600)).padStart(2, '0');
                    const mins = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
                    const secs = String(elapsed % 60).padStart(2, '0');
                    el.textContent = `${hrs}:${mins}:${secs}`;
                });
            }
            setInterval(updateAllLiveDurations, 1000);

            // Responsive Mobile Menu Toggler
            window.toggleMobileNavMenu = function () {
                const menu = document.getElementById('mobileNavMenu');
                if (menu) menu.classList.toggle('hidden');
            };

            // Continuous 2.5-second polling fallback ensuring instant UI consistency
            let isPollingPresence = false;
            async function pollPresence() {
                if (isPollingPresence) return;
                isPollingPresence = true;
                try {
                    const res = await fetch('/admin/devices-realtime-status');
                    if (!res.ok) return;
                    const json = await res.json();
                    if (json.success && Array.isArray(json.devices)) {
                        json.devices.forEach(d => updateDeviceStatusUI(d));

                        if (json.stats) {
                            const statDesks = document.getElementById('stat-online-desktops');
                            if (statDesks) statDesks.textContent = json.stats.online_desktops;
                            const statStreamDesks = document.getElementById('stat-streaming-desktops');
                            if (statStreamDesks) statStreamDesks.textContent = json.stats.streaming_desktops;
                            const statTabs = document.getElementById('stat-online-tablets');
                            if (statTabs) statTabs.textContent = json.stats.online_tablets;
                            const statActiveSessions = document.getElementById('stat-active-sessions');
                            if (statActiveSessions) statActiveSessions.textContent = json.stats.active_sessions_count;
                        }

                        // Update Active WebRTC Streams Table dynamically without page reload
                        const activeContainer = document.getElementById('active-sessions-container');
                        const activeTbody = document.getElementById('active-sessions-tbody');
                        const activeBadge = document.getElementById('active-sessions-badge');

                        if (activeContainer && activeTbody) {
                            if (json.active_sessions && json.active_sessions.length > 0) {
                                activeContainer.classList.remove('hidden');
                                if (activeBadge) activeBadge.textContent = `${json.active_sessions.length} ACTIVE`;

                                activeTbody.innerHTML = json.active_sessions.map(s => `
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3.5">
                                            <div class="font-bold text-cyan-400 font-mono">${s.tablet_identifier}</div>
                                            <div class="text-slate-400 text-[11px] font-sans">${s.tablet_name}</div>
                                        </td>
                                        <td class="py-3.5">
                                            <div class="font-bold text-violet-400 font-mono">${s.desktop_identifier}</div>
                                            <div class="text-slate-400 text-[11px] font-sans">${s.desktop_name}</div>
                                        </td>
                                        <td class="py-3.5">
                                            ${s.status === 'connected' ? `
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-sans">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> LIVE
                                                </span>
                                            ` : `
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20 font-sans">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Connecting...
                                                </span>
                                            `}
                                        </td>
                                        <td class="py-3.5 text-slate-300 font-sans text-xs">
                                            ${s.started_at_ist}
                                        </td>
                                        <td class="py-3.5 font-sans text-xs font-semibold">
                                            <span class="live-duration-timer text-emerald-400 font-mono font-bold" data-started-at="${s.started_at_ts}">${s.duration_formatted}</span>
                                        </td>
                                    </tr>
                                `).join('');
                            } else {
                                activeContainer.classList.add('hidden');
                                activeTbody.innerHTML = '';
                            }
                        }
                    }
                } catch (e) {
                } finally {
                    isPollingPresence = false;
                }
            }

            pollPresence();
            setInterval(pollPresence, 1200);

            // Floating Toast Notification
            window.showAdminToast = function (title, message) {
                const toast = document.getElementById('adminToast');
                const tTitle = document.getElementById('toastTitle');
                const tMsg = document.getElementById('toastMessage');
                if (!toast || !tTitle || !tMsg) return;
                tTitle.textContent = title;
                tMsg.textContent = message;
                toast.classList.remove('hidden', 'translate-y-4', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
                clearTimeout(window._adminToastTimeout);
                window._adminToastTimeout = setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('translate-y-4', 'opacity-0');
                    setTimeout(() => toast.classList.add('hidden'), 300);
                }, 4000);
            };

            // Global 1-Click Direct Link Copier for Tablet Viewers
            window.copyDirectTabletLink = function (identifier) {
                const url = `${window.location.origin}/t/${identifier}`;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(() => {
                        window.showAdminToast('📋 1-Click Link Copied!', url);
                    }).catch(() => {
                        prompt('Copy this 1-click tablet link:', url);
                    });
                } else {
                    prompt('Copy this 1-click tablet link:', url);
                }
            };

            // Global Tablet Links Modal
            window.openTabletLinksModal = async function () {
                const modal = document.getElementById('tabletLinksModal');
                const loading = document.getElementById('tabletLinksLoading');
                const content = document.getElementById('tabletLinksContent');
                if (!modal) return;
                modal.classList.remove('hidden');
                loading.classList.remove('hidden');
                content.classList.add('hidden');
                content.innerHTML = '';

                try {
                    const res = await fetch('/admin/tablet-links');
                    const data = await res.json();
                    loading.classList.add('hidden');
                    content.classList.remove('hidden');

                    if (!data.success || !data.tablets || data.tablets.length === 0) {
                        content.innerHTML = `<div class="py-8 text-center text-slate-500 text-xs">No tablets registered yet. Go to Devices to register one.</div>`;
                        return;
                    }

                    data.tablets.forEach(t => {
                        const card = document.createElement('div');
                        card.className = 'p-4 rounded-2xl bg-slate-950/70 border border-slate-800 hover:border-cyan-500/40 transition-all space-y-3 shadow-inner';
                        const deskText = t.assigned_desktop
                            ? `<span class="text-violet-300 font-bold font-mono">${t.assigned_desktop.identifier}</span> <span class="text-slate-400">(${t.assigned_desktop.name})</span>`
                            : `<span class="text-slate-500 italic">None assigned</span>`;

                        const statusBadge = t.status === 'online'
                            ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">🟢 Online</span>`
                            : `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">⚪ Offline</span>`;

                        card.innerHTML = `
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="px-2.5 py-1 rounded-lg bg-cyan-500/20 text-cyan-300 font-mono font-extrabold text-xs border border-cyan-500/30">${t.identifier}</span>
                                    <div>
                                        <div class="font-bold text-white text-sm flex items-center gap-2">
                                            <span>${t.name}</span>
                                            ${statusBadge}
                                        </div>
                                        <div class="text-xs text-slate-400 mt-0.5">${t.location} &bull; Target feed: ${deskText}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <a href="/admin/mappings" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 bg-indigo-500/10 px-2.5 py-1.5 rounded-lg border border-indigo-500/20 transition-colors">
                                        🔄 Switch Feed
                                    </a>
                                    <a href="/admin/devices/${t.id}" class="text-xs font-semibold text-slate-400 hover:text-white bg-slate-800 px-2.5 py-1.5 rounded-lg border border-slate-700 transition-colors">
                                        Details &rarr;
                                    </a>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                                <input type="text" readonly value="${t.direct_url}" onclick="this.select()"
                                    class="flex-1 px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700 text-cyan-300 font-mono text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500 shadow-inner">
                                <button type="button" onclick="copyDirectTabletLink('${t.identifier}')"
                                    class="px-3.5 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold transition-all shadow-md active:scale-95 flex items-center justify-center gap-1 flex-shrink-0">
                                    📋 Copy Link
                                </button>
                                <a href="${t.direct_url}" target="_blank"
                                    class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition-colors flex items-center justify-center gap-1 flex-shrink-0">
                                    🔗 Open
                                </a>
                                <button type="button" onclick="toggleCardQr('${t.identifier}', '${t.direct_url}')"
                                    class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors flex items-center justify-center gap-1 flex-shrink-0" title="Toggle QR Code">
                                    📱 QR
                                </button>
                            </div>

                            <div id="qr-container-${t.identifier}" class="hidden p-4 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center gap-4">
                                <canvas id="qr-canvas-${t.identifier}" class="w-24 h-24 rounded bg-white p-1 shadow-md"></canvas>
                                <div class="text-xs text-slate-300 space-y-1">
                                    <div class="font-bold text-white">Scan to Watch Live</div>
                                    <div class="text-[11px] text-slate-400">Scan with tablet camera to stream live feed instantly without pairing code.</div>
                                </div>
                            </div>
                        `;
                        content.appendChild(card);
                    });
                } catch (e) {
                    loading.classList.add('hidden');
                    content.classList.remove('hidden');
                    content.innerHTML = `<div class="p-4 rounded-xl bg-rose-500/10 text-rose-400 text-xs">Error loading tablet links.</div>`;
                }
            };

            window.toggleCardQr = function (identifier, url) {
                const container = document.getElementById(`qr-container-${identifier}`);
                if (!container) return;
                if (container.classList.contains('hidden')) {
                    container.classList.remove('hidden');
                    const canvas = document.getElementById(`qr-canvas-${identifier}`);
                    if (canvas && typeof QRious !== 'undefined') {
                        new QRious({
                            element: canvas,
                            value: url,
                            size: 110,
                            level: 'M'
                        });
                    }
                } else {
                    container.classList.add('hidden');
                }
            };
        })();
    </script>

    <!-- Global Modal: Tablet Direct 1-Click Links -->
    <div id="tabletLinksModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-3xl w-full p-6 shadow-2xl relative">
            <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-5">
                <div class="flex items-center gap-2.5">
                    <span class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xl font-bold">📱</span>
                    <div>
                        <h3 class="text-lg font-bold text-white">1-Click Client Tablet Links</h3>
                        <p class="text-xs text-slate-400">Fixed permanent URLs. Client clicks once &rarr; streams immediately with NO code needed.</p>
                    </div>
                </div>
                <button onclick="document.getElementById('tabletLinksModal').classList.add('hidden')" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-lg transition-colors">&times;</button>
            </div>

            <div id="tabletLinksLoading" class="py-12 text-center text-slate-400 text-sm flex flex-col items-center gap-3">
                <div class="w-8 h-8 border-2 border-cyan-500 border-t-transparent rounded-full animate-spin"></div>
                <span>Fetching tablet fleet and links...</span>
            </div>

            <div id="tabletLinksContent" class="hidden space-y-4 max-h-[60vh] overflow-y-auto pr-1">
                <!-- Dynamic Tablet Cards injected here -->
            </div>

            <div class="pt-4 mt-5 border-t border-slate-800 flex items-center justify-between">
                <a href="{{ route('admin.devices.index') }}" class="text-xs font-semibold text-cyan-400 hover:text-cyan-300 flex items-center gap-1">
                    <span>➕ Manage Tablets in Devices Inventory &rarr;</span>
                </a>
                <button type="button" onclick="document.getElementById('tabletLinksModal').classList.add('hidden')"
                    class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Toast Notification -->
    <div id="adminToast" class="fixed bottom-6 right-6 z-50 hidden transform transition-all duration-300 ease-out translate-y-4 opacity-0">
        <div class="bg-slate-900 border border-emerald-500/50 text-white px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-3">
            <span class="text-lg">📋</span>
            <div>
                <div id="toastTitle" class="text-xs font-bold text-emerald-400">Link Copied!</div>
                <div id="toastMessage" class="text-[11px] text-slate-300 font-mono"></div>
            </div>
        </div>
    </div>

    <!-- QRious library loaded globally for QR codes -->
    <script src="{{ asset('admin-assets/js/qrious.min.js') }}"></script>

    @stack('scripts')
</body>
</html>
