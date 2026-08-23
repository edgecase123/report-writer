-- Coffee-shop POS schema. staff + payments added in A3.1; template_drafts lands with A5.

CREATE TABLE IF NOT EXISTS categories (
    id   INTEGER PRIMARY KEY,
    name TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS items (
    id               INTEGER PRIMARY KEY,
    category_id      INTEGER NOT NULL REFERENCES categories(id),
    name             TEXT    NOT NULL,
    unit_price_cents INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
    id         INTEGER PRIMARY KEY,
    opened_at  TEXT    NOT NULL,             -- ISO-8601 UTC
    closed_at  TEXT                          -- NULL = tab still open
);

CREATE TABLE IF NOT EXISTS order_items (
    id               INTEGER PRIMARY KEY,
    order_id         INTEGER NOT NULL REFERENCES orders(id),
    item_id          INTEGER NOT NULL REFERENCES items(id),
    quantity         INTEGER NOT NULL,
    unit_price_cents INTEGER NOT NULL         -- snapshotted at order time
);

CREATE INDEX IF NOT EXISTS orders_closed_at_idx     ON orders(closed_at);
CREATE INDEX IF NOT EXISTS order_items_order_id_idx ON order_items(order_id);

CREATE TABLE IF NOT EXISTS staff (
    id   INTEGER PRIMARY KEY,
    name TEXT    NOT NULL,
    role TEXT    NOT NULL              -- e.g. 'barista', 'shift_lead', 'manager'
);

CREATE TABLE IF NOT EXISTS payments (
    id            INTEGER PRIMARY KEY,
    order_id      INTEGER NOT NULL REFERENCES orders(id),
    method        TEXT    NOT NULL,    -- 'cash' | 'card' | 'mobile'
    amount_cents  INTEGER NOT NULL,
    taken_at      TEXT    NOT NULL,    -- ISO-8601 UTC
    staff_id      INTEGER NOT NULL REFERENCES staff(id)
);

CREATE INDEX IF NOT EXISTS payments_order_id_idx ON payments(order_id);
CREATE INDEX IF NOT EXISTS payments_taken_at_idx ON payments(taken_at);
