# Push Notification System - User Guide

## Overview
The enhanced push notification system allows you to send instant notifications to users and contractors via PWA (Progressive Web App), iOS, and Android applications. The system now includes delivery confirmation, detailed tracking, and support for targeting specific users or contractors.

## Features

### 1. Multi-Target Support
- **All Users & Contractors**: Broadcast to everyone
- **All Users Only**: Send only to regular users (excluding contractors)
- **All Contractors Only**: Send only to contractor users
- **Specific User**: Target an individual user
- **Specific Contractor**: Target all users of a specific contractor company

### 2. Delivery Confirmation
- Real-time delivery status tracking
- Individual recipient delivery confirmation
- Failed delivery error logging
- Delivery timestamp recording

### 3. Platform Support
- **PWA (Progressive Web App)**: Browser-based notifications
- **iOS**: Native iOS app notifications
- **Android**: Native Android app notifications
- Platform-specific payload optimization

### 4. Notification History
- View all sent notifications
- Filter by delivery status, target type, and date range
- See success/failure counts
- Review error messages for failed deliveries

## How to Use

### Sending Notifications via Web UI

1. Navigate to **Push Notifications** (Menu → Push Notifications or `/push_notifications/`)

2. Fill in the form:
   - **Title**: Short notification title (e.g., "New Defect Assigned")
   - **Message**: Detailed notification message
   - **Send to**: Select your target audience
     - Choose from: All, All Users, All Contractors, Specific User, or Specific Contractor
   - **Select User/Contractor**: (appears when targeting specific recipients)
   - **Link to Defect**: Optional - link notification to a specific defect

3. Click **Send Notification**

4. Review the confirmation message showing:
   - Number of successful deliveries
   - Number of failed deliveries (if any)
   - Error details (if applicable)

### Viewing Notification History

1. Navigate to **Notification History** (`/push_notifications/notification_history.php`)

2. Use filters to find specific notifications:
   - **Delivery Status**: pending, sent, delivered, failed
   - **Target Type**: filter by recipient type
   - **Date Range**: select from/to dates

3. Each notification card shows:
   - Title and message
   - Delivery status badge
   - Success/failure counts
   - Recipient information
   - Linked defect (if any)
   - Error messages (if any)

### Sending Notifications via API

For automated/programmatic notifications, use the API endpoint:

**Endpoint**: `POST /api/send_push_notification.php`

**Authentication**: Requires active session

**Parameters** (JSON or form-data):
```json
{
  "title": "Notification Title",
  "message": "Notification message body",
  "target_type": "user",
  "user_id": 123,
  "contractor_id": null,
  "defect_id": 456
}
```

**Response**:
```json
{
  "success": true,
  "message": "Notification sent successfully",
  "data": {
    "recipients": 5,
    "failed": 0,
    "total": 5,
    "log_id": 789
  }
}
```

## Device Setup

### For End Users

#### PWA (Web Browser)
1. Visit the Defect Tracker website
2. When prompted, allow notifications
3. Your browser will automatically receive push notifications
4. FCM token is registered automatically

#### iOS App
1. Install the Defect Tracker iOS app
2. Grant notification permissions when prompted
3. Log in to your account
4. The app will register your device automatically

#### Android App
1. Install the Defect Tracker Android app
2. Grant notification permissions when prompted
3. Log in to your account
4. The app will register your device automatically

### For Administrators

#### Setting Up Firebase Cloud Messaging (FCM)

1. **Get Firebase Server Key**:
   - Go to Firebase Console (https://console.firebase.google.com)
   - Select your project
   - Go to Project Settings → Cloud Messaging
   - Copy the **Server Key**

2. **Configure the System**:
   - Set environment variable: `FIREBASE_SERVER_KEY=your_key_here`
   - Or edit `push_notifications/notification_sender.php` line 20

3. **Test the Setup**:
   - Send a test notification to yourself
   - Check delivery status in notification history
   - Review logs at `/logs/api_error.log` if issues occur

## Understanding Delivery Status

- **Pending**: Notification created but not yet sent
- **Sent**: Notification sent to FCM, awaiting device confirmation
- **Delivered**: Device confirmed receipt of notification
- **Failed**: Delivery failed (check error message for details)

## Troubleshooting

### No Recipients Found
- **Cause**: Selected users/contractors don't have registered FCM tokens
- **Solution**: Users must log in via app/PWA and allow notifications

### Delivery Failed
Common causes:
1. Invalid FCM Server Key
2. Expired FCM token
3. User uninstalled app
4. Network connectivity issues

**Check**:
- Error message in notification history
- FCM response in notification_recipients table
- Logs at `/logs/api_error.log`

### Notification Not Appearing on Device
1. Verify notification was sent (check history)
2. Check device notification settings
3. Ensure app is installed and logged in
4. Test with a different device/platform

## Best Practices

### When to Send Notifications
- **Defect Assignment**: Notify assignee immediately
- **Status Changes**: Alert relevant stakeholders
- **Urgent Issues**: Use for critical/high-priority defects
- **Bulk Updates**: Schedule during business hours

### What to Avoid
- Don't spam users with excessive notifications
- Don't send sensitive data in notification body
- Don't use for non-urgent communications
- Avoid sending during off-hours unless critical

### Message Guidelines
- **Title**: Keep under 50 characters
- **Message**: Clear, concise, actionable (under 200 characters)
- **Link Defects**: Always link to related defect when applicable
- **Be Professional**: Use appropriate tone and language

## API Integration Examples

### Example 1: Send Notification on Defect Creation
```php
// After creating a defect
$notificationData = [
    'title' => 'New Defect Created',
    'message' => "Defect #{$defectId}: {$defectTitle}",
    'target_type' => 'user',
    'user_id' => $assignedUserId,
    'defect_id' => $defectId
];

$ch = curl_init('https://your-domain.com/api/send_push_notification.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
```

### Example 2: Notify All Contractors
```javascript
// JavaScript fetch example
fetch('/api/send_push_notification.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    title: 'Site Meeting Tomorrow',
    message: 'All contractors - site meeting at 9 AM tomorrow',
    target_type: 'all_contractors'
  })
})
.then(response => response.json())
.then(data => console.log('Notification sent:', data));
```

## Security Notes
- All API endpoints require authentication
- FCM Server Key is stored securely (environment variable preferred)
- User permissions are checked before sending
- All actions are logged in activity_log
- Notification content is sanitized before display

## Support
For issues or questions:
1. Check notification history for error details
2. Review system logs
3. Test with a single user first
4. Contact system administrator if problems persist

---

**Version**: 2.0  
**Last Updated**: 2025-11-04  
**Maintained by**: DefectTracker Development Team
