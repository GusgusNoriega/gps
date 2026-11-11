<?php

namespace App\Console\Commands;

use App\Services\DemoService;
use Illuminate\Console\Command;

class SimulateDevices extends Command
{
    protected $signature = 'gps:simulate {--devices=4} {--steps=1} {--interval=5} {--clear}';

    protected $description = 'Genera dispositivos y posiciones de demostración solo en desarrollo';

    public function handle(DemoService $demo): int
    {
        if (! app()->environment('local')) {
            $this->error('La simulación está disponible únicamente en entorno local.');

            return self::FAILURE;
        }
        if ($this->option('clear')) {
            $demo->clear();
            $this->info('Datos simulados eliminados.');

            return self::SUCCESS;
        }
        $demo->seed(max(1, min(200, (int) $this->option('devices'))));
        $steps = max(1, min(10000, (int) $this->option('steps')));
        for ($index = 0; $index < $steps; $index++) {
            $demo->advance();
            $this->line('Actualización '.($index + 1).' de '.$steps);
            if ($index < $steps - 1) {
                sleep(max(1, min(60, (int) $this->option('interval'))));
            }
        }

        return self::SUCCESS;
    }
}
