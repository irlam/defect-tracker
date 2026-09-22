# Push Notification System - Testing Guide

## Overview
This document outlines the testing procedures for the enhanced push notification system.

## Prerequisites

### 1. Database Setup
Run the database migration first:
```bash
cd /database/migrations
php run_migration.php
```

Or manually execute `add_notification_enhancements.sql` in your MySQL client.

### 2. Firebase Configuration
Set your Firebase Server Key in one of these ways:
- **Environment Variable (Recommended)**: `export FIREBASE_SERVER_KEY="your_key_here"`
- **Config File**: Edit `push_notifications/notification_sender.php` line 20

### 3. User Setup
Ensure you have:
- At least 2 regular users
- At least 1 contractor user
- At least 1 contractor company in the database

## Test Cases

### Test 1: Send to All Users & Contractors
**Objective**: Verify broadcast notifications work

**Steps**:
1. Navigate to `/push_notifications/`
2. Fill in:
   - Title: "System Maintenance Alert"
   - Message: "Scheduled maintenance tonight at 10 PM"
   - Send to: "All Users & Contractors"
3. Click "Send Notification"

**Expected Results**:
- Success message appears
- Shows "Sent successfully to X recipients"
- No errors displayed
- Check notification_log table: new entry with target_type = 'all'

### Test 2: Send to Specific User
**Objective**: Verify targeted user notifications

**Steps**:
1. Navigate to `/push_notifications/`
2. Fill in:
   - Title: "Task Assignment"
   - Message: "You have been assigned a new task"
   - Send to: "Specific User"
   - Select User: [Choose a user]
3. Click "Send Notification"

**Expected Results**:
- Success message: "Sent successfully to 1 recipient(s)"
- notification_log entry with target_type = 'user' and user_id set
- notification_recipients table has entry for that user

### Test 3: Send to Specific Contractor
**Objective**: Verify contractor targeting

**Steps**:
1. Navigate to `/push_notifications/`
2. Fill in:
   - Title: "Contractor Update"
   - Message: "Important update for your team"
   - Send to: "Specific Contractor"
   - Select Contractor: [Choose a contractor]
3. Click "Send Notification"

**Expected Results**:
- Success message showing number of contractor users
- notification_log entry with target_type = 'contractor' and contractor_id set
- All users of that contractor receive notification

### Test 4: Notification with Defect Link
**Objective**: Verify defect linking

**Steps**:
1. Navigate to `/push_notifications/`
2. Fill in:
   - Title: "Defect Requires Attention"
   - Message: "Critical defect needs immediate action"
   - Send to: "Specific User"
   - Link to Defect: [Choose a defect]
3. Click "Send Notification"

**Expected Results**:
- Success message
- notification_log entry has defect_id populated
- FCM payload includes defectId in data

### Test 5: View Notification History
**Objective**: Verify history and filtering

**Steps**:
1. Navigate to `/push_notifications/notification_history.php`
2. Verify recent notifications are listed
3. Test filters:
   - Filter by Status: "sent"
   - Filter by Target: "user"
   - Set Date Range
4. Click "Apply Filters"

**Expected Results**:
- Notifications display with correct information
- Status badges show correct colors
- Filters reduce results appropriately
- Success/failure counts display correctly

### Test 6: API - Send Notification
**Objective**: Test programmatic notification sending

**Steps**:
1. Use curl or Postman to send POST request:
```bash
curl -X POST http://your-domain.com/api/send_push_notification.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "title": "API Test Notification",
    "message": "Testing API endpoint",
    "target_type": "user",
    "user_id": 1
  }'
```

**Expected Results**:
- JSON response with success: true
- Response includes recipients count
- Notification appears in history

### Test 7: API - Confirm Delivery
**Objective**: Test delivery confirmation

**Steps**:
1. Get a notification log_id from history
2. Send POST request:
```bash
curl -X POST http://your-domain.com/api/confirm_notification_delivery.php \
  -H "Content-Type: application/json" \
  -d '{
    "log_id": 123,
    "user_id": 1
  }'
```

**Expected Results**:
- JSON response with success: true
- notification_recipients entry updated with delivery_status = 'delivered'
- delivered_at timestamp set

### Test 8: Platform Detection
**Objective**: Verify platform tracking

**Steps**:
1. Use different devices/apps to register FCM tokens
2. Send POST to `/api/update_fcm_token.php`:
```bash
curl -X POST http://your-domain.com/api/update_fcm_token.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "user_id=1&fcm_token=test_token_123&platform=android"
```

**Expected Results**:
- Response includes platform confirmation
- users.device_platform updated
- Platform appears in notification_recipients when notification sent

### Test 9: Error Handling - No Recipients
**Objective**: Test graceful handling of no recipients

**Steps**:
1. Ensure target user has no FCM token (set to NULL in database)
2. Try to send notification to that user

**Expected Results**:
- Error message: "No registered devices found for selected recipients"
- No crash or SQL errors
- Error logged appropriately

### Test 10: Error Handling - Invalid FCM Key
**Objective**: Test handling of FCM errors

**Steps**:
1. Temporarily set invalid Firebase Server Key
2. Try to send notification

**Expected Results**:
- Failed notification recorded
- error_message populated in notification_log
- User sees helpful error message
- Error details in logs

### Test 11: Service Worker Push Handling
**Objective**: Test PWA push notification reception

**Steps**:
1. Open browser console on PWA
2. Trigger a push notification
3. Observe service worker logs

**Expected Results**:
- Service worker 'push' event fires
- Notification displays on screen
- Delivery confirmation sent to API
- Clicking notification navigates to correct page

### Test 12: Notification with Actions
**Objective**: Test notification action buttons

**Steps**:
1. Send notification with defect link
2. When notification appears, verify action buttons present
3. Click "View Defect" button

**Expected Results**:
- Action buttons: "View Defect" and "Dismiss"
- Clicking "View Defect" opens correct defect page
- Clicking "Dismiss" closes notification

## Database Verification Queries

### Check Notification Log
```sql
SELECT * FROM notification_log 
ORDER BY sent_at DESC 
LIMIT 10;
```

### Check Individual Recipients
```sql
SELECT nr.*, u.username, nr.delivery_status
FROM notification_recipients nr
LEFT JOIN users u ON nr.user_id = u.id
WHERE nr.notification_log_id = [your_log_id];
```

### Check Platform Distribution
```sql
SELECT device_platform, COUNT(*) as count
FROM users 
WHERE fcm_token IS NOT NULL
GROUP BY device_platform;
```

### Check Delivery Success Rate
```sql
SELECT 
  delivery_status,
  COUNT(*) as count,
  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM notification_recipients
GROUP BY delivery_status;
```

## Performance Testing

### Test Large Broadcast
1. Send notification to "All Users & Contractors" with 100+ recipients
2. Monitor:
   - Page load time
   - Database response time
   - FCM API response time
   - Memory usage

**Expected**: Complete within 30 seconds for 100 recipients

### Test Concurrent Sends
1. Have 2-3 admins send notifications simultaneously
2. Verify:
   - No database locks
   - All notifications send successfully
   - Correct logging for each

## Security Testing

### Test Authentication
1. Log out
2. Try to access `/push_notifications/`
3. Try to access `/api/send_push_notification.php`

**Expected**: Redirect to login or 401 Unauthorized

### Test SQL Injection
1. Try sending notification with SQL in title/message:
```
Title: "Test'; DROP TABLE users; --"
```

**Expected**: Text stored as-is, no SQL execution

### Test XSS
1. Send notification with script tag:
```
Message: "<script>alert('XSS')</script>"
```

**Expected**: Script tag escaped in display, no execution

## Troubleshooting

### Notifications Not Sending
1. Check Firebase Server Key is correct
2. Verify users have FCM tokens: `SELECT COUNT(*) FROM users WHERE fcm_token IS NOT NULL;`
3. Check error logs: `/logs/api_error.log`
4. Verify network connectivity to FCM

### Delivery Not Confirmed
1. Check service worker is active: Browser DevTools → Application → Service Workers
2. Verify API endpoint accessible: `/api/confirm_notification_delivery.php`
3. Check browser console for errors
4. Ensure notification data includes log_id and user_id

### History Not Showing Notifications
1. Verify database migration ran successfully
2. Check notification_log table has entries
3. Try clearing filters
4. Check for database errors in page source

## Automated Testing Script

For automated testing, use this PHP script:

```php
<?php
// test_notifications.php
require_once 'config/database.php';
require_once 'push_notifications/notification_sender.php';

$database = new Database();
$db = $database->getConnection();

// Test 1: Send to all
$result = sendNotification(
    "Test: All Users",
    "Automated test notification",
    "all"
);
echo "Test All Users: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n";

// Test 2: Send to specific user
$result = sendNotification(
    "Test: Specific User",
    "Automated test for user 1",
    "user",
    1
);
echo "Test Specific User: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n";

// Test 3: Send with defect link
$result = sendNotification(
    "Test: Defect Link",
    "Test notification with defect",
    "user",
    1,
    null,
    1
);
echo "Test Defect Link: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n";

echo "\nAll tests completed!\n";
?>
```

## Sign-Off Checklist

- [ ] All 12 test cases pass
- [ ] Database migration successful
- [ ] Firebase configuration verified
- [ ] API endpoints tested
- [ ] Security tests pass
- [ ] Performance acceptable
- [ ] Documentation reviewed
- [ ] No console errors in browser
- [ ] Service worker functioning
- [ ] Delivery confirmation working

## Support

For issues during testing:
1. Check `/logs/api_error.log` for errors
2. Review notification_history for failed notifications
3. Verify database tables created correctly
4. Confirm Firebase credentials are valid
5. Test with a single user before broadcasting

---
**Testing Version**: 1.0  
**Last Updated**: 2025-11-04  
**Tested By**: _____________  
**Date**: _____________
