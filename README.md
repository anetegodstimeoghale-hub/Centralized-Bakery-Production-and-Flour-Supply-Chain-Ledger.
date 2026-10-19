# Centralized Bakery Production and Flour Supply Chain Ledger

A small but complete **PHP 8 + MySQL + Bootstrap + Chart.js** web application that manages a
bakery's daily operations: outlets and their demand, flour suppliers and orders, production
planning with a mandatory 10% buffer, waste control, morning/evening deliveries, a flour
reorder point, an immutable production audit log and a Chart.js management dashboard.

The project is designed to run **locally with Laragon on Windows**, uses **PDO prepared
statements** everywhere, never calls `fetchAll()` (all result sets are read **row by row**),
and contains a clearly labelled **IDOR vulnerability demonstration together with its patch**.

---

## 1. Bakery operational context

A central bakery bakes bread in one production facility and pushes it to several retail
outlets during the day. The operation has four connected problems:

1. **Demand.** Every outlet declares how many units it can sell per day. The plant must bake
   *all* of that plus a buffer, otherwise an outlet runs out of bread before closing.
2. **Supply.** Flour is the main raw material. Flour arrives from suppliers after a lead time
   of 1–5 days, so the bakery must reorder **before** it runs out, not after.
3. **Production.** A production run must be planned once per day: how many units to bake, how
   many were really baked, and how many were wasted.
4. **Control.** Management needs one screen (dashboard) that shows demand, production, waste,
   flour stock and reorder status, and a permanent audit trail of every planning decision.

This application implements exactly that flow:

```
OUTLETS (demand) ──► PRODUCTION PLAN (demand × 1.10) ──► PRODUCTION RUN (actual + waste)
                                   ▲                            │
                                   │                            ▼
SUPPLIERS ──► FLOUR ORDERS ──► FLOUR ON HAND ──► FLOUR USAGE ──► WASTE %
      └──── REORDER POINT ◄──────────────────────────────────────┘
                                   │
                                   ▼
                     DELIVERIES (morning + evening)
```

---

## 2. Project structure

```
bakey-ledgerr/
│
├── index.php                 FRONT CONTROLLER / router (single entry point)
├── router.php                OPTIONAL router for PHP's built-in dev server
├── .htaccess                 Apache rewrite rules (clean URLs / endpoints)
├── README.md                 this file
│
├── config/
│   ├── config.php            database credentials + business constants
│   └── database.php          PDO connection + streaming query helpers
│
├── database/
│   └── bakery.sql            COMPLETE schema + indexes + triggers + sample data
│
├── includes/
│   ├── init.php              bootstrap (required by every page)
│   ├── functions.php         validation, formatting, THE FORMULAS, audit log
│   ├── auth.php              demo users + can_access_outlet() (the IDOR patch)
│   ├── router.php            route -> PHP file resolver
│   ├── header.php            HTML head, shared TOP navigation menu, title bar, flashes
│   └── footer.php            closing HTML + JavaScript
│
├── outlets/                  OUTLET REGISTRY
│   ├── index.php             list + registration form
│   ├── register.php          POST /outlets/register
│   └── view.php              SECURE single-outlet demand view (patched IDOR)
│
├── suppliers/                SUPPLIER REGISTRY
│   ├── index.php             list + registration form
│   └── register.php          POST /suppliers/register
│
├── production/               PRODUCTION PLANNING
│   ├── index.php             plan form + run history + waste status
│   └── run.php               POST /production/run  (idempotent)
│
├── flour/                    FLOUR SUPPLY & USAGE
│   ├── index.php             reorder status + order list + usage list
│   ├── order.php             POST /flour/order
│   └── usage.php             POST /flour/usage
│
├── deliveries/               MORNING / EVENING DELIVERIES
│   ├── index.php             form + daily summary + history
│   └── save.php              POST /deliveries/save
│
├── dashboard/
│   └── index.php             statistics + Chart.js line & bar charts
│
├── audit/
│   └── index.php             append-only production audit log
│
├── security/                 IDOR LAB
│   ├── index.php             explanation + code comparison + demo buttons
│   ├── idor_vulnerable.php   INTENTIONALLY BROKEN (exploit)
│   ├── idor_secure.php       PATCHED version
│   └── switch.php            demo user switcher
│
├── docs/
│   └── system-diagram.md     hand-drawn diagram instructions + reference drawing
│
└── assets/
    ├── css/style.css         layout + cards + badges
    ├── js/dashboard.js       Chart.js rendering
    └── vendor/               Bootstrap CSS/JS + Chart.js (local, offline ready)
```

---

## 3. Installation (Laragon on Windows)

### 3.1 Requirements

| Component | Version |
|-----------|---------|
| PHP       | 8.0 or newer (Laragon ships 8.2/8.3) with `pdo_mysql` enabled |
| MySQL     | 5.7 / 8.x / MariaDB 10.x |
| Web server| Apache (bundled with Laragon) |
| Browser   | Chrome / Edge / Firefox |

No Composer, no framework, no npm. Everything the browser needs is inside `assets/vendor/`.

### 3.2 Copy the project

1. Open the Laragon folder → `www` directory (default `C:\laragon\www`).
2. Copy this whole folder into it so you get:
   `C:\laragon\www\bakey-ledgerr\index.php`

### 3.3 Start Laragon

1. Start **Laragon**.
2. Click **Start all** (Apache + MySQL must both be green).
3. If PHP is missing from the menu: `Menu → PHP → version → ...` (Laragon will download it),
   then `Menu → Reload`.

### 3.4 Create the database and import `database/bakery.sql`

**Option A – phpMyAdmin (easiest)**

1. Open <http://localhost/phpmyadmin>.
2. Click the **Import** tab.
3. **Choose File** → `C:\laragon\www\bakey-ledgerr\database\bakery.sql`.
4. Press **Go**.

The script creates the database `bakery_ledger` by itself, builds all seven tables,
the indexes, the two audit triggers and inserts the sample data.

**Option B – MySQL CLI**

```bat
C:\laragon\bin\mysql\mysql-8.x.x-winx64\bin\mysql.exe -u root < C:\laragon\www\bakey-ledgerr\database\bakery.sql
```

### 3.5 Check the database credentials

`config/config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'bakery_ledger');
define('DB_USER', 'root');
define('DB_PASS', '');          // Laragon default password is EMPTY
```

Laragon's default MySQL user is `root` with an **empty** password. If you changed it,
update `DB_PASS`.

### 3.6 Run the application

Open one of these addresses:

```
http://localhost/bakey-ledgerr/
http://localhost/bakey-ledgerr/index.php
```

You should see the **Dashboard**.

### 3.7 (Optional) run without Apache

The bundled `router.php` reproduces the `.htaccess` behaviour:

```bat
cd C:\laragon\www\bakey-ledgerr
C:\laragon\bin\php\php-8.x\php.exe -S localhost:8000 router.php
```

then open <http://localhost:8000/>.

---

## 4. Required endpoints

| Method | Endpoint | Handler file | What it does |
|--------|----------|--------------|--------------|
| POST | `/outlets/register`   | `outlets/register.php`   | registers an outlet |
| POST | `/production/run`     | `production/run.php`     | creates/updates one production plan (idempotent) |
| POST | `/flour/order`        | `flour/order.php`        | places a flour order |
| POST | `/suppliers/register` | `suppliers/register.php` | registers a flour supplier |
| POST | `/flour/usage`        | `flour/usage.php`        | records flour consumed by a run |
| POST | `/deliveries/save`    | `deliveries/save.php`    | records a morning/evening delivery |

**How the routing works**

* `.htaccess` forwards every request that is not a real file/folder to `index.php?route=/...`
* `index.php` (front controller) calls `resolve_route()` in `includes/router.php`
* `resolve_route('/outlets/register')` → `outlets/register.php`

```php
// includes/router.php
$candidates = [
    APP_ROOT . '/' . $route . '.php',        // /outlets/register
    APP_ROOT . '/' . $route . '/index.php',  // /dashboard
];
```

Forms build their address with `endpoint()`:

```php
// outlets/index.php
<form method="post" action="<?= e(endpoint('/outlets/register')) ?>">
```

If your Apache ever answers **404** when you submit a form, set
`define('USE_CLEAN_URLS', false);` in `config/config.php` – the same endpoints are then
reached through `index.php?route=/outlets/register` (no rewrite needed).

---

## 5. The formulas (memorise these for the defence)

### 5.1 Planned production — the 10% buffer (MANDATORY)

```
planned production = total outlet demand × 1.10
```

```
total outlet demand = Σ daily_demand_units   (all outlets)

example:  1,000 × 1.10 = 1,100 units
```

```php
// includes/functions.php
function planned_production(int $totalDemand): int
{
    return (int) ceil($totalDemand * PRODUCTION_BUFFER_RATE);   // 1.10
}
```

`planned_units` is **never** taken from the browser – `production/run.php` recalculates it
on every submission, so a user cannot submit a fake plan.

### 5.2 Waste percentage

```
waste % = waste units ÷ actual production units × 100
```

```
example:  60 ÷ 1,040 × 100 = 5.77%  →  greater than 5%  →  INVESTIGATION REQUIRED
```

```php
function waste_percent(int $wasteUnits, int $actualUnits): ?float
{
    if ($actualUnits <= 0) return null;                 // never divide by zero
    return round(($wasteUnits / $actualUnits) * 100, 2);
}

function waste_investigation_required($percent): bool
{
    return $percent !== null && $percent > WASTE_ALERT_PERCENT;   // 5.0
}
```

### 5.3 Flour reorder point (with the 20% safety factor)

```
reorder point = daily flour usage × supplier lead time (days) × 1.20
```

```
example:  604.286 kg/day × 2 days × 1.20 = 1,450.69 kg
          flour on hand 1,244.95 kg  <  1,450.69 kg  →  REORDER REQUIRED
```

```php
function reorder_point(float $dailyUsageKg, int $leadTimeDays): float
{
    return round($dailyUsageKg * $leadTimeDays * REORDER_SAFETY_RATE, 3);   // 1.20
}
```

Daily flour usage is the average of the last `FLOUR_USAGE_WINDOW_DAYS` (7) production days:

```
daily flour usage = SUM(flour used in window) ÷ number of production days in the window
```

The bakery keeps **one pooled flour stock**, so the same "flour on hand" is compared with
every supplier's own reorder point (their lead times are different — a supplier with a long
lead time needs a higher point).

### 5.4 Money in kobo

```
1 Naira = 100 kobo

total_cost_kobo = quantity_kg × unit_price_kobo        (integer arithmetic)
display         = total_cost_kobo / 100                (only when printing)
revenue_kobo    = units_delivered × outlet unit price  (kobo)
```

Money is stored as **integers** (`INT UNSIGNED` / `BIGINT UNSIGNED`). No `FLOAT`/`DOUBLE`
money values exist anywhere in the schema.

---

## 6. Database structure

File: **`database/bakery.sql`**

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `outlets` | outlet registry | `name` (unique), `daily_demand_units`, `unit_price_kobo` |
| `suppliers` | flour suppliers | `name` (unique), `lead_time_days` |
| `production_runs` | one plan per day | `production_date` **UNIQUE**, `total_demand_units`, `planned_units`, `actual_units`, `waste_units`, `buffer_rate` |
| `production_audit_log` | append-only history | FK `production_run_id` **ON DELETE RESTRICT**, `action`, `old_values`, `new_values` |
| `flour_orders` | flour purchasing | FK `supplier_id`, `quantity_kg DECIMAL(12,3)`, `unit_price_kobo`, `total_cost_kobo`, `ordered_at`, `delivered_at` |
| `flour_usage` | flour consumed | FK `production_run_id`, `flour_kg DECIMAL(12,3)` |
| `deliveries` | morning/evening runs | FK `outlet_id`, `production_date`, `session_name ENUM('morning','evening')`, `units_delivered`, `revenue_kobo` |

### Keys, indexes and constraints

```sql
PRIMARY KEY (id)                                     -- every table
UNIQUE KEY uq_outlets_name (name)                    -- no duplicate outlet names
UNIQUE KEY uq_production_date (production_date)      -- IDEMPOTENCY GUARANTEE
UNIQUE KEY uq_delivery_slot (outlet_id, production_date, session_name)

-- REQUIRED COMPOSITE INDEX
KEY idx_outlet_production_date (outlet_id, production_date)   -- on `deliveries`

FOREIGN KEY supplier_id      REFERENCES suppliers(id)          ON DELETE RESTRICT
FOREIGN KEY production_run_id REFERENCES production_runs(id)   ON DELETE RESTRICT (audit)
FOREIGN KEY production_run_id REFERENCES production_runs(id)   ON DELETE CASCADE  (usage)
FOREIGN KEY outlet_id        REFERENCES outlets(id)            ON DELETE CASCADE
```

**Why the composite index `(outlet_id, production_date)`?**
It answers the delivery query *"all deliveries of one outlet on one date"* with one index
scan instead of reading the whole table (the unique slot key has the same leading columns,
and the dedicated index keeps the required design explicit).

**Why `DECIMAL` for flour?**
`DECIMAL(12,3)` stores 1500.500 kg exactly. A `FLOAT` would store 1500.4999… and the stock
total would slowly drift.

---

## 7. Idempotent production planning

Requirement: *"Repeated submission of the same production planning request must not create
duplicate production plans."*

Three mechanisms work together:

1. **Database level** – `UNIQUE KEY uq_production_date (production_date)`. Two rows for the
   same day are physically impossible.
2. **Application level** – `production/run.php` first reads the existing row inside a
   transaction:

   ```php
   $existing = ... "SELECT ... FROM production_runs WHERE production_date = ? FOR UPDATE";

   if (!$existing)                    -> INSERT  + audit "CREATE"
   elseif (same values)               -> DO NOTHING (flash: "Idempotent request ignored")
   else                               -> UPDATE  + audit "UPDATE"
   ```
3. **UI level** – `planned_units` is recalculated from the current total demand, so the same
   request always produces the same numbers.

Submitting the same form 10 times leaves exactly **one** production plan and (after the
first identical one) creates **no further audit rows**.

---

## 8. Security / IDOR demonstration

### 8.1 What IDOR is

Insecure Direct Object Reference = the application uses an identifier that comes from the
user (here `?outlet_id=3`) **without checking whether the signed-in user is allowed to see
that object**. It is OWASP's *Broken Object Level Authorization* (OWASP API1:2023 /
OWASP Top 10 A01:2021).

### 8.2 The deliberately vulnerable file — `security/idor_vulnerable.php`

```php
// NO authorisation check at all
$outletId = (int) ($_GET['outlet_id'] ?? 0);

$stmt = $pdo->prepare('SELECT name, daily_demand_units FROM outlets WHERE id = ?');
$stmt->execute([$outletId]);
```

It still uses a **prepared statement** — that is the point of the exercise: a prepared
statement stops SQL injection, **not** broken access control.

### 8.3 The patched file — `security/idor_secure.php` (and `outlets/view.php`)

```php
$outletId = (int) ($_GET['outlet_id'] ?? 0);

// *** THE PATCH: authorisation BEFORE the query ***
require_outlet_access($outletId);      // -> can_access_outlet() in includes/auth.php

$stmt = $pdo->prepare('SELECT name, daily_demand_units FROM outlets WHERE id = ?');
$stmt->execute([$outletId]);
```

```php
// includes/auth.php
function can_access_outlet(int $outletId): bool
{
    $user = current_user();
    if ($user['outlet_ids'] === null) return true;              // manager
    return in_array($outletId, $user['outlet_ids'], true);      // cashier
}
```

### 8.4 How to demonstrate it in 30 seconds

1. Open **Security / IDOR** in the top navigation menu.
2. Click *Act as Cashier of outlet 1 (Igando)*.
3. Click **Run exploit (outlet 3)** → the page shows **DATA LEAKED!** and the demand of
   outlet 3, even though this user only owns outlet 1.
4. Click **Send the SAME request to the patched version** → **HTTP 403 FORBIDDEN**, no data.
5. Click *Act as Manager* → both URLs are allowed (a manager really may see everything).

The rest of the application only uses the patched helper
(`outlets/view.php`, `deliveries/save.php`). `security/idor_vulnerable.php` is the single
file that skips the check, and it is clearly labelled.

### 8.5 Other security measures in the project

| Threat | Measure |
|--------|---------|
| SQL injection | PDO prepared statements with `ATTR_EMULATE_PREPARES = false` everywhere |
| XSS | every printed value goes through `e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` |
| Mass assignment / fake plans | server-side validation (`input_int`, `input_text`, `input_date`, …); `planned_units` is recalculated |
| Path traversal in the router | route must match `^[a-zA-Z0-9_\-/]+$`, must not contain `..`, resolved file must be inside the project |
| History tampering | audit log: no UI + `BEFORE UPDATE`/`BEFORE DELETE` triggers + `ON DELETE RESTRICT` |
| Session | `session_start()` in `includes/init.php`; the demo user switcher only accepts whitelisted keys |

*Not included on purpose (kept beginner-friendly):* CSRF tokens, password hashing/login,
rate limiting. These are listed in *Deployment preparation* below.

---

## 9. Dashboard

`dashboard/index.php` + `assets/js/dashboard.js`

**Stat cards:** number of outlets · total daily demand · planned production (+10%) ·
actual production · waste (units + %) · flour usage (kg/day) · flour on hand ·
flour reorder status.

**Charts (Chart.js, loaded from `assets/vendor/chart.umd.min.js`):**

1. **Line chart** – total demand vs planned production vs actual production over the last
   14 production runs (this is the required *"production versus demand over time"* chart).
2. **Bar chart** – kilograms of flour used per production day.

The data travels from PHP to JavaScript as JSON:

```php
<script type="application/json" id="bakery-chart-data"><?= json_encode($chartData) ?></script>
```

```js
var data = JSON.parse(document.getElementById('bakery-chart-data').textContent);
new Chart(document.getElementById('productionChart').getContext('2d'), { type: 'line', ... });
```

---

## 10. Streaming database queries (no `fetchAll()`)

Every list in the application reads the result **row by row**:

```php
$stmt = stream_query('SELECT id, name, daily_demand_units FROM outlets ORDER BY id');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {      // one row in memory at a time
    echo ...;
}
```

`stream_query()` (in `config/database.php`) prepares + executes and returns the
`PDOStatement`; the caller iterates it. `fetchAll()` appears **only inside comments** that
explain it is never used.

The only place where rows are collected into arrays is the Chart.js JSON block, because a
chart cannot be drawn while the data is still streaming — and that block is bounded with
`LIMIT 14`.

---

## 11. Audit log design

```
production_runs  ──INSERT/UPDATE──►  production_audit_log (append only)
```

`write_audit_log()` in `includes/functions.php` writes one row for every `CREATE` and
`UPDATE` of a production run, with the old values, the new values and who did it.

**Normal users cannot edit or delete history:**

1. There is **no update/delete code** for this table anywhere in the application, and the
   audit page has no edit/delete buttons.
2. Two triggers refuse the statements at database level:

   ```sql
   CREATE TRIGGER trg_audit_no_update BEFORE UPDATE ON production_audit_log ...
       SIGNAL SQLSTATE '45000'
       SET MESSAGE_TEXT = 'production_audit_log is APPEND ONLY: UPDATE is not allowed';

   CREATE TRIGGER trg_audit_no_delete BEFORE DELETE ON production_audit_log ...
       SIGNAL SQLSTATE '45000'
       SET MESSAGE_TEXT = 'production_audit_log is APPEND ONLY: DELETE is not allowed';
   ```
3. `fk_audit_production_run` uses `ON DELETE RESTRICT`, so a production run that already has
   an audit entry cannot be deleted either.
4. For deployment you can connect with an account that only has `SELECT, INSERT`
   (see the commented `GRANT` block at the end of `database/bakery.sql`).

Test it in phpMyAdmin:

```sql
UPDATE production_audit_log SET action='CREATE' WHERE id=1;
-- ERROR 1644 (45000): production_audit_log is APPEND ONLY: UPDATE is not allowed
```

---

## 12. Hand-drawn system diagram

The assignment requires a **hand-drawn** system diagram. Read
[`docs/system-diagram.md`](docs/system-diagram.md) — it contains:

* the exact boxes and arrows to draw,
* an ASCII reference version you can copy,
* the labels your lecturer expects (tables, endpoints, formulas).

---

## 13. Test data to enter

Sample data is already inserted by `database/bakery.sql`. Extra data you can type:

**Outlets**

| name | daily_demand_units |
|------|--------------------|
| Surulere Kiosk | 180 |
| Yaba Corner | 220 |

**Suppliers**

| name | lead_time_days |
|------|----------------|
| Big Joe Millers | 5 |

**Production run**

| production_date | actual_units | waste_units |
|-----------------|--------------|-------------|
| today | 1300 | 90 | → 6.9% → *INVESTIGATION REQUIRED*

**Flour order** — supplier `Big Joe Millers`, quantity `1200.500` kg, price `48500` kobo/kg,
delivered empty (still on the way).

**Delivery** — outlet `Surulere Kiosk`, today, `morning`, `180` units.

---

## 14. Deployment preparation (before showing it "for real")

1. `define('APP_DEBUG', false);` in `config/config.php`.
2. Create a dedicated MySQL account instead of `root`:

   ```sql
   CREATE USER 'bakery_app'@'localhost' IDENTIFIED BY 'A-strong-password';
   GRANT SELECT, INSERT, UPDATE ON bakery_ledger.* TO 'bakery_app'@'localhost';
   GRANT SELECT ON  bakery_ledger.production_audit_log TO 'bakery_app'@'localhost';
   GRANT INSERT ON  bakery_ledger.production_audit_log TO 'bakery_app'@'localhost';
   FLUSH PRIVILEGES;
   ```
   Note: **no `UPDATE` and no `DELETE`** on `production_audit_log`.
3. Put `config/config.php` outside the web root or protect it with `<Files>` rules.
4. Add CSRF tokens on every POST form.
5. Replace the demo users in `includes/auth.php` with a real login
   (`password_hash()` / `password_verify()`).
6. Force HTTPS (`RewriteCond %{HTTPS} off`) and set secure cookie flags.
7. Take a nightly `mysqldump` of `bakery_ledger`.
8. Show the two `--` commented sections at the end of `database/bakery.sql`
   (read-only account) as evidence of an audit-safe design.

---

## 15. Troubleshooting

| Symptom | Fix |
|---------|-----|
| *"Database connection failed"* | start MySQL in Laragon, import `database/bakery.sql`, check `DB_USER`/`DB_PASS` |
| `500 Internal Server Error` on the first load | open `.htaccess` and delete the `Options -Indexes` / `DirectoryIndex` lines (your `AllowOverride` is restricted) |
| `404` when submitting a form | set `USE_CLEAN_URLS` to `false` in `config/config.php` |
| Blank page | `APP_DEBUG` is `false` – temporarily set it to `true` to see the error |
| `Could not find driver` | enable `extension=pdo_mysql` in `php.ini`, restart Apache |
| Charts do not appear | check that `assets/vendor/chart.umd.min.js` exists (open the browser console) |
| Wrong folder name in links | leave `APP_BASE_URL` empty (auto-detection) or set it manually, e.g. `'/bakey-ledgerr'` |

---

## 16. Quality checklist (done in this build)

* [x] `php -l` clean on every PHP file
* [x] all foreign keys and indexes verified against a real MySQL/MariaDB server
* [x] every form posts to a real endpoint and redirects (Post/Redirect/Get)
* [x] PDO prepared statements only – no string-built SQL
* [x] all user input validated server side
* [x] formulas verified: demand×1.10, waste%, reorder point×1.20
* [x] idempotency verified by submitting the same request twice
* [x] IDOR exploit and patch both verified with HTTP status codes (200 vs 403)
* [x] dashboard JSON + Chart.js assets load correctly
* [x] `fetchAll()` is not used anywhere
* [x] no external network dependency (Bootstrap and Chart.js are local)

---

## 17. References (bakery operations research)

1. Slack, N. and Brandon-Jones, A. (2019) *Operations Management*. 8th edn.
   Harlow: Pearson Education. — aggregate planning: matching forecast demand to a
   production capacity before the shift starts (the logic behind the daily plan).

2. Chase, R.B., Jacobs, F.R. and Aquilano, N.J. (2006) *Operations Management for
   Competitive Advantage*. 11th edn. New York: McGraw-Hill/Irwin. — production planning
   and control, and the use of a planned-order schedule for a single plant.

3. Heizer, J., Render, B. and Munson, C. (2017) *Operations Management: Sustainability
   and Supply Chain Management*. 12th edn. Harlow: Pearson. — operations strategy of a
   repetitive food-production facility and capacity planning.

4. Cachon, G. and Terwiesch, C. (2009) *Matching Supply with Demand: Introduction to
   Operations Management*. 2nd edn. New York: McGraw-Hill/Irwin. — how a demand forecast
   is turned into a feasible production quantity (the `total outlet demand` input).

5. Silver, E.A., Pyke, D.F. and Peterson, R. (1998) *Inventory Management and Production
   Planning and Scheduling*. 3rd edn. New York: John Wiley & Sons. — safety stock and the
   `lead time × average demand × service factor` structure behind the 1.20 factor.

6. Wild, T. (2008) *Best Practices in Inventory Management*. 2nd edn. Chichester:
   John Wiley & Sons. — raw-material (flour) replenishment, reorder points and service
   levels for consumables in a processing plant.

7. Chopra, S. and Meindl, P. (2016) *Supply Chain Management: Strategy, Planning, and
   Operation*. 6th edn. Harlow: Pearson. — supplier lead time, the bullwhip effect and
   coordination between a central plant and its retail outlets.

8. Syntetos, A.A., Babai, M.Z., Boylan, J.E., Kolassa, S. and Nikolopoulos, K. (2016)
   'Supply chain forecasting: theory, practice, their gap and the future', *European
   Journal of Operational Research*, 254(1), pp. 1–26. — forecasting accuracy, which is
   why a buffer is placed on top of the raw demand forecast.

9. Parfitt, J., Barthel, M. and Macnaughton, S. (2010) 'Food waste within food supply
   chains: quantification and potential for change to 2050', *Philosophical Transactions
   of the Royal Society B*, 365(1554), pp. 3065–3081. — why food waste is expressed as a
   percentage of production and monitored with an escalation threshold (the 5% rule here).

10. Gustavsson, J., Cederberg, C., Sonesson, U., van Otterdijk, R. and Meybeck, A. (2011)
    *Global Food Losses and Food Waste*. Rome: Food and Agriculture Organization of the
    United Nations (FAO). — magnitude of losses in food supply chains and the case for
    measuring production loss at the plant level.

> **Note for the defence:** the 10% buffer in this project is an assignment requirement
> (`PRODUCTION_BUFFER_RATE = 1.10`). In a real bakery the buffer is derived from the
> forecast error and the target service level (see refs. 5, 6 and 8); the 20% safety
> factor in the reorder point plays the same role for the raw material.
