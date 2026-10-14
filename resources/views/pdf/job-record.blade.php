@php
    $rm = fn (int $cents) => 'RM '.number_format($cents / 100, 2);
    $status = fn (string $status) => match ($status) {
        'draft' => __('Draft'), 'sent' => __('Sent to client'), 'active' => __('Active'), 'completed' => __('Completed'),
        'cancelled' => __('Cancelled'), 'planned' => __('Planned'), 'in_progress' => __('In progress'),
        'requested' => __('Payment requested'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'paid' => __('Paid'),
        'open', 'pending' => __('Awaiting client'), 'withdrawn' => __('Withdrawn'), 'signed' => __('Signed'),
        'confirmed' => __('Confirmed'), 'disputed' => __('Disputed'), default => ucfirst(str_replace('_', ' ', $status)),
    };
    $method = fn (string $method) => match ($method) {
        'transfer' => __('Bank transfer'), 'cash' => __('Cash'), 'cheque' => __('Cheque'), default => __('Other'),
    };
    $proofType = fn (string $type) => match ($type) { 'photo' => __('Photo'), 'file' => __('File'), default => __('Note') };
    $date = fn ($value) => $value?->format('d/m/Y');
    $dateTime = fn ($value) => $value?->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Job record') }} — {{ $job->title }}</title>
    <style>
        @if (app()->getLocale() === 'zh')
            {{-- dompdf has no per-glyph fallback, so Chinese records use one CJK font (it has Latin too) throughout. --}}
            @font-face { font-family: 'Noto Sans SC'; font-weight: normal; src: url('{{ resource_path('fonts/NotoSansSC-Regular.ttf') }}') format('truetype'); }
            @font-face { font-family: 'Noto Sans SC'; font-weight: bold; src: url('{{ resource_path('fonts/NotoSansSC-Regular.ttf') }}') format('truetype'); }
            body, .hash { font-family: 'Noto Sans SC', sans-serif; }
        @else
            body { font-family: DejaVu Sans, sans-serif; }
            .hash { font-family: DejaVu Sans Mono, monospace; }
        @endif
        body { font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #b38f00; color: #1c2128; }
        h3 { font-size: 11px; margin: 10px 0 4px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { text-align: left; padding: 4px 5px; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { background: #f1f2f4; }
        .right { text-align: right; }
        .photo { display: inline-block; width: 31%; margin: 0 1% 6px 0; vertical-align: top; }
        .photo img { width: 100%; }
        .hash { font-size: 7px; color: #666; word-break: break-all; }
        .flag { color: #b45309; }
    </style>
</head>
<body>
    <h1>{{ $job->title }}</h1>
    <div class="muted">{{ $job->site_address }}</div>
    <table style="margin-top: 10px">
        <tr><th>{{ __('Contractor') }}</th><td>{{ $job->company->name }}</td><th>{{ __('Client') }}</th><td>{{ $job->client->name }} ({{ $job->client->phone }})</td></tr>
        <tr><th>{{ __('Status') }}</th><td>{{ $status($job->status) }}</td><th>{{ __('Accepted') }}</th><td>{{ $dateTime($job->accepted_at) ?? '—' }}</td></tr>
        <tr><th>{{ __('Quote') }}</th><td>{{ $rm($summary['quote_cents']) }}</td><th>{{ __('Total incl. signed VOs') }}</th><td>{{ $rm($summary['total_cents']) }}</td></tr>
        <tr><th>{{ __('Confirmed by client') }}</th><td>{{ $rm($summary['confirmed_cents']) }}</td><th>{{ __('Balance') }}</th><td>{{ $rm($summary['balance_cents']) }}</td></tr>
    </table>

    <h2>{{ __('Milestones') }}</h2>
    <table>
        <tr><th>#</th><th>{{ __('Milestone') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th><th class="right">{{ __('Amount') }}</th></tr>
        @foreach ($job->milestones as $milestone)
            <tr>
                <td>{{ $milestone->sequence }}</td>
                <td>{{ $milestone->name }}</td>
                <td>{{ $date($milestone->due_date) ?? '—' }}</td>
                <td>{{ $status($milestone->status) }}</td>
                <td class="right">{{ $rm($milestone->amount_cents) }}</td>
            </tr>
        @endforeach
    </table>

    @foreach ($job->milestones as $milestone)
        @continue($milestone->proofs->isEmpty() && $milestone->paymentRequests->isEmpty())
        <h3>{{ $milestone->sequence }}. {{ $milestone->name }}</h3>

        @foreach ($milestone->paymentRequests->sortBy('id') as $request)
            <div>
                {{ __('Payment request :amount — :status, sent :date', ['amount' => $rm($request->amount_cents), 'status' => $status($request->status), 'date' => $dateTime($request->created_at)]) }}@if ($request->decided_at), {{ __('decided :date', ['date' => $dateTime($request->decided_at)]) }}@endif
                @if ($request->verbal_approval_note)<br><span class="flag">{{ __('APPROVED VERBALLY (recorded by contractor, not by the client in the app): :note', ['note' => $request->verbal_approval_note]) }}</span>@endif
                @if ($request->reject_reason)<br><span class="flag">{{ __('Reason: :reason', ['reason' => $request->reject_reason]) }}</span>@endif
            </div>
        @endforeach

        <div style="margin-top: 4px">
            @foreach ($milestone->proofs as $proof)
                <div class="photo">
                    @isset($photos[$proof->id])
                        <img src="{{ $photos[$proof->id] }}" alt="">
                    @endisset
                    <div>{{ $proofType($proof->type) }} · {{ $dateTime($proof->captured_at) ?? __('no capture time') }}
                        @if ($proof->lat && $proof->lng) · {{ number_format((float) $proof->lat, 5) }}, {{ number_format((float) $proof->lng, 5) }}@endif</div>
                    @if ($proof->caption)<div>{{ $proof->caption }}</div>@endif
                    @if ($proof->sha256)<div class="hash">SHA-256 {{ $proof->sha256 }}</div>@endif
                </div>
            @endforeach
        </div>
    @endforeach

    <h2>{{ __('Variation orders') }}</h2>
    @if ($job->variationOrders->isEmpty())
        <p class="muted">{{ __('None yet.') }}</p>
    @else
        <table>
            <tr><th>{{ __('No.') }}</th><th>{{ __('Description') }}</th><th>{{ __('Status') }}</th><th>{{ __('Signed by') }}</th><th class="right">{{ __('Amount') }}</th></tr>
            @foreach ($job->variationOrders as $vo)
                <tr>
                    <td>{{ $vo->number }}</td>
                    <td>{{ $vo->description }}@if ($vo->reject_reason)<br><span class="flag">{{ __('Rejected: :reason', ['reason' => $vo->reject_reason]) }}</span>@endif</td>
                    <td>{{ $status($vo->status) }}</td>
                    <td>{{ $vo->signer_name ? $vo->signer_name.', '.$dateTime($vo->signed_at) : '—' }}</td>
                    <td class="right">{{ $rm($vo->amount_cents) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('Payments') }}</h2>
    @if ($job->payments->isEmpty())
        <p class="muted">{{ __('None recorded.') }}</p>
    @else
        <table>
            <tr><th>{{ __('Date') }}</th><th>{{ __('Milestone') }}</th><th>{{ __('Method') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Client') }}</th><th class="right">{{ __('Amount') }}</th></tr>
            @foreach ($job->payments->sortBy('paid_on') as $payment)
                <tr>
                    <td>{{ $date($payment->paid_on) }}</td>
                    <td>{{ $payment->milestone?->name ?? '—' }}</td>
                    <td>{{ $method($payment->method) }}</td>
                    <td>{{ $payment->reference ?? '—' }}</td>
                    <td>{{ $status($payment->client_status) }}@if ($payment->dispute_note)<br><span class="flag">{{ $payment->dispute_note }}</span>@endif</td>
                    <td class="right">{{ $rm($payment->amount_cents) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('Audit trail') }}</h2>
    <table>
        <tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th></tr>
        @foreach ($activity as $entry)
            <tr>
                <td>{{ $entry->created_at?->format('d/m/Y H:i:s') }}</td>
                <td>{{ match ($entry->actor_type) { (new \App\Models\User)->getMorphClass() => __('Contractor'), (new \App\Models\Client)->getMorphClass() => __('Client'), default => __('System') } }}</td>
                <td>{{ $entry->label() }}</td>
            </tr>
        @endforeach
    </table>

    <p class="muted" style="margin-top: 14px">{{ __('Generated :date by :app. Photo hashes let anyone check a file has not been altered since upload.', ['date' => now()->format('d/m/Y H:i'), 'app' => config('app.name')]) }}</p>
</body>
</html>
