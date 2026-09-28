# Mobile Arena — XentroMall Calapan Storefront Prototype

A Laravel 12 e-commerce prototype inspired by marketplace shopping flows, redesigned for a **single local gadget store**. It supports **brand-new, pre-owned and refurbished items**, a session cart, authenticated order-request checkout, repair/refurbishment requests and a role-protected operations preview.

## Public business details used

The prototype uses only public location/contact details that could be independently found online as of September 2026:

- **Store:** Mobile Arena Cellphone & Accessories
- **Location:** XentroMall Calapan, Roxas Drive, Lumang Bayan, Calapan City, Oriental Mindoro 5200
- **Mall hours:** 9:00 AM–9:00 PM, Monday–Sunday
- **Public business-listing phone:** 0999 579 2317

Sources checked during prototype preparation:
- XentroMalls official mall locator/tenant list: https://www.xentromalls.com/mall-locator/xentro-mall-calapan/
- Public local business listing returned for “Mobile Arena cellphone & accessories” in Calapan
- Third-party Calapan business directories confirming Mobile Arena at XentroMall Calapan

**Important:** The products, stock counts, prices, warranty text, unit grading and delivery/payment policies in this project are **demo data**. Public sources confirm the store/location but do not provide a reliable current Mobile Arena inventory feed. The product photos are real model/reference images; they are not proof that those exact units are currently in stock. Replace them with Mobile Arena-owned photos and actual inventory before public deployment.

## Implemented flows

- Claymorphism responsive storefront
- Search, category, brand and condition filters
- Brand New / Pre-Owned / Refurbished condition labels
- Unit condition grade fields for second-hand/refurbished devices
- Product detail and specification pages
- Session-based cart with quantity updates/removal
- Checkout with saved or new delivery addresses, server-calculated item and delivery totals, stock reservation, and repeat-submit protection
- Customer order details, status history, and purchase status filters
- Customer address book with a default delivery address
- Pickup and delivery workflow with configurable city, province, regional and nationwide demo fees
- Repair / diagnostics / refurbishment request form with reference numbers and customer-owned history
- Customer registration, login by email or mobile, password reset, profile editing and account history
- Persistent wishlists and customer-linked order and repair requests
- Admin-only operations preview with product stock, order/payment and repair updates at `/local-admin`
- Inventory movement history, staff action audits, customer notifications, refund reviews, and fulfillment tracking
- SQLite demo database with seed data
- Feature tests for catalog, cart, checkout and repair requests
- Prebuilt public CSS/JS assets so the prototype runs without a frontend build step
- Real reference product photography from public/licensed sources, with an in-app image-credit page and local SVG fallback


## PHP requirements

Use PHP 8.2+ with the common Laravel extensions enabled, especially **mbstring**, **OpenSSL**, **PDO MySQL or PDO SQLite**, and **ZIP** for Composer package downloads. XAMPP includes these extensions, but some may need to be enabled in `C:\xampp\php\php.ini`.

## Run locally (fastest)

The project includes a pre-seeded SQLite demo database and prebuilt public CSS/JS, so Node is **not required just to preview it**.

First copy `.env.example` to a private `.env` and apply the local overrides in [DEPLOYMENT.md](DEPLOYMENT.md#local-development). The example file now has production-safe defaults and cannot be used unchanged for local SQLite development. The same guide documents production deployment and every required environment group.

```bash
composer install
php artisan key:generate
php artisan serve
```

To apply new migrations without clearing existing data:

```bash
php artisan migrate
```

Open: `http://127.0.0.1:8000`

Product photo credits: `http://127.0.0.1:8000/image-credits`

The reference photos load from public/official web sources, so internet access is required for the photos. If a source cannot load, the storefront automatically falls back to a local placeholder.

The operations preview at `/local-admin` requires an admin account. To create one, set `MOBILE_ARENA_ADMIN_NAME`, `MOBILE_ARENA_ADMIN_EMAIL`, `MOBILE_ARENA_ADMIN_MOBILE` and a strong `MOBILE_ARENA_ADMIN_PASSWORD` in the environment, then run `php artisan db:seed --class=AdminUserSeeder`. The normal demo seeder does not create a login with a known password.

## XAMPP / MySQL option

Start MySQL from the XAMPP control panel. Create an empty database with the MySQL client (the command prompts for the MySQL administrator password):

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --user=root --password
```

```sql
CREATE DATABASE mobile_arena_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set `.env` to a MySQL account authorized for that database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mobile_arena_db
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

Then run:

```bash
php artisan config:clear
php artisan migrate:status
php artisan migrate
php artisan db:seed  # Only on a new, empty demo catalog
php artisan serve
```

Changing `.env` from SQLite to MySQL selects a different database; it does not copy existing SQLite users, orders, or products. Migrate data separately if those records must move. Never run `migrate:fresh` against a database containing records to keep. Automated tests use in-memory SQLite by default; run MySQL tests only against a separate disposable database.

The migrations and feature suite were also verified on an isolated MariaDB 10.4 instance using `DB_CONNECTION=mysql`, with all tests passing.

## Inventory, fulfillment and payment rules

`products.stock` is physical on-hand stock. `products.reserved_stock` is the number committed to active orders; available stock is their difference. Checkout locks products in a transaction and atomically increases the reserved count only when enough stock remains. Completing an order subtracts its items from both physical and reserved stock. Cancelling an eligible order releases the reservation once. Older orders have `stock_state=none`, so changing their status never invents stock.

GCash orders enter `pending_payment` with a configurable 30-minute reservation. Run `php artisan schedule:run` every minute in the server scheduler, or run `php artisan orders:expire-reservations` manually, to cancel expired orders. A submitted reference opens a separate configurable 24-hour verification window; it also expires if staff do not confirm it. Rejection opens a new payment window. Pickup, pay-at-store and cash-on-delivery orders enter `processing` without an automatic expiry. Staff must review stale offline orders manually.

For local Windows/XAMPP testing, run `php artisan schedule:work` in a separate terminal while the app is running. For production, configure one scheduler job every minute to run `php artisan schedule:run` from the application directory. Verify registration with `php artisan schedule:list`. Reservation expiration is safe to rerun: a cancelled order releases inventory once, adds a status event and audit entry, and notifies its customer. A host that does not run Laravel's scheduler will not expire reservations automatically.

Allowed order changes are `pending_payment → processing → to_ship → shipped → completed`, with cancellation from `pending_payment` or `processing` while unpaid. Pickup orders may move from `processing` directly to `completed` once payment is confirmed. Delivery orders must pass through shipping. Completed, shipped and paid orders cannot be cancelled through the customer flow; a paid cancellation requires a separate refund review. All changes create timeline events. An admin confirms cash payments manually or verifies/rejects a GCash reference. **No real payment is collected by the application.**

Delivery uses the saved/new address against Calapan City, Oriental Mindoro, MIMAROPA. The default demo fees are ₱99 in Calapan, ₱199 elsewhere in Oriental Mindoro, ₱349 elsewhere in MIMAROPA, and ₱499 in other regions. Pickup is free. The amounts, reservation duration and low-stock threshold are in `config/mobile-arena.php` and can be overridden with the `MOBILE_ARENA_*` settings in `.env.example`. Checkout recalculates the fee on the server and stores it on the order.

## Operations history, refunds and notifications

Each new reservation, release, completed sale, staff stock adjustment, and sellable refund return creates an inventory movement with physical and reserved stock before/after values. Staff must enter a reason for a manual stock change. Existing stock at migration time is the opening balance; historical changes before this feature are not reconstructed. Staff can see a product's movements from the operations dashboard and filter staff actions at `/local-admin/audit`.

Completed, paid orders can request one refund within `MOBILE_ARENA_REFUND_WINDOW_DAYS` (7 by default). Staff may review, approve or reject. The recorded amount cannot exceed the order total. An approved refund is marked complete only after staff handle the external money movement. Sellable returns restore all order items only for a full-order refund and only once; damaged and unreturned items do not restore sellable stock. There is no payment gateway or automatic refund transfer.

Customer updates are stored in Laravel's database notifications and can be read at `/account/notifications`. Email is optional: set `MOBILE_ARENA_EMAIL_NOTIFICATIONS=true` and configure a real Laravel mail transport. The default log/array mailers do not send operational email. Database notifications continue to work if email fails. The notification center is in-app only; it does not use live push or WebSockets.

## Catalog discovery and purchase reviews

`/shop` supports shareable query parameters: `q` (or `search`), `category`, `brand`, `condition`, `min_price`, `max_price`, `in_stock=1`, `min_rating=3|4`, and `sort=relevance|newest|price_asc|price_desc|rating|name`. Search covers product name, brand, model, category and condition. Ratings count only published reviews; products without reviews show an honest empty state. Related products use the same category or brand and nearby prices.

A review belongs to one order item. Only the customer who owns a completed order containing that product can submit it. The order-item unique key prevents duplicate reviews of the same purchased line. Customers can edit or remove their own reviews; admins can hide or restore them at `/local-admin/reviews`. Hidden reviews do not affect public ratings. No customer-provided verified-purchase or moderation field is accepted.

Admins can add and edit product details, image references and active/inactive visibility at `/local-admin/products`. Product photos can be HTTPS URLs or paths under `public/images`; the gallery accepts up to six additional references. This phase does not upload files. Inactive products stay in the database for historical orders but disappear from the public catalog. Opening inventory and every later physical stock adjustment remain in the audited inventory ledger; editing catalog details cannot silently change stock.

## Before a real deployment

1. Replace demo inventory with Mobile Arena's actual products, store-owned unit photos, prices and stock. For pre-owned/refurbished devices, use photos of each exact physical unit.
2. Confirm the store's phone number, exact counter/floor location, warranty rules, accepted payments and delivery coverage directly with management.
3. Configure transactional mail for password reset links, secure sessions, HTTPS and an admin account through the optional seeder.
4. Connect an approved payment provider only after business approval; do not treat the prototype payment choices as current store policy.
5. Add image storage (S3/Cloudinary/etc.) and product image uploads if staff will maintain inventory online.
6. Configure production `APP_URL`, database, mail, HTTPS, backups, queues and error logging.
7. Run `php artisan test` before each release. If you switch back to the Vite source workflow, also run `npm install` and `npm run build`.

## Design direction

The UI uses claymorphism: soft raised surfaces, rounded shapes, inset controls, layered shadows and pastel accents. It deliberately keeps condition/warranty information prominent so second-hand and refurbished listings are not visually confused with brand-new stock.
