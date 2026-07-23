# SwiftTrack Courier — REST API v1

Base URL: `http://localhost:8000/api/v1`

Authentication is [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens.
Every response is JSON; the API never returns an HTML error page.

---

## Table of contents

- [Conventions](#conventions)
- [Authentication](#authentication)
- [Public tracking](#public-tracking)
- [Dashboard](#dashboard)
- [Parcels](#parcels)
- [Deliveries](#deliveries)
- [Customers](#customers)
- [Drivers](#drivers)
- [Branches](#branches)
- [Reports](#reports)
- [Roles and permissions](#roles-and-permissions)
- [Error reference](#error-reference)

---

## Conventions

### Request headers

| Header | Value | Notes |
|---|---|---|
| `Accept` | `application/json` | **Required.** Without it Laravel may negotiate HTML. |
| `Authorization` | `Bearer {token}` | Required on every endpoint except login, forgot-password and public tracking. |
| `Content-Type` | `application/json` | Use `multipart/form-data` when uploading a file. |

### Response envelope

Single resources and actions:

```json
{
  "success": true,
  "message": "Parcel booked. Tracking number: SWT-20260722-K4F9C2",
  "data": { }
}
```

Collections are paginated and additionally carry `links` and `meta`:

```json
{
  "success": true,
  "data": [ ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "per_page": 25, "total": 820, "last_page": 33 }
}
```

Failures:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "weight": ["The parcel weight must be greater than zero."] }
}
```

### Status codes

| Code | Meaning |
|---|---|
| `200` | Success |
| `201` | Resource created |
| `204` | Success, no body |
| `401` | Missing, invalid or revoked token |
| `403` | Authenticated, but your role may not do this |
| `404` | Resource does not exist, or is outside your branch |
| `409` | Account state prevents the action (e.g. driver has no driver record) |
| `422` | Validation failed, or a business rule was violated |
| `429` | Rate limited |
| `500` | Server error |

### Pagination

All collection endpoints accept `?page=` and `?per_page=`.
`per_page` defaults to **25** and is clamped to a maximum of **100**.

### Rate limits

| Endpoint | Limit |
|---|---|
| `POST /auth/login` | 10 per minute per IP, plus a 5-attempt lockout per email + IP |
| `POST /auth/forgot-password` | 6 per minute |
| `GET /track/{trackingNumber}` | 60 per minute |
| Everything else | Laravel's default API throttle |

---

## Authentication

### `POST /auth/login`

Public. Exchanges credentials for a token.

```json
{
  "email": "admin@swifttrack.lk",
  "password": "password",
  "device_name": "Nimal's iPhone"
}
```

**200**

```json
{
  "success": true,
  "message": "Signed in successfully.",
  "data": {
    "token": "3|kR8x...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Ashan Wickramasinghe",
      "email": "admin@swifttrack.lk",
      "role": { "value": "super_admin", "label": "Super Admin" },
      "is_active": true,
      "branch": null
    }
  }
}
```

The token carries the user's role as its only ability, so a leaked driver token
cannot be replayed against management endpoints.

A deactivated account receives **422** with the same message as a wrong
password — account state is not disclosed to an unauthenticated caller.

### `GET /auth/me`

Returns the authenticated user with their branch and driver record.

### `POST /auth/logout`

Revokes **only the token used for this request**. Other devices stay signed in.

### `POST /auth/logout-all`

Revokes every token belonging to the user.

### `PUT /auth/password`

```json
{
  "current_password": "password",
  "password": "BrandNew123",
  "password_confirmation": "BrandNew123"
}
```

Revokes all other tokens; the current one survives so the caller is not logged
out mid-session.

### `POST /auth/forgot-password`

Public. Always answers **200** with the same message whether or not the address
is registered, so staff accounts cannot be enumerated.

---

## Public tracking

### `GET /track/{trackingNumber}`

**No authentication.** This is what a QR-code scan resolves to.

The payload is deliberately reduced — sender and receiver names are masked,
full addresses and phone numbers are withheld, and staff names are replaced
with "Courier Team" — because anyone who guesses a tracking number can call it.

```bash
curl -H "Accept: application/json" \
  http://localhost:8000/api/v1/track/SWT-20260722-K4F9C2
```

**200**

```json
{
  "success": true,
  "data": {
    "tracking_number": "SWT-20260722-K4F9C2",
    "status": { "value": "out_for_delivery", "label": "Out for Delivery" },
    "priority": "Express",
    "is_overdue": false,
    "sender":   { "name": "Nim*** *******", "city": "Colombo" },
    "receiver": { "name": "Kam*** *****", "city": "Kandy" },
    "shipment": {
      "type": "Package",
      "weight_kg": 2.5,
      "dimensions": "30 × 20 × 15 cm",
      "payment_method": "Cash on Delivery",
      "payment_status": "Pending",
      "handled_by": "Colombo Head Office",
      "delivery_attempts": 0
    },
    "dates": {
      "booked_at": "2026-07-22T09:15:00+05:30",
      "expected_delivery_at": "2026-07-23T23:59:59+05:30",
      "delivered_at": null
    },
    "timeline": [
      {
        "status": "out_for_delivery",
        "label": "Out for Delivery",
        "location": "Kandy",
        "remarks": "Parcel is on the vehicle and out for delivery.",
        "updated_by": "Courier Team",
        "happened_at": "2026-07-22T14:02:00+05:30"
      }
    ],
    "qr_code": "data:image/svg+xml;base64,PHN2Zy…"
  }
}
```

**404** when no parcel has that tracking number.

---

## Dashboard

### `GET /dashboard`

Returns figures scoped to the caller's role:

- **Super Admin** — the whole network, plus `branch_performance`
- **Branch Manager** — their branch only
- **Dispatcher** — their branch, with revenue fields removed
- **Driver** — their own workload only (`scope: "driver"`)

A Driver whose account has no linked driver record receives **409**.

---

## Parcels

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/parcels` | List, filter and search |
| `POST` | `/parcels` | Book a parcel |
| `GET` | `/parcels/{id}` | Full detail |
| `PUT` | `/parcels/{id}` | Update (blocked once delivered/returned/cancelled) |
| `DELETE` | `/parcels/{id}` | Soft delete |
| `POST` | `/parcels/{id}/cancel` | Cancel and release any assigned driver |
| `GET` | `/parcels/{id}/trackings` | Tracking timeline |
| `POST` | `/parcels/{id}/trackings` | Log a tracking event |
| `GET` | `/parcels/{id}/qr` | QR payload plus an SVG data URI |

### Filters on `GET /parcels`

| Parameter | Example | Notes |
|---|---|---|
| `search` | `SWT-2026` | Tracking number, receiver, phone, city, or customer name/code |
| `status` | `out_for_delivery` | See [Parcel statuses](#parcel-statuses) |
| `priority` | `express` | `normal`, `express`, `same_day` |
| `customer_id` | `12` | |
| `branch_id` | `3` | Ignored for non-admins — you always get your own branch |
| `driver_id` | `7` | Parcels ever assigned to this driver |
| `from` / `to` | `2026-07-01` | Booking date range, inclusive |

### `POST /parcels`

```json
{
  "customer_id": 12,
  "branch_id": 1,
  "receiver_name": "Kamala Silva",
  "receiver_phone": "0771234567",
  "receiver_address": "42, Temple Road",
  "receiver_city": "Kandy",
  "receiver_postal_code": "20000",
  "pickup_address": "10, Galle Road, Colombo 03",
  "parcel_type": "package",
  "weight": 2.5,
  "length_cm": 30,
  "width_cm": 20,
  "height_cm": 15,
  "delivery_charge": 750.00,
  "cod_amount": 4500.00,
  "payment_method": "cash_on_delivery",
  "priority": "express",
  "special_instructions": "Call before delivery."
}
```

Validation highlights:

- `weight` must be **numeric and greater than zero**
- `receiver_phone` must be a **Sri Lankan mobile** (`07XXXXXXXX`, `+947XXXXXXXX` or `947XXXXXXXX`)
- Dimensions are all-or-nothing — supply all three or none
- `cod_amount` is only allowed when `payment_method` is `cash_on_delivery`
- A customer who is `inactive` or `blacklisted` cannot book

On success the tracking number is generated, the QR code is written, and the
`Package Created` tracking event is logged.

### `POST /parcels/{id}/trackings`

```json
{
  "status": "at_warehouse",
  "location": "Colombo sorting facility",
  "remarks": "Received and scanned."
}
```

Accepted values: `picked_up`, `at_warehouse`, `sorted`, `dispatched`,
`out_for_delivery`, `delivered`, `failed_delivery`, `returned`, `note`.

`sorted`, `dispatched` and `note` are timeline-only and do not change the
parcel's status. The rest move the parcel, and an illegal transition (for
example Pending → Delivered) returns **422**.

---

## Deliveries

| Method | Endpoint | Who |
|---|---|---|
| `GET` | `/deliveries` | Everyone (scoped) |
| `GET` | `/deliveries/{id}` | Everyone (scoped) |
| `POST` | `/deliveries` | Dispatcher / management |
| `POST` | `/deliveries/{id}/accept` | **Assigned driver only** |
| `POST` | `/deliveries/{id}/reject` | **Assigned driver only** |
| `POST` | `/deliveries/{id}/in-transit` | **Assigned driver only** |
| `POST` | `/deliveries/{id}/complete` | **Assigned driver only** |
| `POST` | `/deliveries/{id}/fail` | **Assigned driver only** |
| `POST` | `/deliveries/{id}/cancel` | Dispatcher / management |

A delivery belongs to exactly one driver. No one — not even a Super Admin —
can accept or complete it on that driver's behalf.

### `POST /deliveries` — assign a parcel

```json
{ "parcel_id": 42, "driver_id": 7, "notes": "Fragile, handle with care." }
```

Rejected with **422** when the driver is inactive, their licence has expired,
they belong to a different branch, or the parcel exceeds their vehicle's
capacity. Any assignment already open on the parcel is closed automatically,
so two drivers can never hold the same parcel.

### `POST /deliveries/{id}/complete`

```json
{
  "received_by": "Kamala Silva",
  "receiver_nic": "199012345678",
  "delivery_location": "42, Temple Road, Kandy",
  "delivery_latitude": 7.2906,
  "delivery_longitude": 80.6337,
  "cod_collected": 4500.00,
  "notes": "Left with the receiver in person.",
  "signature": "data:image/png;base64,iVBORw0KG…"
}
```

`signature` is a base64 data URL, so a mobile client can post proof of delivery
as plain JSON. To attach a photo as well, send the request as
`multipart/form-data` with a `proof_image` file part.

Completing marks the parcel **Delivered**, settles a cash-on-delivery payment,
and frees the driver.

### `POST /deliveries/{id}/fail`

```json
{ "reason": "Receiver not available at the address.", "location": "Kandy" }
```

After `courier.delivery.max_attempts` (default **3**) failed attempts the parcel
is automatically returned to sender.

---

## Customers

| Method | Endpoint |
|---|---|
| `GET` | `/customers` — filters: `search`, `status`, `city` |
| `POST` | `/customers` |
| `GET` | `/customers/{id}` |
| `PUT` | `/customers/{id}` |
| `DELETE` | `/customers/{id}` — soft delete, management only |
| `GET` | `/customers/{id}/parcels` — shipment history |

`customer_code` is generated automatically (`CUS-2026-00042`).
NIC and mobile are unique among live records only — soft-deleting a customer
frees their number for re-registration.

---

## Drivers

| Method | Endpoint |
|---|---|
| `GET` | `/drivers` — filters: `search`, `status`, `branch_id` |
| `POST` | `/drivers` — send as `multipart/form-data` to include `photo` |
| `GET` | `/drivers/{id}` |
| `PUT` | `/drivers/{id}` |
| `DELETE` | `/drivers/{id}` — refused while the driver has open deliveries |
| `GET` | `/drivers/{id}/deliveries` |

`driver_code` is generated automatically (`DRV-2026-00007`).

---

## Branches

| Method | Endpoint |
|---|---|
| `GET` | `/branches` |
| `POST` | `/branches` — Super Admin only |
| `GET` | `/branches/{id}` |
| `PUT` | `/branches/{id}` |
| `DELETE` | `/branches/{id}` — refused while parcels are in transit |
| `GET` | `/branches/{id}/parcels` |

---

## Reports

All five accept `from`, `to`, and where relevant `branch_id`, `driver_id`,
`customer_id`. A Branch Manager's scope is forced to their own branch whatever
the query string says. Drivers receive **403**.

| Endpoint | Returns |
|---|---|
| `GET /reports/daily-shipments` | Per-day counts and revenue |
| `GET /reports/monthly-revenue` | Collected, outstanding, refunded, by method |
| `GET /reports/driver-performance` | Assignments, outcomes, success rate |
| `GET /reports/customer-shipments` | Volume and spend per customer |
| `GET /reports/deliveries` | Row per delivery |

```json
{
  "success": true,
  "data": [
    { "day": "2026-07-22", "total_shipments": 14, "delivered": 11, "revenue": 9772.50 }
  ],
  "meta": { "report": "daily_shipments", "from": "2026-07-01", "to": "2026-07-22", "row_count": 22 }
}
```

CSV, Excel and PDF export are available through the web UI at
`/reports/export/{report}/{format}`.

---

## Roles and permissions

| Capability | Super Admin | Branch Manager | Dispatcher | Driver |
|---|:---:|:---:|:---:|:---:|
| See all branches | ✅ | — | — | — |
| Manage branches | ✅ | edit own | — | — |
| Manage staff accounts | ✅ | dispatchers & drivers in own branch | — | — |
| Assign roles | ✅ | — | — | — |
| Manage customers | ✅ | ✅ | create/edit | — |
| Delete customers | ✅ | ✅ | — | — |
| Manage drivers | ✅ | own branch | read only | own record |
| Book / edit parcels | ✅ | ✅ | ✅ | — |
| Cancel parcels | ✅ | ✅ | ✅ | — |
| Assign deliveries | ✅ | ✅ | ✅ | — |
| Accept / complete a delivery | — | — | — | ✅ own only |
| View reports | ✅ | own branch | ✅ | — |
| View revenue figures | ✅ | ✅ | — | — |

### Parcel statuses

`pending` → `picked_up` → `at_warehouse` → `out_for_delivery` → `delivered`

Alternatives: `failed_delivery` (retryable), `returned`, `cancelled`.
Transitions are enforced server-side; each parcel's `status.allowed_transitions`
tells a client exactly which moves are legal next.

### Delivery statuses

`assigned` → `accepted` → `in_transit` → `completed`
Alternatives: `rejected`, `failed`, `cancelled`.

---

## Error reference

| Situation | Code | Body |
|---|---|---|
| No / bad token | 401 | `{"message": "Unauthenticated. Provide a valid bearer token."}` |
| Wrong role | 403 | `{"message": "This action is unauthorized."}` |
| Unknown id, or outside your branch | 404 | `{"message": "The requested resource was not found."}` |
| Validation | 422 | `{"message": "The given data was invalid.", "errors": {…}}` |
| Business rule | 422 | `{"success": false, "message": "A parcel cannot move from Pending to Delivered."}` |
| Throttled | 429 | `{"message": "Too many requests."}` |

---

## Quick start

```bash
# 1. Get a token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@swifttrack.lk","password":"password"}' \
  | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["data"]["token"];')

# 2. Call an endpoint
curl -s http://localhost:8000/api/v1/parcels?per_page=5 \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

# 3. Track a parcel with no token at all
curl -s http://localhost:8000/api/v1/track/SWT-20260722-K4F9C2 \
  -H "Accept: application/json"
```
