# Business Owner User Guide

This guide documents functions found in the existing implementation. Screens and labels can vary slightly by WordPress version.

## Access the dashboard

Open:

```text
https://tinascakehaven.noscamcoin.org/wp-admin/
```

Use an authorized administrator or staff account. Customer accounts are redirected away from `wp-admin`.

## Business dashboard

Open **Cake Orders → Dashboard**. The dashboard summarizes:

- Total orders
- New orders in the last 48 hours
- Pending and processing orders
- Payments awaiting verification
- Verified sales
- Outstanding balances
- Recent orders
- Popular products

## Manage products

Open **Cake Products** in the WordPress administration menu.

- Add a new product.
- Edit title, description, excerpt and featured image.
- Assign a product category.
- Configure pricing metadata, quantity limits and allowed sizes through the product editor fields provided by the plugin.
- Publish or unpublish the product using WordPress controls.

## Manage categories

Open **Cake Products → Product categories** to add or edit categories such as cakes, cupcakes and cookies.

## Manage sizes and flavours

Open **Cake Orders → Sizes & flavours**.

The interface supports:

- Adding, editing and activating sizes.
- Setting size weight labels and prices.
- Sorting size display.
- Adding, editing and activating flavours.

Server-side pricing uses these values; changing values can change future order totals.

## View and manage orders

Open **Cake Orders → All orders**.

For each order, staff can review:

- Customer and contact details
- Fulfilment and delivery/collection choice
- Needed-by date
- Items, sizes, flavours and quantities
- Total and payment state
- Custom design information
- Status history
- Payment submissions

Authorized staff can update order status and add internal/admin notes. Changing an order status is recorded in status history.

## Verify Mobile Money payments

Open **Cake Orders → Verify payments**.

The queue lists payments in `pending_verification`. Staff must first check the real Mobile Money account. The system does not call a payment provider or verify money automatically.

For each payment:

1. Confirm the transaction reference and amount.
2. Confirm the payer phone if applicable.
3. Open evidence if the customer provided it.
4. Choose **Verified** only after real account confirmation.
5. Rejection requires a reason, visible to the customer.

The staff decision and time are recorded. Staff need the `tch_verify_payments` capability.

Staff may also record a payment already received manually when supported by the order screen. It is recorded as verified immediately because it is a staff-entered action.

## Monitor order statuses

Possible order statuses, labels and transitions are implemented in the plugin. Typical labels include:

- Pending
- Awaiting verification
- Confirmed
- Preparing
- Ready
- Completed
- Cancelled

Status names and transition rules should be checked in the live dashboard because some workflows depend on payment state.

## Manage website content

Use **Appearance → Editor** or WordPress page/post editors to manage normal pages and navigation. Use **Hero Slider** under the Cake Orders menu for the homepage slides.

## Manage messages

Open **Cake Orders → Messages** to review contact-form submissions and mark messages as read.

## Manage business settings

Open **Cake Orders → Business settings** to maintain:

- Business name and owner
- Tagline and location
- Phone and WhatsApp
- Public and notification email values
- Custom-cake notice hours
- Mobile Money instructions
- Delivery note
- Social URLs
- Map URL

Do not enter passwords or secrets in public business settings.

## Customer Website User Guide

This section explains how customers can browse products, place orders, submit payment details and track their orders.

### 1. Browse available products

1. Open the Tina's Cake Haven website: https://tinascakehaven.noscamcoin.org/
2. Navigate to the products or menu section.
3. Browse the available cakes, cupcakes and cookies.
4. Review product descriptions, sizes and displayed prices.

### 2. Place an order

1. Open the **Order Online** page.
2. Select the product you want to order.
3. Choose the available size, flavour and quantity where applicable.
4. Provide the requested contact and fulfilment information.
5. For customized birthday cakes, provide the design details and required date.
6. Review the order information before submitting.
7. Submit the order and retain the order reference displayed by the website.

Customized birthday cake orders require at least 24 hours' notice.

### 3. Submit Mobile Money payment details

1. Follow the Mobile Money payment instructions displayed by the website.
2. Make the payment using the provided business payment details.
3. Return to the order payment instructions or submission area.
4. Enter the requested transaction reference and payment information.
5. Submit the payment details for verification.

**Important:** Payment is not automatically confirmed by the website. The business owner or authorized staff must verify the transaction against the actual Mobile Money account.

Customers should wait for confirmation before assuming that payment has been accepted.

### 4. Track an order

1. Open the **Order Tracking** page.
2. Enter the requested order identification details.
3. Submit the tracking request.
4. Review the displayed order status and available updates.

Customers should use the order reference associated with their submitted order.

### 5. Access customer accounts and order history

Customers can use the **My Account** and **My Orders** pages to access the account features provided by the website.

Depending on account status and available features, customers can review their recent orders and follow order progress.

Keep account login details private and do not share passwords with other people.

### 6. Contact the business

For questions about cake designs, payments, collection or delivery, customers can use the contact information and communication links provided on the website.

When making an enquiry about an existing order, provide the relevant order reference without sharing passwords or other confidential account information.

### Customer guidance

- Check product sizes, flavours and prices before ordering.
- Provide accurate contact information.
- Allow at least 24 hours for customized birthday cake orders.
- Keep the order reference for tracking.
- Wait for manual payment verification.
- Contact the business if an order or payment status needs clarification.
