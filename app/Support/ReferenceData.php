<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Idempotently synchronises reference data that every environment needs:
 * permissions, role templates, the membership-number sequence and default
 * settings. It never creates users, members or financial records.
 */
final class ReferenceData
{
    public static function sync(): void
    {
        $now = now();

        foreach (PermissionCatalog::permissions() as $key => $meta) {
            DB::table('permissions')->updateOrInsert(['key' => $key], [
                'group' => $meta['group'], 'description' => $meta['description'], 'updated_at' => $now, 'created_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        foreach (PermissionCatalog::roles() as $key => $role) {
            DB::table('roles')->updateOrInsert(['key' => $key], [
                'name' => $role['name'], 'description' => $role['description'], 'is_staff' => $role['is_staff'],
                'updated_at' => $now, 'created_at' => $now,
            ]);
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach ($role['permissions'] as $permission) {
                DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
            }
        }

        DB::table('membership_number_sequences')->insertOrIgnore(['name' => 'members', 'next_value' => 1, 'updated_at' => $now]);

        foreach (Content::PAGES as $key => $label) {
            DB::table('pages')->insertOrIgnore(['key' => $key, 'label' => $label, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (Settings::defaults() as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => json_encode($value), 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
