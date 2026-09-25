@extends('adminlte::page')

@php
    $money = fn (int $poysha) => \App\Support\Money::format($poysha);
@endphp

@section('title', 'Financial')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="m-0">Financial</h1>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up"></i> Reports</a>
    </div>
@stop

@section('content')
    @include('admin.partials.flash')

    <div class="callout callout-info small">
        Sales revenue, cost of goods and commissions are recorded automatically. Use this page for everything else.
        Expenses in the <em>product cost</em> and <em>commission</em> categories are kept for your records but are
        <strong>not</strong> subtracted again in the P&amp;L.
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'expenses' ? 'active' : '' }}" href="{{ route('admin.financial.index', ['tab' => 'expenses']) }}">Expenses</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'income' ? 'active' : '' }}" href="{{ route('admin.financial.index', ['tab' => 'income']) }}">Other income</a></li>
    </ul>

    @if ($tab === 'expenses')
        <form method="POST" action="{{ route('admin.financial.expenses.store') }}" class="card card-body mb-3">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-select" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ ucfirst(str_replace('_', ' ', $category->value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="amount" class="form-label">Amount (৳)</label>
                    <input id="amount" name="amount" value="{{ old('amount') }}" class="form-control" inputmode="decimal" required>
                </div>
                <div class="col-6 col-md-2">
                    <label for="date" class="form-label">Date</label>
                    <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="form-control" required>
                </div>
                <div class="col-12 col-md-4">
                    <label for="description" class="form-label">Description</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="form-control" required>
                </div>
                <div class="col-12 col-md-2"><button class="btn btn-primary w-100">Add expense</button></div>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th>Recorded by</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="text-nowrap">{{ $expense->date->format('d M Y') }}</td>
                                <td>
                                    {{ ucfirst(str_replace('_', ' ', $expense->category->value)) }}
                                    @if (in_array($expense->category->value, $derived, true))
                                        <span class="badge text-bg-secondary" title="Not counted in the P&L — already derived automatically">records only</span>
                                    @endif
                                </td>
                                <td>{{ $expense->description }}</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($expense->amount) }}</td>
                                <td>{{ $expense->recordedBy?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.financial.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense? The deletion is logged.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Delete expense"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-4">No expenses recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())<div class="card-footer">{{ $expenses->links() }}</div>@endif
        </div>
    @else
        <form method="POST" action="{{ route('admin.financial.income.store') }}" class="card card-body mb-3">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="source" class="form-label">Source</label>
                    <select id="source" name="source" class="form-select" required>
                        @foreach ($sources as $value => $label)
                            <option value="{{ $value }}" @selected(old('source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="amount" class="form-label">Amount (৳)</label>
                    <input id="amount" name="amount" value="{{ old('amount') }}" class="form-control" inputmode="decimal" required>
                </div>
                <div class="col-6 col-md-2">
                    <label for="date" class="form-label">Date</label>
                    <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="form-control" required>
                </div>
                <div class="col-12 col-md-4">
                    <label for="description" class="form-label">Description</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="form-control" required>
                </div>
                <div class="col-12 col-md-2"><button class="btn btn-primary w-100">Add income</button></div>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Source</th><th>Description</th><th class="text-end">Amount</th><th>Recorded by</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($incomes as $income)
                            <tr>
                                <td class="text-nowrap">{{ $income->date->format('d M Y') }}</td>
                                <td>{{ $sources[$income->source] ?? ucfirst($income->source) }}</td>
                                <td>{{ $income->description }}</td>
                                <td class="text-end" style="font-variant-numeric: tabular-nums">{{ $money($income->amount) }}</td>
                                <td>{{ $income->recordedBy?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.financial.income.destroy', $income) }}" onsubmit="return confirm('Delete this income entry? The deletion is logged.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Delete income"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-4">No other income recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($incomes->hasPages())<div class="card-footer">{{ $incomes->links() }}</div>@endif
        </div>
    @endif
@stop
