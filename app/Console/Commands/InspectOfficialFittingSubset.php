<?php

namespace App\Console\Commands;

use App\Services\FittingOfficialDataService;
use Illuminate\Console\Command;

class InspectOfficialFittingSubset extends Command
{
    protected $signature = 'fitting:inspect-official-subset {--slot= : Optional slot filter}';

    protected $description = 'Inspect the current official fitting subset sqlite';

    public function __construct(private readonly FittingOfficialDataService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->service->getSummary();

        if (!($summary['ready'] ?? false)) {
            $this->error('Official fitting subset database is not ready.');
            return self::FAILURE;
        }

        $this->info('=== Official fitting subset summary ===');
        foreach ($summary['tables'] as $table => $count) {
            $this->line("{$table}: {$count}");
        }

        $this->newLine();
        $this->info('=== Slot counts ===');
        foreach ($summary['slot_counts'] as $row) {
            $this->line($row['slot_type'] . ': ' . $row['count']);
        }

        $slot = $this->option('slot');
        if ($slot) {
            $this->newLine();
            $this->info("=== Sample types for slot: {$slot} ===");
            foreach ($this->service->getSampleTypesBySlot($slot) as $row) {
                $this->line(($row['type_id'] ?? 0) . ' | ' . ($row['name_cn'] ?: $row['name_en']));
            }
        }

        return self::SUCCESS;
    }
}
