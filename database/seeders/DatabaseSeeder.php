<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Platform reference data (permissions, currencies, plans, the super admin) and, outside
 * production, two demo stores with products, stock, coupons and orders.
 *
 * Demo sign-ins (local only), all with the password in DEMO_PASSWORD (default below):
 *   super admin   admin@appharu.com      at /superadmin
 *   store owner   owner@appharu.com      at http://localhost:8020/admin
 *   tea owner     tea@appharu.com        at http://tea.localhost:8020/admin
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Appharu@2026';

    public function run(): void
    {
        foreach (Permission::ALL as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
            }
        }

        foreach ([
            ['NPR', 'Nepalese Rupee', 'Rs.', '🇳🇵'],
            ['INR', 'Indian Rupee', '₹', '🇮🇳'],
            ['USD', 'US Dollar', '$', '🇺🇸'],
            ['EUR', 'Euro', '€', '🇪🇺'],
            ['GBP', 'British Pound', '£', '🇬🇧'],
            ['AUD', 'Australian Dollar', 'A$', '🇦🇺'],
        ] as [$code, $name, $symbol, $flag]) {
            Currency::updateOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol, 'decimals' => 2, 'flag' => $flag]);
        }

        $all = array_keys(Plan::FEATURES);

        foreach ([
            ['starter', 'Starter', 'For a new shop getting its first orders online.', 1500, 15000, 0, 100, 2, ['coupons', 'khalti', 'qr'], 1],
            ['business', 'Business', 'Inventory, accounting and reports for a growing shop.', 3500, 35000, 5000, 2000, 10, array_diff($all, ['api']), 2],
            ['enterprise', 'Enterprise', 'Everything, with custom domain, API and priority support.', 9000, 90000, 15000, null, null, $all, 3],
        ] as [$slug, $name, $description, $monthly, $yearly, $setup, $products, $staff, $features, $sort]) {
            Plan::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'price_monthly' => $monthly, 'price_yearly' => $yearly,
                'setup_fee' => $setup, 'max_products' => $products, 'max_staff' => $staff, 'features' => array_values($features), 'sort' => $sort,
            ]);
        }

        if (app()->environment('production')) {
            $this->command?->warn('Production: only reference data was seeded. Create the super admin with: php artisan tinker');

            return;
        }

        User::updateOrCreate(['email' => 'admin@appharu.com'], [
            'name' => 'Appharu Admin',
            'password' => env('DEMO_PASSWORD', self::DEMO_PASSWORD),
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $this->call(DemoStoreSeeder::class);
    }
}
