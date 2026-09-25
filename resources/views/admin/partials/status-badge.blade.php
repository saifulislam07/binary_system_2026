@php
    $class = match ($status) {
        'active', 'paid', 'completed', 'approved', 'success' => 'text-bg-success',
        'pending', 'initiated' => 'text-bg-warning',
        'processing' => 'text-bg-info',
        'suspended', 'rejected', 'failed', 'refunded', 'reversed' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
@endphp
<span class="badge {{ $class }}">{{ ucfirst($status) }}</span>
