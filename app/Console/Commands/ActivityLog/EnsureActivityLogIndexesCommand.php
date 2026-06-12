<?php

namespace App\Console\Commands\ActivityLog;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureActivityLogIndexesCommand extends Command
{
    protected $signature = 'activity-logs:ensure-indexes';

    protected $description = 'Create the MongoDB indexes used by activity logs';

    public function handle(): int
    {
        $collection = DB::connection('mongodb')->getCollection('activity_logs');

        $collection->createIndex(['event' => 1, 'created_at' => -1]);
        $collection->createIndex(['actor_id' => 1, 'created_at' => -1]);
        $collection->createIndex(['acting_as' => 1, 'created_at' => -1]);
        $collection->createIndex(['entity_type' => 1, 'entity_id' => 1]);
        $collection->createIndex(['severity' => 1, 'created_at' => -1]);
        $collection->createIndex(['created_at' => -1]);

        $this->components->info('Activity log indexes are ready.');

        return self::SUCCESS;
    }
}
