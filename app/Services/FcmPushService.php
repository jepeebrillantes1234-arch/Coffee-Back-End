<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

class FcmPushService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): string
    {
        if (!$user->fcm_token) {
            return 'no_token';
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $stringData[(string) $key] = (string) $value;
            }
        }

        $token = (string) $user->fcm_token;

        try {
            $message = CloudMessage::new()
                ->toToken($token)
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($stringData)
                ->withDefaultSounds();

            // Kreait returns the accepted FCM message result on success and throws
            // for rejected sends. Do not log the response, token, or message body.
            app(Messaging::class)->send($message);

            return 'sent';
        } catch (NotFound $exception) {
            if ($exception->token() === $token) {
                User::query()
                    ->whereKey($user->id)
                    ->where('fcm_token', $token)
                    ->update(['fcm_token' => null, 'fcm_platform' => null]);
            }

            Log::warning('Firebase rejected a registered device token; it was cleared.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return 'invalid_token';
        } catch (Throwable $exception) {
            $safeMessage = $exception->getMessage();
            $sensitiveValues = array_filter([
                $token,
                $title,
                $body,
                ...array_values($stringData),
                config('firebase.projects.app.credentials'),
            ], static fn ($value) => is_string($value) && $value !== '');
            $safeMessage = str_replace(array_values($sensitiveValues), '[redacted]', $safeMessage);
            $safeMessage = preg_replace(
                '/AIza[0-9A-Za-z_-]{20,}|(?:sk|pk|whsk)_live_[0-9A-Za-z_-]+|-----BEGIN [^-]+-----.*?-----END [^-]+-----/s',
                '[redacted]',
                $safeMessage
            ) ?? 'Diagnostic message unavailable.';
            $safeMessage = preg_replace(
                '/(private_key|client_email|authorization|credential|secret|api[_-]?key)\s*["\']?\s*[:=]\s*["\']?[^,\s}\']+/i',
                '$1=[redacted]',
                $safeMessage
            ) ?? 'Diagnostic message unavailable.';

            Log::warning('Unable to send Firebase push notification.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
                'exception_message' => mb_substr($safeMessage, 0, 200),
            ]);

            return 'failed';
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
