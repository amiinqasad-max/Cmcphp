<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Filament Shield's `shield:generate` writes Policy *files* to disk (already
 * committed to the repo) but the Permission *rows* it creates only exist in
 * whatever database you ran it against manually. That's not reproducible
 * across fresh environments (CI, a new teammate's machine, production), so
 * we regenerate the permission rows here — with `--option=permissions` so
 * this never touches (or overwrites) the hand-tuned Policy classes.
 *
 * Safe to re-run: Shield's generator is idempotent for permissions.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);
    }
}
