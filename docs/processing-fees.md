# Processing Fees

## What "processing fee" means

A single processing fee is made up of two internal components:

| Component | Responsibility | Default | Environment variable |
| --- | --- | --- | --- |
| Gateway fee | Cost charged by the payment gateway (PesaPal) to collect the payment | 3.5% | `EVENTS_GATEWAY_FEE_PERCENT` |
| Custospark fee | Custospark Events platform fee | 1.5% | `EVENTS_CUSTOSPARK_FEE_PERCENT` |

The combined processing fee defaults to 5%.

The fee split is internal. Public and customer-facing UI must only show **Processing fee**. Do not expose the gateway or Custospark components.

## Fee bearer types

Every event has a `processing_fee_bearer` value. Only a platform admin can set it for a client's event. Tenant organisers cannot change it; if they send a value, the backend ignores it and keeps the existing value, or uses the platform default when creating an event.

| Value | UI label | Who pays the processing fee | Buyer total | Organiser payout |
| --- | --- | --- | --- | --- |
| `customer` | Customer pays | Ticket buyer | `subtotal + processing_fee` | `subtotal` |
| `organizer` | Organiser absorbs | Event organiser | `subtotal` | `subtotal - processing_fee` |

Default: `customer` (`EVENTS_DEFAULT_FEE_BEARER=customer`).

### Customer pays

The processing fee is added to the buyer's total as one line named **Processing fee**.

Example on a UGX 100,000 subtotal:

- Subtotal: UGX 100,000
- Processing fee: UGX 5,000
- Buyer pays: UGX 105,000
- Organiser payout: UGX 100,000

### Organiser absorbs

The processing fee is not added to the buyer's total. It is still recorded and is deducted from the organiser's payout.

Example on a UGX 100,000 subtotal:

- Subtotal: UGX 100,000
- Processing fee: UGX 5,000
- Buyer pays: UGX 100,000
- Organiser payout: UGX 95,000

## Stored values

### Event

- `processing_fee_bearer`: `customer` or `organizer`.

### Order

- `subtotal`: ticket value before fees.
- `processing_fee`: total actual processing fee (`gateway_fee + custospark_fee`).
- `gateway_fee`: gateway component of the processing fee.
- `custospark_fee`: Custospark component of the processing fee.
- `buyer_processing_fee`: amount added to the buyer's total. This is `processing_fee` for `customer`, or `0` for `organizer`.
- `processing_fee_bearer`: fee bearer snapshot used when the order was created.
- `gateway_fee_percent`: gateway percentage snapshot.
- `custospark_fee_percent`: Custospark percentage snapshot.
- `total`: `subtotal + buyer_processing_fee`.

### Payment

Payments carry clear reconciliation labels:

- `organiser_name`: organiser name snapshot.
- `event_title`: event title snapshot.
- `fee_bearer_code`: machine code for the fee bearer (`PFB:CUSTOMER` or `PFB:ORGANIZER`).
- `transaction_label`: `PFB:CUSTOMER | organiser | event | order reference`.

The same label is sent to the payment gateway as the transaction description. The `PFB:*` prefix is the first token so it survives the 100-character truncation, which makes gateway statements easy to join back to orders.

| Code | Meaning |
| --- | --- |
| `PFB:CUSTOMER` | Buyer paid the processing fee; buyer total includes it. |
| `PFB:ORGANIZER` | Organiser absorbed the processing fee; buyer total excludes it. |

The code is intentionally technical. Customer-facing UI must not show it.

### Gateway reconciliation

On success, the gateway response is reconciled against the stored order. The result is stored on the payment as `reconciliation_code` and `reconciliation` for the internal Custospark technical team.

Codes:

| Code | Meaning |
| --- | --- |
| `RECONCILED` | Gateway amount, currency and fee match the system exactly. |
| `FEE_UNAVAILABLE` | Gateway success payload did not return a fee. Payout fallback uses the system's expected gateway fee. |
| `FEE_VARIANCE` | Gateway fee differs from the system's expected gateway fee by more than 0.01. Needs review. |
| `AMOUNT_MISMATCH` | Gateway amount differs from the order total. Payment is held as `requires_review`. |
| `CURRENCY_MISMATCH` | Gateway currency differs from the order currency. Payment is held as `requires_review`. |
| `MISSING_AMOUNT` | Gateway payload did not return an amount or currency. Payment is held as `requires_review`. |
| `GATEWAY_PENDING` | Gateway status is pending. |
| `GATEWAY_FAILED` | Gateway status is failed. |

The reconciliation record contains:

- `fee_bearer_code`: `PFB:CUSTOMER` or `PFB:ORGANIZER`.
- `fee_bearer`: `customer` or `organizer`.
- `gateway_amount`, `gateway_currency`, `gateway_fee`.
- `expected_amount`, `expected_gateway_fee`, `fee_variance`.
- `organiser_payout`: computed as `gateway_amount - gateway_fee - custospark_fee`, using system expected values when the gateway omits data.
- `custospark_revenue`.

These fields are exposed only inside internal order/payment API responses. Public order API responses return them as `null`.

Example for an organiser-absorbed UGX 100,000 subtotal:

```json
{
  "status": "succeeded",
  "code": "RECONCILED",
  "gateway_amount": 100000,
  "gateway_currency": "UGX",
  "gateway_fee": 3500,
  "expected_amount": 100000,
  "expected_gateway_fee": 3500,
  "fee_variance": 0,
  "fee_bearer_code": "PFB:ORGANIZER",
  "fee_bearer": "organizer",
  "organiser_payout": 95000,
  "custospark_revenue": 1500
}
```

## Payout formula

For paid orders on an event:

- `gross_amount = sum(order.total)`
- `processing_fees = sum(order.processing_fee)`
- `net_amount = gross_amount - processing_fees`

Legacy orders without a fee breakdown fall back to their stored `processing_fee`.

## Frontend configuration

The frontend fallback values are:

- `VITE_EVENTS_PROCESSING_FEE_PERCENT=5`
- `VITE_EVENTS_DEFAULT_FEE_BEARER=customer`

The event API remains authoritative: events return `processing_fee_bearer` and the combined `processing_fee_percent`. The public UI calculates and displays only the combined processing fee.

## UI rules

- Customer and buyer screens show one line: **Processing fee**.
- When the organiser absorbs the fee, buyer screens show **Covered by organiser** instead of an amount.
- Admin order screens show one **Processing fee** value.
- The fee-bearer selector is visible only to platform admins.
- Tenant organisers cannot see or change the fee bearer.
- The reconciliation code is visible only on internal payment screens.
- Public order and ticket screens return the reconciliation fields as `null`.
- Never show gateway or Custospark fee percentages on any UI.
