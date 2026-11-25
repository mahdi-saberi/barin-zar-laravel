<?php

namespace Database\Seeders;

use App\Models\Entry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoInstallationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $installation = Entry::firstOrCreate(
                ['kind' => 'installation', 'slug' => 'demo-catalog'],
                ['data' => []],
            );

            if ($installation->wasRecentlyCreated && ! Entry::where('kind', 'product')->exists()) {
                $this->call(DatabaseSeeder::class);
            }
        });
    }
}
