# Appharu MasterStore

One Laravel application that runs many independent online stores. `store.appharu.com` is
Appharu's own shop; every client gets their own store at `<slug>.store.appharu.com` or
their own domain, with separate products, stock, customers, orders, invoices and books.

## Run it locally

Needs PHP 8.2+, MySQL/MariaDB, Composer and Node (only to copy front-end libraries).

```bash
composer install
npm install && npm run vendor
copy .env.example .env    # then set DB_* and APP_URL=http://localhost:8020
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8020
```

| What | Address |
|---|---|
| Appharu Store (shop) | http://localhost:8020 |
| Store admin | http://localhost:8020/admin |
| Second demo store | http://tea.localhost:8020 (and `/admin`) |
| Super admin | http://localhost:8020/superadmin |

The demo sign-ins and their password are listed at the top of
`database/seeders/DatabaseSeeder.php`. Demo data is never seeded in production.

Tests (use their own database, `appharu_store_test`): `php artisan test`

## How it is built

- **Laravel 12, Blade, Bootstrap 5, Alpine.js.** No build step: libraries are served from `public/vendor`.
- **Tenancy:** `ResolveStore` middleware finds the store from the host name. Every store-owned
  model uses `App\Tenancy\BelongsToStore`, which filters queries to that store and stamps new
  rows. The store is resolved before route model binding, so `/admin/orders/5` can only ever
  load an order of the store whose domain you are on.
- **Two separate money systems:** a store's sales (orders, invoices, credit notes, journal) and
  the platform's billing (`store_subscriptions`, `subscription_invoices`, `subscription_payments`).
- **Services** (`app/Services`) hold all business rules; controllers stay thin:
  `PricingService` (subtotal → coupon → delivery → VAT), `CheckoutService` (one transaction,
  row locks), `InventoryService` (reserve / commit / release, append-only ledger),
  `PaymentService` + `Payments/KhaltiGateway`, `InvoiceService`, `AccountingService`
  (double entry, refuses unbalanced entries), `RefundService`, `ReportService`,
  `SubscriptionService`, `StoreProvisioner` (a new store is ready to sell).
- **Money** is `DECIMAL(19,4)` in the database and whole paisa in PHP. Orders snapshot
  prices, discount, tax, currency and rate, so later edits never change them.
- **Numbers** (`ORD-2026-000001`, `INV-…`, `CN-…`, `PO-…`) come from a locked counter per
  store and year, never `MAX(id)+1`.

## Payments

| Method | How it is confirmed |
|---|---|
| **Khalti** | Shopper pays on Khalti's page. The return URL is only a hint: the order is paid only when Khalti's `epayment/lookup` says `Completed` **and** the amount matches. Repeated returns are ignored. Each store saves its own secret key (encrypted) in *Settings → Payments*; "Live mode" switches from `dev.khalti.com` to `khalti.com`. |
| **Scan & Pay (QR)** | Store uploads its Fonepay / bank / wallet QR. Shopper scans, pays, uploads the screenshot and/or transaction ID. Staff see it under *Verify payments* and click Received or Not received. Screenshots are stored privately. |
| **Cash on delivery** | Marked paid when the order is marked Delivered. Can be turned off per delivery zone. |
| **Bank transfer** | Same flow as QR, with bank details. |

Unpaid online orders hold their stock for 30 minutes (`STOCK_RESERVATION_MINUTES`), then the
scheduler cancels them and frees the stock. A paid order is confirmed, its stock is taken off
the shelf, the invoice is issued and the journal is posted, all in one transaction.

## Deploying on the server (CyberPanel / OpenLiteSpeed)

1. Create the website `store.appharu.com`, document root `…/store/public`, PHP 8.3, SSL.
2. DNS: `A store.appharu.com` and **`A *.store.appharu.com`** to the server (Cloudflare proxied is fine).
   For client subdomains add the wildcard as an alias and issue a wildcard certificate
   (Cloudflare origin certificate, or Let's Encrypt DNS challenge).
3. Client custom domains: the client points `CNAME shop.client.com → store.appharu.com`; add
   the domain as an alias of the same vhost and issue its SSL; add it in the store's *Settings → Domains*.
4. Upload the code, then:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate   # fill DB_*, MAIL_*, APP_URL
   php artisan migrate --force --seed                 # reference data only in production
   php artisan storage:link
   php artisan optimize
   ```
5. Create the first super admin:
   `php artisan tinker` → `App\Models\User::create(['name'=>'…','email'=>'…','password'=>'…','is_super_admin'=>true]);`
6. Cron (every minute): `cd /path/to/store && php artisan schedule:run >> /dev/null 2>&1`
   and a queue worker for emails: `php artisan queue:work --tries=3` (Supervisor or CyberPanel cron `@reboot`).
7. Back up the database and `storage/app` off the server every day, and test a restore.

## Not included yet

- eSewa and Fonepay **API** integrations (the QR method works with any static merchant QR today).
- SMS notifications (email notifications are sent on order updates).
- Automatic promotions such as buy-one-get-one; coupons cover percent, fixed and free delivery.
- A full returns/RMA workflow (refunds, credit notes and restocking of inspected items are in).
- Stock counts, batch and expiry tracking.
- Charging in foreign currencies (other currencies are display-only; checkout is in NPR).
