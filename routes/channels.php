<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin Realtime Monitoring Channel
Broadcast::channel('admin.devices', function ($user) {
    return (bool) ($user && method_exists($user, 'isAdmin') && $user->isAdmin());
});

