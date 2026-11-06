<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\StoreProvisioner;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoStoreSeeder extends Seeder
{
    private const COLORS = [
        'Black' => '#222326', 'White' => '#ececef', 'Silver' => '#b8bcc4', 'Space Grey' => '#5b5e66', 'Blue' => '#2f6fdf',
        'Red' => '#d93b3b', 'Rose Gold' => '#e2a99a', 'Green' => '#2e9b5f', 'Grey' => '#8a8d93', 'Purple' => '#7b4bd6',
    ];

    public function run(): void
    {
        $tenant = app(TenantContext::class);
        $password = env('DEMO_PASSWORD', DatabaseSeeder::DEMO_PASSWORD);

        // ---------- Store 1: Appharu Store on localhost and store.appharu.com ----------
        $store = app(StoreProvisioner::class)->create([
            'name' => 'Appharu Store',
            'slug' => 'appharu',
            'owner_name' => 'Appharu Owner',
            'owner_email' => 'owner@appharu.com',
            'owner_password' => $password,
            'phone' => '9801234567',
            'plan_id' => Plan::where('slug', 'enterprise')->value('id'),
        ]);

        StoreDomain::where('store_id', $store->id)->update(['is_primary' => false]);
        StoreDomain::create(['store_id' => $store->id, 'domain' => 'store.appharu.com', 'is_primary' => true]);
        StoreDomain::create(['store_id' => $store->id, 'domain' => 'localhost']);
        StoreDomain::create(['store_id' => $store->id, 'domain' => '127.0.0.1']);

        $store->update([
            'email' => 'store@appharu.com',
            'whatsapp' => '+9779801234567',
            'address' => 'Kathmandu, Nepal',
            'pan_vat_number' => '600000000',
        ]);

        $tenant->run($store->fresh(), function (Store $store) {
            $store->setSettings([
                'tagline' => 'Gadgets, audio and creator gear for everyday life, delivered across Nepal.',
                'hero_eyebrow' => 'Genuine Tech Gear',
                'hero_title' => 'Gear up for your next',
                'hero_highlight' => 'Call,Reel,Workout,Trip',
                'hero_text' => 'Earbuds, smart watches, chargers and creator accessories, tested by our team and delivered to your door with real warranty.',
                'featured_title' => 'Everyday essentials',
                'featured_text' => 'The two things most of our customers buy first',
                'free_delivery_note' => 'Free delivery over Rs. 5,000',
                'delivery_note' => 'Delivered in 1 to 3 days',
                'warranty_note' => 'Up to 1-year warranty',
                'business_hours' => 'Sun to Fri, 10am to 6pm',
                'social_facebook' => 'https://facebook.com/appharu',
                'social_instagram' => 'https://instagram.com/appharu',
                'order_prefix' => 'APH',
            ]);

            $this->catalog($store);
            $this->extras($store);
            $this->orders($store);
        });

        // ---------- Store 2: a separate client, to show stores never see each other ----------
        $tea = app(StoreProvisioner::class)->create([
            'name' => 'Himalayan Tea House',
            'slug' => 'tea',
            'owner_name' => 'Tea House Owner',
            'owner_email' => 'tea@appharu.com',
            'owner_password' => $password,
            'phone' => '9812345678',
            'plan_id' => Plan::where('slug', 'starter')->value('id'),
        ]);
        StoreDomain::create(['store_id' => $tea->id, 'domain' => 'tea.localhost']);
        $tea->update(['email' => 'hello@teahouse.example', 'address' => 'Ilam, Nepal']);

        $tenant->run($tea->fresh(), function (Store $store) {
            $store->setSettings([
                'theme' => 'forest',
                'accent_color' => '#8fd16a',
                'tagline' => 'Fresh tea from the hills of Ilam, packed the week it is picked.',
                'hero_eyebrow' => 'First flush 2026',
                'hero_title' => 'Tea for every',
                'hero_highlight' => 'morning,break,guest',
                'hero_text' => 'Small-batch green, black and masala teas straight from our garden in Ilam.',
                'order_prefix' => 'TEA',
            ]);

            $cat = Category::create(['name' => 'Tea', 'slug' => 'tea', 'sort' => 1]);
            $warehouse = app(InventoryService::class)->defaultWarehouse();

            foreach ([
                ['Ilam Green Tea', 'cup-hot', [['100 g', 650, 'Green', 40, null], ['250 g', 1450, 'Green', 25, null]], 'Best seller'],
                ['Masala Chai', 'cup-straw', [['100 g', 550, 'Red', 30, null]], null],
                ['Himalayan Black Tea', 'cup', [['100 g', 600, 'Black', 35, null], ['250 g', 1350, 'Black', 10, null]], null],
            ] as $i => [$name, $icon, $variants, $badge]) {
                $this->product($store, $cat, $warehouse, $name, $icon, $variants, $badge, 'Hand-picked and packed in Ilam.', $i);
            }

            PaymentMethod::where('code', 'qr')->first()->update(['is_enabled' => true, 'config' => ([
                'qr_image' => DemoArt::demoQr($store->id), 'account_name' => 'Himalayan Tea House', 'qr_label' => 'Fonepay / any bank app',
            ])]);
        });

        $this->command?->info('Demo stores ready: http://localhost:8020 and http://tea.localhost:8020');
    }

    private function catalog(Store $store): void
    {
        $categories = collect([
            'audio' => 'Audio',
            'power' => 'Power & Charging',
            'wearables' => 'Wearables',
            'creator' => 'Creator Gear',
            'computer' => 'Computer Accessories',
        ])->map(fn ($name, $slug) => Category::create(['name' => $name, 'slug' => $slug, 'sort' => array_search($slug, ['audio', 'power', 'wearables', 'creator', 'computer'])]));

        $warehouse = app(InventoryService::class)->defaultWarehouse();
        Warehouse::create(['name' => 'Pokhara branch', 'code' => 'PKR', 'address' => 'Lakeside, Pokhara']);

        $items = [
            ['audio', 'Wireless Earbuds Pro', 'earbuds', [['Black', 3499, 'Black', 42, 3999], ['White', 3499, 'White', 18, 3999]], 'Best seller', 'Clear calls, deep bass and 30 hours of battery with the case.'],
            ['wearables', 'Smart Watch S2', 'smartwatch', [['Black', 6999, 'Black', 15, 7999], ['Silver', 6999, 'Silver', 9, 7999], ['Rose Gold', 7299, 'Rose Gold', 3, null]], null, 'Heart rate, SpO2, sleep tracking and 7-day battery.'],
            ['power', 'Power Bank 20000 mAh', 'battery-charging', [['Black', 2999, 'Black', 30, null], ['White', 2999, 'White', 12, null]], null, 'Charges a phone four times. 22.5W fast charging, two USB ports.'],
            ['power', '65W GaN Fast Charger', 'plug', [['White', 2499, 'White', 25, 2899]], null, 'One small charger for laptop, tablet and phone.'],
            ['creator', 'LED Ring Light 12"', 'brightness-high', [['With stand', 2800, 'Black', 6, null], ['Light only', 1900, 'Grey', 0, null]], null, 'Three colour tones and ten brightness levels for videos and calls.'],
            ['creator', 'Phone Tripod Stand', 'camera-video', [['Black', 1800, 'Black', 22, 2200]], null, 'Extends to 160 cm with a Bluetooth remote.'],
            ['audio', 'Bluetooth Speaker Mini', 'speaker', [['Black', 3200, 'Black', 14, null], ['Blue', 3200, 'Blue', 4, null], ['Red', 3200, 'Red', 7, null]], 'Limited time', 'Waterproof, loud and small enough for a jacket pocket.'],
            ['computer', 'Wireless Mouse', 'mouse', [['Black', 1200, 'Black', 40, null], ['Grey', 1200, 'Grey', 16, null]], null, 'Silent clicks, 2.4 GHz and Bluetooth, 12-month battery.'],
            ['computer', 'Mechanical Keyboard', 'keyboard', [['Black', 6500, 'Black', 0, null]], null, 'Hot-swappable switches and RGB backlight. Next batch arrives soon.', true],
            ['power', 'USB-C Cable 1m (2-pack)', 'usb-c', [['Black', 450, 'Black', 80, null], ['White', 450, 'White', 60, null]], null, 'Braided, 60W, survives 10,000 bends.'],
            ['computer', 'Aluminium Laptop Stand', 'laptop', [['Silver', 2200, 'Silver', 18, null], ['Space Grey', 2200, 'Space Grey', 11, null]], null, 'Six heights, folds flat, fits 10" to 17" laptops.'],
            ['audio', 'Noise-Cancelling Headphones', 'headphones', [['Black', 8900, 'Black', 8, 9900]], null, 'Active noise cancelling and 40 hours of playtime.'],
        ];

        foreach ($items as $i => $item) {
            [$cat, $name, $icon, $variants, $badge, $short] = $item;
            $preorder = $item[6] ?? false;
            $this->product($store, $categories[$cat], $warehouse, $name, $icon, $variants, $badge, $short, $i, $preorder);
        }

        Product::where('slug', 'wireless-earbuds-pro')->update(['is_featured' => true]);
        Product::where('slug', 'smart-watch-s2')->update(['is_featured' => true]);
    }

    private function product(Store $store, Category $category, Warehouse $warehouse, string $name, string $icon, array $variants, ?string $badge, string $short, int $sort, bool $preorder = false): Product
    {
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'badge' => $badge,
            'short_description' => $short,
            'description' => '<p>'.e($short).'</p><ul><li>Genuine product with warranty</li><li>Delivered across Nepal</li><li>Pay with Khalti, QR or cash on delivery</li></ul>',
            'status' => 'active',
            'sort' => $sort,
        ]);

        foreach ($variants as $v => [$label, $price, $color, $stock, $compare]) {
            $hex = self::COLORS[$color] ?? '#444444';
            $image = DemoArt::product($store->id, $name.' '.$label, $icon, $hex);

            if ($v === 0) {
                ProductImage::create(['product_id' => $product->id, 'path' => $image, 'alt' => $name, 'sort' => 0]);
            }

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'name' => $label,
                'sku' => sprintf('%s%02d-%s', collect(preg_split('/[^A-Za-z0-9]+/', $name))->filter()->map(fn ($w) => strtoupper($w[0]))->implode(''),
                    $product->id, strtoupper(Str::substr(Str::slug($label, ''), 0, 3))),
                'price' => $price,
                'compare_at_price' => $compare,
                'cost_price' => round($price * 0.62),
                'image_path' => $image,
                'swatch' => $hex,
                'allow_preorder' => $preorder || $stock === 0,
                'sort' => $v,
            ]);

            if ($stock > 0) {
                app(InventoryService::class)->adjust($variant, $warehouse, $stock, 'Opening stock', round($price * 0.62));
            }
        }

        return $product;
    }

    private function extras(Store $store): void
    {
        $earbuds = Product::where('slug', 'wireless-earbuds-pro')->first();
        $watch = Product::where('slug', 'smart-watch-s2')->first();

        Banner::create(['eyebrow' => '30-hour battery', 'title' => 'Wireless Earbuds Pro', 'subtitle' => 'Clear calls and deep bass, in a case that fits any pocket.', 'image_path' => $earbuds->images()->value('path'), 'link' => '/product/wireless-earbuds-pro', 'button_text' => 'Shop Now', 'sort' => 1]);
        Banner::create(['eyebrow' => '7-day battery', 'title' => 'Smart Watch S2', 'subtitle' => 'Heart rate, SpO2 and sleep tracking on your wrist.', 'image_path' => $watch->variants()->skip(1)->value('image_path'), 'link' => '/product/smart-watch-s2', 'button_text' => 'Shop Now', 'sort' => 2]);

        Coupon::create(['code' => 'WELCOME10', 'name' => '10% off your first order', 'type' => 'percent', 'value' => 10, 'max_discount_amount' => 500, 'first_order_only' => true, 'usage_limit_per_customer' => 1]);
        Coupon::create(['code' => 'FREESHIP', 'name' => 'Free delivery over Rs. 2,000', 'type' => 'free_shipping', 'min_order_amount' => 2000]);
        Coupon::create(['code' => 'AUDIO500', 'name' => 'Rs. 500 off audio over Rs. 5,000', 'type' => 'fixed', 'value' => 500, 'min_order_amount' => 5000, 'applies_to' => 'categories', 'usage_limit' => 100]);
        Coupon::where('code', 'AUDIO500')->first()->categories()->sync(Category::where('slug', 'audio')->pluck('id'));

        ExchangeRate::create(['base' => 'NPR', 'quote' => 'INR', 'rate' => 0.625, 'effective_at' => now()->subDay()]);
        ExchangeRate::create(['base' => 'NPR', 'quote' => 'USD', 'rate' => 0.0074, 'effective_at' => now()->subDay()]);

        Supplier::create(['name' => 'Shenzhen Audio Trading', 'contact_person' => 'Li Wei', 'email' => 'sales@supplier.example', 'phone' => '+86 755 0000 0000']);
        Supplier::create(['name' => 'Kathmandu Electronics Wholesale', 'contact_person' => 'Ram Shrestha', 'phone' => '01-4000000', 'address' => 'New Road, Kathmandu']);

        foreach ([['Wireless Earbuds Pro', 'Sita K.', 5, 'Battery really lasts. Calls are clear even on the bus.'], ['Wireless Earbuds Pro', 'Aayush', 5, 'Great value. Delivered next day in Lalitpur.'], ['Smart Watch S2', 'Bikash', 4, 'Good watch, strap could be softer.'], ['Power Bank 20000 mAh', 'Nisha', 5, 'Charged my phone 4 times on a trek.']] as [$product, $name, $rating, $body]) {
            ProductReview::create(['product_id' => Product::where('name', $product)->value('id'), 'name' => $name, 'rating' => $rating, 'body' => $body, 'is_approved' => true]);
        }

        // QR is ready with a placeholder image; Khalti waits for the shop's own secret key.
        PaymentMethod::where('code', 'qr')->first()->update(['is_enabled' => true, 'config' => ([
            'qr_image' => DemoArt::demoQr($store->id), 'account_name' => 'Appharu Store', 'qr_label' => 'Fonepay / any bank app',
        ])]);
        PaymentMethod::where('code', 'bank')->first()->update(['is_enabled' => true, 'config' => ([
            'bank_name' => 'Demo Bank Ltd.', 'account_name' => 'Appharu Store', 'account_number' => '0010 0100 0000 1234', 'branch' => 'Kathmandu',
        ])]);
    }

    /** Realistic order history for the dashboard, placed through the real checkout. */
    private function orders(Store $store): void
    {
        $checkout = app(CheckoutService::class);
        $payments = app(PaymentService::class);
        $orders = app(OrderService::class);

        $khalti = PaymentMethod::where('code', 'khalti')->first();
        $khalti->update(['is_enabled' => true, 'config' => ['secret_key' => 'demo-only']]); // lets the seeder use it; removed below

        $people = [
            ['Sita Sharma', '9841000001', 'Kathmandu', 'Baneshwor'], ['Ram Thapa', '9851000002', 'Lalitpur', 'Jhamsikhel'],
            ['Anita Gurung', '9861000003', 'Pokhara', 'Lakeside'], ['Bikash Rai', '9802000004', 'Dharan', 'Bhanu Chowk'],
            ['Nisha Karki', '9811000005', 'Bhaktapur', 'Suryabinayak'], ['Suman Adhikari', '9821000006', 'Butwal', 'Traffic Chowk'],
            ['Pooja Maharjan', '9841000007', 'Kathmandu', 'Kalanki'], ['Aayush Shrestha', '9851000008', 'Chitwan', 'Narayangadh'],
        ];
        $methods = ['cod', 'cod', 'khalti', 'khalti', 'qr', 'cod', 'khalti'];
        mt_srand(42);

        for ($i = 0; $i < 28; $i++) {
            [$name, $phone, $city, $area] = $people[$i % count($people)];
            $cart = Cart::create(['token' => Str::random(48)]);

            // Only items that still have plenty left, picked with the seeded generator so every run is the same.
            $pool = ProductVariant::with('inventory')->get()->filter(fn ($v) => $v->available() >= 6)->values();

            foreach (array_unique([mt_rand(0, $pool->count() - 1), mt_rand(0, $pool->count() - 1)]) as $pick) {
                CartItem::create(['cart_id' => $cart->id, 'variant_id' => $pool[$pick]->id, 'quantity' => mt_rand(1, 2)]);
            }

            $method = $methods[$i % count($methods)];
            $details = [
                'name' => $name, 'phone' => $phone, 'email' => null, 'province' => 'Bagmati', 'city' => $city, 'area' => $area,
                'address' => 'House '.mt_rand(1, 400).', '.$area, 'payment_method' => $method, 'coupon_code' => $i % 9 === 0 ? 'FREESHIP' : null,
            ];

            try {
                $order = $checkout->placeOrder($cart, $details, '127.0.0.1');
            } catch (\App\Services\CheckoutException) {
                $order = $checkout->placeOrder($cart, ['coupon_code' => null] + $details, '127.0.0.1');
            }

            $daysAgo = 29 - $i;
            $placed = now()->subDays($daysAgo)->setTime(mt_rand(9, 20), mt_rand(0, 59));
            $order->forceFill(['placed_at' => $placed, 'created_at' => $placed])->save();

            // Older orders are further along; the last few are still open.
            if ($daysAgo > 3) {
                if ($method === 'khalti') {
                    $payments->complete($order->payments()->first(), 'KH'.Str::upper(Str::random(10)), ['demo' => true]);
                } elseif ($method === 'qr') {
                    $payments->submitManual($order, 'FP'.mt_rand(100000, 999999), null);
                    $payments->complete($order->payments()->first(), 'FP'.mt_rand(100000, 999999), ['demo' => true]);
                } else {
                    $orders->changeStatus($order, 'confirmed');
                }

                if ($i % 11 === 5 && $method === 'cod') {
                    $orders->cancel($order->fresh(), 'Customer asked to cancel.');

                    continue;
                }

                $orders->changeStatus($order->fresh(), 'shipped', null, null, ['courier' => 'Pathao', 'tracking_number' => 'PTH'.mt_rand(100000, 999999)]);
                $orders->changeStatus($order->fresh(), 'delivered');
            } elseif ($method === 'qr') {
                $payments->submitManual($order, 'FP'.mt_rand(100000, 999999), null);
            }
        }

        $khalti->update(['is_enabled' => false, 'config' => []]);

        foreach (Order::all() as $order) {
            app(ReportService::class)->refreshDay($order->placed_at);
        }

    }
}
