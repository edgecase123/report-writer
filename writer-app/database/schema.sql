-- Coffee-shop POS schema (subset used by A2).
-- Full schema (staff, payments, template_drafts) lands with A3 and A5.

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
