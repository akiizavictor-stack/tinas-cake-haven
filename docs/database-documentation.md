# Database Documentation

## Scope and prefix

The WordPress prefix is a deployment-specific value. Custom table examples use `<wpdb_prefix>tch_`, for example `<wpdb_prefix>tch_orders`. The plugin allows only a fixed list of table short names and computes names through `tch_table()`.

No real customer records or database credentials are included here.

## Custom tables

### `<wpdb_prefix>tch_cake_sizes`

Stores selectable cake sizes.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `name` | Size name |
| `weight_label` | Display weight |
| `price` | Whole-shilling size price |
| `sort_order` | Display order |
| `is_active` | Whether the size is selectable |
| `updated_at` | Last update |

### `<wpdb_prefix>tch_flavours`

Stores selectable cake flavours.

| Field | Purpose |
|---|---|
| `id` | Primary key |
| `name` | Unique flavour name |
| `sort_order` | Display order |
| `is_active` | Whether the flavour is selectable |

### `<wpdb_prefix>tch_orders`

Stores the main order record.

Important fields: `id`, `order_ref`, `submission_key`, `user_id`, `order_type`, `customer_name`, `customer_phone`, `customer_phone_norm`, `customer_email`, `fulfilment`, `delivery_address`, `needed_by`, `total_amount`, `payment_plan`, `amount_expected`, `amount_paid`, `order_status`, `payment_status`, timestamps and notes.

- Primary key: `id`
- Unique keys: `order_ref`, `submission_key`
- Logical relationship: `user_id` points to the corresponding standard WordPress user ID when the customer is logged in; a guest order may have a null user ID.

### `<wpdb_prefix>tch_order_items`

Stores each line in an order.

Important fields include `order_id`, `product_id`, `product_name`, `size_id`, `size_label`, `flavour_id`, `flavour_label`, quantity and line totals.

- Primary key: `id`
- Foreign key: `order_id -> tch_orders.id`, cascading on delete
- Foreign keys: `size_id -> tch_cake_sizes.id` and `flavour_id -> tch_flavours.id`, with `SET NULL`
- `product_id` logically references a WordPress `tch_product` post ID; no SQL foreign key is declared in the custom schema.

### `<wpdb_prefix>tch_custom_requests`

Stores customized-cake details.

Important fields: `order_id`, design description, inscription, event date, reference file and special instructions.

- Primary key: `id`
- Unique key: `order_id`
- Foreign key: `order_id -> tch_orders.id`, cascading on delete

### `<wpdb_prefix>tch_payments`

Stores customer and staff payment records.

Important fields include `order_id`, payment type, method, amount, transaction reference, payer phone, evidence file, status, source, submitter/verifier IDs, timestamps and admin note.

- Primary key: `id`
- Foreign key: `order_id -> tch_orders.id`, cascading on delete
- `submitted_by` and `verified_by` logically reference WordPress user IDs.

### `<wpdb_prefix>tch_payment_log`

Stores payment-state audit history.

Important fields: `payment_id`, action, old/new status, actor ID, note and timestamp.

- Primary key: `id`
- Foreign key: `payment_id -> tch_payments.id`, cascading on delete
- `actor_id` logically references a WordPress user ID.

### `<wpdb_prefix>tch_status_history`

Stores order-status changes.

Important fields: `order_id`, old/new status, changed-by user ID, note and timestamp.

- Primary key: `id`
- Foreign key: `order_id -> tch_orders.id`, cascading on delete

### `<wpdb_prefix>tch_messages`

Stores contact-form messages.

Important fields: name, phone, email, subject, message, read status and timestamp.

- Primary key: `id`

## Standard WordPress tables and data

The application also uses standard WordPress tables for:

- Users and password hashes.
- Posts, including `tch_product`.
- Taxonomies, including `tch_product_cat`.
- Post metadata, including product price and ordering configuration.
- Options, including business settings, page IDs and plugin database version.

## Relationship summary

```mermaid
erDiagram
    WP_USERS ||--o{ TCH_ORDERS : "optional customer"
    WP_USERS ||--o{ TCH_PAYMENTS : "submit or verify"
    WP_PRODUCTS ||--o{ TCH_ORDER_ITEMS : "referenced logically"
    TCH_ORDERS ||--|{ TCH_ORDER_ITEMS : contains
    TCH_ORDERS ||--o| TCH_CUSTOM_REQUESTS : may_have
    TCH_ORDERS ||--o{ TCH_PAYMENTS : receives
    TCH_PAYMENTS ||--o{ TCH_PAYMENT_LOG : has
    TCH_ORDERS ||--o{ TCH_STATUS_HISTORY : has
    TCH_CAKE_SIZES ||--o{ TCH_ORDER_ITEMS : optional
    TCH_FLAVOURS ||--o{ TCH_ORDER_ITEMS : optional
```

The diagram distinguishes logical WordPress relationships from SQL foreign keys. Only relationships explicitly present in source or schema are shown.


## Data Integrity and Relationship Behaviour

The database uses primary keys, unique constraints and foreign keys to maintain the consistency of stored information.

### Primary keys and unique constraints

- Each custom table has an `id` primary key to identify individual records.
- The order reference and submission key in the orders table have unique constraints to prevent duplicate values.
- The flavour name is unique in the flavours table.
- The custom requests table has a unique `order_id`, allowing at most one custom request record per order.

### Foreign key behaviour

The custom database schema defines foreign keys for relationships that it manages directly.

- Deleting an order cascades to its associated order items, custom request, payments and status history.
- Deleting a payment cascades to its associated payment log records.
- If a cake size or flavour record is deleted, the corresponding optional reference in an order item is set to NULL.
- Product IDs and WordPress user IDs are logical references. They are not declared as foreign keys in the custom schema.

These behaviours help maintain consistency between related custom records. Administrators should exercise caution when deleting records because cascading deletion can remove associated records.

### Order and payment data consistency

The orders table stores the main order information, while order items store individual products, quantities and line totals.

The payments table records payment submissions and staff payment records. The payment log preserves payment-state changes, while the status history records changes to an order's status.

This separation supports order tracking and payment verification without combining all operational records into one table.

## Database Maintenance and Protection

The following practices should be followed when maintaining the database:

1. Back up the database before performing structural changes or major updates.
2. Never publish database credentials, password hashes, customer records or payment evidence.
3. Use the WordPress database API and the plugin's approved table-name helper when accessing custom tables.
4. Use prepared database queries for values supplied by users.
5. Validate and sanitize submitted data before storing it.
6. Restrict database and administrative access to authorized personnel.
7. Test database migrations and updates in a local or staging environment before applying them to the live website.
8. Periodically verify that backups can be restored successfully.

### Database Documentation Limitations

This document describes the custom tables and relationships identified in the project documentation and implementation.

It is not a complete SQL schema export. Exact column types, default values, indexes, storage engines and all constraint definitions should be confirmed against the installed database schema before making structural changes.

