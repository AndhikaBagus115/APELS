<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RoleSeeder — bootstrap 3 role + 10 permission APELS.
 *
 * Idempotent: aman dijalankan berkali-kali (firstOrCreate semantics).
 *
 * Requirements: 2.1, 2.7, 2.8, 2.9.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache permission Spatie agar setelah seed langsung tersedia.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 4 permission untuk mahasiswa (Req 2.7).
        $mahasiswaPermissions = [
            'take diagnostic',
            'view dashboard',
            'view learning path',
            'do exercises',
        ];

        // 3 permission untuk dosen (Req 2.8).
        $dosenPermissions = [
            'manage questions',
            'view reports',
            'import questions',
        ];

        // 3 permission untuk admin (Req 2.9).
        $adminPermissions = [
            'manage users',
            'manage modules',
            'manage all',
        ];

        $allPermissions = array_merge(
            $mahasiswaPermissions,
            $dosenPermissions,
            $adminPermissions
        );

        // Buat semua permission dengan guard `web`.
        foreach ($allPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Buat role + sync permission.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($adminPermissions);

        $dosen = Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $dosen->syncPermissions($dosenPermissions);

        $mahasiswa = Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
        $mahasiswa->syncPermissions($mahasiswaPermissions);
    }
}
