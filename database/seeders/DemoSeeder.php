<?php

namespace Database\Seeders;

use App\Services\DemoService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        app(DemoService::class)->seed();
    }
}
