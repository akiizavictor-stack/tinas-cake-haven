# Project Overview

## Business background

Tina's Cake Haven is a bakery website for a business located in Kibimba, Fort Portal, Uganda. The site presents birthday cakes, cupcakes and cookies, allows customers to request custom designs, and connects order and payment information to a staff dashboard. The verified owner name in the project settings is Aluka Christin Hellen.

## Problem statement

Customers need a clear way to view products and prices, request standard or custom cakes, submit orders and track progress. The business owner also needs order records, payment claims and customer details in one organized system rather than relying only on informal messages.

## Objectives

- Publish accurate catalogue and business information.
- Support standard and customized birthday-cake orders.
- Calculate prices server-side from trusted catalogue data.
- Support full and 50% deposit payment plans.
- Record and review Mobile Money payment claims manually.
- Provide customer history and guest order tracking.
- Provide a secure, role-limited administration interface.

## Target users

| User | Main needs |
|---|---|
| Public visitor | Browse products, gallery and business information |
| Customer | Place orders, submit payments, track orders and manage an account |
| Owner/staff | Review orders, update statuses, verify payments and manage catalogue settings |
| Academic examiner | Understand implementation, architecture and documentation |

## Functional requirements

- Catalogue products and categories.
- Price standard and customized items by server-side rules.
- Record customer, fulfilment, item and delivery information.
- Generate an order reference.
- Record custom design details and reference photos.
- Submit Mobile Money transaction details and optional evidence.
- Require staff verification of payment claims.
- Update order and payment statuses.
- Let customers view order history or track an order by reference and phone.
- Let authorized staff manage products, sizes, flavours, messages and settings.

## Non-functional requirements

- Responsive presentation.
- HTTPS on the live domain.
- Input validation, sanitization and nonce protection.
- Prepared SQL and database transactions for important writes.
- Rate limiting for public forms.
- Private pages excluded from public caching.
- Protected private uploads.
- Clear, understandable status and error feedback.
- Maintainable separation between theme presentation and plugin business logic.

## Scope

The implementation covers public catalogue and content pages, WordPress customer accounts, custom database records, order placement, manual payment submission and staff management. It does not integrate an automated Mobile Money provider or payment gateway.

## Technologies

- WordPress
- PHP
- MySQL/MariaDB
- Apache/LiteSpeed rewrite rules
- HTML and CSS
- JavaScript
- WordPress block themes
- Elementor-compatible theme support
- Wordfence
- Yoast SEO
- WP Super Cache
- UpdraftPlus

## Main features

- Homepage hero slider.
- Products and categories.
- Customized cake form and photo upload.
- Order tracking.
- Customer accounts.
- Manual Mobile Money verification.
- Staff dashboard and order history.
- Contact form.
- WhatsApp and phone links.
- Local-business and product structured data.

