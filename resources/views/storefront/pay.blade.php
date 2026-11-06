@extends('storefront.layout', ['title' => 'Pay for order '.$order->number, 'inlineErrors' => true])

@section('content')
<div class="container py-4 py-lg-5" style="max-width: 980px">
    <div class="text-center mb-4">
        <div class="section-kicker">Order {{ $order->number }}</div>
        <h1 class="section-title h2">Complete your payment</h1>
        <p class="text-muted mb-0">Amount to pay: <strong class="text-body fs-5">{{ $order->money($order->balanceDue()) }}</strong></p>
        @if ($expiresAt && ! $pending)
            <p class="small text-muted mt-1">We are holding your items until {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('g:i A') }}.</p>
        @endif
    </div>

    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    @if ($method->code === 'khalti')
        <div class="panel text-center mx-auto" style="max-width:480px">
            <div class="pay-logo khalti mx-auto mb-3" style="width:64px;height:64px;font-size:1.5rem">K</div>
            <h2 class="h5 fw-bold">Pay with Khalti</h2>
            <p class="text-muted small">You will go to Khalti's secure page to pay with your Khalti wallet, mobile banking, or cards, then come back here automatically.</p>
            <form method="POST" action="{{ route('shop.order.pay.khalti', ['number' => $order->number, 't' => $token]) }}" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button class="btn w-100 py-2 fw-bold text-white" style="background:#5c2d91;border-radius:10px" :disabled="busy">
                    <span x-show="!busy">Pay {{ $order->money($order->balanceDue()) }} with Khalti</span>
                    <span x-show="busy" x-cloak><span class="spinner-border spinner-border-sm"></span> Opening Khalti...</span>
                </button>
            </form>
            <a href="{{ route('shop.order', ['number' => $order->number, 't' => $token]) }}" class="small d-inline-block mt-3">View order details</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-md-6">
                @if ($method->code === 'qr')
                    <div class="qr-box">
                        <div class="fw-bold mb-2">Scan to pay</div>
                        <img src="{{ $method->qrImageUrl() }}" alt="Payment QR code">
                        @if ($holder = $method->configValue('account_name'))<div class="fw-semibold mt-2">{{ $holder }}</div>@endif
                        @if ($label = $method->configValue('qr_label'))<div class="small text-muted">{{ $label }}</div>@endif
                        <div class="mt-2 fs-5 fw-bold">{{ $order->money($order->balanceDue()) }}</div>
                        <div class="small text-muted">Write <strong>{{ $order->number }}</strong> in the payment remarks.</div>
                    </div>
                @else
                    <div class="panel">
                        <div class="fw-bold mb-3"><i class="bi bi-bank"></i> Bank transfer details</div>
                        <dl class="row small mb-0">
                            <dt class="col-5">Bank</dt><dd class="col-7">{{ $method->configValue('bank_name') }}</dd>
                            <dt class="col-5">Account name</dt><dd class="col-7">{{ $method->configValue('account_name') }}</dd>
                            <dt class="col-5">Account number</dt><dd class="col-7 fw-bold">{{ $method->configValue('account_number') }}</dd>
                            @if ($branch = $method->configValue('branch'))<dt class="col-5">Branch</dt><dd class="col-7">{{ $branch }}</dd>@endif
                            <dt class="col-5">Amount</dt><dd class="col-7 fw-bold">{{ $order->money($order->balanceDue()) }}</dd>
                            <dt class="col-5">Remarks</dt><dd class="col-7">{{ $order->number }}</dd>
                        </dl>
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                @if ($pending)
                    <div class="panel text-center py-5">
                        <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                        <h2 class="h5 fw-bold mt-2">Payment submitted</h2>
                        <p class="text-muted">We are checking your payment and will confirm your order soon. You will see the update on your order page.</p>
                        <a href="{{ route('shop.order', ['number' => $order->number, 't' => $token]) }}" class="btn btn-ink">View order</a>
                    </div>
                @else
                    <div class="panel">
                        <ol class="steps small">
                            <li>{{ $method->code === 'qr' ? 'Open your banking or wallet app and scan the QR code.' : 'Transfer the amount to the account shown.' }}</li>
                            <li>Pay exactly <strong>{{ $order->money($order->balanceDue()) }}</strong> and put <strong>{{ $order->number }}</strong> in the remarks.</li>
                            <li>Upload the payment screenshot or enter the transaction ID below.</li>
                        </ol>
                        @if ($method->instructions)<p class="small text-muted">{{ $method->instructions }}</p>@endif
                        <form method="POST" action="{{ route('shop.order.pay.manual', ['number' => $order->number, 't' => $token]) }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="proof">Payment screenshot</label>
                                <input id="proof" type="file" name="proof" accept="image/png,image/jpeg,image/webp" class="form-control @error('proof') is-invalid @enderror">
                                @error('proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="reference">Transaction ID</label>
                                <input id="reference" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}" maxlength="100" placeholder="e.g. 0123456789">
                                @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-accent w-100 py-2" :disabled="busy">I have paid</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
