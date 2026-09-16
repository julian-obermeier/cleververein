<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $modules = ['organizations', 'persons', 'members', 'roles', 'audit'];
        $actions = ['view', 'create', 'update', 'archive', 'export', 'manage'];
        $rows = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $rows[] = ['key' => "{$module}.{$action}", 'module' => $module, 'action' => $action, 'name' => ucfirst($module).' · '.$action, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('permissions')->upsert($rows, ['key'], ['name', 'updated_at']);
    }
}
