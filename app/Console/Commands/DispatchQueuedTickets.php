<?php

namespace App\Console\Commands;

use App\Services\TicketAssignmentService;
use Illuminate\Console\Command;

class DispatchQueuedTickets extends Command
{
    protected $signature = 'tickets:dispatch-queue {--limit=100 : Maximum queued tickets to inspect}';

    protected $description = 'Assign triaged queued tickets to eligible on-shift technicians by workload';

    public function handle(TicketAssignmentService $assignments): int
    {
        $count = $assignments->dispatchQueued(max(1, (int) $this->option('limit')));
        $this->info($count.' queued ticket(s) assigned.');

        return self::SUCCESS;
    }
}
