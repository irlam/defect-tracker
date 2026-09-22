# Role Capability Matrix

## Overview
This document defines the complete role-based access control (RBAC) structure for the McGoff Defect Tracker application, documenting permissions for each user role.

**Last Updated**: 2025-11-03
**Document Version**: 1.0

---

## Role Definitions

### Admin
**Full System Access** - Complete control over all system features, data, and users.

#### Core Capabilities
- ✅ **Dashboard**: Full access to system dashboard and analytics
- ✅ **Defect Management**: Create, view, edit, delete, assign, accept, reject defects
- ✅ **Project Management**: Create, view, edit, delete projects and floor plans
- ✅ **User Management**: Create, view, edit, delete, activate/deactivate users
- ✅ **Contractor Management**: Create, view, edit, delete contractors
- ✅ **Role Management**: Manage roles and permissions
- ✅ **Reports**: Access all reports and export data
- ✅ **Communications**: Send notifications and broadcasts
- ✅ **System Administration**: Access system settings, logs, backups, diagnostics
- ✅ **Site Presentation**: Access system documentation and training materials

#### Permission Details
| Feature | Create | Read | Update | Delete | Special |
|---------|--------|------|--------|--------|---------|
| Defects | ✅ | ✅ | ✅ | ✅ | Assign, Accept, Reject |
| Projects | ✅ | ✅ | ✅ | ✅ | - |
| Floor Plans | ✅ | ✅ | ✅ | ✅ | Upload, Delete |
| Users | ✅ | ✅ | ✅ | ✅ | Activate/Deactivate |
| Contractors | ✅ | ✅ | ✅ | ✅ | - |
| Roles | ✅ | ✅ | ✅ | ✅ | Assign permissions |
| Reports | - | ✅ | - | - | Export |
| Notifications | ✅ | ✅ | - | - | Broadcast |
| System Settings | - | ✅ | ✅ | - | - |
| Backups | ✅ | ✅ | - | - | Restore |

---

### Manager
**Managerial Access** - Can manage defects, projects, users, and contractors but cannot access system administration.

#### Core Capabilities
- ✅ **Dashboard**: Full access to dashboard
- ✅ **Defect Management**: Create, view, assign defects
- ✅ **Project Management**: View and manage projects
- ✅ **User Management**: View users, limited editing
- ✅ **Contractor Management**: View and manage contractors
- ✅ **Reports**: Access most reports
- ✅ **Communications**: Send notifications
- ❌ **System Administration**: No access to system settings or diagnostics
- ❌ **Role Management**: Cannot manage roles
- ❌ **Site Presentation**: No access to system documentation

#### Permission Details
| Feature | Create | Read | Update | Delete | Special |
|---------|--------|------|--------|--------|---------|
| Defects | ✅ | ✅ | ✅ | ❌ | Assign |
| Projects | ❌ | ✅ | ✅ | ❌ | - |
| Floor Plans | ✅ | ✅ | ❌ | ❌ | Upload only |
| Users | ✅ | ✅ | ⚠️ | ❌ | Limited edit |
| Contractors | ✅ | ✅ | ✅ | ❌ | - |
| Roles | ❌ | ❌ | ❌ | ❌ | - |
| Reports | - | ✅ | - | - | Limited export |
| Notifications | ✅ | ✅ | - | - | Broadcast |
| System Settings | - | ❌ | ❌ | - | - |
| Backups | ❌ | ❌ | - | - | - |

---

### Inspector
**Quality Control Access** - Can create defects and view projects but cannot manage users or contractors.

#### Core Capabilities
- ✅ **Dashboard**: Access to dashboard
- ✅ **Defect Management**: Create and view defects
- ✅ **Project Management**: View projects and floor plans
- ✅ **Reports**: Access to reports
- ❌ **User Management**: No access
- ❌ **Contractor Management**: No access
- ❌ **Communications**: No broadcast capability
- ❌ **System Administration**: No access

#### Permission Details
| Feature | Create | Read | Update | Delete | Special |
|---------|--------|------|--------|--------|---------|
| Defects | ✅ | ✅ | ❌ | ❌ | - |
| Projects | ❌ | ✅ | ❌ | ❌ | - |
| Floor Plans | ❌ | ✅ | ❌ | ❌ | - |
| Users | ❌ | ❌ | ❌ | ❌ | - |
| Contractors | ❌ | ❌ | ❌ | ❌ | - |
| Roles | ❌ | ❌ | ❌ | ❌ | - |
| Reports | - | ✅ | - | - | - |
| Notifications | ❌ | ✅ | - | - | - |

---

### Contractor
**Task-Focused Access** - Can only view assigned defects and submit completion evidence.

#### Core Capabilities
- ✅ **Dashboard**: Limited dashboard view
- ✅ **Assigned Defects**: View only defects assigned to them
- ✅ **Evidence Submission**: Upload completion photos/documents
- ✅ **Notifications**: Receive and view notifications
- ❌ **Defect Creation**: Cannot create defects
- ❌ **Project Management**: No access
- ❌ **User Management**: No access
- ❌ **Reports**: No access

#### Permission Details
| Feature | Create | Read | Update | Delete | Special |
|---------|--------|------|--------|--------|---------|
| Defects | ❌ | ⚠️ | ⚠️ | ❌ | Assigned only |
| Evidence Upload | ✅ | ✅ | ❌ | ❌ | Own defects only |
| Projects | ❌ | ❌ | ❌ | ❌ | - |
| Floor Plans | ❌ | ❌ | ❌ | ❌ | - |
| Users | ❌ | ❌ | ❌ | ❌ | - |
| Contractors | ❌ | ❌ | ❌ | ❌ | - |
| Reports | - | ❌ | - | - | - |
| Notifications | ❌ | ✅ | - | - | - |

---

### Viewer
**Read-Only Access** - Can view defects and reports but cannot make any changes.

#### Core Capabilities
- ✅ **Dashboard**: Read-only dashboard access
- ✅ **Defect Viewing**: View all defects (read-only)
- ✅ **Reports**: Access to reports
- ❌ **Editing**: Cannot create, update, or delete anything
- ❌ **User Management**: No access
- ❌ **Project Management**: No access

#### Permission Details
| Feature | Create | Read | Update | Delete | Special |
|---------|--------|------|--------|--------|---------|
| Defects | ❌ | ✅ | ❌ | ❌ | - |
| Projects | ❌ | ❌ | ❌ | ❌ | - |
| Floor Plans | ❌ | ❌ | ❌ | ❌ | - |
| Users | ❌ | ❌ | ❌ | ❌ | - |
| Contractors | ❌ | ❌ | ❌ | ❌ | - |
| Reports | - | ✅ | - | - | - |
| Notifications | ❌ | ✅ | - | - | - |

---

### Client
**Client View Access** - Can view defects, visualizations, and reports.

#### Core Capabilities
- ✅ **Dashboard**: Client dashboard view
- ✅ **Defect Viewing**: View defects
- ✅ **Visualizations**: Access defect visualizations
- ✅ **Reports**: Access to reports
- ❌ **Editing**: Cannot create, update, or delete
- ❌ **User Management**: No access
- ❌ **Project Management**: No access

#### Permission Details
| Feature | Create | Read | Update | Delete | Special |
|---------|--------|------|--------|--------|---------|
| Defects | ❌ | ✅ | ❌ | ❌ | - |
| Visualizations | - | ✅ | - | - | - |
| Projects | ❌ | ❌ | ❌ | ❌ | - |
| Floor Plans | ❌ | ❌ | ❌ | ❌ | - |
| Users | ❌ | ❌ | ❌ | ❌ | - |
| Contractors | ❌ | ❌ | ❌ | ❌ | - |
| Reports | - | ✅ | - | - | - |
| Notifications | ❌ | ✅ | - | - | - |

---

## RBAC Implementation

### Database Schema
The RBAC system uses the following tables:
- `users` - User accounts with `user_type` field
- `roles` - Role definitions
- `permissions` - Permission definitions
- `role_permissions` - Role-to-permission mappings
- `user_roles` - User-to-role assignments

### Code Implementation
**File**: `/classes/RBAC.php`

The RBAC class provides:
- `getRoles()` - Fetch all roles
- `getPermissions()` - Fetch all permissions
- `assignUserRole($userId, $roleId)` - Assign role to user
- `hasPermission($userId, $permissionName)` - Check user permission

### Navigation Implementation
**File**: `/includes/navbar.php`

Navigation is role-based:
- Each role gets a specific set of menu items
- Dropdowns are organized by function
- Mobile-responsive with Bootstrap 5

---

## Permission Verification Checklist

### Admin Verification
- [x] Can access all pages
- [x] Can create/edit/delete all resources
- [x] Can manage users and roles
- [x] Can access system administration
- [x] Has Help and Logout links

### Manager Verification
- [x] Can manage defects and projects
- [x] Can manage users (limited)
- [x] Cannot access system administration
- [x] Has Help and Logout links

### Inspector Verification
- [x] Can create defects
- [x] Can view projects
- [x] Cannot manage users
- [x] Has Help and Logout links

### Contractor Verification
- [x] Can only see assigned defects
- [x] Can upload completion evidence
- [x] Cannot create defects
- [x] Has Help and Logout links

### Viewer Verification
- [x] Read-only access to defects
- [x] Can access reports
- [x] Cannot edit anything
- [x] Has Help and Logout links

### Client Verification
- [x] Can view defects and visualizations
- [x] Can access reports
- [x] Cannot edit anything
- [x] Has Help and Logout links

---

## Security Recommendations

1. **Regular Audits**: Review role assignments quarterly
2. **Principle of Least Privilege**: Users should have minimum necessary permissions
3. **Activity Logging**: All role changes should be logged
4. **Session Management**: Implement timeout for sensitive operations
5. **Two-Factor Authentication**: Consider for admin accounts
6. **Permission Caching**: Cache permission checks for performance
7. **Role Hierarchy**: Document clear hierarchy (Admin > Manager > Inspector > Contractor/Viewer/Client)

---

## Future Enhancements

1. **Custom Roles**: Allow creation of custom roles with specific permissions
2. **Temporary Permissions**: Grant temporary elevated access
3. **Role Templates**: Pre-configured role templates for common scenarios
4. **Audit Trail**: Detailed logging of all RBAC changes
5. **API Permissions**: Granular API endpoint permissions
6. **Time-Based Access**: Schedule-based access restrictions

---

**Legend**:
- ✅ = Full access/permission granted
- ❌ = No access/permission denied
- ⚠️ = Limited or conditional access
