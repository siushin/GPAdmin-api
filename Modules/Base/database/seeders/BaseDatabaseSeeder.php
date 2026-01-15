<?php

namespace Modules\Base\Database\Seeders;

use Illuminate\Database\Seeder;

class BaseDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            DictionarySeeder::class,
            OrganizationSeeder::class,
            AccountSeeder::class,
            ModuleSeeder::class,
            AccountModuleSeeder::class,
            MenuSeeder::class,
            RbacSeeder::class,
            // LogSeeder::class,
            // NotificationSeeder::class,
        ]);
    }
}
