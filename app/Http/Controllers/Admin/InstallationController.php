<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use Illuminate\Http\Request;

class InstallationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = Installation::query()
            ->with(['application', 'technician'])
            ->latest('installation_date')
            ->latest('installation_time')
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('application', function ($applicationQuery) use ($search) {
                        $applicationQuery->where('id', $search)
                            ->orWhere('reference_no', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                    })
                    ->orWhereHas('technician', function ($technicianQuery) use ($search) {
                        $technicianQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $installations = $query->paginate(15)->withQueryString();

        return view('admin.installations.index', compact('installations', 'status', 'search'));
    }
}
