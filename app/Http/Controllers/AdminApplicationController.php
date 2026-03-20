<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AdminApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // optional filter

        $query = Application::query()->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->paginate(10)->withQueryString();

        return view('admin.applications.index', compact('applications', 'status'));
    }

    public function show(Application $application)
    {
        $application->load('installation');
        $technicians = User::where('role', 'technician')->orderBy('name')->get();

        return view('admin.applications.show', compact('application', 'technicians'));
    }

    public function updateStatus(Request $request, Application $application)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:submitted,under_review,approved,rejected'],
        ]);

        $updateData = ['status' => $validated['status']];

        if (Schema::hasColumn('applications', 'rejection_reason')) {
            if ($validated['status'] === 'rejected') {
                $request->validate([
                    'rejection_reason' => ['required', 'string'],
                ]);
                $updateData['rejection_reason'] = $request->input('rejection_reason');
            }

            if ($validated['status'] === 'approved') {
                $updateData['rejection_reason'] = null;
            }
        }

        $application->update($updateData);

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', 'Application status updated.');
    }

    public function schedule(Request $request, Application $application)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'exists:users,id'],
            'installation_date' => ['required', 'date'],
            'installation_time' => ['nullable'],
        ]);

        if ($application->status === 'installed') {
            return back()->withErrors([
                'schedule' => 'Installation already completed. Cannot reschedule.',
            ]);
        }

        $technician = User::find($validated['technician_id']);
        if (! $technician || ! $technician->isTechnician()) {
            return back()
                ->withErrors(['technician_id' => 'Selected user is not a technician.'])
                ->withInput();
        }

        $installation = Installation::where('application_id', $application->id)->first();
        if ($installation && $installation->status === 'completed') {
            return back()->withErrors([
                'schedule' => 'This installation has already been completed.',
            ]);
        }

        Installation::updateOrCreate(
            ['application_id' => $application->id],
            [
                'technician_id' => $validated['technician_id'],
                'installation_date' => $validated['installation_date'],
                'installation_time' => $validated['installation_time'],
                'status' => 'scheduled',
            ]
        );

        if (! in_array($application->status, ['rejected', 'installed'], true)) {
            $application->update([
                'status' => 'scheduled',
            ]);
        }

        return back()->with('success', 'Installation scheduled successfully.');
    }
}

