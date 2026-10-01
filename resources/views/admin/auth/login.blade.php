<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In - RemoteMonitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-gradient-to-b from-slate-900 to-slate-950">
    <div class="w-full max-w-md">
        <!-- Logo Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 items-center justify-center shadow-xl shadow-indigo-500/25 mb-4 ring-4 ring-indigo-500/20">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">RemoteMonitor</h1>
            <p class="text-sm text-slate-400 mt-1">Centralized Administrative Control Center</p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-950/50 border border-rose-500/40 text-rose-300 text-xs font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('admin.login.post', [], false) }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Administrator Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', 'admin@remotemonitor.local') }}" required autofocus
                        class="w-full px-4 py-3 rounded-xl bg-slate-950/70 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Password</label>
                    <input type="password" id="password" name="password" value="admin123456" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-950/70 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                        <span>Remember session</span>
                    </label>
                </div>

                <button type="submit"
                    class="w-full py-3.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm tracking-wide shadow-lg shadow-indigo-600/30 transition-all active:scale-[0.99] flex items-center justify-center space-x-2">
                    <span>Authenticate & Enter</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
            </form>
        </div>

        <!-- Helper hint -->
        <div class="mt-6 p-4 rounded-xl bg-slate-900/50 border border-slate-800 text-center text-xs text-slate-400">
            <span class="font-semibold text-slate-300">Phase 1 Dev Credentials:</span><br>
            <code class="text-indigo-400 font-mono">admin@remotemonitor.local</code> &bull; <code class="text-indigo-400 font-mono">admin123456</code>
        </div>
    </div>
</body>
</html>
