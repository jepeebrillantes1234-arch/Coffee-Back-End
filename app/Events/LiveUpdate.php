<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class LiveUpdate implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 1;
    public int $timeout = 8;

    public function __construct(public string $kind, public int|string|null $recordId = null, public ?int $userId = null, public array $meta = []) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('staff.updates')];
        if (in_array($this->kind, ['product','category','promo','banner'], true)) $channels[] = new Channel('menu.updates');
        if ($this->userId) $channels[] = new PrivateChannel('users.'.$this->userId);
        return $channels;
    }

    public function broadcastAs(): string { return 'live.updated'; }
    public function broadcastWith(): array { return ['kind'=>$this->kind, 'id'=>$this->recordId, 'meta'=>$this->meta, 'at'=>now()->toIso8601String()]; }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Queued live update broadcast failed.', [
            'kind' => $this->kind,
            'record_id' => $this->recordId,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
