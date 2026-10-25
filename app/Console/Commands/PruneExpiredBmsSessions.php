<?php

namespace App\Console\Commands;

use App\Models\BmsSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneExpiredBmsSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bms:prune-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all expired BMS sessions from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $expiredCount = BmsSession::where('expires_at', '<=', $now)->count();

        if ($expiredCount === 0) {
            $this->info("No expired BMS sessions found (checked at {$now->toDateTimeString()}).");

            return self::SUCCESS;
        }

        $deleted = BmsSession::where('expires_at', '<=', $now)->delete();

        $this->info("Successfully pruned {$deleted} expired BMS session(s) at {$now->toDateTimeString()}.");
        Log::info("BMS Session Pruner: Deleted {$deleted} expired session(s).");

        return self::SUCCESS;
    }
}
