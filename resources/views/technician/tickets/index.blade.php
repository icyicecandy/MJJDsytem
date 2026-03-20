<x-app-layout>
    <div class="tech-page">
        <section class="card tech-page-header">
            <div>
                <h1>My Assigned Tickets</h1>
                <p>Work through only the support issues assigned to you, reply to customers, and resolve completed concerns cleanly.</p>
            </div>
        </section>

        @if(session('success'))
            <section class="notice-card">
                {{ session('success') }}
            </section>
        @endif

        <section class="card table-card">
            <div class="table-wrap">
                <table class="telecom-table tech-ticket-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                        <tr>
                            <td>
                                <div class="record-strong">{{ $ticket->user->name }}</div>
                                <div class="record-muted">Ticket #{{ $ticket->id }}</div>
                            </td>
                            <td>
                                <a href="{{ route('technician.tickets.show', $ticket) }}" class="record-strong">{{ $ticket->subject }}</a>
                            </td>
                            <td>{{ $ticket->description }}</td>
                            <td><span class="badge {{ $ticket->displayStatusClass() }}">{{ $ticket->displayStatusLabel() }}</span></td>
                            <td class="actions-cell">
                                @if($ticket->status !== \App\Models\Ticket::STATUS_RESOLVED && $ticket->status !== \App\Models\Ticket::STATUS_CLOSED)
                                    <form method="POST" action="{{ route('technician.tickets.resolve', $ticket) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn">Resolve</button>
                                    </form>
                                @else
                                    <span class="record-muted">No action</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5"><div class="empty-state compact">No assigned tickets.</div></td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
