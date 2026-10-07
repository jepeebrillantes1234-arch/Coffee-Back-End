<?php
namespace App\Console\Commands;

use App\Services\PaymentGateway;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';
    protected $description = 'I-cancel ang mga online order na hindi nabayaran sa takdang oras';

    public function handle(PaymentGateway $gw): int
    {
        $n = $gw->expireStale();
        $this->info("Na-expire: {$n}");
        return self::SUCCESS;
    }
}
