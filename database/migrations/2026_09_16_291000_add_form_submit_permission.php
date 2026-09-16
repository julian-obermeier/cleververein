<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'forms.submit'],
            [
                'module' => 'forms',
                'action' => 'submit',
                'name' => 'Interne Formulare ausfüllen',
                'description' => 'Veröffentlichte interne Formulare ausfüllen und einreichen.',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('key', 'forms.submit')->value('id');
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', 'administrator')->value('id');
            if ($roleId && $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'forms.submit')->delete();
    }
};
