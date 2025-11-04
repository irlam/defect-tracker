# Push Notification System - Architecture Diagram

## System Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                     PUSH NOTIFICATION SYSTEM                         │
└─────────────────────────────────────────────────────────────────────┘

┌────────────────────┐
│   Administrator    │
│   or System        │
└────────┬───────────┘
         │
         │ 1. Triggers notification
         ▼
┌────────────────────────────────────────────────────────────────────┐
│                         WEB INTERFACE                               │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  /push_notifications/index.php                               │  │
│  │  ┌──────────────────────────────────────────────────────┐   │  │
│  │  │  • Select target (all/users/contractors/specific)    │   │  │
│  │  │  • Enter title and message                           │   │  │
│  │  │  • Optional: link to defect                          │   │  │
│  │  └──────────────────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬────────────────────────────────────┘
                                │ 2. Submit form / API call
                                ▼
┌────────────────────────────────────────────────────────────────────┐
│                    NOTIFICATION SENDER                              │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  notification_sender.php                                     │  │
│  │  ┌───────────────────────────────────────────────────────┐  │  │
│  │  │  sendNotification($title, $body, $targetType, ...)    │  │  │
│  │  │    • Validate input                                    │  │  │
│  │  │    • Get recipients from database                      │  │  │
│  │  │    • Create notification_log entry                     │  │  │
│  │  │    • Send to FCM for each recipient                    │  │  │
│  │  │    • Track individual results                          │  │  │
│  │  └───────────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬────────────────────────────────────┘
                                │ 3. Get recipients
                                ▼
┌────────────────────────────────────────────────────────────────────┐
│                          DATABASE                                   │
│  ┌──────────────────┐  ┌──────────────────┐  ┌─────────────────┐  │
│  │     users        │  │   contractors    │  │ notification_log│  │
│  ├──────────────────┤  ├──────────────────┤  ├─────────────────┤  │
│  │ • id             │  │ • id             │  │ • id            │  │
│  │ • fcm_token      │  │ • company_name   │  │ • title         │  │
│  │ • device_platform│  │ • trade          │  │ • message       │  │
│  │ • contractor_id  │  └──────────────────┘  │ • target_type   │  │
│  └──────────────────┘                        │ • user_id       │  │
│                                               │ • contractor_id │  │
│  ┌──────────────────────────────────────┐    │ • defect_id     │  │
│  │   notification_recipients (NEW)      │    │ • delivery_status│ │
│  ├──────────────────────────────────────┤    │ • success_count │  │
│  │ • id                                 │    │ • failed_count  │  │
│  │ • notification_log_id  ──────────────┼────► • sent_at       │  │
│  │ • user_id                            │    └─────────────────┘  │
│  │ • contractor_id                      │                         │
│  │ • fcm_token                          │                         │
│  │ • platform (pwa/ios/android)         │                         │
│  │ • delivery_status                    │                         │
│  │ • sent_at, delivered_at, failed_at   │                         │
│  │ • error_message, fcm_response        │                         │
│  └──────────────────────────────────────┘                         │
└───────────────────────────────┬────────────────────────────────────┘
                                │ 4. Send via FCM
                                ▼
┌────────────────────────────────────────────────────────────────────┐
│              FIREBASE CLOUD MESSAGING (FCM)                         │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  FCM Server                                                   │  │
│  │  • Receives notification payload                             │  │
│  │  • Routes to appropriate platform (iOS/Android/Web)          │  │
│  │  • Handles device token management                           │  │
│  │  • Returns delivery status                                   │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────┬────────────────────┬────────────────────┬────────────────────┘
     │                    │                    │
     │ 5. Push to device  │                    │
     ▼                    ▼                    ▼
┌─────────────┐   ┌─────────────┐   ┌──────────────────┐
│   PWA       │   │  iOS App    │   │   Android App    │
│  (Browser)  │   │             │   │                  │
└─────┬───────┘   └──────┬──────┘   └────────┬─────────┘
      │                  │                   │
      │ 6. Receive notification             │
      ▼                  ▼                   ▼
┌────────────────────────────────────────────────────────────┐
│           SERVICE WORKER (PWA) / NATIVE HANDLER            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  service-worker.js (PWA)                             │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  'push' event                                   │  │  │
│  │  │  • Parse notification data                      │  │  │
│  │  │  • Display notification to user                 │  │  │
│  │  │  • Send delivery confirmation to API            │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  'notificationclick' event                      │  │  │
│  │  │  • Close notification                           │  │  │
│  │  │  • Navigate to relevant page (e.g., defect)    │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  └──────────────────────────────────────────────────────┘  │
└───────────────────────────┬────────────────────────────────┘
                            │ 7. Confirm delivery
                            ▼
┌────────────────────────────────────────────────────────────────────┐
│                    DELIVERY CONFIRMATION API                        │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  /api/confirm_notification_delivery.php                      │  │
│  │  • Receives confirmation from device                         │  │
│  │  • Updates notification_recipients.delivery_status           │  │
│  │  • Sets delivered_at timestamp                               │  │
│  │  • Updates notification_log if all delivered                 │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                      NOTIFICATION HISTORY                           │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  /push_notifications/notification_history.php                │  │
│  │  • View all sent notifications                               │  │
│  │  • Filter by status, date, target type                       │  │
│  │  • See delivery success/failure counts                       │  │
│  │  • Review error messages                                     │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────┘
```

## Component Interactions

### 1. Sending Flow
```
User/System → UI/API → notification_sender.php → Database → FCM → Devices
```

### 2. Delivery Confirmation Flow
```
Device → Service Worker → confirm_delivery API → Database
```

### 3. History/Monitoring Flow
```
Admin → notification_history.php → Database → Display Results
```

## Target Types Mapping

```
┌─────────────────┬───────────────────────────────────────────────────┐
│  Target Type    │  Recipients Selected                              │
├─────────────────┼───────────────────────────────────────────────────┤
│  all            │  All users (with or without contractor_id)        │
├─────────────────┼───────────────────────────────────────────────────┤
│  all_users      │  Users where contractor_id IS NULL                │
├─────────────────┼───────────────────────────────────────────────────┤
│  all_contractors│  Users where contractor_id IS NOT NULL            │
├─────────────────┼───────────────────────────────────────────────────┤
│  user           │  Single user by user_id                           │
├─────────────────┼───────────────────────────────────────────────────┤
│  contractor     │  All users where contractor_id = [selected]       │
└─────────────────┴───────────────────────────────────────────────────┘
```

## Database Relationships

```
┌────────────────────┐         ┌──────────────────────┐
│      users         │◄────────┤  notification_log    │
│  • fcm_token       │ 1     * │  • user_id           │
│  • device_platform │         │  • contractor_id     │
│  • contractor_id   │         │  • delivery_status   │
└────────────────────┘         └──────────┬───────────┘
         │                                 │
         │ *                             1 │
         │                                 │
         │                                 ▼
         │              ┌─────────────────────────────────┐
         └──────────────┤  notification_recipients        │
                      * │  • user_id                      │
                        │  • fcm_token                    │
                        │  • platform                     │
                        │  • delivery_status              │
                        │  • sent_at, delivered_at        │
                        └─────────────────────────────────┘
```

## Delivery Status State Machine

```
                    ┌─────────┐
                    │ pending │
                    └────┬────┘
                         │
                         │ FCM request sent
                         ▼
                    ┌─────────┐
              ┌─────┤  sent   ├─────┐
              │     └─────────┘     │
              │                     │
    Device confirms        FCM error/timeout
              │                     │
              ▼                     ▼
        ┌───────────┐         ┌─────────┐
        │ delivered │         │ failed  │
        └───────────┘         └─────────┘
```

## Platform Support Matrix

```
┌──────────────┬─────────┬─────────┬──────────┬──────────┐
│   Feature    │   PWA   │   iOS   │ Android  │   Web    │
├──────────────┼─────────┼─────────┼──────────┼──────────┤
│ Push Notif.  │   ✓     │    ✓    │    ✓     │    ✓*    │
├──────────────┼─────────┼─────────┼──────────┼──────────┤
│ Actions      │   ✓     │    ✓    │    ✓     │    ✓*    │
├──────────────┼─────────┼─────────┼──────────┼──────────┤
│ Rich Content │   ✓     │    ✓    │    ✓     │    ✓*    │
├──────────────┼─────────┼─────────┼──────────┼──────────┤
│ Auto-Confirm │   ✓     │    ~    │    ~     │    ✓     │
└──────────────┴─────────┴─────────┴──────────┴──────────┘

* Limited browser support
~ Manual confirmation required
```

## Security Layers

```
┌────────────────────────────────────────────────────────────┐
│  Layer 1: Authentication                                   │
│  • Session check for all UI pages                          │
│  • API endpoints require valid session                     │
└────────────────────────────────────────────────────────────┘
         │
         ▼
┌────────────────────────────────────────────────────────────┐
│  Layer 2: Input Validation                                 │
│  • Required field checks                                   │
│  • Data type validation                                    │
│  • Range validation                                        │
└────────────────────────────────────────────────────────────┘
         │
         ▼
┌────────────────────────────────────────────────────────────┐
│  Layer 3: SQL Injection Protection                         │
│  • Prepared statements with bound parameters               │
│  • No direct SQL concatenation                             │
└────────────────────────────────────────────────────────────┘
         │
         ▼
┌────────────────────────────────────────────────────────────┐
│  Layer 4: Output Escaping                                  │
│  • HTML entity encoding                                    │
│  • JSON encoding for API responses                         │
└────────────────────────────────────────────────────────────┘
         │
         ▼
┌────────────────────────────────────────────────────────────┐
│  Layer 5: Error Handling                                   │
│  • Generic error messages to users                         │
│  • Detailed logging to server logs                         │
│  • No stack traces in production                           │
└────────────────────────────────────────────────────────────┘
```

---

**Legend:**
- ─►  Data flow
- ◄── Database relation
- ✓    Fully supported
- ~    Partial support
- *    Browser dependent

**Version**: 2.0  
**Created**: 2025-11-04
