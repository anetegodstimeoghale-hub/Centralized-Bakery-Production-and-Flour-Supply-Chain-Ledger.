# System Diagram (hand-drawn assignment)

The assignment requires a **hand-drawn** system diagram. This file gives you

1. exactly which boxes and arrows to draw,
2. an ASCII reference version you can copy onto paper,
3. the labels your lecturer will look for.

Draw it on an A4 sheet (landscape), using a ruler. Boxes = rectangles, arrows = single
headed lines. Write the table names in small capitals inside the database cylinder.

---

## 1. Components to draw

| # | Box | What to write inside |
|---|-----|----------------------|
| 1 | **Browser (user)** | form: outlets / production / flour / deliveries |
| 2 | **Apache + PHP 8** | `index.php` (front controller) → `includes/router.php` |
| 3 | **Presentation layer** | `includes/header.php`, `dashboard/index.php`, Bootstrap + Chart.js |
| 4 | **Business logic** | `includes/functions.php` → 1.10 buffer, waste %, 1.20 reorder |
| 5 | **Data access** | `config/database.php` → PDO prepared statements (streaming) |
| 6 | **MySQL database** | cylinders for the 7 tables |
| 7 | **Audit protection** | `BEFORE UPDATE/DELETE` triggers + `ON DELETE RESTRICT` |

---

## 2. Reference drawing (ASCII)

```
+---------------------------+        HTTP request         +-----------------------------------+
|                           |  GET  /dashboard            |           APACHE 2.4 + PHP 8     |
|   BROWSER (user)          |  POST /outlets/register     |                                   |
|   - Bootstrap 5 UI        |  POST /production/run       |  index.php  (FRONT CONTROLLER)    |
|   - Chart.js charts       |  POST /flour/order          |     |                             |
|   - ?outlet_id=3 -------->|---------------------------->|     v                             |
|                           | <----------------------------  includes/router.php             |
+---------------------------+        302 redirect          |     route -> file resolver       |
                                                           +------------------+--------------+
                                                                              |
                                            +---------------------------------+-----------------+
                                            |                                 |                 |
                                            v                                 v                 v
                          +---------------------------+   +------------------------+   +-------------------+
                          |  PRESENTATION             |   |  BUSINESS LOGIC        |   |  SECURITY         |
                          |  includes/header.php      |   |  includes/functions.php|   |  includes/auth.php|
                          |  assets/css  assets/js    |   |                        |   |                   |
                          |  dashboard/index.php      |   |  planned = demand      |   | can_access_outlet |
                          |  (Chart.js line + bar)    |   |          x 1.10        |   |  -> 403 if not    |
                          +---------------------------+   |  waste% = waste/actual  |   |     allowed       |
                                            ^             |        x 100           |   +-------------------+
                                            |             |  reorder = usage       |
                                            | JSON        |        x lead x 1.20   |
                                            +-------------+------------------------+
                                                          |           |
                                                          | prepared  |
                                                          | statements|
                                                          v           v
                                            +------------------------------------------+
                                            |        config/database.php (PDO)         |
                                            |   stream_query()  ->  while(fetch())     |
                                            |   fetchAll() is NEVER used               |
                                            +--------------------+---------------------+
                                                                 |
                                                                 v
      =====================================================================
      ||                       MySQL  (InnoDB)                          ||
      ||                                                               ||
      ||   outlets ----+              suppliers ----+                  ||
      ||               |                            |                  ||
      ||               |        production_runs     |                  ||
      ||               |        (UNIQUE date) <-----+-- flour_orders   ||
      ||               |             |                               ||
      ||               |             +---- production_audit_log      ||
      ||               |                    (APPEND ONLY triggers)   ||
      ||               |             +---- flour_usage (DECIMAL kg)  ||
      ||               |                                            ||
      ||               +------------> deliveries                    ||
      ||                        index: (outlet_id, production_date)  ||
      ||                        UNIQUE (outlet, date, morning/evening)||
      =====================================================================
```

---

## 3. Data flow arrows (draw these five)

1. **Outlets → Production**: `Σ daily_demand_units` becomes the input of
   `planned = demand × 1.10`.
2. **Production → Waste**: `waste% = waste / actual × 100`; if `> 5%` the UI shows
   `INVESTIGATION REQUIRED`.
3. **Suppliers → Flour reorder**: `lead_time_days` × `daily flour usage` × `1.20`.
4. **Flour usage → Reorder point**: `SUM(flour_kg)` of the last 7 production days ÷ 7.
5. **Production runs → Audit log**: every INSERT/UPDATE writes one immutable audit row.

---

## 4. Labels your lecturer expects

* the **10 % buffer** written next to the production arrow (`× 1.10`)
* the **20 % safety factor** next to the reorder arrow (`× 1.20`)
* **`DECIMAL(12,3)`** next to the flour tables
* **kobo (integer)** next to money columns
* **`UNIQUE (production_date)`** = idempotency
* **composite index `(outlet_id, production_date)`** on `deliveries`
* **403 FORBIDDEN** next to the secure outlet box and **DATA LEAK** next to the
  vulnerable one
* **`BEFORE UPDATE/DELETE` trigger** next to `production_audit_log`

---

## 5. Sequence of one production run (draw as a second, small diagram)

```
Cashier            index.php        functions.php        MySQL
   |  POST /production/run   |              |              |
   |------------------------>|              |              |
   |                         |-- validate() |              |
   |                         |   (date, actual, waste)     |
   |                         |-- total_daily_demand() ---->|  SELECT SUM(...)
   |                         |-- planned_production()      |  demand x 1.10
   |                         |-- SELECT ... FOR UPDATE --->|  existing plan?
   |                         |              |              |
   |                         |   if same values -> no write (idempotent)
   |                         |   else INSERT/UPDATE ------>|
   |                         |   + audit row ------------->|
   |  302 Location: /production/           |              |
   |<-------------------------|              |              |
```
