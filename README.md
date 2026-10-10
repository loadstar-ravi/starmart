# StarMart

StarMart is an online shop built with Laravel 13, MySQL and Tailwind CSS. Customers browse products, fill a cart, check out, pay and follow their orders. Admins manage the catalog, the orders and the customers, and see the sales figures. Everything a customer can do on the website is also available through a JSON API.

## Contents

- [What it does](#what-it-does)
- [Requirements](#requirements)
- [Installation](#installation)
- [Environment settings](#environment-settings)
- [Database: migrations and seeders](#database-migrations-and-seeders)
- [Running the app](#running-the-app)
- [Login details](#login-details)
- [Running the tests](#running-the-tests)
- [API documentation](#api-documentation)
- [Database indexes](#database-indexes)
- [Scaling to 10M products, 50M orders and 100M order items](#scaling-to-10m-products-50m-orders-and-100m-order-items)
- [Architecture](#architecture)
- [Queues and notifications](#queues-and-notifications)
- [Error handling and logging](#error-handling-and-logging)

## What it does

**Customers**

- Register, log in, and update their profile and password.
- Browse products with search, category and price filters, sorting and paging. The list updates without reloading the page.
- Keep a cart. Quantities update without a reload and are checked against the stock on the server.
- Check out with cash on delivery or online payment. Online payment is simulated: no real money moves.
- See their orders and each order's details, and cancel an order while it is placed or confirmed.
- Get an email when an order is placed and whenever its status changes.

**Admins** (under `/admin`)

- Dashboard with totals, orders by payment status, total sales and a chart of the last 30 days.
- Categories and products: create, edit, delete, activate or deactivate, upload images, set stock.
- Orders: list, search, filter, view, and move through placed, confirmed, processing, shipped and delivered, or cancel.
- Users: list, search, and block or unblock customers.
- Sales report for a date range, with daily figures and best-selling products.

**API**: registration, login, profile, products, cart, orders, cancellation and payment. [API documentation](#api-documentation).

## Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.5 |
| Composer | 2 |
| MySQL | ^8.0 |
| Node.js | ^22.12, with npm |

## Installation

```bash
git clone https://github.com/loadstar-ravi/starmart.git
cd starmart

composer install
cp .env.example .env
php artisan key:generate
```

Create an empty database, then put its name and your MySQL login into `.env` (see [Environment settings](#environment-settings)):

```bash
mysql -u root -p -e "CREATE DATABASE starmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Create the tables, load the sample data, link the image folder and build the CSS and JavaScript:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

## Environment settings

All settings live in `.env`. These are the ones that matter for this project:

| Setting | Default | What it is for |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | The address you open the shop at. Image addresses and the links in emails are built from it. |
| `APP_DEBUG` | `true` | Shows full error details. Set it to `false` on a live server, so visitors get the friendly error pages instead. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `127.0.0.1`, `3306`, `starmart`, `root`, empty | Your MySQL connection. |
| `QUEUE_CONNECTION` | `database` | Emails are sent by a queue worker that reads jobs from the `jobs` table. See [Queues and notifications](#queues-and-notifications). |
| `MAIL_MAILER` | `log` | Writes emails into `storage/logs/laravel.log` instead of sending them. Change it to `smtp` and fill in the `MAIL_*` settings to send real email. |
| `SESSION_DRIVER`, `CACHE_STORE` | `database` | Sessions and cache are kept in MySQL, so no extra service is needed. |

Dates and times are stored and shown in UTC.

## Database: migrations and seeders

`php artisan migrate` creates these tables: `users`, `categories`, `products`, `product_images`, `carts`, `cart_items`, `orders`, `order_items`, `payments`, plus the framework's own tables for sessions, cache, queue jobs and API tokens.

`php artisan db:seed` loads the sample data. It is safe to run more than once: rows that already exist are left alone.

| Seeder | What it creates |
| --- | --- |
| `AdminUserSeeder` | One admin account |
| `CatalogSeeder` | 6 categories and 48 products with prices and stock |
| `DatabaseSeeder` | Runs the two above and adds one customer account |

Useful commands:

```bash
php artisan migrate            # create or update the tables
php artisan db:seed            # load the sample data
php artisan migrate:fresh --seed   # drop everything and start again (deletes all data)
```

## Running the app

Open two terminals in the project folder:

```bash
php artisan serve        # the shop, at http://localhost:8000
php artisan queue:work   # sends the emails
```

The shop works without the queue worker, but no emails go out until it runs.

When you change CSS or JavaScript, run `npm run dev` while you work, or `npm run build` once you are done.

`composer dev` is a shortcut that starts the server, a queue listener, a live log viewer and `npm run dev` together.

## Login details

The seeders create these accounts:

| Role | Where to log in | Email | Password |
| --- | --- | --- | --- |
| Admin | `/admin/login` | `admin@starmart.test` | `password` |
| Customer | `/login` | `customer@starmart.test` | `password` |

New customers can register at `/register`.

## Running the tests

```bash
php artisan test
```

The suite has more than 650 tests. They run against an in-memory SQLite database, so they need no setup and never touch your MySQL data. To run part of the suite:

```bash
php artisan test tests/Feature/Services/OrderServiceTest.php
php artisan test --filter=test_cancelling_a_paid_order_refunds_its_payment
```

`vendor/bin/pint` formats the PHP code in the project's style.

## API documentation

**Base address:** `http://localhost:8000/api`

**Headers:** send `Accept: application/json` with every request, and `Content-Type: application/json` when there is a body.

**Authentication:** register or log in to get a token, then send it as `Authorization: Bearer <token>`. Cart, order, payment and profile-update endpoints are for customers only; an admin token gets `403`.

### Endpoints

| Method | Endpoint | Token | What it does |
| --- | --- | --- | --- |
| POST | `/api/register` | No | Create a customer account and get a token |
| POST | `/api/login` | No | Get a token |
| POST | `/api/logout` | Yes | Revoke the token used for the request |
| GET | `/api/user` | Yes | The signed-in user |
| PUT | `/api/user` | Customer | Update name and email |
| PUT | `/api/user/password` | Customer | Change the password |
| GET | `/api/products` | No | List products, with search, filters, sorting and paging |
| GET | `/api/products/{id}` | No | One product with its images |
| GET | `/api/cart` | Customer | The cart |
| POST | `/api/cart` | Customer | Add a product to the cart |
| PUT | `/api/cart/{id}` | Customer | Set the quantity of a cart line |
| DELETE | `/api/cart/{id}` | Customer | Remove a cart line |
| POST | `/api/orders` | Customer | Place an order for everything in the cart |
| GET | `/api/orders` | Customer | The customer's orders, newest first |
| GET | `/api/orders/{id}` | Customer | One order |
| POST | `/api/orders/{id}/cancel` | Customer | Cancel an order |
| POST | `/api/payment/process` | Customer | Pay for an online order (simulated) |

### Status codes

| Code | Meaning | Body |
| --- | --- | --- |
| 200 | Done | The result |
| 201 | Created | The new record |
| 204 | Done, nothing to return | Empty |
| 401 | No token, or the token is not valid | `{"message": "Unauthenticated."}` |
| 402 | The payment was declined | The failed payment |
| 403 | Not allowed for this account | `{"message": "Only customers can do this."}` |
| 404 | Not found, or it belongs to someone else | `{"message": "The requested resource was not found."}` |
| 422 | The input is invalid | `message` and `errors`, one list of messages per field |
| 422 | A shop rule refused the request | `message` only, for example `{"message": "Your cart is empty."}` |
| 429 | Too many requests | `message` |

A validation error looks like this:

```json
{
    "message": "The product field is required. (and 1 more error)",
    "errors": {
        "product_id": ["The product field is required."],
        "quantity": ["The quantity field is required."]
    }
}
```

### Accounts

**`POST /api/register`**

```json
{
    "name": "Ravi Kumar",
    "email": "ravi@starmart.com",
    "password": "Password@123",
    "password_confirmation": "Password@123"
}
```

The password needs at least 8 characters. Answers `201`:

```json
{
    "data": {
        "id": 1,
        "name": "Ravi Kumar",
        "email": "ravi@starmart.com",
        "role": "customer",
        "created_at": "2026-10-10T10:30:00+00:00"
    },
    "token": "1|rW8Mx6JF9x7XxkRV7JZAXQ6E2kzNnvdyJVKime7d7c95a888",
    "token_type": "Bearer"
}
```

**`POST /api/login`** takes `email` and `password` and answers `200` with the same body as registration.

- `401`: wrong email or password.
- `403`: the account has been blocked by an admin.
- `429`: more than 5 attempts in a minute for the same email and IP address.

**`POST /api/logout`** answers `204`.

**`GET /api/user`** answers `200` with the user under `data`.

**`PUT /api/user`** takes `name` and `email` and answers `200` with the updated user and `"message": "Profile updated."`. The email must not belong to another account.

**`PUT /api/user/password`**

```json
{
    "current_password": "Password@123",
    "password": "NewPassword@456",
    "password_confirmation": "NewPassword@456"
}
```

Answers `200` with `{"message": "Password changed."}`. The current password must be right, and the new one must differ from it. Limited to 6 requests a minute.

### Products

**`GET /api/products`** lists the products customers can buy. Every query parameter is optional:

| Parameter | Value |
| --- | --- |
| `search` | Part of the product name |
| `category` | The slug of a category, for example `electronics` |
| `min_price`, `max_price` | A price range |
| `sort` | `newest` (default), `price_asc` or `price_desc` |
| `per_page` | 1 to 48, default 12 |
| `page` | The page number |

Answers `200` with the products under `data`, and the paging details under `links` and `meta` (`current_page`, `last_page`, `per_page`, `total`).

**`GET /api/products/{id}`** answers `200`:

```json
{
    "data": {
        "id": 8,
        "name": "Logitech M235 Wireless Mouse",
        "slug": "logitech-m235-wireless-mouse",
        "description": "Logitech M235 Wireless Mouse from our Electronics range.",
        "price": "799.00",
        "stock": 75,
        "in_stock": true,
        "category": { "id": 1, "name": "Electronics", "slug": "electronics" },
        "image_url": null,
        "images": [],
        "created_at": "2026-10-10T10:30:00+00:00"
    }
}
```

`image_url` is the address of the main image, or `null` when the product has none. The list leaves `images` out. A product that is inactive, or in an inactive category, answers `404`.

### Cart

Every cart endpoint answers with the whole cart, so the client always has the current lines and totals.

**`POST /api/cart`** adds a product. Adding a product that is already in the cart raises its quantity.

```json
{ "product_id": 8, "quantity": 1 }
```

Answers `201`:

```json
{
    "data": {
        "items": [
            {
                "id": 1,
                "quantity": 1,
                "line_total": "799.00",
                "is_purchasable": true,
                "product": { "id": 8, "name": "Logitech M235 Wireless Mouse", "price": "799.00", "stock": 75, "in_stock": true }
            }
        ],
        "total_quantity": 1,
        "total": "799.00"
    }
}
```

`product` holds the same fields as in the product list; the example shows only some of them. `is_purchasable` turns `false` when the product is no longer available or the quantity is above the stock. Such lines are left out of `total`.

**`GET /api/cart`** answers `200` with the cart.

**`PUT /api/cart/{id}`** sets the quantity of a line. `{id}` is the `id` of the cart line (`items[].id`), not of the product.

```json
{ "quantity": 2 }
```

**`DELETE /api/cart/{id}`** removes a line.

- `422`: the product is not available, or there is not enough stock, for example `{"message": "\"Logitech M235 Wireless Mouse\" has only 74 in stock."}`.
- `404`: the line is not in your cart.

### Orders

**`POST /api/orders`** turns the cart into an order.

```json
{
    "name": "Ravi Kumar",
    "mobile": "9876543210",
    "email": "ravi@starmart.com",
    "address": "Bank Avenue",
    "city": "Bengaluru",
    "state": "Karnataka",
    "pincode": "560043",
    "payment_method": "cod"
}
```

`mobile` is 10 digits starting with 6 to 9, `pincode` is 6 digits, and `payment_method` is `cod` or `online`. Prices and the total are taken from the products on the server; a price sent by the client is ignored. Answers `201`:

```json
{
    "data": {
        "id": 1,
        "order_number": "ORD-10001",
        "status": "placed",
        "payment_status": "pending",
        "payment_method": "cod",
        "total_amount": "1598.00",
        "shipping_address": {
            "name": "Ravi Kumar",
            "mobile": "9876543210",
            "email": "ravi@starmart.com",
            "address": "Bank Avenue",
            "city": "Bengaluru",
            "state": "Karnataka",
            "pincode": "560043"
        },
        "items": [
            {
                "id": 1,
                "product_id": 8,
                "product_name": "Logitech M235 Wireless Mouse",
                "unit_price": "799.00",
                "quantity": 2,
                "line_total": "1598.00"
            }
        ],
        "created_at": "2026-10-10T10:30:00+00:00"
    },
    "message": "Order ORD-10001 placed."
}
```

Placing an order reduces the stock, records a pending payment and empties the cart. An empty cart, or a product that ran out, answers `422`.

| Field | Values |
| --- | --- |
| `status` | `placed`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled` |
| `payment_status` | `pending`, `success`, `failed`, `refunded` |
| `payment_method` | `cod`, `online` |

**`GET /api/orders`** answers `200` with the customer's orders, newest first, with `links` and `meta` for paging. `per_page` is 1 to 50, default 10.

**`GET /api/orders/{id}`** answers `200` with one order. Another customer's order answers `404`.

**`POST /api/orders/{id}/cancel`** cancels an order that is still `placed` or `confirmed`. The stock goes back, and a paid order becomes `refunded`. Answers `200` with the order and a `message`.

- `422`: the order is already processing, shipped, delivered or cancelled, for example `{"message": "Order ORD-10001 is already cancelled."}`.

### Payment

**`POST /api/payment/process`** pays for an order placed with `"payment_method": "online"`.

```json
{ "order_id": 2, "amount": "799.00", "simulate": "failed" }
```

`amount` must be the order total. `simulate` is optional and exists so both results can be tried: leave it out or send `success` for a payment that goes through, send `failed` for one the bank declines.

Answers `200` when the payment goes through:

```json
{
    "data": {
        "id": 3,
        "order_id": 2,
        "order_number": "ORD-10002",
        "amount": "799.00",
        "status": "success",
        "transaction_reference": "TXN-TXWKL7WPQNC1D4MM",
        "failure_reason": null,
        "paid_at": "2026-10-10T10:30:00+00:00"
    },
    "message": "Payment successful."
}
```

- `402`: the payment was declined. The body is the failed payment, with `"status": "failed"` and a `failure_reason`. The order stays unpaid and can be paid again.
- `422`: the amount is wrong, the order is cash on delivery, or it is already paid, refunded or cancelled.
- `404`: the order is not yours.

## Database indexes

Every index below serves a query the app really runs. The single-column indexes on `order_id` and `product_id` are the ones MySQL creates for the foreign keys.

| Table | Index | The query it serves |
| --- | --- | --- |
| `users` | `email` (unique) | Finding the user at login; keeping emails unique at registration and in the profile |
| `categories` | `slug` (unique) | The category filter of the product list; keeping slugs unique |
| `products` | `slug` (unique) | Opening a product page by its slug |
| `products` | `(category_id, status)` | Listing the active products of one category; also the admin's category filter |
| `products` | `(status, price)` | Sorting by price and filtering by a price range among active products |
| `products` | `(status, created_at)` | The default "newest first" list and the new arrivals on the home page |
| `carts` | `user_id` (unique) | Finding a customer's cart; one cart per customer |
| `cart_items` | `(cart_id, product_id)` (unique) | Finding the line when a product is added again; one line per product |
| `orders` | `order_number` (unique) | Opening an order page by its number |
| `orders` | `(user_id, created_at)` | "My orders", newest first, and a customer's orders on the admin user page |
| `orders` | `(status, created_at)` | The admin order list filtered by status, newest first |
| `orders` | `payment_status` | The admin filter by payment status and the dashboard's count per payment status |
| `orders` | `created_at` | The admin order list, newest first, and the date range of the sales report |
| `order_items` | `order_id` | The lines of an order |
| `order_items` | `product_id` | Keeping order lines when a product is deleted |
| `payments` | `order_id` | The payment attempts of an order |
| `payments` | `transaction_reference` (unique) | No two payments share a reference |
| `product_images` | `product_id` | The images of a product |

The composite indexes start with the column that is compared with `=` and end with the column that is sorted or ranged, so one index both narrows the rows and returns them in order.

Two things are deliberately not indexed:

- **`products.name`**. The search uses `LIKE '%term%'`, which a normal index cannot serve. The next section says what replaces it at scale.
- **`users.role`** and **`products.stock`**. They have too few distinct values, or change too often, for an index to pay off at this size.

## Scaling to 10M products, 50M orders and 100M order items

### What already holds up

These queries touch a handful of rows through an index, however large the tables get:

- A product page (by `slug`), an order page (by `order_number`) and login (by `email`) are single-row lookups on unique indexes.
- "My orders" reads one customer's rows through `(user_id, created_at)`. A customer has few orders, so 50M orders in total do not slow it down.
- The lines of an order are read through `order_items.order_id`. Each order has a few lines, so 100M lines in total do not matter.
- An order line stores the product's name and price at the time of purchase. Order pages never join the 10M-row `products` table.
- Checkout locks only the product rows in the cart, by primary key, inside one short transaction.

### What would have to change, in the order it would start to hurt

1. **Product search.** `LIKE '%term%'` reads every active product. At 10M rows, replace it with a MySQL `FULLTEXT` index on the name (with the ngram parser, so parts of words still match), or with a search engine such as Meilisearch through Laravel Scout, kept up to date by queued jobs.
2. **Paging.** `paginate()` runs a `COUNT(*)` over all matching rows and uses `OFFSET`, which reads and throws away every earlier row. Switch the product list and the admin lists to cursor paging (`cursorPaginate()`, "after this price and id"), and show "more than 1,000 results" instead of an exact count.
3. **Dashboard and sales report.** They add up `orders` on every page load. At 50M orders, keep a summary table with one row per day (orders, sales) and one per day and product (units, sales). The `OrderPlaced` and `OrderStatusChanged` events already exist; listeners on them, and on payments, would update the summaries, and the pages would read a few hundred small rows.
4. **Admin search.** Searching orders by part of a number or of a customer name has the same `LIKE '%term%'` problem. Match the order number exactly or by its start (`LIKE 'ORD-1000%'` can use the index), and search customers through the search engine from step 1.
5. **Sessions, cache and queue in MySQL.** They add writes to the same database as the orders. Move them to Redis, and run the queue with Laravel Horizon and several workers.
6. **Read load.** Send the product list, the reports and other read-only pages to read replicas, using Laravel's read/write connection setting, and keep writes on the primary.
7. **Table size.** Move orders older than a few years, with their lines and payments, to archive tables, so the live tables and their indexes stay small enough to fit in memory. Partitioning `orders` and `order_items` by month is the heavier alternative: MySQL partitioned tables cannot have foreign keys and need the partition column in every unique index, so it trades database-checked integrity for size.
8. **Wider indexes for the remaining hot queries**, chosen from the slow query log and `EXPLAIN` on real data. For example, `(payment_status, created_at, total_amount)` lets the daily sales query be answered from the index alone.

Product images would move from the local disk to object storage behind a CDN. The code already reads and writes them through Laravel's storage layer, so only the configuration of the `public` disk changes.

## Architecture

### How a request flows

```text
Route
  → Middleware        who are you, and may you be here (auth, admin, customer, blocked)
  → Form Request      is the input valid
  → Controller        read the request, call one service, shape the response
  → Service           the shop's rules, inside a database transaction
  → Model             tables, relationships, casts, scopes
  → Event             "an order was placed", "an order's status changed"
  → Listener → Job / Notification    emails, sent by the queue
```

### Where things are

| Folder | What is in it |
| --- | --- |
| `app/Http/Controllers` | Website controllers for customers; `Admin/` for the admin panel; `Api/` for the JSON API |
| `app/Http/Requests` | Validation rules, one class per form |
| `app/Http/Resources` | The JSON shape of each model in the API |
| `app/Http/Middleware` | `admin`, `customer` and the check that logs out blocked users |
| `app/Services` | The shop's rules: cart, orders, payments, catalog, users, sales reports |
| `app/Models` | Eloquent models with their relationships, casts and query scopes |
| `app/Enums` | Order status, payment status, payment method, product status, user role |
| `app/Policies` | Who may see or change an order, and who may be blocked |
| `app/Events`, `app/Listeners` | What happens after an order is placed or changes status |
| `app/Jobs`, `app/Notifications` | The queued confirmation job and the two emails |
| `app/Exceptions` | Refusals with a message for the user: cart, order, payment |
| `resources/views` | Blade pages and components; `resources/js` holds small scripts for the cart, the filters and the chart |
| `tests` | Feature tests for every endpoint, service and rule; unit tests for pure logic |

### The decisions behind it

- **One service per feature, two thin controllers on top.** The website controller and the API controller for the cart both call `CartService`; the same goes for orders, payments and the profile. A rule such as "never more than the stock" is written once, so the website and the API cannot drift apart.
- **Controllers only translate.** They turn a request into a service call and the result into a redirect with a message or a JSON response. They hold no shop rules, which keeps them short and the rules testable without HTTP.
- **Refusals are exceptions with a customer-ready message.** `CartException`, `OrderException` and `PaymentException` carry texts like `"Gaming Laptop" has only 3 in stock.` The website shows the message as a flash notice; the API returns it with status 422.
- **Money and stock are decided on the server.** Prices come from the `products` table at the moment of ordering, totals are added up on the server, and a payment must match the order total. Nothing about price is trusted from the request.
- **Order lines copy the product's name and price.** An order stays correct when the product is later repriced, renamed or deleted.
- **Every multi-step change is one transaction with row locks.** Placing an order, cancelling it, changing its status and paying for it each lock the rows they change. Two customers cannot buy the same last unit, and an order cannot be paid or cancelled twice. Products are always locked in id order, so two checkouts cannot deadlock.
- **Status rules live in the enum.** `OrderStatus` knows which statuses may follow which, and until when an order can be cancelled. The service, the admin page and the customer page all ask the same enum.
- **Roles by middleware, ownership by policy.** `admin` and `customer` middleware decide which area a user may enter. `OrderPolicy` decides whether an order is yours; someone else's order answers 404, not 403, so order numbers cannot be probed.
- **Sensitive columns cannot be mass-assigned.** `role` and `blocked_at` are not fillable, so no request can promote a user or lift a block.
- **Side effects happen after the commit, through events.** The service announces `OrderPlaced` and `OrderStatusChanged` only once the data is saved. Listeners send the emails on the queue, so a mail problem can never undo or delay an order.
- **Server-rendered pages with small scripts on top.** Pages are Blade templates styled with Tailwind. The cart, the product filters and the sales chart are enhanced with plain JavaScript and still work without it.

## Queues and notifications

### What runs on the queue

| When | What happens | Class |
| --- | --- | --- |
| An order is placed | The `OrderPlaced` event fires; a listener dispatches the job that emails the confirmation ("Order ORD-10001 Confirmed") | `SendOrderConfirmation` → `SendOrderConfirmationJob` → `OrderConfirmed` |
| An order's status changes or it is cancelled | The `OrderStatusChanged` event fires; a queued listener emails the customer ("Your order ORD-10001 has been shipped") | `SendOrderStatusNotification` → `OrderStatusUpdated` |

Both events are dispatched only after the database transaction has committed, so a worker never picks up a job for an order that was rolled back.

### Configuration

- **Connection.** `QUEUE_CONNECTION=database`: jobs wait in the `jobs` table. Nothing else needs to be installed. `config/queue.php` holds the details.
- **Worker.** `php artisan queue:work` runs jobs as they arrive. On a server, keep it running with Supervisor or systemd, and run `php artisan queue:restart` after each deploy so the worker loads the new code. While developing, `php artisan queue:listen` reloads the code for every job.
- **Tests** use `QUEUE_CONNECTION=sync`, so queued work runs at once and needs no worker.

### Retries

Both the job and the queued listener declare their own settings with attributes on the class:

```php
#[Tries(3)]
#[Backoff(10, 60)]
#[Timeout(30)]
```

- **3 tries.** A failed attempt is tried again, up to three attempts in total.
- **Backoff.** The second attempt waits 10 seconds, the third waits 60 seconds. This gives a mail server time to recover.
- These class settings take priority over the `--tries` and `--backoff` options of the worker.

### Timeout

- An attempt may run for 30 seconds. The worker stops one that takes longer and counts it as failed. Enforcing this needs PHP's `pcntl` extension, which Linux servers normally have.
- `retry_after` for the database queue is 90 seconds. It is how long the queue waits before it assumes a job was lost and hands it to another worker. It must stay longer than the timeout, so a job is never run twice at the same time.

### Failed jobs

- After the third failed attempt the job is moved to the `failed_jobs` table with its error.
- Its `failed()` method writes an error to the log with the order id and number, so there is a record that the customer was not told.
- To deal with failed jobs:

```bash
php artisan queue:failed         # list them
php artisan queue:retry all      # put them back on the queue (or pass one id)
php artisan queue:forget <id>    # delete one
php artisan queue:flush          # delete all
```

## Error handling and logging

**What users see**

- The website has its own pages for 403, 404, 419 (expired form), 429, 500 and 503, and a general page for any other error. Each has a link back to the home page, or to the admin dashboard inside the admin panel.
- The API always answers errors as JSON with a `message`.
- With `APP_DEBUG=false`, no file names, queries or stack traces are shown. An unexpected error shows "Something went wrong" on the website and `{"message": "Server Error"}` in the API, and the full error goes to the log.
- Refusals by a shop rule are not errors: the user gets a plain message, for example "Order ORD-10001 is shipped and can no longer be cancelled."

**What is logged** (in `storage/logs/laravel.log`)

| Level | Events |
| --- | --- |
| info | Order placed, order cancelled, order status updated, payment succeeded, product stock updated, user blocked or unblocked, password changed |
| notice | Order refused, cancellation refused, status change refused, payment refused |
| warning | Payment failed |
| error | A confirmation or status email could not be sent after all retries; any unexpected exception |

Each entry carries the ids needed to trace it, such as the order id, order number, user id, amount and reason.
