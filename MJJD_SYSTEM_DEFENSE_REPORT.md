# MJJD Telecom Customer Relationship Management System

## System Functions, Current Status, and Defense Review Report

**Report date:** November 9, 2026  
**Purpose:** This report provides a factual description of the currently implemented MJJD Telecom Customer Relationship Management System. It is intended as a reference for final-defense preparation and for review by ChatGPT or another academic reviewer.  
**Review basis:** Current Laravel routes, controllers, models, migrations, configuration, views, and automated feature tests in the local project.

---

## 1. Executive Status

The system is a responsive, web-based customer relationship and service-operations platform for MJJD Telecom. It centralizes customer registration, service applications, identity-document review, location capture, application decisions, technician assignment, installation scheduling, support tickets, live chat, notifications, operational reporting, and audit logs.

The current source contains dedicated portals and permissions for three roles:

1. **Customer** – applies for service, monitors applications and installations, creates and tracks tickets, uses live chat, and receives notifications.
2. **Administrator** – manages applications, customers, technicians, installations, tickets, chat assignments, system settings, analytics, reports, and logs.
3. **Technician** – handles assigned installation work, assigned support tickets, customer records needed for work, live chat, and unavailability requests.

### Current verification result

- **Automated test command:** `composer test`
- **Result:** 438 tests passed
- **Assertions:** 3,075
- **Failed tests:** 0
- **Test date:** November 9, 2026

This result confirms that the tested workflows and validation rules behave as expected in the controlled local test environment. It does not replace final manual testing on the live Hostinger deployment, particularly for real SMTP delivery, Google OAuth configuration, browser-specific appearance, scheduled server tasks, and hosting backups.

---

## 2. Technology and Architecture

| Area | Current implementation |
|---|---|
| Backend framework | Laravel 12 |
| Programming language | PHP 8.2 or later |
| Database | MySQL in deployment; Laravel testing database during automated tests |
| Frontend | Blade templates, Tailwind CSS, Alpine.js, JavaScript, Vite |
| Authentication | Laravel session authentication, Google OAuth for customers, mandatory TOTP for staff |
| Email | Laravel mail transport using configured SMTP credentials |
| Maps and location | Customer latitude and longitude captured through the application map |
| Reports | Browser-printable summaries and CSV export |
| Hosting | Hostinger deployment |
| Time zone | Asia/Manila |

The application follows a role-based server-rendered architecture. Important state-changing actions are processed by Laravel controllers, validated on the server, and recorded in system history where applicable. Database transactions and workflow locks protect important multi-record operations from partial saves and duplicate processing.

---

## 3. Functional Scope

The implemented system covers the following operational areas:

- Customer account creation and authentication
- Customer email verification
- Service-plan selection
- Online installation application submission
- Application drafts and form-data preservation
- Private identity-document submission and review
- Customer address and map-location capture
- Manual serviceability and slot-availability assessment
- Application approval or rejection
- Technician recommendation, assignment, and scheduling
- Installation progress, proof, completion, and activation records
- Customer cancellation and rescheduling requests
- Technician unavailability requests and affected-job handling
- Support-ticket submission, assignment, queueing, replies, resolution, closure, cancellation, and reopening
- Staff and customer live chat with limited automated assistance
- In-system notifications and sidebar notification indicators
- Email workflow notifications and delivery logs
- Public application and ticket tracking
- Customer and technician record management
- Analytics, operational reports, CSV export, and printable summaries
- System activity and authentication logs
- Editable service plans and public business information
- Profile settings, password changes, avatars, account export, and staff security controls

### Functions outside the current scope

The application does not perform the following:

- Billing computation
- Online payment processing
- Load purchasing or reload requests
- Billing verification
- Direct Surf2Sawa activation or loading integration
- Real-time internet connectivity monitoring
- Prepaid-load or subscription-usage monitoring
- Bandwidth monitoring or provisioning
- Physical fiber-network management
- Automated road-navigation or traffic-aware routing
- Application-managed server backup and restoration

Database and file backups are handled through the Hostinger hosting environment rather than through an administrator page inside the CRM.

---

## 4. Public Website and Public Tracking

| Function | Description | Current status |
|---|---|---|
| Landing page | Displays the company introduction, active service plans, business contact details, location, and business hours. | Implemented and tested |
| Dynamic plan display | Only administrator-enabled plans appear on public and customer application pages. | Implemented and tested |
| Public application tracking | Allows a user to enter an application reference and matching contact information to view a limited status summary. | Implemented and tested |
| Public ticket tracking | Allows a user to enter a ticket reference and matching email to view a limited ticket status summary. | Implemented and tested |
| Tracking protection | Application and ticket tracking each allow five attempts per minute per IP address, followed by a one-minute cooldown message. | Implemented and tested |
| Privacy protection | A reference number alone is insufficient; matching customer contact information is required. | Implemented and tested |

Public tracking displays status information without exposing staff-only notes, private ID documents, or unrestricted customer records.

---

## 5. Authentication and Account Security

### Customer authentication

- Customers may register manually with name, email, password, and required consent.
- Manual registration creates a customer account that must verify its email before accessing customer services.
- Customers may use Google authentication only when the email corresponds to an eligible customer account.
- Google authentication does not create duplicate accounts for an existing manual-registration email.
- Customers can request password-reset links through email.
- Disabled accounts cannot sign in or continue using protected pages.

### Password policy

New and changed passwords require:

- At least eight characters
- Uppercase and lowercase letters
- At least one number
- At least one symbol
- A password that is not identified as compromised by the configured breach check

### Administrator and technician authentication

- Staff accounts use staff credentials and cannot use customer Google authentication as a substitute for staff login.
- Newly created technicians receive a temporary account key and must create a compliant password during first login.
- Administrators and technicians must enroll an authenticator application using TOTP.
- Staff receive eight one-time recovery codes after enrollment.
- Used recovery codes cannot be reused.
- Staff may trust a browser for 30 days after a successful authenticator challenge.
- Staff can review and revoke individual trusted browsers or revoke all trusted browsers.
- Changing a staff password revokes trusted-browser access.
- Replacing the authenticator revokes the previous authenticator configuration and trusted devices.
- Relevant login, logout, TOTP, recovery-code, password, and trusted-device activities are recorded in System Logs.

---

## 6. Customer Functions

| Customer function | Behavior and validation | Current status |
|---|---|---|
| Dashboard | Shows the latest application, current installation information, ticket summary, notifications, and available quick actions. | Implemented and tested |
| Service application | Collects applicant details, active plan, contact information, address, municipality/barangay, and map pin. | Implemented and tested |
| Application drafts | Saves an incomplete application separately, encrypts its payload, preserves private images, and expires the draft after 30 days. | Implemented and tested |
| Contact validation | Normalizes Philippine phone formats, validates email and phone values, and prevents conflicting customer identities. | Implemented and tested |
| Identity submission | Requires a readable ID image and a customer-held-ID/selfie image in supported formats and minimum quality. | Implemented and tested |
| Application tracking | Displays current application, identity-review, schedule, installation, cancellation, and rescheduling information. | Implemented and tested |
| ID resubmission | Allows replacement only after the administrator requests new identity documents. | Implemented and tested |
| Cancellation request | Requires a reason and, when work is already scheduled or active, requires administrator review. | Implemented and tested |
| Reschedule request | Requires a reason and preferred available date/time and is reviewed by the administrator. | Implemented and tested |
| Support tickets | Creates tickets with subject, description, optional attachment, priority handling, replies, and tracked status. | Implemented and tested |
| Ticket lifecycle | Supports queued, open, in-progress, cancellation-requested, resolved, closed, and cancelled states with role-based actions. | Implemented and tested |
| Live chat | Allows customer communication with available staff and provides limited automated assistance while staff are unavailable. | Implemented and tested |
| Notifications | Provides full notification history, unread indicators, section badges, and mark-as-read controls. | Implemented and tested |
| Profile settings | Updates customer details and avatar, changes password, and exports account information. | Implemented and tested |

---

## 7. Application and Identity-Review Workflow

### Normal application workflow

1. The customer registers and verifies their email.
2. The customer selects an active plan.
3. The customer enters contact details and the installation address.
4. The customer pins the installation location on the map.
5. The customer uploads the required identity images.
6. The system validates and stores the application with a reference number.
7. The administrator reviews the identity documents.
8. The administrator records serviceability and slot availability.
9. The administrator approves or rejects the application.
10. An approved application proceeds to technician assignment and installation scheduling.

### Important identity and application safeguards

- The administrator cannot approve an application until the identity submission is marked verified.
- Identity verification requires the administrator checklist.
- Rejected identity documents require a reason and may be returned for customer resubmission without rejecting the entire service application.
- Exact duplicate ID images across accounts generate a warning and require a documented override reason.
- A rejected service application cannot be scheduled.
- An approved application cannot later be changed directly to rejected through an invalid workflow transition.
- Private ID images are accessible only to authorized users such as the application owner and administrator.
- Important status changes, decisions, notes, actors, and timestamps are stored in application or installation history.

### Application states

- Submitted
- Under review
- Approved
- Scheduled
- Cancellation requested
- Installed
- Activated
- Rejected
- Cancelled

---

## 8. Administrator Functions

| Administrator function | Description | Current status |
|---|---|---|
| Operations dashboard | Shows workload metrics, records requiring attention, technician workload, upcoming work, and recent activity. | Implemented and tested |
| Application management | Searches, filters, reviews, assesses, approves, rejects, schedules, and reviews cancellation/reschedule requests. | Implemented and tested |
| Identity review | Reviews private images, completes a verification checklist, records decisions, and requests replacement documents. | Implemented and tested |
| Customer management | Searches and reviews customers and may enable or disable system access with a required reason. | Implemented and tested |
| Technician management | Creates technician accounts, defines schedules and employment type, reviews workload, and enables/disables access. | Implemented and tested |
| Installation management | Searches and filters jobs, reassigns technicians, reschedules jobs, and handles records requiring action. | Implemented and tested |
| Ticket management | Reviews urgency, assigns or transfers staff, takes over work, replies, resolves, reviews cancellation, and handles reopen requests. | Implemented and tested |
| Chat management | Monitors queues, assigns/reassigns conversations, takes over chats, adds private notes, closes, and reopens conversations. | Implemented and tested |
| Unavailability review | Reviews technician requests, evidence, affected installations, clarification, approval, and rejection. | Implemented and tested |
| Analytics | Presents summarized trends and comparisons for a selected period and the immediately preceding matching period. | Implemented and tested |
| Reports | Builds record-level previews with filters, prints summaries, and exports CSV data. | Implemented and tested |
| System Logs | Displays authentication, application, installation, ticket, chat, availability, account, email, and system-setting activity. | Implemented and tested |
| Email Delivery | Displays notification type, recipient, status, attempt, date, and limited failure details. | Implemented and tested |
| System Settings | Creates and edits service plans, activates/deactivates plans, and edits public business information. | Implemented and tested |

---

## 9. Technician Functions

| Technician function | Description | Current status |
|---|---|---|
| Dashboard | Shows today’s jobs, active tickets, overdue work, workload counts, and quick actions. | Implemented and tested |
| Installation list | Shows assigned jobs and separates scheduled, in-progress, reschedule-required, completed, and activated work. | Implemented and tested |
| Installation details | Shows authorized customer, plan, location, schedule, history, and work controls. | Implemented and tested |
| Start installation | Moves only an assigned scheduled job to in progress. | Implemented and tested |
| Require reschedule | Records the operational reason and moves the job into administrator attention. | Implemented and tested |
| Complete installation | Requires completion information and proof images before completion. | Implemented and tested |
| Activate installation | Requires the completed state and required activation/technical details. | Implemented and tested |
| Installation print | Produces a printable summary only for the assigned technician. | Implemented and tested |
| Customer records | Provides read-only access to customer details needed for assigned operational work. | Implemented and tested |
| Ticket handling | Shows assigned tickets and allows start, reply, and resolution with required notes. | Implemented and tested |
| Live chat | Allows availability control, assigned conversations, replies, and attachments. | Implemented and tested |
| Unavailability | Submits planned leave or emergency unavailability with reason category, private explanation, and optional evidence. | Implemented and tested |

---

## 10. Installation Scheduling and Technician Recommendation

### Scheduling controls

- Only approved applications with verified identities and valid assessments may be scheduled.
- The configured installation start times are 8:00 AM, 11:00 AM, and 2:00 PM.
- Weekend scheduling is rejected by the current rules.
- A technician cannot receive overlapping installation assignments.
- Part-time technicians can receive assignments only on their configured workdays.
- Break periods, leave, pending unavailability, approved unavailability, and daily limits are included in availability checks.
- Reassignment cannot place a technician into an occupied or invalid time slot.
- A scheduled application cannot be scheduled a second time through a stale or duplicate request.

### Location-based recommendation

The technician recommendation is calculated from stored latitude and longitude coordinates. It uses straight-line geographic distance between the proposed customer location and nearby/adjacent jobs in the technician’s schedule. It also considers schedule availability, workload, workday, leave or unavailability, existing installation slots, and location proximity.

The default maximum consecutive straight-line distance is 30 kilometers and may be changed through the `TECHNICIAN_MAX_ROUTE_KM` environment setting.

This recommendation is an operational aid. It is not a road-route, traffic, travel-time, or GPS-navigation calculation. The administrator remains responsible for confirming the final assignment.

---

## 11. Technician Unavailability Workflow

1. The technician selects a start and end date/time.
2. The technician selects a reason category and enters an operational reason.
3. The technician may upload private supporting evidence.
4. Submission creates a pending request and immediately blocks new assignments during the affected period.
5. Administrators receive in-system and email notifications.
6. The administrator may request clarification, reject the request, or prepare it for approval.
7. If existing installation assignments overlap, the administrator must resolve the affected work before final approval.
8. The administrator may reassign a job to preserve its current appointment or move it to the reschedule queue when no replacement can keep the appointment.
9. The technician receives the administrator’s decision through in-system and email notifications.
10. Customers receive schedule-related updates without seeing the technician’s private reason or evidence.

### Unavailability states

- Pending review
- Clarification requested
- Approved
- Rejected

Pending, clarification, and approved periods protect the schedule. Rejection releases the temporary scheduling hold.

---

## 12. Ticket Management Workflow

### Ticket creation and assignment

- Customers create tickets using a structured subject, description, and optional image attachment.
- Tickets enter a priority queue when no eligible technician is available.
- Recognized subjects may be automatically assigned to an eligible on-duty technician.
- The assignment service excludes technicians who are off duty, unavailable, disabled, at capacity, or conflicting with installation work.
- The default maximum active-ticket capacity is five per technician.
- Critical, high, medium, and low priorities use response targets of 1, 4, 8, and 24 hours respectively.
- Other or unclear concerns may wait for administrator urgency review.

### Ticket lifecycle

1. Queued or open
2. Assigned to staff
3. In progress after the assigned staff member starts work
4. Resolved with a required resolution note
5. Closed by the customer or administrator after resolution

Additional controlled paths include customer cancellation, administrator cancellation review, reopening a resolved ticket, and administrator approval of a closed-ticket reopen request.

The customer can see the assigned staff member’s name where appropriate. Staff contact information that should remain private is not disclosed in customer workflow emails.

---

## 13. Live Chat and Automated Assistance

- Customers can start or continue a live-chat conversation.
- Available staff may receive conversations automatically.
- Staff availability can be set to available, away, or unavailable according to the implemented controls.
- Busy or unavailable staff are excluded from new automatic assignments.
- Administrators may assign, reassign, or take over a conversation.
- Private staff notes are separated from customer-visible messages.
- Chat supports image attachments.
- Unread chat activity creates in-system notification indicators.
- When staff are unavailable, the customer receives limited programmed assistance and quick replies.
- An optional locally configured Ollama model may be used as a fallback for questions not matched by the programmed responses.
- If the optional external/local AI service is unavailable, the system returns programmed support guidance rather than failing the chat workflow.

For defense purposes, this should be described as **limited support-chat assistance**, not as a general-purpose AI capable of resolving every customer problem.

---

## 14. Notifications, Email, and Audit Trail

### In-system notifications

The application creates role-appropriate notifications for important events, including:

- New customer accounts
- Application submission and decisions
- ID resubmission requests
- Installation scheduling and schedule changes
- Technician assignments
- Ticket assignment and status changes
- Ticket cancellation or reopen decisions
- Live-chat messages and assignments
- Technician unavailability submission and decisions
- Overdue operational work

Sidebar indicators group unread notifications by the corresponding module so users can see which area requires attention.

### Email notifications

Email is used for eligible workflow events such as:

- Customer email verification
- Password reset
- Technician account creation
- Application decisions and installation schedules
- ID resubmission requests
- Ticket assignment and ticket-status updates
- Technician unavailability submission and administrator decisions

Every tracked email attempt may create an Email Delivery record containing its type, recipient, status, attempt count, retry availability, timestamp, and limited failure information. Successful and failed sends are also represented in System Logs.

The system marks a failed delivery as retry available, but the current implementation should not be described as a complete automatic retry queue unless a separate retry process is added. Actual delivery depends on valid production SMTP credentials and the recipient provider’s spam filtering.

---

## 15. Analytics, Reports, and System Logs

### Analytics

Analytics summarizes operational performance for a selected period. It may include counts, status distributions, workloads, trends, and comparison with the immediately preceding period of equal length. Quick-period controls include this month, the last 30 days, the current quarter, and the current year.

Analytics answers questions such as:

- How many applications were received or approved?
- How many installations were scheduled or completed?
- How many tickets were opened, resolved, or closed?
- Which technicians handled the current workload?
- How did the selected period compare with the previous period?

### Reports

Reports produce reviewable record lists for operational documentation. Report types cover applications, installations, customers, technicians, tickets, and related operational data. The administrator selects filters, previews the result, then prints or exports matching CSV data.

### System Logs

System Logs provide an audit trail showing actor, role, action, description, module, and date/time. Logs cover important authentication and operational actions. The interface supports search, user, module, action, log type, and date-range filters. Email Delivery is available as a separate log view.

Analytics explains summarized patterns, reports provide filtered business records, and system logs provide accountability for individual actions.

---

## 16. System Settings

### Service-plan management

Administrators can:

- Add a plan with validated information
- Edit the plan name, code, speed, installation fee, description, features, display order, and featured state
- Deactivate a plan without deleting historical records
- Reactivate a plan
- Prevent deactivation of the final active plan

Inactive plans disappear from new application choices. Previously submitted applications retain a snapshot of the plan information used at submission, so later plan edits do not rewrite historical customer records.

### Business-information management

Administrators can update public business details such as:

- Business contact information
- Business address or location
- Business hours
- Facebook or public page link
- Map link

The landing page reads the current settings, allowing one administrator change to appear wherever the shared business information is displayed.

---

## 17. Reliability and Data-Integrity Controls

The implementation contains safeguards for important workflows:

- Server-side validation is authoritative even when browser validation is bypassed.
- Forms prevent or reduce duplicate submissions and display processing states.
- Important actions use professional confirmation modals.
- Validation failures preserve appropriate user input.
- Multi-record workflows use database transactions.
- Failed scheduling, cancellation, activation, ticket, notification, or audit operations roll back related changes.
- Workflow locks reload current record state before applying sensitive changes.
- Duplicate scheduling and duplicate activation are rejected.
- Uploaded files created by a failed transaction are removed without deleting existing files.
- Oversized text and invalid attachments are rejected.
- Friendly error pages exist for 403, 404, 419, 422, 500, and 503 conditions.
- Disabled staff cannot perform new protected actions.

---

## 18. Responsive Interface and Accessibility Status

The interface uses responsive layouts for desktop, laptop, tablet, and mobile widths. Implemented protections include:

- Mobile card alternatives or horizontal handling for large tables
- Grouped date-range controls that remain together until narrow widths require stacking
- Reachable buttons and scrollable content
- Consistent page headings, form fields, status badges, loading buttons, empty states, and pagination
- Text labels in addition to status colors
- Responsive report previews and operational lists
- Shared filtering behavior across major record pages

Automated layout and page-smoke tests currently pass. Final defense preparation should still include manual checks in at least Chrome and Edge at desktop and mobile-width viewports because automated feature tests do not measure every visual detail of every physical monitor.

---

## 19. Scheduled Server Tasks and Deployment Dependencies

The deployment requires the Laravel scheduler to run for background operational checks. Current scheduled tasks include:

- Removing expired application drafts daily
- Checking overdue installations and ticket work every five minutes
- Dispatching queued tickets every five minutes

Important production requirements include:

- Correct `APP_URL` and production environment settings
- Valid MySQL database credentials
- All database migrations applied
- Correct mail host, port, username, password, sender address, and sender name
- Correct Google OAuth client ID, secret, and deployed callback URL
- Writable application storage directories
- Laravel scheduler/cron configured on Hostinger
- Production cache cleared and rebuilt after configuration changes when necessary
- HTTPS enabled for secure cookies and OAuth callbacks

Hostinger backup schedules and restore procedures should be verified separately through the hosting control panel.

---

## 20. Current Limitations and Honest Defense Statements

1. **Technician distance is approximate.** It uses straight-line coordinates and does not account for roads, traffic, or actual travel time.
2. **Email delivery is external.** The system can record successful or failed sending attempts, but inbox placement and spam filtering are controlled by email providers.
3. **Automated chat assistance is limited.** It supports programmed operational questions and an optional configured model; it does not replace staff judgment.
4. **Status tracking is current-state tracking.** It should not be presented as continuous network monitoring or real-time Surf2Sawa integration.
5. **Account status is access control.** Enabling or disabling a customer account does not represent the customer’s internet connection, load, or subscription status.
6. **Backups are hosting-level operations.** The CRM has no internal backup-and-restore module.
7. **Software-quality survey results are evaluator perceptions.** They support the study’s evaluation but do not constitute formal ISO certification.

---

## 21. Recommended Defense Demonstration

Use prepared test accounts and data so the demonstration follows one clear story.

1. Show the public landing page and administrator-configured plans.
2. Register or sign in as a verified customer.
3. Submit an application with plan, address, map pin, and identity images.
4. Sign in as administrator and show the ID approval guard.
5. Verify the identity documents and assess serviceability.
6. Approve the application and demonstrate technician recommendation.
7. Assign a technician and schedule the installation.
8. Sign in as the assigned technician and start the job.
9. Show completion proof and activation requirements using prepared data.
10. Return to the customer view and show status history and notifications.
11. Demonstrate ticket creation, staff assignment, response, resolution, and customer closure.
12. Show live chat and explain the limited assistance shown when no staff are available.
13. Show Analytics, a filtered report preview, print/CSV actions, and System Logs.
14. Show System Settings and explain historical plan snapshots.

Avoid changing live configuration, passwords, plans, or production customer data during the defense unless the change is part of a prepared and reversible demonstration.

---

## 22. Likely Panel Questions and Suggested Answers

### Why is this considered a CRM?

It centralizes customer profiles, applications, communications, tickets, notifications, and service histories. It also coordinates internal staff actions that directly affect the customer relationship, such as application review and installation scheduling.

### Why does the system contain installation management?

Installation is the main service-fulfillment process after a customer application. Connecting application, assignment, schedule, and installation history prevents fragmented customer records and supports consistent updates.

### How do you prevent approval of an invalid application?

Approval is protected by server-side rules. The ID must be verified, the application must be serviceable, and slot availability must be recorded before approval and scheduling can continue.

### Is technician recommendation accurate?

It is accurate for the stored coordinates and configured schedule rules, using straight-line geographic distance and workload information. It is a recommendation rather than a road-navigation result, so the administrator confirms the final assignment.

### What happens when a technician becomes unavailable after assignment?

The technician submits an unavailability request. Existing overlapping jobs appear as affected work. The administrator must resolve those jobs through reassignment or rescheduling before approving the unavailable period.

### How are customer documents protected?

Identity images use authorized application routes instead of unrestricted public links. Customers can view their own documents, administrators can review them, and unrelated customers or technicians cannot retrieve them.

### How are impossible workflow combinations prevented?

The system uses role middleware, server validation, status-transition rules, scheduling-conflict checks, database transactions, and workflow locks. Automated tests cover invalid transitions and rollback behavior.

### What is the difference between analytics and reports?

Analytics provides summarized trends and comparisons for decision-making. Reports provide filtered record-level data that can be previewed, printed, or exported.

### Does the system provide backup and recovery?

Backup and recovery are managed through Hostinger’s hosting tools. The CRM does not expose an internal backup module, reducing the risk of application users performing destructive database operations.

### Is the system completely error-free?

No professional software should be claimed to be permanently error-free. The defensible statement is that the current automated suite passes 438 tests and 3,075 assertions, key workflows were manually exercised, and production services continue to require monitoring and maintenance.

---

## 23. Prompt for Further ChatGPT Defense Review

Copy the prompt below into ChatGPT and attach this report together with the latest manuscript if available:

> Act as a strict capstone final-defense panelist for an Information Technology project. Review the attached MJJD Telecom system report and manuscript, focusing on Chapters 3 and 4 and the implemented system. Do not assume that a feature exists unless it is documented. Identify inconsistencies between methodology, survey findings, conclusions, scope, and implemented functions. Then ask me one defense question at a time. After each answer, grade it for technical accuracy, clarity, evidence, and honesty, provide a better answer, and ask a follow-up question. Include questions about respondents and evaluators, sampling, statistical treatment, ISO/IEC 25010, application validation, ID privacy, technician scheduling, ticket lifecycle, authentication, email delivery, backups, limitations, and test evidence.

---

## 24. Final Status Statement

As of November 9, 2026, the reviewed local project contains the complete operational modules described in this report, and the official automated test command passes 438 tests with 3,075 assertions. The system is suitable for a controlled final-defense demonstration using prepared accounts and data. Production email, Google authentication, scheduled tasks, and Hostinger backup behavior remain dependent on correct hosting configuration and should be checked before the defense.
