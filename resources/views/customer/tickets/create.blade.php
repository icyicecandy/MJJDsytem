<x-app-layout>
    <x-slot name="header">
        <h2>New Support Ticket</h2>
    </x-slot>

    <div class="p-6">
        <div class="card">
            <h3 class="title">Submit a Ticket</h3>
            <form method="POST" action="{{ route('customer.tickets.store') }}">
                @csrf
                <label>Subject</label>
                <input type="text" name="subject" required />

                <label>Description</label>
                <textarea name="description" rows="5" required></textarea>

                <button type="submit" class="btn">Create Ticket</button>
            </form>
        </div>
    </div>
</x-app-layout>
