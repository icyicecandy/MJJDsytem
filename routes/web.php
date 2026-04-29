<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CustomerApplicationController;
use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\TechnicianDashboardController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\TechnicianTicketController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\TechnicianController;
use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('public.home');
});

Route::get('/track-application', [PublicTrackingController::class, 'form'])->name('track.form');
Route::post('/track-application', [PublicTrackingController::class, 'search'])->name('track.search');
Route::get('/track-ticket', [PublicTrackingController::class, 'ticketForm'])->name('track.ticket.form');
Route::post('/track-ticket', [PublicTrackingController::class, 'ticketSearch'])->name('track.ticket.search');

Route::get('/dashboard', function () {
    $user = auth()->user();
    $application = $user->application;

    if ($user->isCustomer()) {
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
})->middleware(['auth', 'account.active', 'verified', 'track.staff.presence'])->name('dashboard');

Route::middleware(['auth', 'account.active', 'track.staff.presence'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/export', [ProfileController::class, 'export'])->name('profile.export');
    Route::get('/profile/username-check', [ProfileController::class, 'usernameCheck'])->name('profile.username.check');
    Route::post('/profile/deletion-request', [ProfileController::class, 'requestDeletionReview'])->name('profile.deletion-request');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/{notification}/go', [NotificationController::class, 'redirect'])->name('notifications.redirect');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence', 'role:customer'])->group(function () {
    Route::get('/customer/dashboard', function () {
        $user = auth()->user();
        $application = $user->application()->with('installation')->first();

        $totalApplications = $application ? 1 : 0;
        $latestApplication = $application;
        $tickets = $user->tickets()->latest()->take(5)->get();
        $openTickets = $user->tickets()->where('status', 'open')->count();
        $activeApplication = $application
            ? in_array($application->status, ['submitted', 'under_review', 'approved', 'scheduled', 'cancellation_requested', 'installed', 'activated'], true)
            : false;

        return view('customer.dashboard', compact('totalApplications', 'latestApplication', 'tickets', 'openTickets', 'activeApplication'));
    })->name('customer.dashboard');

    Route::get('/customer/apply', [CustomerApplicationController::class, 'create'])->name('customer.apply');
    Route::get('/customer/apply/contact-check', [CustomerApplicationController::class, 'contactCheck'])->name('customer.apply.contact-check');
    Route::post('/customer/apply', [CustomerApplicationController::class, 'store'])->name('customer.apply.store');
    Route::get('/customer/apply/done', [CustomerApplicationController::class, 'done'])->name('customer.apply.done');
    Route::get('/customer/application', [CustomerApplicationController::class, 'show'])->name('customer.application.show');
    Route::patch('/customer/application/cancellation', [CustomerApplicationController::class, 'requestCancellation'])->name('customer.application.cancel');
    Route::get('/customer/tickets', [TicketController::class, 'index'])->name('customer.tickets.index');
    Route::get('/customer/tickets/create', [TicketController::class, 'create'])->name('customer.tickets.create');
    Route::post('/customer/tickets', [TicketController::class, 'store'])->name('customer.tickets.store');
    Route::get('/customer/tickets/{ticket}', [TicketController::class, 'show'])->name('customer.tickets.show');
    Route::post('/customer/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('customer.tickets.reply');
    Route::patch('/customer/tickets/{ticket}/close', [TicketController::class, 'close'])->name('customer.tickets.close');
    Route::patch('/customer/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('customer.tickets.reopen');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        $totalApplications = Application::count();
        $pendingApplications = Application::whereIn('status', ['submitted', 'under_review'])->count();
        $approvedApplications = Application::where('status', 'approved')->count();
        $scheduledInstallations = Application::where('status', 'scheduled')->count();
        $installedApplications = Application::whereIn('status', [Application::STATUS_INSTALLED, Application::STATUS_ACTIVATED])->count();
        $openTickets = Ticket::where('status', 'open')->count();

        return view('admin.dashboard', compact('totalApplications', 'pendingApplications', 'approvedApplications', 'scheduledInstallations', 'installedApplications', 'openTickets'));
    })->name('admin.dashboard');

    Route::get('/admin/applications', [AdminApplicationController::class, 'index'])->name('admin.applications.index');
    Route::get('/admin/applications/{application}', [AdminApplicationController::class, 'show'])->name('admin.applications.show');
    Route::patch('/admin/applications/{application}/status', [AdminApplicationController::class, 'updateStatus'])->name('admin.applications.status');
    Route::post('/admin/applications/{application}/schedule', [AdminApplicationController::class, 'schedule'])->name('admin.applications.schedule');
    Route::patch('/admin/applications/{application}/cancellation', [AdminApplicationController::class, 'cancellationDecision'])->name('admin.applications.cancellation');
    Route::get('/admin/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
    Route::patch('/admin/tickets/{ticket}', [AdminTicketController::class, 'update'])->name('admin.tickets.update');
    Route::get('/admin/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
    Route::post('/admin/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('admin.tickets.reply');
    Route::patch('/admin/tickets/{ticket}/reopen-request', [AdminTicketController::class, 'approveReopenRequest'])->name('admin.tickets.reopen-request.approve');
    Route::get('/admin/installations', [\App\Http\Controllers\Admin\InstallationController::class, 'index'])->name('admin.installations.index');
    Route::patch('/admin/installations/{installation}/reassign', [\App\Http\Controllers\Admin\InstallationController::class, 'reassign'])->name('admin.installations.reassign');
    Route::patch('/admin/installations/{installation}/reschedule', [\App\Http\Controllers\Admin\InstallationController::class, 'reschedule'])->name('admin.installations.reschedule');
    Route::get('/admin/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('/admin/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
    Route::get('/admin/technicians', [TechnicianController::class, 'index'])->name('admin.technicians.index');
    Route::post('/admin/technicians', [TechnicianController::class, 'store'])->name('admin.technicians.store');
    Route::patch('/admin/technicians/{technician}/disable', [TechnicianController::class, 'disableAccount'])->name('admin.technicians.disable');
    Route::patch('/admin/technicians/{technician}/enable', [TechnicianController::class, 'enableAccount'])->name('admin.technicians.enable');
    Route::get('/admin/technicians/{technician}', [TechnicianController::class, 'show'])->name('admin.technicians.show');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence', 'role:technician'])->group(function () {
    Route::get('/technician/dashboard', function () {
        $technician = auth()->user();
        $technicianId = auth()->id();
        $tickets = Ticket::with('user')
            ->assignedTo($technician)
            ->whereIn('status', [
                Ticket::STATUS_OPEN,
                Ticket::STATUS_QUEUED,
                Ticket::STATUS_IN_PROGRESS,
            ])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'queued' THEN 1 WHEN status = 'open' THEN 2 ELSE 3 END")
            ->oldest('created_at')
            ->get();

        $totalInstallations = Installation::where('technician_id', $technicianId)->count();
        $pendingInstallations = Installation::where('technician_id', $technicianId)->where('status', 'scheduled')->count();
        $completedInstallations = Installation::where('technician_id', $technicianId)->whereIn('status', Installation::completedStatuses())->count();
        $assignedTickets = $tickets->count();

        return view('technician.dashboard', compact('totalInstallations', 'pendingInstallations', 'completedInstallations', 'assignedTickets', 'tickets'));
    })->name('technician.dashboard');

    Route::get('/technician/installations', [TechnicianDashboardController::class, 'installations'])->name('technician.installations.index');
    Route::get('/technician/installations/{installation}', [TechnicianDashboardController::class, 'showInstallation'])->name('technician.installations.show');
    Route::patch('/technician/installations/{installation}/status', [TechnicianDashboardController::class, 'updateInstallationStatus'])->name('technician.installations.status');
    Route::post('/technician/installations/{installation}/complete', [TechnicianDashboardController::class, 'complete'])->name('technician.installations.complete');
    Route::patch('/technician/installations/{installation}/activate', [TechnicianDashboardController::class, 'activate'])->name('technician.installations.activate');
    Route::get('/technician/tickets', [TechnicianTicketController::class, 'index'])->name('technician.tickets.index');
    Route::patch('/technician/tickets/{ticket}/start', [TechnicianTicketController::class, 'start'])->name('technician.tickets.start');
    Route::patch('/technician/tickets/{ticket}/resolve', [TechnicianTicketController::class, 'resolve'])->name('technician.tickets.resolve');
    Route::get('/technician/tickets/{ticket}', [TechnicianTicketController::class, 'show'])->name('technician.tickets.show');
    Route::post('/technician/tickets/{ticket}/reply', [TechnicianTicketController::class, 'reply'])->name('technician.tickets.reply');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence'])->group(function () {
    Route::post('/tickets/{ticket}/message', [\App\Http\Controllers\TicketMessageController::class, 'store'])->name('tickets.messages.store');
    Route::get('/chat', [\App\Http\Controllers\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/availability', [\App\Http\Controllers\ChatController::class, 'updateAvailability'])->name('chat.availability');
    Route::get('/chat/{user}', [\App\Http\Controllers\ChatController::class, 'conversation'])->name('chat.conversation');
    Route::get('/chat/{user}/snapshot', [\App\Http\Controllers\ChatController::class, 'snapshot'])->name('chat.snapshot');
    Route::post('/chat/{user}', [\App\Http\Controllers\ChatController::class, 'send'])->name('chat.send');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence', 'role:admin,technician'])->group(function () {
    Route::get('/{role}/chat', [\App\Http\Controllers\ChatController::class, 'index'])->name('role.chat.index');
    Route::post('/{role}/chat/availability', [\App\Http\Controllers\ChatController::class, 'updateAvailability'])->name('role.chat.availability');
    Route::get('/{role}/chat/{user}', [\App\Http\Controllers\ChatController::class, 'conversation'])->name('role.chat.conversation');
    Route::get('/{role}/chat/{user}/snapshot', [\App\Http\Controllers\ChatController::class, 'snapshot'])->name('role.chat.snapshot');
    Route::post('/{role}/chat/{user}', [\App\Http\Controllers\ChatController::class, 'send'])->name('role.chat.send');
    Route::post('/{role}/chat/{user}/note', [\App\Http\Controllers\ChatController::class, 'addNote'])->name('role.chat.note');
    Route::post('/{role}/chat/{user}/close', [\App\Http\Controllers\ChatController::class, 'closeConversation'])->name('role.chat.close');
    Route::post('/{role}/chat/{user}/reopen', [\App\Http\Controllers\ChatController::class, 'reopenConversation'])->name('role.chat.reopen');
    Route::post('/{role}/chat/{user}/takeover', [\App\Http\Controllers\ChatController::class, 'takeOver'])->name('role.chat.takeover');
    Route::post('/{role}/chat/{user}/assign', [\App\Http\Controllers\ChatController::class, 'assign'])->name('role.chat.assign');
});

require __DIR__.'/auth.php';

