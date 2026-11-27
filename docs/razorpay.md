# Razorpay — the website's payments

The website takes payments through Razorpay: job post plans (Choose Plan),
the watermark-free resume and plan upgrades. The Android and iOS apps use
Google Play / App Store billing instead (docs/in_app_purchases.md) — store
rules do not allow an outside gateway for these purchases inside the apps.

## 1. Razorpay account

1. Sign up at razorpay.com and complete **KYC / activation** (business PAN,
   bank account, GST if any). Live payments work only after activation.
2. **API keys** — Dashboard → Account & Settings → API Keys → Generate. Keep
   the Key Secret safe; it is shown once. Start in **Test mode** (keys
   `rzp_test_…`), switch to Live keys (`rzp_live_…`) for real money.
3. **Payment capture** — Account & Settings → Payment capture → Automatic is
   recommended. (If it is manual, the server captures each payment itself.)
4. **Webhook** — Account & Settings → Webhooks → Add:
   - URL: `https://inthesjobs.com/api/v1/payments/razorpay/webhook`
   - Secret: a long random string (the same goes in `.env`)
   - Events: `payment.captured`, `order.paid`, `refund.processed`
   - Add it in Test mode and again in Live mode (each mode has its own).

## 2. Server `.env`

```
PAYMENT_GATEWAY=razorpay
RAZORPAY_KEY_ID=rzp_live_xxxxxxxx
RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxx
RAZORPAY_WEBHOOK_SECRET=<the webhook secret>
RAZORPAY_BUSINESS_NAME=INTHES
```

Then `php artisan migrate` (adds `payment_orders.gateway_order_id`) and
`php artisan config:clear`.

**Apps:** with `PAYMENT_GATEWAY=razorpay`, an app that is not on store
billing cannot complete a payment (it has no Razorpay screen). Switch
`IAP_ANDROID_ENABLED` / `IAP_IOS_ENABLED` on at the same time, so app users
pay through Google Play / the App Store.

## 3. How it works

1. The payer picks UPI / Card / Net banking on our sheet.
2. The server opens the order and creates a **Razorpay order** for the exact
   amount, with our order id in its notes.
3. The website opens **Razorpay Checkout** (the picked method shown first).
4. On success, the website sends the payment id to the server, which
   **fetches the payment from Razorpay** and accepts it only if it is
   captured (authorised payments are captured here), for the full amount, on
   a Razorpay order this order created, and not already used by another
   order. Then the plan / postings / clean resume are granted.
5. Closing the Razorpay window cancels; nothing is charged.
6. **Webhook** — if the browser never returns after paying, `payment.captured`
   settles the order the same way. A full `refund.processed` marks the order
   refunded and takes back what it bought (unused postings, the clean
   resume); partial refunds are left to the admin.

## 4. Test before going live

With test keys, use Razorpay's test methods (UPI id `success@razorpay`, test
cards from the Razorpay docs):

1. Buy the watermark-free resume and a 1-posting plan on the website.
2. Check the order is `paid` and the plan applied.
3. Close the Razorpay window once — the toast says you were not charged.
4. Refund a test payment from the Razorpay dashboard — the order becomes
   `refunded` (webhook).
