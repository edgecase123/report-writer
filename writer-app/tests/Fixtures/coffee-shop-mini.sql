-- coffee-shop-mini.sql
--
-- Deterministic ~20-row fixture shared across A3 snapshot tests.
-- Anchor date: 2026-08-22 (same as Seed::ANCHOR_DATE).
--
-- Edge cases included:
--   • Sales by Category: multiple categories with different totals
--   • Open Tabs: order 2000 has closed_at IS NULL
--   • Sales by Category → Item: multiple items per category, multiple orders per item
--   • Register Close: two payment methods (cash + card) on 2026-08-22
--   • Full Menu Book: category has a non-null description column (added in later task if needed)

DELETE FROM payments;
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM items;
DELETE FROM staff;
DELETE FROM categories;

INSERT INTO categories (id, name) VALUES
    (1, 'Coffee'),
    (2, 'Pastry');

INSERT INTO items (id, category_id, name, unit_price_cents) VALUES
    (1, 1, 'Espresso',  500),
    (2, 1, 'Latte',     600),
    (3, 2, 'Croissant', 400),
    (4, 2, 'Muffin',    350);

INSERT INTO staff (id, name, role) VALUES
    (1, 'Ada Lovelace', 'barista'),
    (2, 'Farah Grant',  'shift_lead');

-- 2026-08-22 orders (all closed).
INSERT INTO orders (id, opened_at, closed_at) VALUES
    (1001, '2026-08-22T09:10:00Z', '2026-08-22T09:15:00Z'),
    (1002, '2026-08-22T10:20:00Z', '2026-08-22T10:22:00Z'),
    (1003, '2026-08-22T14:00:00Z', '2026-08-22T14:05:00Z');

INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
    (1, 1001, 1, 1, 500),   -- 500  → order 1001
    (2, 1001, 3, 1, 400),   -- 400  → order 1001 total 900
    (3, 1002, 2, 2, 600),   -- 1200 → order 1002 total 1200
    (4, 1003, 1, 1, 500),   -- 500  → order 1003
    (5, 1003, 4, 1, 350);   -- 350  → order 1003 total 850

INSERT INTO payments (id, order_id, method, amount_cents, taken_at, staff_id) VALUES
    (1, 1001, 'card', 900,  '2026-08-22T09:15:00Z', 1),
    (2, 1002, 'cash', 1200, '2026-08-22T10:22:00Z', 1),
    (3, 1003, 'card', 850,  '2026-08-22T14:05:00Z', 2);

-- Prior-day order (should be excluded by any date=2026-08-22 filter).
INSERT INTO orders     (id, opened_at, closed_at)                                  VALUES (999, '2026-08-21T09:00:00Z', '2026-08-21T09:10:00Z');
INSERT INTO order_items(id, order_id, item_id, quantity, unit_price_cents)         VALUES (99, 999, 2, 1, 600);
INSERT INTO payments   (id, order_id, method, amount_cents, taken_at, staff_id)    VALUES (99, 999, 'cash', 600, '2026-08-21T09:10:00Z', 1);

-- Open tab (closed_at NULL, no payment) — required by Open Tabs report.
INSERT INTO orders     (id, opened_at, closed_at)                                  VALUES (2000, '2026-08-22T15:00:00Z', NULL);
INSERT INTO order_items(id, order_id, item_id, quantity, unit_price_cents)         VALUES (200, 2000, 1, 1, 500);
