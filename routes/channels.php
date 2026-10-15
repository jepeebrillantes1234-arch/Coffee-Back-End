<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('staff.updates', fn ($user) => in_array($user->role, ['admin','staff'], true));
Broadcast::channel('users.{id}', fn ($user, $id) => (int)$user->id === (int)$id);

// Menu changes expose only the event kind and record ID; menu data remains public API data.
