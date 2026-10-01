<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ControlRoomController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\MappingController;
use App\Http\Controllers\Admin\PairingController;
use App\Http\Controllers\Admin\SessionLogController;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', function () {
    return redirect('/admin');
});

// Tablet PWA entrypoint
Route::get('/tablet', function () {
    return view('tablet.index');
})->name('tablet.index');

Route::get('/t/{device}', function ($device) {
    return view('tablet.index', ['directDevice' => $device]);
})->name('tablet.direct');

// Direct EXE Download Route
Route::get('/download', function () {
    $path = public_path('downloads/RemoteMonitor-Setup.exe');
    if (file_exists($path)) {
        return response()->download($path, 'RemoteMonitor-Setup.exe', [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
        ]);
    }
    abort(404, 'Setup EXE not found');
})->name('download.agent');

// Login route alias
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/admin', function () {
    return redirect('/admin/overview');
});

// Admin Authentication & Console
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('overview');
        Route::get('/overview', [DashboardController::class, 'index'])->name('overview.index');

        // Live CCTV Master Control Room
        Route::get('control-room', [ControlRoomController::class, 'index'])->name('control_room.index');
        Route::get('control-room/devices', [ControlRoomController::class, 'devices'])->name('control_room.devices');

        // Devices CRUD & Pairing
        Route::get('devices-realtime-status', [DeviceController::class, 'realtimeStatus'])->name('devices.realtime_status');
        Route::get('tablet-links', [DeviceController::class, 'tabletLinks'])->name('tablet_links');
        Route::resource('devices', DeviceController::class);
        Route::post('devices/{device}/toggle', [DeviceController::class, 'toggleStatus'])->name('devices.toggle');
        Route::post('devices/{device}/assign-desktop', [DeviceController::class, 'assignDesktop'])->name('devices.assign_desktop');
        Route::post('devices/{device}/pair-ticket', [PairingController::class, 'generate'])->name('devices.pair_ticket');

        // Desktop ↔ Tablet Mappings
        Route::resource('mappings', MappingController::class)->only(['index', 'store', 'destroy']);

        // Session Logs & Excel Export
        Route::get('sessions', [SessionLogController::class, 'index'])->name('sessions.index');
        Route::get('sessions/realtime', [SessionLogController::class, 'realtimeData'])->name('sessions.realtime');
        Route::get('sessions/export', [SessionLogController::class, 'export'])->name('sessions.export');
    });
});


