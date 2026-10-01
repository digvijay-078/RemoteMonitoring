@extends('admin.layouts.app')

@section('title', 'CCTV Master Control Room - Live Video Wall')
@section('container_class', 'max-w-[1920px]')

@section('content')
<div class="space-y-6" id="control-room-root">
    <!-- Top Command & Control Header -->
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 bg-slate-900/90 border border-slate-800 p-5 rounded-2xl backdrop-blur-xl shadow-2xl relative overflow-hidden">
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-start sm:items-center gap-4 relative z-10">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-rose-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 flex-shrink-0">
                <svg class="w-6 h-6 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                        Security Control Room &bull; Master Video Wall
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/30">
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                            LIVE SURVEILLANCE
                        </span>
                    </h1>
                </div>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span>Centralized real-time monitoring across all desktop workstations</span>
                    <span class="text-slate-600">&bull;</span>
                    <span class="font-mono text-indigo-300" id="live-wall-clock">--:--:-- IST</span>
                    <span class="text-slate-600">&bull;</span>
                    <span class="text-emerald-400 font-semibold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> 30 FPS Low-Latency MJPEG
                    </span>
                </p>
            </div>
        </div>

        <!-- Video Wall Controls & Layout Switcher -->
        <div class="flex flex-wrap items-center gap-2 sm:gap-3 relative z-10">
            <!-- Audio Indicator -->
            <div id="active-audio-chip" class="hidden items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-950/80 border border-emerald-500/40 text-emerald-300 text-xs font-mono shadow-lg">
                <div class="flex items-end gap-0.5 h-3">
                    <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.6s_infinite]"></span>
                    <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.4s_infinite]"></span>
                    <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.8s_infinite]"></span>
                </div>
                <span>Listening: <strong id="active-audio-device-name" class="text-white">None</strong></span>
                <button onclick="stopAllAudio()" class="ml-1 text-slate-400 hover:text-white p-0.5" title="Mute Audio">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Master Mute Button -->
            <button onclick="stopAllAudio()" id="btn-master-mute" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors flex items-center gap-1.5" title="Mute all desktop feeds">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
                Mute All
            </button>

            <!-- Layout Selector Buttons -->
            <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800 shadow-inner">
                <button onclick="changeLayout('auto')" id="layout-auto" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-600 transition-colors" title="Auto-fit Grid">
                    Auto Wall
                </button>
                <button onclick="changeLayout('quad')" id="layout-quad" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-colors" title="2x2 Quad View (4 Cameras)">
                    2x2 Quad
                </button>
                <button onclick="changeLayout('wall')" id="layout-wall" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-colors" title="3x3 Matrix (9 Cameras)">
                    3x3 Matrix
                </button>
                <button onclick="changeLayout('single')" id="layout-single" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-colors" title="Single Focused View">
                    1x1 Focus
                </button>
            </div>

            <!-- Refresh Feeds -->
            <button onclick="reloadAllStreams()" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors" title="Reconnect / Reload All Streams">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>

            <!-- Download Standalone EXE -->
            <a href="/downloads/RemoteMonitor-Setup.exe" download class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition-colors flex items-center gap-1.5" title="Download Standalone 1-Click Desktop Setup (.EXE)">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Desktop Setup (.EXE)</span>
            </a>
            <a href="/downloads/RemoteMonitor-1Click-Setup.bat" download class="px-3 py-2 rounded-xl bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 hover:text-white text-xs font-semibold border border-amber-500/30 transition-colors flex items-center gap-1.5" title="Windows 11 Direct Launcher - Never blocked by Smart App Control">
                <span>⚡ 1-Click Launcher (.BAT)</span>
            </a>

            <!-- Master Fullscreen Video Wall -->
            <button onclick="toggleVideoWallFullscreen()" class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-bold shadow-lg shadow-indigo-500/20 border border-indigo-400/30 transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0 0l-5-5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
                Master Fullscreen ⛶
            </button>
        </div>
    </div>

    <!-- Active Telemetry Stat Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-slate-900/60 border border-slate-800/80 p-3.5 rounded-xl flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 font-mono text-sm font-bold">
                🖥️
            </div>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Total Desktops</div>
                <div class="text-lg font-extrabold text-white">{{ $totalDesktops }} Registered</div>
            </div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800/80 p-3.5 rounded-xl flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-mono text-sm font-bold">
                🟢
            </div>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Online & Ready</div>
                <div class="text-lg font-extrabold text-emerald-400" id="stat-online-count">{{ $onlineDesktops }} Active</div>
            </div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800/80 p-3.5 rounded-xl flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 font-mono text-sm font-bold">
                📡
            </div>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Broadcasting</div>
                <div class="text-lg font-extrabold text-rose-400" id="stat-stream-count">{{ $streamingDesktops }} Streaming</div>
            </div>
        </div>
        <div class="bg-slate-900/60 border border-slate-800/80 p-3.5 rounded-xl flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-400 font-mono text-sm font-bold">
                ⚡
            </div>
            <div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Frame Rate</div>
                <div class="text-lg font-extrabold text-white">30 FPS Sync</div>
            </div>
        </div>
    </div>

    <!-- Video Wall Grid Container -->
    @if ($desktops->isEmpty())
        <div class="bg-slate-900/40 border border-dashed border-slate-800 rounded-2xl p-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-800 flex items-center justify-center mx-auto text-slate-400 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white mb-1">No Desktop Devices Registered</h3>
            <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Enroll your office workstations in the Devices section to view them in the Master Control Room.</p>
            <a href="{{ route('admin.devices.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold shadow-lg shadow-indigo-600/30 transition-all">
                + Register Desktop Agent
            </a>
        </div>
    @else
        <div id="video-wall-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-2 gap-5 transition-all duration-300">
            @foreach ($desktops as $index => $desktop)
                <div id="cam-card-{{ $desktop->uuid }}" 
                     class="cam-card bg-slate-900/90 border border-slate-800 hover:border-indigo-500/50 rounded-2xl overflow-hidden shadow-2xl flex flex-col transition-all duration-200 group relative"
                     data-uuid="{{ $desktop->uuid }}"
                     data-name="{{ $desktop->name }}"
                     data-identifier="{{ $desktop->device_identifier }}">

                    <!-- Camera Card Header -->
                    <div class="bg-slate-950/90 px-4 py-2.5 border-b border-slate-800/80 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="font-mono text-indigo-400 font-bold px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20">
                                CAM {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <div class="flex flex-col">
                                <span class="font-extrabold text-white text-sm tracking-tight flex items-center gap-1.5">
                                    {{ $desktop->name }}
                                    <span class="text-[11px] font-mono font-medium text-slate-400">({{ $desktop->device_identifier }})</span>
                                </span>
                                <span class="text-[10px] text-slate-400">{{ $desktop->location ?? 'Main Office' }} &bull; {{ $desktop->ip_address ?? '127.0.0.1' }}</span>
                            </div>
                        </div>

                        <!-- Card Status Badge -->
                        <div class="flex items-center gap-2">
                            <span id="badge-status-{{ $desktop->uuid }}" class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border {{ $desktop->status === 'online' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-800 text-slate-400 border-slate-700' }} flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ $desktop->status === 'online' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span>
                                {{ $desktop->status === 'online' ? 'LIVE 30 FPS' : 'OFFLINE' }}
                            </span>
                            
                            <!-- Quick Action Buttons -->
                            <button onclick="toggleAudio('{{ $desktop->uuid }}', '{{ addslashes($desktop->name) }}')" 
                                    id="btn-audio-{{ $desktop->uuid }}" 
                                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors" 
                                    title="Click to Listen Live Audio">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                </svg>
                            </button>

                            <button onclick="openTheaterMode('{{ $desktop->uuid }}', '{{ addslashes($desktop->name) }}', '{{ $desktop->device_identifier }}')" 
                                    class="p-1.5 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white border border-slate-700 transition-colors" 
                                    title="Expand to Fullscreen Theater View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0 0l-5-5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Screen Viewport Display -->
                    <div class="relative aspect-video bg-black flex items-center justify-center overflow-hidden select-none cursor-pointer"
                         onclick="handleScreenClick('{{ $desktop->uuid }}', '{{ addslashes($desktop->name) }}', '{{ $desktop->device_identifier }}')">
                        
                        <!-- CCTV Video Overlay Timecode -->
                        <div class="absolute top-2.5 left-2.5 z-20 pointer-events-none flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 font-mono text-[10px] text-rose-400 bg-slate-950/80 px-2 py-0.5 rounded border border-rose-500/30 backdrop-blur-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-ping"></span>
                                REC &bull; <span class="cctv-timecode">--:--:--</span>
                            </span>
                        </div>

                        <!-- Audio Active Indicator Banner -->
                        <div id="audio-banner-{{ $desktop->uuid }}" class="hidden absolute bottom-3 left-3 z-20 pointer-events-none items-center gap-2 px-2.5 py-1 rounded-lg bg-emerald-950/90 border border-emerald-500/50 text-emerald-300 text-xs font-mono shadow-xl backdrop-blur-sm">
                            <div class="flex items-end gap-0.5 h-3">
                                <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.6s_infinite]"></span>
                                <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.4s_infinite]"></span>
                                <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.8s_infinite]"></span>
                            </div>
                            <span>AUDIO LIVE</span>
                        </div>

                        <!-- Hover Expand Hint Button -->
                        <div class="absolute inset-0 z-10 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                            <div class="px-4 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-bold border border-slate-700 shadow-2xl flex items-center gap-2 transform translate-y-2 group-hover:translate-y-0 transition-transform">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Click to Zoom &bull; Double-Click Theater
                            </div>
                        </div>

                        <!-- Live Continuous MJPEG Stream (Low Latency 30 FPS) -->
                        <img id="stream-img-{{ $desktop->uuid }}"
                             src="/stream/{{ $desktop->uuid }}/live" 
                             alt="{{ $desktop->name }} Live Feed"
                             class="w-full h-full object-contain"
                             onerror="onStreamError('{{ $desktop->uuid }}')"
                             onload="onStreamLoaded('{{ $desktop->uuid }}')" />

                        <!-- Offline / Reconnecting Radar Graphic Fallback -->
                        <div id="stream-fallback-{{ $desktop->uuid }}" class="hidden absolute inset-0 z-0 bg-slate-950 flex flex-col items-center justify-center p-6 text-center">
                            <div class="relative w-14 h-14 mb-3 flex items-center justify-center">
                                <div class="absolute inset-0 rounded-full border-2 border-indigo-500/20 animate-ping"></div>
                                <div class="w-10 h-10 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500">
                                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                            </div>
                            <span class="text-xs font-semibold text-slate-300">Connecting to Stream Engine...</span>
                            <span class="text-[11px] text-slate-500 mt-1">Endpoint: /stream/{{ substr($desktop->uuid, 0, 8) }}.../live</span>
                            <button onclick="retryStream('{{ $desktop->uuid }}')" class="mt-3 px-3 py-1 rounded-lg bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-300 text-xs font-semibold border border-indigo-500/40">
                                Force Reconnect
                            </button>
                        </div>
                    </div>

                    <!-- Card Footer Telemetry -->
                    <div class="bg-slate-950 px-4 py-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <div class="flex items-center gap-3">
                            <span>RES: <strong class="text-slate-200">{{ $desktop->screen_resolution ?? '1920x1080' }}</strong></span>
                            <span>&bull;</span>
                            <span>FPS: <strong class="text-emerald-400" id="fps-val-{{ $desktop->uuid }}">{{ $desktop->fps ?? 30 }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500">Tablets: {{ $desktop->assignedTablets->count() }}</span>
                            <a href="{{ route('admin.devices.show', $desktop->id) }}" class="text-indigo-400 hover:text-indigo-300 font-sans font-medium hover:underline">
                                Configure &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- CCTV Fullscreen Theater Mode Modal -->
<div id="theater-modal" class="fixed inset-0 z-50 bg-black/95 backdrop-blur-md hidden flex-col">
    <!-- Theater Topbar -->
    <div class="bg-slate-950/90 border-b border-slate-800 px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <span class="px-2.5 py-1 rounded-md bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                THEATER FOCUS
            </span>
            <div>
                <h2 id="theater-device-name" class="text-base font-extrabold text-white">Device Name</h2>
                <div class="text-xs text-slate-400 font-mono" id="theater-device-meta">IDENTIFIER &bull; IP &bull; 1920x1080</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Theater Audio Toggle -->
            <button onclick="toggleTheaterAudio()" id="btn-theater-audio" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
                <span id="theater-audio-label">Listen Audio</span>
            </button>

            <!-- Native Fullscreen Toggle -->
            <button onclick="toggleElementFullscreen(document.getElementById('theater-modal'))" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700" title="Toggle Fullscreen">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0 0l-5-5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
            </button>

            <!-- Close Modal -->
            <button onclick="closeTheaterMode()" class="px-3 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-bold transition-colors flex items-center gap-1">
                Close (Esc) ✕
            </button>
        </div>
    </div>

    <!-- Theater Screen Canvas -->
    <div class="flex-1 relative bg-black flex items-center justify-center p-2 overflow-hidden">
        <img id="theater-stream-img" src="" alt="Theater Live Feed" class="max-w-full max-h-full object-contain" />
    </div>
</div>

<!-- Hidden Persistent Audio Element for Real-Time Sound -->
<audio id="global-cctv-audio" preload="none" style="display: none;"></audio>

<script>
    // Global State
    let activeAudioUuid = null;
    let currentTheaterUuid = null;
    let clickTimeout = null;
    const audioElement = document.getElementById('global-cctv-audio');

    // 1. Live Wall Clock & CCTV Timecodes
    function updateClocks() {
        const now = new Date();
        const istString = now.toLocaleTimeString('en-US', { timeZone: 'Asia/Kolkata', hour12: false });
        const ms = String(now.getMilliseconds()).padStart(3, '0').slice(0, 2);
        
        const clockEl = document.getElementById('live-wall-clock');
        if (clockEl) clockEl.innerText = `${istString} IST`;

        document.querySelectorAll('.cctv-timecode').forEach(el => {
            el.innerText = `${istString}.${ms}`;
        });
    }
    setInterval(updateClocks, 200);
    updateClocks();

    // 2. Grid Layout Switcher
    function changeLayout(layout) {
        const grid = document.getElementById('video-wall-grid');
        if (!grid) return;

        // Reset button states
        ['auto', 'quad', 'wall', 'single'].forEach(l => {
            const btn = document.getElementById(`layout-${l}`);
            if (btn) {
                if (l === layout) {
                    btn.classList.add('bg-indigo-600', 'text-white');
                    btn.classList.remove('text-slate-400');
                } else {
                    btn.classList.remove('bg-indigo-600', 'text-white');
                    btn.classList.add('text-slate-400');
                }
            }
        });

        // Reset grid classes
        grid.className = "grid gap-5 transition-all duration-300";

        if (layout === 'auto') {
            grid.classList.add('grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-2', '2xl:grid-cols-2');
        } else if (layout === 'quad') {
            grid.classList.add('grid-cols-1', 'md:grid-cols-2');
        } else if (layout === 'wall') {
            grid.classList.add('grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3');
        } else if (layout === 'single') {
            grid.classList.add('grid-cols-1');
        }
    }

    // 3. Audio Streaming Engine (Click-to-Listen CCTV Solo Focus)
    function toggleAudio(uuid, deviceName) {
        if (activeAudioUuid === uuid) {
            stopAllAudio();
            return;
        }

        stopAllAudio();

        activeAudioUuid = uuid;
        // In Python stream manager, /stream/<uuid>/audio provides continuous WAV stereo stream
        audioElement.src = `/stream/${uuid}/audio?t=${Date.now()}`;
        audioElement.volume = 1.0;
        audioElement.play().catch(e => {
            console.warn("[Audio] Autoplay blocked or interrupted:", e.message);
        });

        // Update UI
        const btn = document.getElementById(`btn-audio-${uuid}`);
        if (btn) {
            btn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-500', 'shadow-lg', 'shadow-emerald-600/30');
            btn.classList.remove('bg-slate-800', 'text-slate-300');
        }

        const banner = document.getElementById(`audio-banner-${uuid}`);
        if (banner) banner.classList.remove('hidden');

        const chip = document.getElementById('active-audio-chip');
        const nameEl = document.getElementById('active-audio-device-name');
        if (chip && nameEl) {
            nameEl.innerText = deviceName || uuid.slice(0, 8);
            chip.classList.remove('hidden');
            chip.classList.add('flex');
        }
    }

    function stopAllAudio() {
        if (audioElement) {
            audioElement.pause();
            audioElement.src = "";
        }

        if (activeAudioUuid) {
            const btn = document.getElementById(`btn-audio-${activeAudioUuid}`);
            if (btn) {
                btn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-500', 'shadow-lg', 'shadow-emerald-600/30');
                btn.classList.add('bg-slate-800', 'text-slate-300');
            }
            const banner = document.getElementById(`audio-banner-${activeAudioUuid}`);
            if (banner) banner.classList.add('hidden');
        }

        activeAudioUuid = null;
        const chip = document.getElementById('active-audio-chip');
        if (chip) chip.classList.add('hidden');
    }

    // 4. Double-Click or Click handling for theater mode
    function handleScreenClick(uuid, name, identifier) {
        if (clickTimeout) {
            clearTimeout(clickTimeout);
            clickTimeout = null;
            // Double Click -> Open Theater Mode
            openTheaterMode(uuid, name, identifier);
        } else {
            clickTimeout = setTimeout(() => {
                clickTimeout = null;
                // Single Click -> Toggle Audio Solo Focus
                toggleAudio(uuid, name);
            }, 250);
        }
    }

    // 5. Theater Mode
    function openTheaterMode(uuid, name, identifier) {
        currentTheaterUuid = uuid;
        const modal = document.getElementById('theater-modal');
        const img = document.getElementById('theater-stream-img');
        const nameEl = document.getElementById('theater-device-name');
        const metaEl = document.getElementById('theater-device-meta');

        if (nameEl) nameEl.innerText = name;
        if (metaEl) metaEl.innerText = `${identifier} &bull; /stream/${uuid}/live &bull; 30 FPS Active`;
        if (img) img.src = `/stream/${uuid}/live?theater=1&t=${Date.now()}`;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Update Theater audio button state
        updateTheaterAudioButton();
    }

    function closeTheaterMode() {
        const modal = document.getElementById('theater-modal');
        const img = document.getElementById('theater-stream-img');
        if (img) img.src = "";
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentTheaterUuid = null;
    }

    function toggleTheaterAudio() {
        if (!currentTheaterUuid) return;
        const card = document.getElementById(`cam-card-${currentTheaterUuid}`);
        const name = card ? card.getAttribute('data-name') : 'Workstation';
        toggleAudio(currentTheaterUuid, name);
        updateTheaterAudioButton();
    }

    function updateTheaterAudioButton() {
        const label = document.getElementById('theater-audio-label');
        const btn = document.getElementById('btn-theater-audio');
        if (!label || !btn) return;

        if (activeAudioUuid === currentTheaterUuid) {
            label.innerText = "Mute Audio";
            btn.classList.add('bg-emerald-600', 'text-white');
            btn.classList.remove('bg-slate-800', 'text-slate-200');
        } else {
            label.innerText = "Listen Audio";
            btn.classList.remove('bg-emerald-600', 'text-white');
            btn.classList.add('bg-slate-800', 'text-slate-200');
        }
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (!document.getElementById('theater-modal').classList.contains('hidden')) {
                closeTheaterMode();
            }
        }
        if (e.key === 'm' || e.key === 'M') {
            stopAllAudio();
        }
    });

    // 6. Master Fullscreen Video Wall
    function toggleVideoWallFullscreen() {
        toggleElementFullscreen(document.documentElement);
    }

    function toggleElementFullscreen(el) {
        if (!document.fullscreenElement) {
            el.requestFullscreen().catch(err => {
                console.warn("[Fullscreen Error]:", err.message);
            });
        } else {
            document.exitFullscreen();
        }
    }

    // 7. Reconnect & Stream Error Handling
    function onStreamError(uuid) {
        const img = document.getElementById(`stream-img-${uuid}`);
        const fallback = document.getElementById(`stream-fallback-${uuid}`);
        if (img) img.classList.add('hidden');
        if (fallback) fallback.classList.remove('hidden');

        // Auto retry after 3 seconds
        setTimeout(() => retryStream(uuid), 3000);
    }

    function onStreamLoaded(uuid) {
        const img = document.getElementById(`stream-img-${uuid}`);
        const fallback = document.getElementById(`stream-fallback-${uuid}`);
        if (img) img.classList.remove('hidden');
        if (fallback) fallback.classList.add('hidden');
    }

    function retryStream(uuid) {
        const img = document.getElementById(`stream-img-${uuid}`);
        if (img) {
            img.src = `/stream/${uuid}/live?retry=${Date.now()}`;
        }
    }

    function reloadAllStreams() {
        document.querySelectorAll('.cam-card').forEach(card => {
            const uuid = card.getAttribute('data-uuid');
            if (uuid) retryStream(uuid);
        });
    }

    // 8. Dynamic Device Heartbeat Telemetry (Refreshes without page reload)
    function buildCamCardHtml(device, index) {
        const camNum = String(index + 1).padStart(2, '0');
        const isOnline = device.status === 'online';
        return `
            <div id="cam-card-${device.uuid}" 
                 class="cam-card bg-slate-900/90 border border-slate-800 hover:border-indigo-500/50 rounded-2xl overflow-hidden shadow-2xl flex flex-col transition-all duration-200 group relative"
                 data-uuid="${device.uuid}"
                 data-name="${device.name}"
                 data-identifier="${device.identifier}">

                <!-- Camera Card Header -->
                <div class="bg-slate-950/90 px-4 py-2.5 border-b border-slate-800/80 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="font-mono text-indigo-400 font-bold px-2 py-0.5 rounded bg-indigo-500/10 border border-indigo-500/20">
                            CAM ${camNum}
                        </span>
                        <div class="flex flex-col">
                            <span class="font-extrabold text-white text-sm tracking-tight flex items-center gap-1.5">
                                ${device.name}
                                <span class="text-[11px] font-mono font-medium text-slate-400">(${device.identifier})</span>
                            </span>
                            <span class="text-[10px] text-slate-400">${device.location || 'Main Office'} &bull; ${device.ip_address || '127.0.0.1'}</span>
                        </div>
                    </div>

                    <!-- Card Status Badge -->
                    <div class="flex items-center gap-2">
                        <span id="badge-status-${device.uuid}" class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border ${isOnline ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-800 text-slate-400 border-slate-700'} flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full ${isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'}"></span>
                            ${isOnline ? 'LIVE 30 FPS' : 'OFFLINE'}
                        </span>
                        
                        <!-- Quick Action Buttons -->
                        <button onclick="toggleAudio('${device.uuid}', '${device.name.replace(/'/g, "\\'")}')" 
                                id="btn-audio-${device.uuid}" 
                                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition-colors" 
                                title="Click to Listen Live Audio">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                        </button>

                        <button onclick="openTheaterMode('${device.uuid}', '${device.name.replace(/'/g, "\\'")}', '${device.identifier}')" 
                                class="p-1.5 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white border border-slate-700 transition-colors" 
                                title="Expand to Fullscreen Theater View">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0 0l-5-5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Screen Viewport Display -->
                <div class="relative aspect-video bg-black flex items-center justify-center overflow-hidden select-none cursor-pointer"
                     onclick="handleScreenClick('${device.uuid}', '${device.name.replace(/'/g, "\\'")}', '${device.identifier}')">
                    
                    <!-- CCTV Video Overlay Timecode -->
                    <div class="absolute top-2.5 left-2.5 z-20 pointer-events-none flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 font-mono text-[10px] text-rose-400 bg-slate-950/80 px-2 py-0.5 rounded border border-rose-500/30 backdrop-blur-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-ping"></span>
                            REC &bull; <span class="cctv-timecode">--:--:--</span>
                        </span>
                    </div>

                    <!-- Audio Active Indicator Banner -->
                    <div id="audio-banner-${device.uuid}" class="hidden absolute bottom-3 left-3 z-20 pointer-events-none items-center gap-2 px-2.5 py-1 rounded-lg bg-emerald-950/90 border border-emerald-500/50 text-emerald-300 text-xs font-mono shadow-xl backdrop-blur-sm">
                        <div class="flex items-end gap-0.5 h-3">
                            <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.6s_infinite]"></span>
                            <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.4s_infinite]"></span>
                            <span class="w-1 bg-emerald-400 rounded-sm animate-[bounce_0.8s_infinite]"></span>
                        </div>
                        <span>AUDIO LIVE</span>
                    </div>

                    <!-- Live Continuous MJPEG Stream -->
                    <img id="stream-img-${device.uuid}"
                         src="/stream/${device.uuid}/live" 
                         alt="${device.name} Live Feed"
                         class="${isOnline ? '' : 'hidden '}w-full h-full object-contain"
                         onerror="onStreamError('${device.uuid}')"
                         onload="onStreamLoaded('${device.uuid}')" />

                    <!-- Offline Graphic Fallback -->
                    <div id="stream-fallback-${device.uuid}" class="${isOnline ? 'hidden ' : ''}absolute inset-0 z-0 bg-slate-950 flex flex-col items-center justify-center p-6 text-center">
                        <div class="relative w-14 h-14 mb-3 flex items-center justify-center">
                            <div class="absolute inset-0 rounded-full border-2 border-indigo-500/20 animate-ping"></div>
                            <div class="w-10 h-10 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500">
                                <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-slate-400 font-mono tracking-wider">AWAITING WORKSTATION FEED</span>
                        <span class="text-[11px] text-slate-500 mt-1">Start Desktop Agent on target PC</span>
                    </div>
                </div>

                <!-- Footer Stats Bar -->
                <div class="bg-slate-950/90 px-4 py-2 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center gap-1">
                            <span class="text-indigo-400">FPS:</span>
                            <span id="fps-val-${device.uuid}" class="font-bold text-white">${device.fps || 30}</span>
                        </span>
                        <span class="text-slate-600">&bull;</span>
                        <span class="text-slate-500">${device.screen_resolution || '1920x1080'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500">Last seen: ${device.last_seen_diff || 'Just now'}</span>
                    </div>
                </div>
            </div>
        `;
    }

    async function refreshTelemetry() {
        try {
            const res = await fetch('{{ route("admin.control_room.devices") }}');
            if (!res.ok) return;
            const data = await res.json();
            if (data.success && Array.isArray(data.devices)) {
                let onlineCount = 0;
                let streamCount = 0;

                let gridContainer = document.getElementById('video-wall-grid');
                const rootContainer = document.getElementById('control-room-root');

                // If grid container was absent (initially 0 devices), dynamically create grid
                if (!gridContainer && data.devices.length > 0 && rootContainer) {
                    const existingEmpty = rootContainer.querySelector('.border-dashed');
                    if (existingEmpty) existingEmpty.remove();

                    gridContainer = document.createElement('div');
                    gridContainer.id = 'video-wall-grid';
                    gridContainer.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-2 gap-5 transition-all duration-300';
                    rootContainer.appendChild(gridContainer);
                }

                data.devices.forEach((device, index) => {
                    if (device.status === 'online') onlineCount++;
                    if (device.stream_status === 'streaming') streamCount++;

                    let card = document.getElementById(`cam-card-${device.uuid}`);
                    if (!card && gridContainer) {
                        // Dynamically append new camera card
                        gridContainer.insertAdjacentHTML('beforeend', buildCamCardHtml(device, index));
                    }

                    // Update FPS
                    const fpsEl = document.getElementById(`fps-val-${device.uuid}`);
                    if (fpsEl) fpsEl.innerText = device.fps || 30;

                    // Update Badge and Stream Card Display
                    const badge = document.getElementById(`badge-status-${device.uuid}`);
                    const img = document.getElementById(`stream-img-${device.uuid}`);
                    const fallback = document.getElementById(`stream-fallback-${device.uuid}`);

                    if (badge) {
                        if (device.status === 'online') {
                            badge.className = "px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border bg-emerald-500/20 text-emerald-400 border-emerald-500/30 flex items-center gap-1.5";
                            badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> LIVE 30 FPS`;
                            if (img && img.classList.contains('hidden')) {
                                img.classList.remove('hidden');
                                img.src = `/stream/${device.uuid}/live?t=${Date.now()}`;
                            }
                            if (fallback) fallback.classList.add('hidden');
                        } else {
                            badge.className = "px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border bg-slate-800 text-slate-400 border-slate-700 flex items-center gap-1.5";
                            badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> OFFLINE`;

                            // Hide stream video and show offline card immediately
                            if (img) img.classList.add('hidden');
                            if (fallback) fallback.classList.remove('hidden');
                        }
                    }
                });

                const statOnline = document.getElementById('stat-online-count');
                const statStream = document.getElementById('stat-stream-count');
                if (statOnline) statOnline.innerText = `${onlineCount} Active`;
                if (statStream) statStream.innerText = `${streamCount} Streaming`;
            }
        } catch (err) {
            console.warn("[Telemetry Poll]:", err);
        }
    }

    // React immediately to Reverb WebSocket presence events
    window.addEventListener('device-status-changed', () => {
        refreshTelemetry();
    });

    // High-frequency telemetry polling (every 1.5 seconds)
    refreshTelemetry();
    setInterval(refreshTelemetry, 1500);
</script>
@endsection
