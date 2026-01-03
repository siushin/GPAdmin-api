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
            AccountSeeder::class,
            CompanySeeder::class,
            DictionarySeeder::class,
            // LogSeeder::class,
            MenuSeeder::class,
            NotificationSeeder::class,
            OrganizationSeeder::class,
            RbacSeeder::class,
        ]);
    }
}
