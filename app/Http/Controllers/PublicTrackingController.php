<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Ticket;

class PublicTrackingController extends Controller
{
    public function form()
    {
        return view('public.track');
    }

    public function ticketForm()
    {
        return view('public.track-ticket');
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'reference_no' => ['required', 'string', 'max:50'],
            'contact' => ['required', 'string', 'max:150'],
        ]);

        $referenceNo = strtoupper(trim($validated['reference_no']));
        $contact = trim($validated['contact']);

        $application = Application::with('installation')
            ->where('reference_no', $referenceNo)
            ->where(function ($q) use ($contact) {
                $q->where('email', $contact)
                  ->orWhere('mobile_number', $contact);
            })
            ->first();

        if (! $application) {
            return back()->withErrors([
                'reference_no' => 'We could not find an application that matches those details.',
            ])->withInput();
        }

        return view('public.track-result', compact('application'));
    }

    public function ticketSearch(Request $request)
    {
        $validated = $request->validate([
            'reference_no' => ['required', 'string', 'max:50'],
            'contact' => ['required', 'email', 'max:150'],
        ]);

        $ticketId = trim($validated['reference_no']);
        $contact = trim($validated['contact']);

        $ticket = Ticket::with('user')
            ->whereKey($ticketId)
            ->whereHas('user', function ($q) use ($contact) {
                $q->where('email', $contact);
            })
            ->first();

        if (! $ticket) {
            return back()->withErrors([
                'reference_no' => 'We could not find a ticket that matches those details.',
            ])->withInput();
        }

        return view('public.track-ticket-result', compact('ticket'));
    }
}
