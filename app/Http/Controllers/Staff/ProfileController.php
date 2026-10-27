<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('staff.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);
        Activity::log('update', 'staff', "Staff profile updated by {$user->name}.");

        return back()->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.confirmed' => 'The new password confirmation does not match.',
        ]);

        // The User model's hashed cast stores a one-way password hash.
        $user->password = $data['password'];
        $user->save();
        $request->session()->regenerate();

        // Never include password values or hashes in an activity log.
        Activity::log('password_changed', 'staff', "Staff password changed by {$user->name}.");

        return back()->with('success', 'Your password has been changed.');
    }

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
