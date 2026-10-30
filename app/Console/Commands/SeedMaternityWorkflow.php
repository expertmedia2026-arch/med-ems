<?php

namespace App\Console\Commands;

use Database\Seeders\MaternityWorkflowDemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class SeedMaternityWorkflow extends Command
{
    protected $signature = 'maternity:seed-demo
        {--hospital= : ID of the hospital to receive the synthetic maternity cohort}
        {--refresh : Replace only the selected hospital\'s existing maternity demo cohort}
        {--force : Allow the command to run in production}';

    protected $description = 'Seed ten professional maternity workflow cases for one hospital';

    /** Seed only the explicitly selected tenant and return a meaningful shell exit code. */
    public function handle(MaternityWorkflowDemoSeeder $seeder): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('This command creates synthetic clinical and billing records. Use --force to run it in production.');

            return self::FAILURE;
        }

        $hospitalId = filter_var($this->option('hospital'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($hospitalId === false) {
            $this->error('Provide a positive hospital ID, for example: php artisan maternity:seed-demo --hospital=5');

            return self::FAILURE;
        }

        $seeder->hospitalId = $hospitalId;
        $seeder->refreshExisting = (bool) $this->option('refresh');
        $seeder->setContainer($this->laravel)->setCommand($this);

        try {
            $seeder();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
