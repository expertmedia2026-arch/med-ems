<?php

namespace App\Console\Commands;

use Database\Seeders\FullHospitalFoundationSeeder;
use Database\Seeders\FullHospitalWorkflowSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('hospital:seed-full-demo
    {--hospital= : ID of the hospital to receive the complete synthetic workflow}
    {--force : Allow the command to run in production}')]
#[Description('Seed a complete hospital workflow for one explicitly selected hospital')]
class SeedFullHospitalDemo extends Command
{
    public function handle(
        FullHospitalFoundationSeeder $foundation,
        FullHospitalWorkflowSeeder $workflow,
    ): int {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This command creates synthetic clinical and billing records. Use --force to run it in production.');

            return self::FAILURE;
        }

        $hospitalId = filter_var($this->option('hospital'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($hospitalId === false) {
            $this->error('Provide a positive hospital ID, for example: php artisan hospital:seed-full-demo --hospital=5');

            return self::FAILURE;
        }

        foreach ([$foundation, $workflow] as $seeder) {
            $seeder->hospitalId = $hospitalId;
            $seeder->setContainer($this->laravel)->setCommand($this);

            try {
                $seeder();
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("Full demo workflow is ready for hospital ID {$hospitalId}.");
        $this->line('All synthetic staff accounts use the password: password');

        return self::SUCCESS;
    }
}
