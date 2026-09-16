<?php

namespace App\Console\Commands;

use App\Services\OverdueTaskAlerts;
use Illuminate\Console\Command;

class AlertOverdueTasks extends Command
{
    protected $signature = 'tasks:alert-overdue {--dry-run : Count new recipient alerts without creating notifications}';

    protected $description = 'Notify active administrators and assigned technicians about unfinished appointments';

    public function handle(OverdueTaskAlerts $alerts): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $alerts->run($dryRun);
        $this->info($count.($dryRun ? ' overdue alert(s) would be created. No notifications sent.' : ' overdue alert(s) created.'));

        return self::SUCCESS;
    }
}
