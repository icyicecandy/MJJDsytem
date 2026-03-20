<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CustomerApplicationController;
use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\TechnicianDashboardController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\TechnicianTicketController;
use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('public.home');
});



Route::get('/track-application', [PublicTrackingController::class, 'form'])
    ->name('track.form');

Route::post('/track-application', [PublicTrackingController::class, 'search'])
    ->name('track.search');

Route::get('/track-ticket', [PublicTrackingController::class, 'ticketForm'])
    ->name('track.ticket.form');

Route::post('/track-ticket', [PublicTrackingController::class, 'ticketSearch'])
    ->name('track.ticket.search');


/*
|--------------------------------------------------------------------------
| Breeze Default Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    $user = auth()->user();
    $application = $user->application;

    // after login redirect based on role and onboarding state
    if ($user->isCustomer()) {
        // if no application yet send to apply page
        if (! $application) {
            return redirect()->route('customer.apply');
        }
        return redirect()->route('customer.dashboard');
    }

    if ($user->isTechnician()) {
        return redirect()->route('technician.dashboard');
    }

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::get('/profile/username-check', [ProfileController::class, 'usernameCheck'])
        ->name('profile.username.check');

    Route::get('/profile/export', [ProfileController::class, 'export'])
        ->name('profile.export');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::post('/profile/deletion-request', [ProfileController::class, 'requestDeletion'])
        ->name('profile.deletion.request');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});


/*
|--------------------------------------------------------------------------
| CUSTOMER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:customer'])->group(function () {

    Route::get('/customer/dashboard', function () {

        $user = auth()->user();

        $application = $user->application()
            ->with('installation')
            ->first();

        $totalApplications = $application ? 1 : 0;

        $latestApplication = $application;

        $tickets = $user->tickets()
            ->latest()
            ->take(5)
            ->get();

        $openTickets = $user->tickets()
            ->where('status', 'open')
            ->count();

        $activeApplication = $application
            ? in_array($application->status, [
                'submitted',
                'under_review',
                'approved',
                'scheduled',
                'installed',
            ], true)
            : false;

        // subscription info (guard in case table/migration removed)
        if (\Illuminate\Support\Facades\Schema::hasTable('subscriptions')) {
            $subscription = \App\Models\Subscription::where('user_id', $user->id)->first();
        } else {
            $subscription = null;
        }

        return view('customer.dashboard', compact(
            'totalApplications',
            'latestApplication',
            'tickets',
            'openTickets',
            'activeApplication',
            'subscription'
        ));

    })->name('customer.dashboard');


    Route::get('/customer/apply', [CustomerApplicationController::class, 'create'])
        ->name('customer.apply');

    Route::post('/customer/apply', [CustomerApplicationController::class, 'store'])
        ->name('customer.apply.store');

    Route::get('/customer/apply/done', [CustomerApplicationController::class, 'done'])
        ->name('customer.apply.done');

    Route::get('/customer/application', [CustomerApplicationController::class, 'show'])
        ->name('customer.application.show');

    Route::get('/customer/tickets', [TicketController::class, 'index'])
        ->name('customer.tickets.index');

    Route::get('/customer/tickets/create', [TicketController::class, 'create'])
        ->name('customer.tickets.create');

    Route::post('/customer/tickets', [TicketController::class, 'store'])
        ->name('customer.tickets.store');

    Route::get('/customer/tickets/{ticket}', [TicketController::class, 'show'])
        ->name('customer.tickets.show');

    Route::post('/customer/tickets/{ticket}/reply', [TicketController::class, 'reply'])
        ->name('customer.tickets.reply');
});


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->group(function () {

    Route::get('/admin/dashboard', function () {

        $totalApplications = Application::count();
        $pendingApplications = Application::whereIn('status', ['submitted', 'under_review'])->count();
        $approvedApplications = Application::where('status', 'approved')->count();
        $scheduledInstallations = Application::where('status', 'scheduled')->count();
        $installedApplications = Application::where('status', 'installed')->count();
        $openTickets = Ticket::where('status', 'open')->count();

        // subscription counts (migration may not have run yet)
        if (\Illuminate\Support\Facades\Schema::hasTable('subscriptions')) {
            $activeSubscriptions = \App\Models\Subscription::where('status','active')->count();
            $expiredSubscriptions = \App\Models\Subscription::where('status','expired')->count();
        } else {
            $activeSubscriptions = 0;
            $expiredSubscriptions = 0;
        }

        return view('admin.dashboard', compact(
            'totalApplications',
            'pendingApplications',
            'approvedApplications',
            'scheduledInstallations',
            'installedApplications',
            'openTickets',
            'activeSubscriptions',
            'expiredSubscriptions'
        ));

    })->name('admin.dashboard');


    Route::get('/admin/applications', [AdminApplicationController::class, 'index'])
        ->name('admin.applications.index');

    Route::get('/admin/applications/{application}', [AdminApplicationController::class, 'show'])
        ->name('admin.applications.show');

    Route::patch('/admin/applications/{application}/status', [AdminApplicationController::class, 'updateStatus'])
        ->name('admin.applications.status');

    Route::post('/admin/applications/{application}/schedule', [AdminApplicationController::class, 'schedule'])
        ->name('admin.applications.schedule');

    Route::get('/admin/tickets', [AdminTicketController::class, 'index'])
        ->name('admin.tickets.index');

    Route::patch('/admin/tickets/{ticket}', [AdminTicketController::class, 'update'])
        ->name('admin.tickets.update');

    Route::get('/admin/tickets/{ticket}', [AdminTicketController::class, 'show'])
        ->name('admin.tickets.show');
    Route::post('/admin/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])
        ->name('admin.tickets.reply');

    // additional admin modules
    Route::get('/admin/installations', [\App\Http\Controllers\Admin\InstallationController::class, 'index'])
        ->name('admin.installations.index');
    Route::get('/admin/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])
        ->name('admin.customers.index');
    Route::get('/admin/subscriptions', [\App\Http\Controllers\Admin\SubscriptionController::class, 'index'])
        ->name('admin.subscriptions.index');
    Route::get('/admin/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])
        ->name('admin.reports.index');
});


/*
|--------------------------------------------------------------------------
| TECHNICIAN ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:technician'])->group(function () {

   Route::get('/technician/dashboard', function () {

        $technicianId = auth()->id();

    // Installations
        $totalInstallations = Installation::where('technician_id', $technicianId)->count();

        $pendingInstallations = Installation::where('technician_id', $technicianId)
            ->where('status', 'scheduled')
            ->count();

        $completedInstallations = Installation::where('technician_id', $technicianId)
            ->where('status', 'completed')
            ->count();

    // Tickets
        $assignedTickets = Ticket::where('technician_id', $technicianId)
            ->where('status', 'open')
            ->count();

        return view('technician.dashboard', compact(
            'totalInstallations',
            'pendingInstallations',
            'completedInstallations',
            'assignedTickets'
        ));

    })->name('technician.dashboard');



    Route::get('/technician/installations', [TechnicianDashboardController::class, 'installations'])
        ->name('technician.installations.index');

    Route::get('/technician/installations/{installation}', [TechnicianDashboardController::class, 'showInstallation'])
        ->name('technician.installations.show');

    Route::post('/technician/installations/{installation}/complete',
        [TechnicianDashboardController::class, 'complete'])
        ->name('technician.installations.complete');

    Route::get('/technician/tickets', [TechnicianTicketController::class, 'index'])
        ->name('technician.tickets.index');

    Route::patch('/technician/tickets/{ticket}/resolve',
        [TechnicianTicketController::class, 'resolve'])
        ->name('technician.tickets.resolve');

    Route::get('/technician/tickets/{ticket}', [TechnicianTicketController::class, 'show'])
        ->name('technician.tickets.show');
    Route::post('/technician/tickets/{ticket}/reply', [TechnicianTicketController::class, 'reply'])
        ->name('technician.tickets.reply');
});

// future modules (placeholders)

// subscriptions (customer + admin)
Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/customer/subscription', [\App\Http\Controllers\SubscriptionController::class, 'show'])
        ->name('customer.subscription.show');
});

// ticket messaging and live chat
Route::middleware(['auth'])->group(function () {
    Route::post('/tickets/{ticket}/message', [\App\Http\Controllers\TicketMessageController::class, 'store'])
        ->name('tickets.messages.store');
    Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index'])
        ->name('chat.index');

    Route::get('/chat/{user}', [\App\Http\Controllers\ChatController::class, 'conversation'])
        ->name('chat.conversation');
    Route::post('/chat/{user}', [\App\Http\Controllers\ChatController::class, 'send'])
        ->name('chat.send');
    Route::post('/chat/availability', [\App\Http\Controllers\ChatController::class, 'updateAvailability'])
        ->name('chat.availability');
});

// admin and technician chat panel with conversation list
Route::middleware(['auth', 'role:admin,technician'])->group(function () {
    // reuse same controller methods
    Route::get('/{role}/chat', [\App\Http\Controllers\ChatController::class, 'index'])
        ->name('role.chat.index');
    Route::get('/{role}/chat/{user}', [\App\Http\Controllers\ChatController::class, 'conversation'])
        ->name('role.chat.conversation');
    Route::post('/{role}/chat/{user}', [\App\Http\Controllers\ChatController::class, 'send'])
        ->name('role.chat.send');
    Route::post('/{role}/chat/{user}/takeover', [\App\Http\Controllers\ChatController::class, 'takeOver'])
        ->name('role.chat.takeover');
    Route::post('/{role}/chat/{user}/assign', [\App\Http\Controllers\ChatController::class, 'assign'])
        ->name('role.chat.assign');
    Route::post('/{role}/chat/availability', [\App\Http\Controllers\ChatController::class, 'updateAvailability'])
        ->name('role.chat.availability');
});


require __DIR__.'/auth.php';








