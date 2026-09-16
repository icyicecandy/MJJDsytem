<?php

namespace App\Console\Commands;

use App\Support\ApplicationContact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditApplicationContacts extends Command
{
    protected $signature = 'applications:audit-contacts';

    protected $description = 'Report duplicate application contact identities without displaying contact details or changing records';

    public function handle(): int
    {
        $groups = ['email' => [], 'mobile_number' => []];
        foreach (DB::table('applications')->orderBy('id')->lazyById(500) as $application) {
            foreach (array_keys($groups) as $field) {
                $key = ApplicationContact::key($field, $application->$field);
                $groups[$field][$key][] = $application->reference_no;
            }
        }
        $conflicts = 0;
        foreach ($groups as $field => $contacts) {
            foreach ($contacts as $references) {
                if (count($references) > 1) {
                    $this->warn($field.': '.implode(', ', $references));
                    $conflicts++;
                }
            }
        }
        $this->info($conflicts.' conflicting contact group(s). No records changed.');

        return $conflicts ? self::FAILURE : self::SUCCESS;
    }
}
