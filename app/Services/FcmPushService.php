<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class FcmPushService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (!$user->fcm_token) {
            return;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $stringData[(string) $key] = (string) $value;
            }
        }

        try {
            $message = CloudMessage::new()
                ->withToken($user->fcm_token)
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($stringData)
                ->withDefaultSounds();

            Firebase::messaging()->send($message);
        } catch (Throwable $exception) {
            Log::warning('Unable to send Firebase push notification.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function sendToCustomers(string $title, string $body, array $data = []): void
    {
        User::query()
            ->where('role', 'customer')
            ->where('status', 'active')
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '<>', '')
            ->each(fn (User $user) => $this->sendToUser($user, $title, $body, $data));
    }
}