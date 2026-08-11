<?php

namespace App\Http\Controllers;

use App\Helpers\StorageHelper;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('pages.profile-edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        foreach (['push_notifications_enabled', 'email_notifications_enabled', 'in_app_notifications_enabled'] as $key) {
            if (array_key_exists($key, $data)) {
                $user->$key = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            app(\App\Services\ImageDerivativeService::class)->delete($user->profile_photo);
        }

        $path = StorageHelper::userProfilePath($user->id);
        $storedPath = $request->file('profile_photo')->store($path, 'public');
        \App\Jobs\GenerateImageDerivatives::dispatch($storedPath);

        $user->update(['profile_photo' => $storedPath]);

        return Redirect::route('profile.edit')->with('status', 'Profile photo updated successfully.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            app(\App\Services\ImageDerivativeService::class)->delete($user->profile_photo);
            $user->update(['profile_photo' => null]);
        }

        return Redirect::route('profile.edit')->with('status', 'Profile photo removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Changing a password is how someone responds to a suspected compromise, so it has
        // to end the intruder's session too — otherwise the old one stays signed in and the
        // change accomplishes nothing. Keeps this browser's session alive.
        Auth::logoutOtherDevices($validated['password']);

        return Redirect::route('profile.edit')->with('status', 'Password updated. Any other devices signed in to this account have been logged out.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
