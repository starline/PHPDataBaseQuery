# DataBaseQuery (HugaShop CRM — database layer)

This repository contains a **PHP database access and query-builder layer** extracted from **HugaShop**, an e-commerce / CRM platform by Andri Huga. The code lives under `hugashop/crm/Api/` and is meant to work with **MySQL** via the **mysqli** extension.

## What it provides

- **`Database`** — low-level database access: connection management, parameterized queries via a **placeholder** syntax (`?`, `?@` for lists, `?%` for key/value sets), table-name prefixing (`__tablename` → configured prefix), escaping, and optional **automatic `CREATE TABLE` / `ALTER TABLE`** when a query fails because a table or column is missing. Also includes **dump** and **restore** helpers for prefixed tables.
- **`DatabaseQuery`** — a **fluent query builder** for `SELECT`, `INSERT`, `UPDATE`, and `DELETE`, including `WHERE` (with `AND` / `OR`), `LEFT JOIN` against other API model classes, `ORDER BY`, pagination (`limit`), aggregates (`count`, `sum`), and convenience methods such as `getOne`, `getList`, `getCount`, `add`, `updateOne`, and `deleteOne`. Table metadata (fields, joins) is expected to be defined on extending classes via a static `$table` definition pattern used in the full HugaShop codebase.
- **`Config`** — loads **`config.yaml`** from the CRM directory (next to `Api/`), merges runtime paths and URLs, and exposes settings through `Config::get()`.
- **`Helper`** — shared utilities (caching, string/slug helpers, tokens, normalization of joined row data, etc.). **Note:** this class references other HugaShop types (`Settings`, `Request`, and others) that are **not** included in this repository; it is intended to run as part of the full project or after you provide compatible stubs.

## Project layout

```
hugashop/crm/
├── Api/
│   ├── Config.php
│   ├── Database.php
│   ├── DatabaseQuery.php
│   └── Helper.php
└── config_default.yaml   # example / template settings (copy to config.yaml)
```

## Requirements

- **PHP 8.1+** (uses union types and other modern syntax).
- **MySQL** (or MariaDB) and the **mysqli** extension.
- **`symfony/yaml`** for `Config` (and YAML usage in `Helper` where applicable).

`Helper.php` additionally declares dependencies used in the full application (for example **Illuminate Support**, **Symfony Cache**, **Google reCAPTCHA**, **jenssegers/agent**). If you use `Helper` as-is, install the same packages via **Composer** in your parent project, or trim unused methods.

## Configuration

1. Copy `hugashop/crm/config_default.yaml` to `hugashop/crm/config.yaml`.
2. Fill in at least the **`database`** section: `server`, `user`, `password`, `name`, and optionally `prefix`, `charset`, `sql_mode`.
3. Set **`salt`** and other keys as required by your deployment.

`Config` resolves paths assuming a typical HugaShop tree (CRM folder, project root with `templates/`, `public/`, `var/`, etc.). Adjust or bootstrap `Config` if your directory layout differs.

## Namespace

All PHP classes in `Api/` use the namespace **`HugaShop\Api`**.

## Usage sketch

Extend `DatabaseQuery` in your own model classes, define static `$table` (name, fields, joins), then chain calls — for example: `select()`, `where(...)`, `leftJoin(...)`, `getResult()` / `getResults()`, or use `Database::placehold()` / `query()` for raw SQL.

See inline documentation and examples in `DatabaseQuery.php` (e.g. `where`, `leftJoin`, `order`).

## License / attribution

Original headers credit **Andri Huga** and the HugaShop project. If you redistribute or fork, preserve author and license notices from the upstream project when applicable.
