<?php

namespace App\Console\Commands\Demo;

use Illuminate\Console\Command;

class RefreshDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:refresh {--fresh : Run migrate:fresh before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh demo data by running seeders (optionally with migrate:fresh)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('🔄 Running migrate:fresh --seed...');
            $this->call('migrate:fresh', ['--seed' => true]);
            $this->info('✅ Demo data refreshed with fresh migrations');
        } else {
            $this->info('🔄 Running seeders only...');
            $this->call('db:seed');
            $this->info('✅ Demo data refreshed');
        }

        return self::SUCCESS;
    }
}
