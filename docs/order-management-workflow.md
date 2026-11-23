# Order Management Workflow

## 1. Customer selects a product or custom cake

Customers browse Cake Products or the price list. Standard items include birthday cakes, cupcakes and cookies. A custom birthday-cake link opens the customized-cake form.

## 2. Customer places an order

The order form collects:

- Full name
- Phone number
- Optional email
- Collection or delivery and address
- Full payment or 50% deposit
- Needed-by date/time
- Optional notes
- Products, sizes, flavours and quantities

For custom cakes, the customer also supplies:

- Design description
- Optional inscription
- Event date/time
- Optional reference photo
- Special instructions

The form uses a nonce, honeypot, submission token and network rate limiting.

## 3. Server validates and records the order

The PHP plugin:

- Sanitizes all input.
- Validates the order date and notice period.
- Calculates prices from published product metadata and active custom tables.
- Checks size, flavour and quantity rules.
- Calculates full or 50% deposit amount.
- Stores the order and items in a database transaction.
- Creates a unique order reference.
- Writes the first status-history record.
- Stores an optional custom request and private reference photo.

The order starts as `pending` and the payment status starts as `unpaid`.

## 4. Owner receives and reviews the order

New orders appear in the Cake Orders dashboard and All orders screen. Notifications can be sent through the configured notification module and email when email settings are available.

Staff can open the order, review customer details and items, update status and add admin notes.

## 5. Customer submits Mobile Money payment details

The order page provides Mobile Money instructions. If no custom instructions are configured, the plugin directs the customer to call or WhatsApp the configured phone number.

The customer submits:

- Transaction ID
- Payer phone
- Payment amount
- Optional screenshot

The system validates the amount, pending state and transaction uniqueness. It does not contact a payment provider or infer that money was received.

The first payment must match the required full amount or 50% deposit. Later payments must not exceed the outstanding balance.

## 6. Payment enters verification

The payment record becomes `pending_verification`. The order may move to `awaiting_verification`. Staff see a pending-payment count and queue.

The payment has a separate audit history and records submitter, submit time and eventual verifier.

## 7. Owner manually confirms or rejects payment

Staff compare the claim with the actual Mobile Money account or other authorized records:

- **Verified:** amount is applied to the order and status/history are updated.
- **Rejected:** staff must give a reason.

No API marks payment as received automatically. Staff need the `tch_verify_payments` capability.

## 8. Order status updates

Typical subsequent statuses include confirmed, preparing, ready, completed or cancelled. Status changes should be made by authorized staff in the order screen and are written to `status_history`.

## 9. Customer tracks the order

Guest tracking requires the order reference and phone number used for the order. Logged-in customers can also open My Orders to see their order history.

The public order view uses a signed guest token for authorized navigation and prevents public cache storage.

## 10. Completion

Verified payments update paid amounts and payment status. Staff mark production and fulfilment complete, or cancel the order if necessary. Cancelled orders should not be accepted for new payment submissions.

