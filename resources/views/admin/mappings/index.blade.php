@extends('admin.layouts.app')

@section('title', 'Desktop ↔ Tablet Authorizations')

@section('content')
<div class="space-y-6">
    <!-- Header with Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Desktop ↔ Tablet Authorizations</h1>
            <p class="text-sm text-slate-400 mt-1">Configure Many-to-Many access mappings allowing Tablets to stream Desktops over WebRTC.</p>
        </div>
        <button onclick="document.getElementById('createMappingModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2 active:scale-95 self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>Authorize New Mapping</span>
        </button>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg shadow-emerald-500/5">
            <div class="flex items-center gap-2">
                <span class="text-base">✅</span>
                <span>{{ session('success') }}</span>
            </div>
            @if (session('tablet_identifier'))
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button type="button" onclick="copyDirectTabletLink('{{ session('tablet_identifier') }}')"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600/30 hover:bg-emerald-600/40 text-emerald-200 hover:text-white border border-emerald-500/40 text-xs font-bold transition-all inline-flex items-center gap-1.5 shadow-sm active:scale-95">
                        📋 Copy Direct Tablet Link
                    </button>
                    <a href="/t/{{ session('tablet_identifier') }}" target="_blank"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 text-xs font-semibold inline-flex items-center gap-1 transition-colors">
                        🔗 Open Stream
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Mappings Table -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-sm">
        @if ($mappings->isEmpty())
            <div class="py-16 text-center text-slate-500 text-sm">
                No active Desktop ↔ Tablet authorizations found. Click <strong>"Authorize New Mapping"</strong> to pair a Tablet with a Desktop.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 px-5">Tablet (Viewer)</th>
                            <th class="py-3.5 px-4 text-center">Direction</th>
                            <th class="py-3.5 px-5">Desktop (Streaming Source)</th>
                            <th class="py-3.5 px-4">Authorized At</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach ($mappings as $mapping)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 font-mono text-xs font-bold">
                                            TAB
                                        </div>
                                        <div>
                                            <div class="font-bold text-white font-mono">{{ $mapping->tablet?->device_identifier }}</div>
                                            <div class="text-xs text-slate-400">{{ $mapping->tablet?->name }} ({{ $mapping->tablet?->location }})</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-slate-800 text-indigo-400 text-xs font-mono">
                                        WebRTC &larr;&rarr;
                                    </span>
                                </td>
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-400 font-mono text-xs font-bold">
                                            PC
                                        </div>
                                        <div>
                                            <div class="font-bold text-white font-mono">{{ $mapping->desktop?->device_identifier }}</div>
                                            <div class="text-xs text-slate-400">{{ $mapping->desktop?->name }} ({{ $mapping->desktop?->location }})</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-slate-400 text-xs">
                                    {{ $mapping->created_at ? $mapping->created_at->format('M d, Y H:i') : '-' }}
                                </td>
                                <td class="py-4 px-5 text-right space-x-1.5">
                                    <button type="button" onclick="copyDirectTabletLink('{{ $mapping->tablet?->device_identifier }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 hover:text-white text-xs font-semibold border border-cyan-500/30 transition-all shadow-sm active:scale-95" title="Copy 1-Click Link for Client">
                                        📋 Copy Link
                                    </button>
                                    <a href="/t/{{ $mapping->tablet?->device_identifier }}" target="_blank"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 transition-colors" title="Open Tablet Feed in New Tab">
                                        🔗 Open
                                    </a>
                                    <form action="{{ route('admin.mappings.destroy', $mapping->id) }}" method="POST" onsubmit="return confirm('Revoke WebRTC stream authorization between {{ $mapping->tablet?->device_identifier }} and {{ $mapping->desktop?->device_identifier }}? Active sessions will terminate immediately.')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-semibold border border-rose-500/30 transition-colors">
                                            Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="p-4 border-t border-slate-800">
                    {{ $mappings->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<!-- Modal: Authorize Mapping -->
<div id="createMappingModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-5">
            <h3 class="text-lg font-bold text-white">Authorize Desktop ↔ Tablet Access</h3>
            <button onclick="document.getElementById('createMappingModal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.mappings.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Select Tablet (Viewer Client)</label>
                <select name="tablet_id" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Choose Tablet --</option>
                    @foreach ($tablets as $t)
                        <option value="{{ $t->id }}">{{ $t->device_identifier }} - {{ $t->name }} ({{ $t->location }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Select Desktop (Screen & Audio Source)</label>
                <select name="desktop_id" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Choose Desktop --</option>
                    @foreach ($desktops as $d)
                        <option value="{{ $d->id }}">{{ $d->device_identifier }} - {{ $d->name }} ({{ $d->location }})</option>
                    @endforeach
                </select>
            </div>

            <div class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-xs text-indigo-300">
                <strong>Security Rule:</strong> Tablets cannot request WebRTC sessions with arbitrary Desktops unless an explicit mapping is saved here.
            </div>

            <div class="pt-4 flex justify-end space-x-3 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('createMappingModal').classList.add('hidden')"
                    class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold shadow-lg shadow-indigo-600/20">
                    Authorize Mapping
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
