<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\ExpenseCategory;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\StoreStaff;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a ready-to-sell store: owner account, roles, settings, warehouse, payment
 * methods, delivery zones, VAT, chart of accounts, footer pages and a subscription.
 */
class StoreProvisioner
{
    public function __construct(private TenantContext $tenant, private SubscriptionService $subscriptions)
    {
    }

    /**
     * @param  array{name: string, slug?: string, domain?: ?string, owner_name: string, owner_email: string, owner_password?: ?string, phone?: ?string, plan_id?: ?int, billing_cycle?: string, trial_days?: int}  $data
     */
    public function create(array $data): Store
    {
        return DB::transaction(function () use ($data) {
            $owner = User::firstOrCreate(
                ['email' => strtolower($data['owner_email'])],
                ['name' => $data['owner_name'], 'password' => $data['owner_password'] ?? Str::password(16), 'phone' => $data['phone'] ?? null]
            );

            $store = Store::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                'owner_id' => $owner->id,
                'plan_id' => $data['plan_id'] ?? Plan::orderBy('sort')->value('id'),
                'status' => 'active',
                'email' => $data['owner_email'],
                'phone' => $data['phone'] ?? null,
                'trial_ends_at' => isset($data['trial_days']) ? now()->addDays($data['trial_days']) : null,
            ]);

            StoreDomain::create(['store_id' => $store->id, 'domain' => $store->slug.'.'.config('platform.domain'), 'is_primary' => empty($data['domain'])]);

            if (! empty($data['domain'])) {
                StoreDomain::create(['store_id' => $store->id, 'domain' => strtolower($data['domain']), 'is_primary' => true]);
            }

            $this->tenant->run($store, fn () => $this->seedDefaults($store, $owner));

            if ($store->plan_id) {
                $this->subscriptions->subscribe($store, $store->plan, $data['billing_cycle'] ?? 'monthly', $data['trial_days'] ?? 0);
            }

            return $store;
        });
    }

    private function seedDefaults(Store $store, User $owner): void
    {
        $this->roles($store, $owner);

        $store->setSettings([
            'tagline' => 'Quality products, delivered across Nepal.',
            'hero_eyebrow' => 'New arrivals',
            'hero_title' => 'Shop the things you love',
            'hero_highlight' => 'today',
            'hero_text' => 'Genuine products, fair prices and fast delivery across Nepal.',
            'theme' => 'midnight',
            'accent_color' => '#f5c542',
            'order_prefix' => 'ORD',
            'currencies' => 'NPR,INR,USD',
            'delivery_note' => 'Delivered in 1 to 3 days',
            'warranty_note' => 'Easy 7-day returns',
            'free_delivery_note' => 'Free delivery over Rs. 5,000',
            'business_hours' => 'Sun to Fri, 10am to 6pm',
        ]);

        Warehouse::create(['name' => 'Main store', 'code' => 'MAIN', 'is_default' => true]);

        $methods = [
            ['khalti', 'Khalti', 'Pay securely with your Khalti wallet, mobile banking or cards.', 1],
            ['qr', 'Scan & Pay (QR)', 'Scan our QR with any banking or wallet app, then upload the payment screenshot.', 2],
            ['cod', 'Cash on Delivery', 'Pay in cash when your order arrives.', 3],
            ['bank', 'Bank Transfer', 'Transfer to our bank account and upload the receipt.', 4],
        ];

        foreach ($methods as [$code, $name, $instructions, $sort]) {
            PaymentMethod::create([
                'code' => $code,
                'name' => $name,
                'instructions' => $instructions,
                'config' => [],
                'is_enabled' => $code === 'cod',
                'sort' => $sort,
            ]);
        }

        ShippingZone::create(['name' => 'Inside Kathmandu Valley', 'cities' => 'Kathmandu, Lalitpur, Bhaktapur', 'rate' => 100, 'free_over' => 5000, 'estimated_days' => '1-2 days', 'sort' => 1]);
        ShippingZone::create(['name' => 'Outside the Valley', 'cities' => null, 'rate' => 200, 'free_over' => 10000, 'estimated_days' => '2-5 days', 'sort' => 2]);

        TaxRate::create(['name' => 'VAT', 'rate' => 13, 'is_inclusive' => true, 'applies_to_shipping' => false, 'is_active' => true]);

        foreach (AccountingAccount::DEFAULTS as $code => [$name, $type]) {
            AccountingAccount::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_system' => true]);
        }

        foreach (['Rent', 'Salaries', 'Delivery and courier', 'Marketing', 'Utilities', 'Other'] as $category) {
            ExpenseCategory::create(['name' => $category]);
        }

        $pages = [
            'about-us' => ['About Us', '<p>'.e($store->name).' is an online store based in Nepal. We sell genuine products at fair prices and deliver them to your door.</p>'],
            'contact' => ['Contact', '<p>Questions about an order? Call or WhatsApp us, or email us. We reply during business hours.</p>'],
            'return-policy' => ['Return Policy', '<p>You can return an unused item in its original packaging within 7 days of delivery. Refunds are made to the original payment method after we have inspected the item.</p>'],
            'privacy-policy' => ['Privacy Policy', '<p>We use your name, phone number and address only to deliver your orders and contact you about them. We never sell your data.</p>'],
        ];

        foreach ($pages as $slug => [$title, $content]) {
            Page::create(['slug' => $slug, 'title' => $title, 'content' => $content]);
        }
    }

    private function roles(Store $store, User $owner): void
    {
        $permissions = Permission::pluck('id', 'slug');

        $sets = [
            'owner' => ['Owner', []],  // owners pass every check
            'manager' => ['Manager', $permissions->keys()->reject(fn ($slug) => $slug === 'staff.manage')->all()],
            'staff' => ['Sales staff', ['products.view', 'inventory.view', 'orders.view', 'orders.manage', 'payments.verify', 'customers.view']],
        ];

        foreach ($sets as $slug => [$name, $grants]) {
            $role = Role::create(['store_id' => $store->id, 'name' => $name, 'slug' => $slug, 'is_system' => true]);
            $role->permissions()->sync($permissions->only($grants)->values());

            if ($slug === 'owner') {
                StoreStaff::create(['user_id' => $owner->id, 'role_id' => $role->id, 'status' => 'active']);
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $i = 2;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
