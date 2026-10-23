# Mini-ERP for a beverage store

A web application for a drinks shop in Uzbekistan. It tracks stock in pieces and blocks across warehouses, sales and purchases (paid, part-paid or on debt), returns, transfers, inventory counts, the cash register per payment method, customer and supplier debts, loans, and the owner's net profit. Each store is isolated, with one owner and any number of employees. The interface is in Uzbek (Latin script).

Brief: [Texnik topshiriq: Ichimliklar do'koni uchun mini-ERP](https://telegra.ph/Texnik-topshiriq-Ichimliklar-dokoni-uchun-mini-ERP-10-20)

## Documentation

| Document | What it covers |
| --- | --- |
| [Architecture](docs/architecture.md) | How the system is structured and why: tenancy, document lifecycle, the execution module, average cost, money and debt ledgers, permissions, reports |
| [User guide](docs/user-guide.md) | Every flow for the owner and the employee, in English, with the Uzbek labels as they appear on screen |
| [Prompt log](docs/prompts.md) | Every prompt given to the AI while building the project, verbatim and in order |

Background: [`GLOSSARY.md`](GLOSSARY.md) (domain terms), [`docs/adr/`](docs/adr/) (decision records) and [`docs/research/`](docs/research/) (competitor, accounting and UX research).

## Stack

Laravel 13 (PHP 8.5), Inertia v3 + React + TypeScript, Tailwind, Fortify, spatie/laravel-permission and Pest. SQLite by default.

## Getting started

```bash
composer run setup        # install, .env, key, migrate, build assets
composer run dev          # app server + Vite
```

Open the app, click **Ro'yxatdan o'tish** and register: you become the owner of a new store. To create a super-admin (who manages stores but sees none of their data):

```bash
php artisan app:create-super-admin --name="Admin" --phone="+998901234567" --password="secret-password"
```

## Checks

```bash
php artisan test --compact   # Pest feature and domain tests
composer run types:check     # PHPStan
vendor/bin/pint              # code style
```
