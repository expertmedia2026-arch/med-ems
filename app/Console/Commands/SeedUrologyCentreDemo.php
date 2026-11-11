<?php

namespace App\Console\Commands;

use Database\Seeders\UrologyCentreFoundationSeeder;
use Database\Seeders\UrologyCentreWorkflowSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('hospital:seed-urology-demo
    {--hospital= : ID of the specialist hospital to receive the urology demo workflow}
    {--force : Allow the command to run in production}')]
#[Description('Seed a complete specialist urology workflow for one hospital')]
class SeedUrologyCentreDemo extends Command
{
    public function handle(
        UrologyCentreFoundationSeeder $foundation,
        UrologyCentreWorkflowSeeder $workflow,
    ): int {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This command creates synthetic specialist clinical and billing records. Use --force to run it in production.');

            return self::FAILURE;
        }

        $hospitalId = filter_var($this->option('hospital'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($hospitalId === false) {
            $this->error('Provide a positive hospital ID, for example: php artisan hospital:seed-urology-demo --hospital=5');

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
        $this->info("Urology Centre demo workflow is ready for hospital ID {$hospitalId}.");
        $this->line('All synthetic staff accounts use the password: password');

        return self::SUCCESS;
    }
}
