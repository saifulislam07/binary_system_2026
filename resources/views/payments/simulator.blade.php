<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Simulator</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; margin: 0; padding: 16px; }
        .card { max-width: 420px; margin: 48px auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .warn { background: #fef3c7; color: #92400e; padding: 8px 12px; border-radius: 8px; font-size: 14px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; }
        dt { color: #71717a; }
        a.btn { display: block; text-align: center; padding: 10px; border-radius: 8px; text-decoration: none; margin-top: 8px; font-weight: 600; }
        .success { background: #16a34a; color: #fff; } .failed { background: #dc2626; color: #fff; } .cancelled { background: #e4e4e7; color: #18181b; }
    </style>
</head>
<body>
<div class="card">
    <p class="warn">Development payment simulator — no real money moves.</p>
    <h1>Pay {{ \App\Support\Money::format($payment->amount) }}</h1>
    <dl>
        <dt>Order</dt><dd>{{ $payment->order?->order_number }}</dd>
        <dt>Package</dt><dd>{{ $payment->order?->package?->name }}</dd>
        <dt>Reference</dt><dd>{{ $payment->gateway_ref }}</dd>
    </dl>
    @foreach ($outcomes as $status => $label)
        <a class="btn {{ $status }}" href="{{ route('payments.callback', ['gateway' => 'simulator', 'ref' => $payment->gateway_ref, 'status' => $status]) }}">{{ $label }}</a>
    @endforeach
</div>
</body>
</html>
