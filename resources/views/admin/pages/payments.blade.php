{{--
  Payments — whether the gateway is live, and every payment taken.

  The gateway and its keys are set in the server's .env only (see
  PaymentSettings); this page shows their status and can check them with a
  read-only Razorpay call, but cannot change them.
--}}
<template x-if="view === 'payments'">
  <div class="animate-enter-up" x-init="loadPaySettings(); loadPayments()">
    @include('admin.partials.page-header', [
      'kicker' => 'Payments',
      'title' => 'Payments',
      'description' => 'The payment gateway for the website and the app, and every payment taken.',
    ])

    {{-- ── Gateway settings ─────────────────────────────────────────── --}}
    <template x-if="paySettings.busy && !paySettings.data">
      @include('admin.partials.loading-panel', ['label' => 'Loading payment settings…'])
    </template>

    <template x-if="paySettings.data">
      <div class="mb-xl rounded-card overflow-hidden bg-surface border-hair border-hairline shadow-card">
        <div class="flex flex-wrap items-center justify-between gap-md border-b border-hairline-divider px-lg py-md">
          <div>
            <h2 class="text-h4 text-ink">Payment gateway</h2>
            <p class="mt-[2px] text-caption text-ink-muted">Used by the website and the app. Set on the server (.env) — not changeable here.</p>
          </div>
          <template x-if="paySettings.data.gateway === 'razorpay' && paySettings.data.razorpay.mode === 'live'">
            <span class="inline-flex items-center gap-xs rounded-field bg-success-bg px-md py-[5px] text-tag font-semibold text-success">
              <span class="h-2 w-2 rounded-full bg-success"></span> Razorpay LIVE — taking real payments
            </span>
          </template>
          <template x-if="paySettings.data.gateway === 'razorpay' && paySettings.data.razorpay.mode === 'test'">
            <span class="inline-flex items-center rounded-field bg-warning-bg px-md py-[5px] text-tag font-semibold text-warning">Razorpay test mode</span>
          </template>
          <template x-if="paySettings.data.gateway === 'test'">
            <span class="inline-flex items-center rounded-field bg-danger-bg px-md py-[5px] text-tag font-semibold text-danger">Test gateway — no money is collected</span>
          </template>
        </div>

        <div class="space-y-lg px-lg py-lg">
          {{-- Status (read-only) --}}
          <dl class="grid gap-sm sm:grid-cols-2">
            <div class="rounded-field bg-canvas-alt px-md py-sm">
              <dt class="text-kicker text-ink-secondary">GATEWAY</dt>
              <dd class="mt-xs text-bodysm font-semibold text-ink" x-text="paySettings.data.gateway === 'razorpay' ? 'Razorpay' : 'Test (no money)'"></dd>
            </div>
            <div class="rounded-field bg-canvas-alt px-md py-sm">
              <dt class="text-kicker text-ink-secondary">KEY ID</dt>
              <dd class="mt-xs break-all text-bodysm text-ink" x-text="paySettings.data.razorpay.key_id || '—'"></dd>
            </div>
            <div class="rounded-field bg-canvas-alt px-md py-sm">
              <dt class="text-kicker text-ink-secondary">KEY SECRET</dt>
              <dd class="mt-xs text-bodysm font-semibold" :class="paySettings.data.razorpay.key_secret_set ? 'text-success' : 'text-danger'"
                  x-text="paySettings.data.razorpay.key_secret_set ? 'Set on the server' : 'Missing — add RAZORPAY_KEY_SECRET to the server .env'"></dd>
            </div>
            <div class="rounded-field bg-canvas-alt px-md py-sm">
              <dt class="text-kicker text-ink-secondary">WEBHOOK SECRET</dt>
              <dd class="mt-xs text-bodysm font-semibold" :class="paySettings.data.razorpay.webhook_secret_set ? 'text-success' : 'text-warning'"
                  x-text="paySettings.data.razorpay.webhook_secret_set ? 'Set on the server' : 'Not set — closed-tab payments and refunds will not sync'"></dd>
            </div>
          </dl>

          {{-- Webhook --}}
          <div class="rounded-field bg-canvas-alt px-md py-sm">
            <span class="block text-kicker text-ink-secondary">RAZORPAY WEBHOOK</span>
            <div class="mt-xs flex flex-wrap items-center gap-sm">
              <code class="break-all text-caption text-ink" x-text="paySettings.data.webhook_url"></code>
              <button type="button" @click="copyWebhookUrl()" class="rounded-button px-sm py-[3px] text-caption font-semibold text-primary hover:bg-primary-light"
                      x-text="paySettings.copied ? 'Copied' : 'Copy'"></button>
            </div>
            <p class="mt-xs text-caption text-ink-muted">Add this URL in Razorpay → Webhooks with the events
              <span x-text="paySettings.data.webhook_events.join(', ')"></span>; its secret goes in the server .env as RAZORPAY_WEBHOOK_SECRET.</p>
          </div>

          <template x-if="paySettings.test">
            <p class="rounded-field px-md py-sm text-bodysm" :class="paySettings.test.ok ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'" x-text="paySettings.test.message"></p>
          </template>

          <div x-show="canWrite" class="flex flex-wrap justify-end gap-sm">
            <button type="button" @click="testPaySettings()" :disabled="paySettings.testing"
                    class="inline-flex h-10 items-center gap-xs rounded-button border-hair border-hairline px-lg text-btnghost font-semibold text-ink hover:bg-canvas-alt disabled:opacity-60">
              <span x-show="paySettings.testing" x-html="ICONS.loader" class="animate-spin [&>svg]:h-4 [&>svg]:w-4"></span> Test connection
            </button>
          </div>
        </div>
      </div>
    </template>

    {{-- ── Summary ──────────────────────────────────────────────────── --}}
    <template x-if="payments.summary">
      <div class="mb-lg grid grid-cols-2 gap-md lg:grid-cols-4">
        <template x-for="tile in [
            ['Paid today', rupees(payments.summary.paid_today_paise)],
            ['Paid this month', rupees(payments.summary.paid_month_paise)],
            ['Paid orders', fmtNumber(payments.summary.paid_count)],
            ['Refunded', fmtNumber(payments.summary.refunded_count)],
          ]" :key="tile[0]">
          <div class="rounded-card bg-surface border-hair border-hairline px-lg py-md shadow-card">
            <span class="block text-kicker text-ink-secondary" x-text="tile[0].toUpperCase()"></span>
            <span class="mt-xs block text-h3 tabular-nums text-ink" x-text="tile[1]"></span>
          </div>
        </template>
      </div>
    </template>

    {{-- ── Orders ───────────────────────────────────────────────────── --}}
    @include('admin.partials.section-eyebrow', ['title' => 'Payments', 'hint' => 'Newest first'])

    @include('admin.partials.list-shell', [
      'state' => 'payments',
      'loader' => 'loadPayments',
      'cols' => 6,
      'placeholder' => 'Search phone, order #, pay_… or order_…',
      'emptyTitle' => 'No payments match',
      'emptyMessage' => 'Try a different status or search.',
      'filters' => '
        <div class="relative">
          <select x-model="payments.status" @change="loadPayments(1)" aria-label="Status"
                  class="h-[46px] w-[160px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none focus:border-primary focus:shadow-glow">
            <option value="">Any status</option>
            <option value="paid">Paid</option>
            <option value="created">Awaiting payment</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>
        <div class="relative">
          <select x-model="payments.gateway" @change="loadPayments(1)" aria-label="Gateway"
                  class="h-[46px] w-[150px] cursor-pointer appearance-none rounded-field bg-surface-muted px-md pr-10 text-input text-ink border-hair border-hairline outline-none focus:border-primary focus:shadow-glow">
            <option value="">Any gateway</option>
            <option value="razorpay">Razorpay</option>
            <option value="test">Test</option>
          </select>
          <span x-html="ICONS.chevronDown" aria-hidden="true" class="pointer-events-none absolute right-md top-1/2 -translate-y-1/2 [&>svg]:h-5 [&>svg]:w-5 text-ink-muted"></span>
        </div>',
      'head' => '
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">ORDER</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">ACCOUNT</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">FOR</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted text-right">AMOUNT</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted">STATUS</th>
        <th scope="col" class="whitespace-nowrap border-b border-hairline px-lg py-md text-kicker text-ink-muted hidden lg:table-cell">GATEWAY REF</th>',
      'body' => '
        <template x-for="row in payments.data" :key="row.id">
          <tr @click="openUser(row.user.id)" class="border-b border-hairline-divider last:border-0 cursor-pointer transition-colors duration-micro hover:bg-canvas-alt">
            <td class="px-lg py-md align-middle">
              <span class="block text-bodysm font-semibold text-ink" x-text="\'#\' + row.id"></span>
              <span class="block text-caption text-ink-muted" x-text="fmtDateTime(row.paid_at || row.created_at)"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink">
              <span class="block" x-text="row.user.phone || \'—\'"></span>
              <span class="block text-caption text-ink-muted" x-text="row.audience === \'recruiter\' ? \'Employer\' : \'Job seeker\'"></span>
            </td>
            <td class="px-lg py-md align-middle text-bodysm text-ink" x-text="row.plan_name"></td>
            <td class="px-lg py-md align-middle text-right text-bodysm font-semibold tabular-nums text-ink" x-text="row.amount_label"></td>
            <td class="px-lg py-md align-middle">
              <span class="inline-flex items-center rounded-field px-md py-[5px] text-tag whitespace-nowrap" :class="payStatusTone(row.status)" x-text="row.status_label"></span>
              <span x-show="row.failure_reason" class="mt-[2px] block max-w-[220px] truncate text-caption text-ink-muted" x-text="row.failure_reason"></span>
            </td>
            <td class="hidden px-lg py-md align-middle lg:table-cell">
              <span class="block text-caption text-ink" x-text="row.gateway"></span>
              <span class="block break-all text-caption text-ink-muted" x-text="row.gateway_ref || row.gateway_order_id || \'—\'"></span>
            </td>
          </tr>
        </template>',
    ])
  </div>
</template>
