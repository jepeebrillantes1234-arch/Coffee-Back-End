<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountLoginSecurity
{
    public const LOCKOUT_SECONDS = 300;
    private const MAX_FAILED_ATTEMPTS = 3;
    private const MAX_LOCKOUT_CYCLES = 3;

    /** Check the password and update account counters atomically under a row lock. */
    public function attemptPassword(User $user, string $password): array
    {
        return DB::transaction(function () use ($user, $password): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedUser->password_reset_required) {
                return ['status' => 'password_reset_required', 'retry_after_seconds' => 0];
            }

            if ($lockedUser->login_locked_until && $lockedUser->login_locked_until->isFuture()) {
                return [
                    'status' => 'locked',
                    'retry_after_seconds' => max(1, (int) now()->diffInSeconds($lockedUser->login_locked_until, false)),
                ];
            }

            if ($lockedUser->login_locked_until) {
                $lockedUser->login_locked_until = null;
                $lockedUser->failed_login_attempts = 0;
            }

            if (!Hash::check($password, $lockedUser->password)) {
                $lockedUser->failed_login_attempts++;
                $result = ['status' => 'invalid', 'retry_after_seconds' => 0];

                if ($lockedUser->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
                    $lockedUser->failed_login_attempts = 0;
                    $lockedUser->login_lockout_cycles = min(
                        self::MAX_LOCKOUT_CYCLES,
                        $lockedUser->login_lockout_cycles + 1,
                    );

                    if ($lockedUser->login_lockout_cycles >= self::MAX_LOCKOUT_CYCLES) {
                        $lockedUser->password_reset_required = true;
                        $lockedUser->login_locked_until = null;
                        $result = ['status' => 'password_reset_required', 'retry_after_seconds' => 0];
                    } else {
                        $lockedUser->login_locked_until = now()->addSeconds(self::LOCKOUT_SECONDS);
                        $result = [
                            'status' => 'locked',
                            'retry_after_seconds' => self::LOCKOUT_SECONDS,
                        ];
                    }
                }

                $lockedUser->save();
                return $result;
            }

            $lockedUser->failed_login_attempts = 0;
            $lockedUser->login_lockout_cycles = 0;
            $lockedUser->login_locked_until = null;
            $lockedUser->save();

            return ['status' => 'success', 'retry_after_seconds' => 0];
        }, 3);
    }

    /** Issue a mobile token only after account state and credentials were accepted. */
    public function issuePasswordToken(User $user, string $deviceName): array
    {
        return DB::transaction(function () use ($user, $deviceName): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedUser->password_reset_required) {
                return ['status' => 'password_reset_required', 'retry_after_seconds' => 0];
            }
            if ($lockedUser->login_locked_until && $lockedUser->login_locked_until->isFuture()) {
                return [
                    'status' => 'locked',
                    'retry_after_seconds' => max(1, (int) now()->diffInSeconds($lockedUser->login_locked_until, false)),
                ];
            }

            return [
                'status' => 'success',
                'retry_after_seconds' => 0,
                'token' => $lockedUser->createToken($deviceName)->plainTextToken,
            ];
        }, 3);
    }

    /** Stop alternate sign-in methods from bypassing an active account restriction. */
    public function issueGoogleToken(User $user, string $deviceName): array
    {
        return DB::transaction(function () use ($user, $deviceName): array {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedUser->password_reset_required) {
                return ['status' => 'password_reset_required', 'retry_after_seconds' => 0];
            }
            if ($lockedUser->login_locked_until && $lockedUser->login_locked_until->isFuture()) {
                return [
                    'status' => 'locked',
                    'retry_after_seconds' => max(1, (int) now()->diffInSeconds($lockedUser->login_locked_until, false)),
                ];
            }

            $lockedUser->failed_login_attempts = 0;
            $lockedUser->login_lockout_cycles = 0;
            $lockedUser->login_locked_until = null;
            $lockedUser->save();

            return [
                'status' => 'allowed',
                'retry_after_seconds' => 0,
                'token' => $lockedUser->createToken($deviceName)->plainTextToken,
            ];
        }, 3);
    }

    /** Clear credentials and account restrictions after a verified reset. */
    public function clearAfterPasswordReset(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $lockedUser->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'failed_login_attempts' => 0,
                'login_lockout_cycles' => 0,
                'login_locked_until' => null,
                'password_reset_required' => false,
            ])->save();

            $this->invalidateCredentials($lockedUser);
        }, 3);
    }

    private function invalidateCredentials(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
