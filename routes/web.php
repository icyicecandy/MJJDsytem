<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstallationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TechnicianController;
use App\Http\Controllers\Admin\TechnicianUnavailabilityController as AdminTechnicianUnavailabilityController;
use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\ApplicationDraftController;
use App\Http\Controllers\ApplicationIdPhotoController;
use App\Http\Controllers\Auth\FirstLoginPasswordController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CustomerApplicationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\Technician\UnavailabilityController as TechnicianUnavailabilityController;
use App\Http\Controllers\TechnicianDashboardController;
use App\Http\Controllers\TechnicianTicketController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Models\BusinessSetting;
use App\Models\Installation;
use App\Models\ServicePlan;
use App\Models\Ticket;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('public.home', [
        'servicePlans' => ServicePlan::query()->active()->ordered()->get(),
        'businessSetting' => BusinessSetting::current(),
    ]);
});

Route::get('/track-application', [PublicTrackingController::class, 'form'])->name('track.form');
Route::post('/track-application', [PublicTrackingController::class, 'search'])
    ->middleware('throttle:public-application-tracking')
    ->name('track.search');
Route::get('/track-ticket', [PublicTrackingController::class, 'ticketForm'])->name('track.ticket.form');
Route::post('/track-ticket', [PublicTrackingController::class, 'ticketSearch'])
    ->middleware('throttle:public-ticket-tracking')
    ->name('track.ticket.search');

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/technician/first-login/password', [FirstLoginPasswordController::class, 'edit'])
        ->name('technician.password.change');
    Route::put('/technician/first-login/password', [FirstLoginPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('technician.password.change.update');
});

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
})->middleware(['auth', 'account.active', 'customer.verified', 'track.staff.presence'])->name('dashboard');

Route::middleware(['auth', 'account.active', 'track.staff.presence'])->group(function () {
    Route::get('/applications/{application}/id-photo/{photo}', ApplicationIdPhotoController::class)
        ->whereIn('photo', ['id', 'selfie'])->name('applications.id-photo');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/export', [ProfileController::class, 'export'])->name('profile.export');
    Route::get('/profile/username-check', [ProfileController::class, 'usernameCheck'])->name('profile.username.check');
    Route::post('/profile/deletion-request', [ProfileController::class, 'requestDeletionReview'])->name('profile.deletion-request');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/mark-section-read', [NotificationController::class, 'markSectionRead'])->name('notifications.mark-section-read');
    Route::get('/notifications/{notification}/go', [NotificationController::class, 'redirect'])->name('notifications.redirect');
});

Route::middleware(['auth', 'account.active', 'customer.verified', 'track.staff.presence', 'role:customer'])->group(function () {
    Route::get('/customer/dashboard', function () {
        $user = auth()->user();
        $latestApplication = $user->application()->with('installation')->first();
        $totalApplications = $user->applications()->count();
        $tickets = $user->tickets()->latest()->take(5)->get();
        $openTickets = $user->tickets()->where('status', 'open')->count();
        $activeApplication = $latestApplication
            ? in_array($latestApplication->status, ['submitted', 'under_review', 'approved', 'scheduled', 'cancellation_requested', 'installed'], true)
            : false;

        return view('customer.dashboard', compact('totalApplications', 'latestApplication', 'tickets', 'openTickets', 'activeApplication'));
    })->name('customer.dashboard');

    Route::get('/customer/apply', [CustomerApplicationController::class, 'create'])->name('customer.apply');
    Route::post('/customer/apply/draft', [ApplicationDraftController::class, 'store'])->middleware('throttle:20,1')->name('customer.apply.draft.save');
    Route::delete('/customer/apply/draft', [ApplicationDraftController::class, 'destroy'])->name('customer.apply.draft.destroy');
    Route::get('/customer/apply/draft/photo/{photo?}', [ApplicationDraftController::class, 'photo'])
        ->whereIn('photo', ['id', 'selfie'])->name('customer.apply.draft.photo');
    Route::get('/customer/apply/contact-check', [CustomerApplicationController::class, 'contactCheck'])->name('customer.apply.contact-check');
    Route::post('/customer/apply', [CustomerApplicationController::class, 'store'])->name('customer.apply.store');
    Route::get('/customer/apply/done', [CustomerApplicationController::class, 'done'])->name('customer.apply.done');
    Route::get('/customer/application', [CustomerApplicationController::class, 'show'])->name('customer.application.show');
    Route::patch('/customer/application/identity', [CustomerApplicationController::class, 'resubmitIdentity'])->name('customer.application.identity.resubmit');
    Route::patch('/customer/application/cancellation', [CustomerApplicationController::class, 'requestCancellation'])->name('customer.application.cancel');
    Route::patch('/customer/application/reschedule', [CustomerApplicationController::class, 'requestReschedule'])->name('customer.application.reschedule');
    Route::get('/customer/tickets', [TicketController::class, 'index'])->name('customer.tickets.index');
    Route::get('/customer/tickets/create', [TicketController::class, 'create'])->name('customer.tickets.create');
    Route::post('/customer/tickets', [TicketController::class, 'store'])->name('customer.tickets.store');
    Route::get('/customer/tickets/{ticket}', [TicketController::class, 'show'])->name('customer.tickets.show');
    Route::post('/customer/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('customer.tickets.reply');
    Route::patch('/customer/tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('customer.tickets.cancel');
    Route::patch('/customer/tickets/{ticket}/request-cancellation', [TicketController::class, 'requestCancellation'])->name('customer.tickets.cancellation.request');
    Route::patch('/customer/tickets/{ticket}/close', [TicketController::class, 'close'])->name('customer.tickets.close');
    Route::patch('/customer/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('customer.tickets.reopen');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', DashboardController::class)->name('admin.dashboard');
    Route::get('/admin/reports', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/export/csv', [ReportController::class, 'csv'])->name('admin.reports.csv');
    Route::get('/admin/reports/print/applications/{application}', [ReportController::class, 'printApplication'])->name('admin.reports.print.application');
    Route::get('/admin/reports/print/installations/{installation}', [ReportController::class, 'printInstallation'])->name('admin.reports.print.installation');
    Route::get('/admin/reports/print/customers/{customer}', [ReportController::class, 'printCustomer'])->name('admin.reports.print.customer');
    Route::get('/admin/reports/print/technicians/{technician}', [ReportController::class, 'printTechnician'])->name('admin.reports.print.technician');

    Route::get('/admin/applications', [AdminApplicationController::class, 'index'])->name('admin.applications.index');
    Route::get('/admin/applications/{application}', [AdminApplicationController::class, 'show'])->name('admin.applications.show');
    Route::patch('/admin/applications/{application}/status', [AdminApplicationController::class, 'updateStatus'])->name('admin.applications.status');
    Route::patch('/admin/applications/{application}/identity-verification', [AdminApplicationController::class, 'updateIdentityVerification'])->name('admin.applications.identity-verification');
    Route::post('/admin/applications/{application}/schedule', [AdminApplicationController::class, 'schedule'])->name('admin.applications.schedule');
    Route::patch('/admin/applications/{application}/cancellation', [AdminApplicationController::class, 'cancellationDecision'])->name('admin.applications.cancellation');
    Route::patch('/admin/applications/{application}/reschedule', [AdminApplicationController::class, 'rescheduleDecision'])->name('admin.applications.reschedule');
    Route::get('/admin/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets.index');
    Route::patch('/admin/tickets/{ticket}', [AdminTicketController::class, 'update'])->name('admin.tickets.update');
    Route::get('/admin/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('admin.tickets.show');
    Route::post('/admin/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('admin.tickets.reply');
    Route::patch('/admin/tickets/{ticket}/start', [AdminTicketController::class, 'start'])->name('admin.tickets.start');
    Route::patch('/admin/tickets/{ticket}/resolve', [AdminTicketController::class, 'resolve'])->name('admin.tickets.resolve');
    Route::patch('/admin/tickets/{ticket}/reopen-request', [AdminTicketController::class, 'approveReopenRequest'])->name('admin.tickets.reopen-request.approve');
    Route::patch('/admin/tickets/{ticket}/cancellation/approve', [AdminTicketController::class, 'approveCancellation'])->name('admin.tickets.cancellation.approve');
    Route::patch('/admin/tickets/{ticket}/cancellation/reject', [AdminTicketController::class, 'rejectCancellation'])->name('admin.tickets.cancellation.reject');
    Route::get('/admin/system-logs', [SystemLogController::class, 'index'])->name('admin.system-logs.index');
    Route::get('/admin/settings', [SystemSettingController::class, 'index'])->name('admin.settings.index');
    Route::post('/admin/settings/plans', [SystemSettingController::class, 'storePlan'])->name('admin.settings.plans.store');
    Route::patch('/admin/settings/plans/{servicePlan}', [SystemSettingController::class, 'updatePlan'])->name('admin.settings.plans.update');
    Route::patch('/admin/settings/plans/{servicePlan}/availability', [SystemSettingController::class, 'updatePlanAvailability'])->name('admin.settings.plans.availability');
    Route::patch('/admin/settings/business', [SystemSettingController::class, 'updateBusiness'])->name('admin.settings.business.update');
    Route::get('/admin/installations', [InstallationController::class, 'index'])->name('admin.installations.index');
    Route::patch('/admin/installations/{installation}/reassign', [InstallationController::class, 'reassign'])->name('admin.installations.reassign');
    Route::patch('/admin/installations/{installation}/reschedule', [InstallationController::class, 'reschedule'])->name('admin.installations.reschedule');
    Route::get('/admin/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
    Route::patch('/admin/customers/{customer}/disable', [CustomerController::class, 'disableAccount'])->name('admin.customers.disable');
    Route::patch('/admin/customers/{customer}/enable', [CustomerController::class, 'enableAccount'])->name('admin.customers.enable');
    Route::get('/admin/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
    Route::get('/admin/technicians', [TechnicianController::class, 'index'])->name('admin.technicians.index');
    Route::get('/admin/technician-unavailability', [AdminTechnicianUnavailabilityController::class, 'index'])->name('admin.unavailability.index');
    Route::get('/admin/technician-unavailability/{unavailability}', [AdminTechnicianUnavailabilityController::class, 'show'])->name('admin.unavailability.show');
    Route::patch('/admin/technician-unavailability/{unavailability}/decision', [AdminTechnicianUnavailabilityController::class, 'decision'])->name('admin.unavailability.decision');
    Route::get('/admin/technician-unavailability/{unavailability}/evidence', [AdminTechnicianUnavailabilityController::class, 'evidence'])->name('admin.unavailability.evidence');
    Route::patch('/admin/technician-unavailability/{unavailability}/installations/{installation}/require-reschedule', [AdminTechnicianUnavailabilityController::class, 'requireReschedule'])->name('admin.unavailability.require-reschedule');
    Route::post('/admin/technicians', [TechnicianController::class, 'store'])->name('admin.technicians.store');
    Route::patch('/admin/technicians/{technician}/schedule', [TechnicianController::class, 'updateSchedule'])->name('admin.technicians.schedule');
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
                Ticket::STATUS_CANCELLATION_REQUESTED,
            ])
            ->orderByRaw("CASE WHEN status = 'cancellation_requested' THEN 0 WHEN status = 'in_progress' THEN 1 WHEN status = 'queued' THEN 2 WHEN status = 'open' THEN 3 ELSE 4 END")
            ->oldest('created_at')
            ->get();

        $totalInstallations = Installation::where('technician_id', $technicianId)->count();
        $pendingInstallations = Installation::where('technician_id', $technicianId)->where('status', 'scheduled')->count();
        $completedInstallations = Installation::where('technician_id', $technicianId)->whereIn('status', Installation::completedStatuses())->count();
        $assignedTickets = $tickets->count();
        $overdueTickets = $tickets->filter(fn (Ticket $ticket) => $ticket->response_due_at?->lte(now()))->count();
        $overdueInstallations = Installation::query()
            ->where('technician_id', $technicianId)
            ->whereIn('status', Installation::activeSchedulingStatuses())
            ->whereDate('installation_date', '<=', today())
            ->get()
            ->filter(fn (Installation $installation) => $installation->scheduledEndAt()?->lte(now()))
            ->count();
        $todayWorkloadDate = now(config('app.timezone', 'Asia/Manila'));
        $todayInstallations = Installation::with('application')
            ->where('technician_id', $technicianId)
            ->whereDate('installation_date', $todayWorkloadDate->toDateString())
            ->where('status', '!=', Installation::STATUS_CANCELLED)
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'scheduled' THEN 1 WHEN status = 'reschedule_required' THEN 2 WHEN status = 'completed' THEN 3 WHEN status = 'activated' THEN 4 ELSE 5 END")
            ->orderBy('installation_time')
            ->orderBy('id')
            ->get();

        return view('technician.dashboard', compact('totalInstallations', 'pendingInstallations', 'completedInstallations', 'assignedTickets', 'tickets', 'todayInstallations', 'todayWorkloadDate', 'overdueTickets', 'overdueInstallations'));
    })->name('technician.dashboard');

    Route::get('/technician/customers', [CustomerController::class, 'index'])->name('technician.customers.index');
    Route::get('/technician/customers/{customer}/print', [ReportController::class, 'printCustomer'])->name('technician.customers.print');
    Route::get('/technician/customers/{customer}', [CustomerController::class, 'show'])->name('technician.customers.show');

    Route::get('/technician/installations', [TechnicianDashboardController::class, 'installations'])->name('technician.installations.index');
    Route::get('/technician/unavailability', [TechnicianUnavailabilityController::class, 'index'])->name('technician.unavailability.index');
    Route::post('/technician/unavailability', [TechnicianUnavailabilityController::class, 'store'])->name('technician.unavailability.store');
    Route::patch('/technician/unavailability/{unavailability}/resubmit', [TechnicianUnavailabilityController::class, 'resubmit'])->name('technician.unavailability.resubmit');
    Route::get('/technician/unavailability/{unavailability}/evidence', [TechnicianUnavailabilityController::class, 'evidence'])->name('technician.unavailability.evidence');
    Route::get('/technician/installations/{installation}/print', [TechnicianDashboardController::class, 'printInstallation'])->name('technician.installations.print');
    Route::get('/technician/installations/{installation}', [TechnicianDashboardController::class, 'showInstallation'])->name('technician.installations.show');
    Route::patch('/technician/installations/{installation}/status', [TechnicianDashboardController::class, 'updateInstallationStatus'])->name('technician.installations.status');
    Route::patch('/technician/installations/{installation}/stop-work-notes', [TechnicianDashboardController::class, 'addStopWorkNotes'])->name('technician.installations.stop-work-notes');
    Route::post('/technician/installations/{installation}/complete', [TechnicianDashboardController::class, 'complete'])->name('technician.installations.complete');
    Route::patch('/technician/installations/{installation}/activate', [TechnicianDashboardController::class, 'activate'])->name('technician.installations.activate');
    Route::get('/technician/tickets', [TechnicianTicketController::class, 'index'])->name('technician.tickets.index');
    Route::patch('/technician/tickets/{ticket}/start', [TechnicianTicketController::class, 'start'])->name('technician.tickets.start');
    Route::patch('/technician/tickets/{ticket}/resolve', [TechnicianTicketController::class, 'resolve'])->name('technician.tickets.resolve');
    Route::get('/technician/tickets/{ticket}', [TechnicianTicketController::class, 'show'])->name('technician.tickets.show');
    Route::post('/technician/tickets/{ticket}/reply', [TechnicianTicketController::class, 'reply'])->name('technician.tickets.reply');
});

Route::middleware(['auth', 'account.active', 'customer.verified', 'track.staff.presence'])->group(function () {
    Route::post('/tickets/{ticket}/message', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/availability', [ChatController::class, 'updateAvailability'])->name('chat.availability');
    Route::get('/chat/{user}', [ChatController::class, 'conversation'])->name('chat.conversation');
    Route::get('/chat/{user}/snapshot', [ChatController::class, 'snapshot'])->name('chat.snapshot');
    Route::post('/chat/{user}', [ChatController::class, 'send'])->name('chat.send');
});

Route::middleware(['auth', 'account.active', 'track.staff.presence', 'role:admin,technician'])->group(function () {
    Route::get('/{role}/chat', [ChatController::class, 'index'])->name('role.chat.index');
    Route::post('/{role}/chat/availability', [ChatController::class, 'updateAvailability'])->name('role.chat.availability');
    Route::get('/{role}/chat/{user}', [ChatController::class, 'conversation'])->name('role.chat.conversation');
    Route::get('/{role}/chat/{user}/snapshot', [ChatController::class, 'snapshot'])->name('role.chat.snapshot');
    Route::post('/{role}/chat/{user}', [ChatController::class, 'send'])->name('role.chat.send');
    Route::post('/{role}/chat/{user}/note', [ChatController::class, 'addNote'])->name('role.chat.note');
    Route::post('/{role}/chat/{user}/close', [ChatController::class, 'closeConversation'])->name('role.chat.close');
    Route::post('/{role}/chat/{user}/reopen', [ChatController::class, 'reopenConversation'])->name('role.chat.reopen');
    Route::post('/{role}/chat/{user}/takeover', [ChatController::class, 'takeOver'])->name('role.chat.takeover');
    Route::post('/{role}/chat/{user}/assign', [ChatController::class, 'assign'])->name('role.chat.assign');
});

require __DIR__.'/auth.php';
