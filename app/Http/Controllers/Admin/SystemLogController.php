<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;

class SystemLogController extends Controller
{
    public function index()
    {
        $logs = SystemLog::with('user')
            ->latest()
            ->limit(100)
            ->get();

        return view('admin.system-logs.index', compact('logs'));
    }
}
