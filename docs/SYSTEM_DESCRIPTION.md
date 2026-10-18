# ChoreHive System Description

## 1. System overview

**ChoreHive: A Web-Based Booking System for Cleaning Services** is a web application that connects customers seeking cleaning services with workers who provide them. The system supports booking creation, service-task definition, job progress tracking, evidence photos, customer reviews, worker profiles, administrative management, and operational reporting.

ChoreHive is designed to make service expectations and job progress visible to customers and workers. Customers can specify the work they want done, workers can record task completion and upload job photos, and administrators can manage the marketplace and review business activity.

## 2. Purpose and problem addressed

Customers may be away from home while cleaning work is performed. They may have difficulty explaining exactly which tasks they expect, assessing a worker's experience before booking, or repeating the details of a previous booking. At the same time, managers may lack a consolidated view of completed work, payment records, service demand, and worker performance.

ChoreHive addresses these needs through customer-defined checklists, before-and-after job photos, worker profiles and administrator-managed badges, booking history and rebooking, and an administrative reports dashboard.

## 3. User roles

### 3.1 Customer

Customers can browse approved worker profiles, review available services and worker information, create bookings, select a schedule and payment method, define a task checklist, communicate with workers, view booking progress and job photos, review completed work, and manage their booking history.

### 3.2 Worker

Workers can maintain a profile, view assigned jobs, follow the booking checklist, update job progress, upload before-and-after photos, communicate with customers, and receive customer reviews. Worker listings must be approved by an administrator before appearing in customer discovery.

### 3.3 Administrator

Administrators can manage bookings, users, worker listing approvals, worker badges, and services. They can review marketplace conversations and access reports covering booking activity, recorded paid booking value, service demand, worker performance, and returning-customer activity.

## 4. Core features

### 4.1 Before-and-after photo verification

For an assigned job, the worker can upload photos of the work area before starting and after completing the work. The photos are associated with the booking and are displayed in the booking details for authorized participants. Access is restricted to the booking's customer, its currently assigned worker, and administrators.

The photos provide a visual record to support customer review and clarify what work was performed. They do not, by themselves, certify the quality of the work or prove that every checklist item was completed.

**Payment distinction:** GCash and Maya bookings must be paid through PayMongo before the worker can start. Cash payment is recorded after the job is complete and the customer reviews the work and confirms the cash payment. ChoreHive does not hold funds in escrow or release an online payment after photo approval.

### 4.2 Customizable task checklist per booking

During booking, a customer can define a list of specific tasks for the worker, such as cleaning a kitchen sink, vacuuming a living room, or watering plants. The form supports up to 20 task entries and allows the customer to remove an entry before submitting the booking.

The worker sees the submitted checklist and marks tasks as complete as work progresses. The checklist gives both parties a shared, itemized reference for the requested work. It becomes read-only when the booking reaches the customer-verification stage.

### 4.3 Worker skill badge system

Administrators can award or revoke named badges on worker profiles. Badge names and descriptions can represent skills or milestones, such as a specialist capability or a number of completed jobs. Worker profiles may also show completed-job counts, customer ratings, and reviews to provide additional context.

Badges are administered by ChoreHive staff; the current implementation does not automatically award a badge when a milestone is reached. Worker-listing approval is a marketplace review, not an identity or background check. A badge must not imply identity verification, background screening, licensing, or a formal skills assessment unless those checks are actually conducted and documented.

### 4.4 Booking history, rebooking, and repeat-customer insights

Customers can review their bookings in a history table and calendar, including schedule, worker, status, payment information, and available actions. The customer can rebook from an earlier booking. The rebooking action carries forward the previous service, worker, address, notes, checklist, map coordinates, and payment preference, and creates a new booking scheduled for a future date.

The administrative reports dashboard identifies returning customers by counting customers with more than one completed booking. It summarizes completed bookings and services associated with those customers.

**Current scope:** The reports provide repeat-customer summaries; they do not calculate a recurring interval such as “every two weeks,” predict a customer's next cleaning date, or automatically show a personalized recurring-booking suggestion. Rebooking is initiated by the customer from booking history.

### 4.5 Reports and performance dashboard

The administrator reports dashboard consolidates several operational measures:

- Completed bookings and recorded paid booking value by month.
- Completed booking counts and recorded paid booking value by service.
- Worker completed-job counts, recorded paid booking value, and average customer ratings.
- Returning-customer counts, their share of completed bookings, and the services they book.
- A calendar of upcoming active jobs by worker.
- CSV exports for booking records, worker performance, and monthly paid booking value.

Amounts shown are **recorded paid booking value**, not ChoreHive platform revenue, worker take-home earnings, or verified payouts. Simulation-only payments are excluded from recorded paid totals. The reports summarize application records and depend on those records being complete and accurate.

## 5. Booking workflow

1. A customer selects a service, worker where applicable, schedule, service address, and payment method.
2. The customer supplies a custom checklist describing the requested work.
3. The booking is created and assigned according to the marketplace workflow. Worker approval is required before a worker can be listed for new bookings or assignments.
4. For GCash or Maya, the customer completes PayMongo checkout before work can start. Cash is collected after the job is completed and reviewed.
5. The worker follows the checklist, updates job status, and uploads before-and-after photos.
6. The customer reviews the job information and photos, completes the applicable verification/payment step, and may submit feedback.
7. Booking and payment records contribute to the administrator's operational reports.

## 6. Screenshot descriptions

The following descriptions correspond to the five screenshots supplied with the system-description request, in their order of receipt.

### Figure 1 — Customer booking checklist and payment selection

The screenshot shows the customer booking form. In the checklist section, the customer can enter requested tasks, remove entries, and add tasks up to the form's limit. The next section introduces cash and online payment choices and explains their different timing. The repeated “Clean Kitchen Sink” entries visible in the screenshot are duplicate example entries, not separate built-in checklist categories.

### Figure 2 — Worker checklist and job-photo uploads

The screenshot shows a worker's job page. A checklist task appears as a worker-facing item, followed by separate upload controls for “Before” and “After” images. This illustrates how the worker records job evidence against the booking. The screenshot shows upload controls; it does not itself show uploaded before-and-after evidence or a customer approval action.

### Figure 3 — Worker profile, performance indicators, and badges

The screenshot shows a worker profile with recorded paid booking-value summaries, a daily performance area, and a skill-badge section. These profile details let customers review information beyond a star rating. Badge awards are administrator-managed in the current system.

The visible badge description “Identity and background verified by admin” should not be treated as evidence of an implemented screening process. ChoreHive does not currently perform or document identity or background checks. That badge text should be replaced with an accurate skill or service-related description before being used publicly.

### Figure 4 — Customer booking history and calendar

The screenshot shows the customer's booking-history table alongside a monthly calendar. The history provides booking schedule, worker, status, payment details, and actions such as viewing, cancelling where eligible, and rebooking. The calendar marks scheduled bookings.

The screenshot captures a presentation defect from when it was taken: the table's columns and action labels become cramped and hard to read. That is a UI problem, not intended booking behavior. The booking table has since been adjusted to keep its contents readable within a dedicated horizontal-scroll area.

### Figure 5 — Administrator reports and performance dashboard

The screenshot shows the administrative reporting page, including CSV export controls, recorded paid booking value, completed jobs, service-level results, returning-customer metrics, worker performance, and an upcoming worker-schedule calendar. Its returning-customer section is based on multiple completed bookings, not detection of a fixed recurring interval.

### Figure 6 — Public homepage and service information

The screenshot shows ChoreHive's public homepage. Its opening section presents the service's central value proposition—cleaning work customers can review and verify—and provides links to get started or sign in. The navigation connects visitors to the showcase, services, workers, packages, and contact information.

Further down the page, service showcase cards and a priced service list introduce the available cleaning options. The worker section previews profiles, reviews, completed-job information, and administrator-managed badges; as noted above, these badges do not establish identity or background screening. A payment comparison explains the different cash and PayMongo GCash/Maya payment flows. The contact form gives visitors a way to send a question or inquiry.

This page is intended for prospective and existing customers: it explains the service and booking process and directs visitors to registration, sign-in, service browsing, and contact. The screenshot is a view of the public landing page, not the authenticated customer booking workflow.

### Figure 7 — Administrator reports dashboard

The screenshot shows the Reports section of the administrator console, with navigation to other administrative areas such as bookings, customer chats, contact messages, users and badges, services, and the audit log. The report page provides CSV export controls and summarizes recorded paid booking value, completed jobs, monthly booking value, completed bookings by service, returning-customer activity, worker performance, and the worker schedule calendar.

In this captured state, the totals and returning-customer metrics are zero, while a worker and an upcoming job are listed in the performance and schedule areas. These are values displayed at the time of capture, not fixed system defaults or guarantees. As described above, the financial figures represent recorded paid booking value rather than platform revenue or worker payouts, and returning-customer insights are based on customers with more than one completed booking rather than prediction of recurring schedules.

## 7. Current limitations and interpretation

- A photo supports review but is not an independent certification of work quality.
- Online payment is processed before work begins; ChoreHive does not provide escrow or post-approval release of online funds.
- Badges are awarded by administrators and are not automatically triggered by job counts.
- Worker approval controls marketplace listing; it is not identity verification or background screening.
- Repeat-customer reports identify customers with multiple completed bookings but do not predict a recurring schedule.
- Financial report values describe recorded paid booking totals and should not be represented as platform revenue or worker payouts.
- Operational reports are only as reliable as the underlying booking, payment, and rating records.
