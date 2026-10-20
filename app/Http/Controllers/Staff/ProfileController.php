<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $previousAvatar = $user->avatar;
        $user->update(['avatar' => $request->file('avatar')->store('staff-avatars', 'public')]);

        if ($previousAvatar && str_starts_with($previousAvatar, 'staff-avatars/')) {
            Storage::disk('public')->delete($previousAvatar);
        }

        Activity::log('update', 'staff', "Updated profile photo: {$user->name}");

        return back()->with('success', 'Profile photo updated successfully.');
    }
}