@extends('layouts.app')

@section('title', 'Analytics & Reports')

@section('content')
<div class="report-page">
    <header class="page-header-card report-screen-only analytics-page-header">
        <div>
            <p class="page-eyebrow">Operations intelligence</p>
            <h1>Analytics &amp; Reports</h1>
            <p>Monitor performance, then create a clearly scoped report for printing, PDF archiving, or CSV analysis.</p>
        </div>
    </header>

    <nav class="analytics-tabs report-screen-only" aria-label="Analytics sections">
        <a href="{{ route('admin.reports.index', ['tab' => 'overview', 'type' => 'operations', 'from' => $filters['from'], 'to' => $filters['to']]) }}" class="analytics-tab {{ $tab === 'overview' ? 'active' : '' }}" @if($tab === 'overview') aria-current="page" @endif>
            <strong>Analytics Overview</strong>
            <span>Trends, pipeline health, and period comparisons</span>
        </a>
        <a href="{{ route('admin.reports.index', ['tab' => 'reports', 'type' => $filters['type'] === 'operations' ? 'applications' : $filters['type'], 'from' => $filters['from'], 'to' => $filters['to']]) }}" class="analytics-tab {{ $tab === 'reports' ? 'active' : '' }}" @if($tab === 'reports') aria-current="page" @endif>
            <strong>Reports</strong>
            <span>Choose records, preview the scope, then print or export</span>
        </a>
    </nav>

    <section class="panel report-filter-panel report-screen-only" aria-labelledby="reportFiltersTitle">
        <div class="panel-head">
            <div>
                <div class="panel-title" id="reportFiltersTitle">{{ $tab === 'overview' ? 'Analytics period' : 'Build a report' }}</div>
                <div class="panel-subtitle">{{ $tab === 'overview' ? 'Choose the period to compare with the immediately preceding period of the same length.' : 'Select the records and period first. Preview confirms exactly what will be printed or exported.' }}</div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.reports.index') }}" class="report-filter-form" data-report-builder>
            <input type="hidden" name="generate" value="1">
            <input type="hidden" name="tab" value="{{ $tab }}">

            @if($tab === 'overview')
                <input type="hidden" name="type" value="operations">
            @else
                <label class="report-filter-field">Report type
                    <select name="type" data-report-type>
                        <option value="applications" @selected($filters['type'] === 'applications')>Applications</option>
                        <option value="installations" @selected($filters['type'] === 'installations')>Installations</option>
                        <option value="tickets" @selected($filters['type'] === 'tickets')>Support tickets</option>
                        <option value="customers" @selected($filters['type'] === 'customers')>Customers</option>
                        <option value="technicians" @selected($filters['type'] === 'technicians')>Technician workload</option>
                    </select>
                </label>
            @endif

            <div class="filter-date-range report-date-range" role="group" aria-labelledby="report_date_range_label">
                <span id="report_date_range_label" class="filter-date-range__label">Report period</span>
                <div class="filter-date-range__fields">
                    <label class="report-filter-field">From
                        <input type="date" name="from" value="{{ $filters['from'] }}" max="{{ $filters['to'] }}" required>
                    </label>
                    <label class="report-filter-field">To
                        <input type="date" name="to" value="{{ $filters['to'] }}" min="{{ $filters['from'] }}" required>
                    </label>
                </div>
            </div>

            <p class="report-period-guidance">Historical reports can cover multiple years. Records are included according to their submission, registration, ticket, or installation date.</p>

            @if($tab === 'reports')
                <label class="report-filter-field report-optional-filter" data-report-types="applications installations tickets customers technicians">Status
                    <select name="status" data-report-status>
                        <option value="">All statuses</option>
                        @foreach($filterOptions['statuses_by_type'][$filters['type']] ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="report-filter-field report-optional-filter" data-report-types="installations tickets">Assigned staff
                    <select name="technician">
                        <option value="">All assigned staff</option>
                        @foreach($filterOptions['staff'] as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['technician'] === (int) $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="report-filter-field report-optional-filter" data-report-types="applications installations customers">Municipality
                    <select name="municipality">
                        <option value="">All municipalities</option>
                        @foreach($filterOptions['municipalities'] as $municipality)
                            <option value="{{ $municipality }}" @selected($filters['municipality'] === $municipality)>{{ $municipality }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="report-filter-field report-optional-filter" data-report-types="tickets">Urgency
                    <select name="urgency">
                        <option value="">All urgency levels</option>
                        @foreach($filterOptions['urgencies'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['urgency'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="report-filter-field report-optional-filter" data-report-types="applications customers">Plan
                    <select name="plan">
                        <option value="">All plans</option>
                        @foreach($filterOptions['plans'] as $plan)
                            <option value="{{ $plan }}" @selected($filters['plan'] === $plan)>{{ $plan }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="report-builder-actions">
                <button class="btn" type="submit">{{ $tab === 'overview' ? 'Update Analytics' : 'Preview Report' }}</button>
                @if($tab === 'reports')
                    <a class="btn secondary" href="{{ route('admin.reports.index', ['tab' => 'reports', 'type' => 'applications', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}">Reset</a>
                @endif
            </div>
        </form>

        <div class="report-period-presets" data-report-presets>
            <span>Quick periods:</span>
            <button type="button" data-period="month">This month</button>
            <button type="button" data-period="30">Last 30 days</button>
            <button type="button" data-period="quarter">This quarter</button>
            <button type="button" data-period="year">This year</button>
        </div>
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
    </section>

    @if($previewReady)
        <div class="report-toolbar report-screen-only">
            <div class="report-toolbar-summary">
                <strong>{{ $report['title'] }}</strong>
                <span>{{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }} &middot; {{ number_format($report['total_rows']) }} matching {{ $report['total_rows'] === 1 ? 'record' : 'records' }}</span>
            </div>
            <div class="report-toolbar-actions">
                <button type="button" class="btn" onclick="window.print()">Print / Save as PDF</button>
                @if($tab === 'reports')
                    <a class="btn secondary" href="{{ route('admin.reports.csv', $report['query']) }}">Export CSV</a>
                @endif
            </div>
        </div>

        <article class="report-print-sheet" aria-label="{{ $report['title'] }}">
            <header class="report-document-header">
                <div>
                    <strong class="report-brand">MJJD Telecom</strong>
                    <span>Customer Relationship Management System</span>
                </div>
                <div class="report-document-meta">
                    <strong>{{ $report['title'] }}</strong>
                    <span>{{ \Carbon\Carbon::parse($filters['from'])->format('F d, Y') }} &ndash; {{ \Carbon\Carbon::parse($filters['to'])->format('F d, Y') }}</span>
                </div>
            </header>

            <div class="report-generated-line">
                <span>Generated by {{ auth()->user()->name }}</span>
                <span>{{ $report['generated_at']->format('F d, Y h:i A') }} ({{ config('app.timezone') }})</span>
            </div>

            @if($tab === 'reports')
                <section class="report-scope" aria-labelledby="reportScopeTitle">
                    <div>
                        <span class="report-scope-eyebrow">Confirmed report scope</span>
                        <h2 id="reportScopeTitle">{{ $report['title'] }}</h2>
                        <p>{{ \Carbon\Carbon::parse($filters['from'])->format('M d, Y') }} to {{ \Carbon\Carbon::parse($filters['to'])->format('M d, Y') }} &middot; {{ number_format($report['total_rows']) }} matching {{ $report['total_rows'] === 1 ? 'record' : 'records' }}</p>
                    </div>
                    <div class="report-scope-filters">
                        @forelse($report['active_filters'] as $filter)
                            <span><small>{{ $filter['label'] }}</small><strong>{{ $filter['value'] }}</strong></span>
                        @empty
                            <span><small>Additional filters</small><strong>None &mdash; all matching records</strong></span>
                        @endforelse
                    </div>
                </section>
            @else
                <div class="report-comparison-heading">
                    <div>
                        <strong>Selected-period performance</strong>
                        <span>Compared with {{ $report['previous_period']['label'] }}</span>
                    </div>
                    <small>Select any metric to open its matching report.</small>
                </div>
            @endif

            <section class="report-summary-grid" aria-label="Report totals">
                @foreach($report['summary'] as $metric)
                    @if($tab === 'overview')
                        @php
                            $metricQuery = [
                                'tab' => 'reports',
                                'generate' => 1,
                                'type' => $metric['report_type'],
                                'from' => $filters['from'],
                                'to' => $filters['to'],
                            ];
                            if (!empty($metric['report_status'])) $metricQuery['status'] = $metric['report_status'];
                        @endphp
                        <a class="report-summary-card is-clickable" href="{{ route('admin.reports.index', $metricQuery) }}" aria-label="View matching {{ strtolower($metric['label']) }} report">
                            <span>{{ $metric['label'] }}</span>
                            <strong>{{ number_format($metric['value']) }}</strong>
                            <small class="report-comparison is-{{ $metric['change_direction'] }}">{{ $metric['change_label'] }} <em>vs {{ number_format($metric['previous_value']) }}</em></small>
                        </a>
                    @else
                        <article class="report-summary-card">
                            <span>{{ $metric['label'] }}</span>
                            <strong>{{ number_format($metric['value']) }}</strong>
                        </article>
                    @endif
                @endforeach
            </section>

            @if($tab === 'overview')
                @php
                    $activityMaximum = max(1, $report['monthly_activity']->flatMap(fn ($month) => [
                        $month['applications'], $month['installations'], $month['tickets'], $month['customers']
                    ])->max());
                    $pipelineMaximum = max(1, $report['application_pipeline']->max('value'));
                @endphp
                <div class="analytics-insight-grid">
                    <section class="analytics-insight-card analytics-trend-card">
                        <div class="analytics-section-heading">
                            <div><h2>Monthly Operations Trend</h2><p>Activity recorded inside the selected period</p></div>
                            <div class="analytics-chart-legend" aria-label="Chart legend">
                                <span class="is-applications">Applications</span>
                                <span class="is-installations">Installations</span>
                                <span class="is-tickets">Tickets</span>
                                <span class="is-customers">Customers</span>
                            </div>
                        </div>
                        <div class="analytics-trend-scroll" tabindex="0" role="region" aria-label="Monthly operations chart">
                            <div class="analytics-trend-chart">
                                @foreach($report['monthly_activity'] as $month)
                                    <div class="analytics-trend-month">
                                        <div class="analytics-trend-bars">
                                            <i class="is-applications" style="height: {{ max(3, round($month['applications'] / $activityMaximum * 100, 2)) }}%" title="{{ $month['applications'] }} applications"><b>{{ $month['applications'] }}</b></i>
                                            <i class="is-installations" style="height: {{ max(3, round($month['installations'] / $activityMaximum * 100, 2)) }}%" title="{{ $month['installations'] }} installations"><b>{{ $month['installations'] }}</b></i>
                                            <i class="is-tickets" style="height: {{ max(3, round($month['tickets'] / $activityMaximum * 100, 2)) }}%" title="{{ $month['tickets'] }} tickets"><b>{{ $month['tickets'] }}</b></i>
                                            <i class="is-customers" style="height: {{ max(3, round($month['customers'] / $activityMaximum * 100, 2)) }}%" title="{{ $month['customers'] }} customers"><b>{{ $month['customers'] }}</b></i>
                                        </div>
                                        <span>{{ $month['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="analytics-insight-card analytics-pipeline-card">
                        <div class="analytics-section-heading"><div><h2>Application Pipeline</h2><p>Status distribution for the period</p></div></div>
                        <div class="analytics-pipeline-list">
                            @foreach($report['application_pipeline'] as $stage)
                                <div class="analytics-pipeline-row">
                                    <div><span>{{ $stage['label'] }}</span><strong>{{ number_format($stage['value']) }}</strong></div>
                                    <div class="analytics-pipeline-track"><i style="width: {{ round($stage['value'] / $pipelineMaximum * 100, 2) }}%"></i></div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>

                <section class="analytics-insight-card analytics-recent-card">
                    <div class="analytics-section-heading">
                        <div><h2>Recent Operations</h2><p>Latest records inside the selected period</p></div>
                        <span>{{ $report['recent_activity']->count() }} shown</span>
                    </div>
                    <div class="table-container report-table-wrap">
                        <table class="data-table report-data-table analytics-recent-table">
                            <thead><tr><th>Type</th><th>Reference</th><th>Customer / Record</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                @forelse($report['recent_activity'] as $activity)
                                    <tr>
                                        <td data-label="Type"><span class="analytics-record-type">{{ $activity['type'] }}</span></td>
                                        <td data-label="Reference"><strong>{{ $activity['reference'] }}</strong></td>
                                        <td data-label="Customer / Record">{{ $activity['record'] ?: 'Not recorded' }}</td>
                                        <td data-label="Status">{{ $activity['status'] }}</td>
                                        <td data-label="Date">{{ $activity['date_label'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center">No activity was recorded for this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="analytics-note">
                    <strong>How the period is calculated</strong>
                    <p>Applications, customers, and tickets use their submission or registration date. Installations use the scheduled installation date. Comparisons use the immediately preceding period with the same number of days.</p>
                </section>
            @else
                <section class="report-table-section">
                    <div class="report-table-heading">
                        <h2>Report records</h2>
                        <span>{{ number_format($report['total_rows']) }} {{ $report['total_rows'] === 1 ? 'record' : 'records' }}</span>
                    </div>
                    @if($report['truncated'])
                        <p class="report-limit-note">The printable preview shows the first 1,000 records. Export CSV for up to 10,000 records.</p>
                    @endif
                    <div class="table-container report-table-wrap">
                        <table class="data-table report-data-table">
                            <thead><tr>@foreach($report['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                            <tbody>
                                @forelse($report['rows'] as $row)
                                    <tr>@foreach(array_keys($report['columns']) as $key)<td data-label="{{ $report['columns'][$key] }}">{{ $row[$key] ?? ' ' }}</td>@endforeach</tr>
                                @empty
                                    <tr><td colspan="{{ count($report['columns']) }}" class="text-center">No records match this report scope.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

            @endif

            <footer class="report-document-footer">
                <span>MJJD Telecom confidential operational report</span>
                <span>Generated from current system records</span>
            </footer>
        </article>
    @else
        <section class="panel report-preview-empty report-screen-only" aria-labelledby="reportPreviewTitle">
            <div class="report-preview-empty__icon" aria-hidden="true">R</div>
            <div>
                <h2 id="reportPreviewTitle">Choose the report you need</h2>
                <p>Select the record type, date range, and any optional filters. Preview the result before printing or exporting so the hardcopy always has a clear scope.</p>
            </div>
        </section>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-report-builder]');
    const presets = document.querySelector('[data-report-presets]');
    if (!form) return;

    const from = form.querySelector('[name="from"]');
    const to = form.querySelector('[name="to"]');
    const type = form.querySelector('[data-report-type]');
    const status = form.querySelector('[data-report-status]');
    const statusesByType = @json($filterOptions['statuses_by_type']);
    const selectedStatus = @json($filters['status']);

    const format = value => {
        const year = value.getFullYear();
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    };

    const updateReportFilters = (keepStatus) => {
        if (!type) return;
        const reportType = type.value;
        form.querySelectorAll('[data-report-types]').forEach(field => {
            const visible = field.dataset.reportTypes.split(' ').includes(reportType);
            field.hidden = !visible;
            const control = field.querySelector('select, input');
            if (control) control.disabled = !visible;
        });
        if (!status) return;
        const desired = keepStatus ? selectedStatus : '';
        status.innerHTML = '<option value="">All statuses</option>';
        Object.entries(statusesByType[reportType] || {}).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            option.selected = value === desired;
            status.appendChild(option);
        });
    };

    updateReportFilters(true);
    if (type) type.addEventListener('change', () => updateReportFilters(false));

    if (presets) {
        presets.addEventListener('click', event => {
            const button = event.target.closest('[data-period]');
            if (!button) return;
            const end = new Date();
            const start = new Date(end);
            if (button.dataset.period === 'month') start.setDate(1);
            if (button.dataset.period === '30') start.setDate(start.getDate() - 29);
            if (button.dataset.period === 'quarter') {
                start.setMonth(Math.floor(end.getMonth() / 3) * 3, 1);
            }
            if (button.dataset.period === 'year') {
                start.setMonth(0, 1);
            }
            from.value = format(start);
            to.value = format(end);
            from.max = to.value;
            to.min = from.value;
        });
    }

    from.addEventListener('change', () => { to.min = from.value; });
    to.addEventListener('change', () => { from.max = to.value; });
});
</script>
@endsection
