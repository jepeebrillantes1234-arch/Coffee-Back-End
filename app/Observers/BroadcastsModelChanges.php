<?php
namespace App\Observers;
use App\Events\LiveUpdate;
use Illuminate\Database\Eloquent\Model;
trait BroadcastsModelChanges
{
    protected function announce(Model $model, string $kind, ?int $userId = null): void
    {
        $meta = [];
        if ($model->wasChanged('status') || ($model->exists && $model->getAttribute('status') !== null)) {
            $meta['status'] = $model->status;
        }
        if ($model->wasChanged('payment_status') || ($model->exists && $model->getAttribute('payment_status') !== null)) {
            $meta['payment_status'] = $model->payment_status;
        }

        // A WebSocket outage must not turn a successfully saved order into an API error.
        // The staff page also reconciles its order fragment automatically as a fallback.
        try {
            LiveUpdate::dispatch($kind, $model->getKey(), $userId, $meta);
        } catch (\Throwable $exception) {
            // The record is committed; skip this broadcast and let the client fallback catch up.
        }
    }
}
