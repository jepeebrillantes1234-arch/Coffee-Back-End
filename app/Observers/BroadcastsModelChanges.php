<?php
namespace App\Observers;
use App\Events\LiveUpdate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

        DB::afterCommit(function () use ($kind, $model, $userId, $meta): void {
            try {
                LiveUpdate::dispatch($kind, $model->getKey(), $userId, $meta);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }
}