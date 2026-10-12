# MJJD Telecom Operations Guide

## Add or disable staff

1. Sign in as an administrator and open **Technicians**.
2. Select **Add Technician**, complete the staff and schedule details, and save once.
3. Give the temporary password only to the intended technician. The technician must change it at first sign-in and enroll in MFA.
4. To remove access, open the technician record, enter a clear reason, and choose **Disable Account**. Disabling revokes active trusted-device access and prevents new actions.
5. Use **Enable Account** only after confirming that access should be restored.

## Review IDs and applications

1. Open **Applications**, then use status, date, technician, search, reschedule, or **Requires action** filters.
2. Open the application and review both secured ID photos.
3. Complete the readable-ID, name-consistency, and photo-consistency checklist.
4. Select **Verify ID** only after the checks pass. An address difference is a review note and is not an automatic rejection because the service address may differ from the address printed on the ID.
5. If photos are unreadable or inconsistent, record the reason and request resubmission.
6. Approval is server-blocked until the ID is verified, serviceability is marked serviceable, and a slot is available.

## Assign and reschedule installations

1. After approval, select the date, time, and an eligible technician.
2. Review workload and availability warnings before confirming the schedule.
3. For a reschedule request, review the customer or technician reason, choose a new available slot, and record the decision.
4. A scheduled installation always requires a technician. Completion requires technical details and proof photos. Activation is blocked until that evidence exists.
5. Use the unified history on the application to review assignments, schedule changes, requests, actors, and timestamps.

## Manage tickets and live chat

1. Use the ticket queue filters to find unassigned, urgent, dated, assigned, or action-required records.
2. Assign or take over a ticket with a reason when ownership changes.
3. Start work before resolving the ticket.
4. Enter a useful resolution note; the system will not accept an empty resolution.
5. Close a resolved ticket after customer confirmation or the approved operating procedure.
6. In live chat, assign the conversation before replying and close it only when the concern is complete.

## Generate and print reports

1. Open **Reports** and choose the report type and date range.
2. Apply any status, technician, customer, or reference filters.
3. Review the confirmed scope and matching-record count before printing.
4. Use **Print / Save as PDF** for a formatted operational copy or **Export CSV** for authorized internal analysis.
5. Printed totals describe system operational records; they are not sales, billing, or revenue figures.

## Restore access with MFA recovery codes

1. On the MFA prompt, choose the recovery-code option.
2. Enter one unused recovery code exactly as stored.
3. Each code works once. After access is restored, open the security settings and regenerate recovery codes if the remaining set may be exposed.
4. Revoke unrecognized trusted browsers. An administrator must enable a disabled staff account before any recovery method can be used.

## Email delivery checks

Open **System Logs > Email delivery** to see the notification type, recipient, attempt time, result, and retry availability. A `sent` result means the mail server accepted the message; the recipient may still need to check Spam. A `failed` result includes an administrative diagnostic and is available for operational follow-up.

## Daily closeout

- Review **Requires action** filters for applications, installations, customers, and tickets.
- Check failed email delivery records.
- Review recent System Logs for unexpected login, account, MFA, and workflow activity.
- Confirm completed installations have technical details and proof photos before activation.

