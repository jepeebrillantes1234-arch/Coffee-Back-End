<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRiderLocations extends Command
{
    protected $signature = 'locations:prune {--days=7}';
    protected $description = 'Burahin ang lumang GPS records ng riders';

    public function handle(): int
    {
        $n = DB::table('rider_locations')->where('recorded_at', '<', now()->subDays((int) $this->option('days')))->delete();
        $this->info("Nabura: {$n}");
        return self::SUCCESS;
    }
}
