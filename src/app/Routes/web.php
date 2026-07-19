<?php

use GreenNet\Controllers\HomeController;
use GreenNet\Controllers\LoginController;
use GreenNet\Controllers\InstallController;
use GreenNet\Controllers\DevController;
use GreenNet\Controllers\AdminController;
use GreenNet\Controllers\AdminQosController;
use GreenNet\Controllers\AdminSystemController;
use GreenNet\Controllers\AdminRouterOsController;
use GreenNet\Controllers\AdminCustomerProfileController;
use GreenNet\Controllers\AdminSearchController;
use GreenNet\Controllers\AdminCustomerImportController;
use GreenNet\Controllers\AdminCustomerSyncController;
use GreenNet\Controllers\AdminPackagesController;
use GreenNet\Controllers\AdminCustomerPackageController;
use GreenNet\Controllers\AdminCustomerRenewalController;
use GreenNet\Controllers\AdminSubscriptionsController;
use GreenNet\Controllers\AdminReportsController;
use GreenNet\Controllers\AdminLogsController;
use GreenNet\Controllers\SubscriberPaymentsController;
use GreenNet\Controllers\SupportController;
use GreenNet\Controllers\SubscriberAppController;
use GreenNet\Controllers\AdminCustomerTableController;
use GreenNet\Controllers\AdminBackupController;
use GreenNet\Controllers\AdminExportController;
use GreenNet\Controllers\AdminSettingsController;
use GreenNet\Controllers\AdminSecurityController;
use GreenNet\Controllers\AdminMediaController;
use GreenNet\Controllers\AdminApiDiagnosticsController;
use GreenNet\Controllers\AdminApiBrowserController;
use GreenNet\Controllers\AdminApiRecordController;
use GreenNet\Controllers\AdminApiResetCountersController;
use GreenNet\Controllers\AdminReadinessController;
use GreenNet\Controllers\AdminRouterSetupController;
use GreenNet\Controllers\AdminHealthController;
use GreenNet\Controllers\AdminAutoMatchController;
use GreenNet\Controllers\AdminSubscriberPasswordController;
use GreenNet\Controllers\AdminRenewalRequestsController;
use GreenNet\Controllers\AdminNotificationsController;
use GreenNet\Controllers\AdminCustomerTimelineController;
use GreenNet\Controllers\AdminPreMikroTikController;
use GreenNet\Controllers\AdminMikroTikDryRunController;
use GreenNet\Controllers\AdminUserManagerPackagesController;
use GreenNet\Controllers\AdminPackagePushController;
use GreenNet\Controllers\AdminPackageAssignController;

$router->get('/', [HomeController::class, 'index']);

$router->get('/login', [LoginController::class, 'show']);
$router->post('/login', [LoginController::class, 'login']);
$router->get('/logout', [LoginController::class, 'logout']);

$router->get('/dashboard', [SubscriberAppController::class, 'home']);
$router->get('/my/usage', [SubscriberAppController::class, 'usage']);
$router->get('/my/package', [SubscriberAppController::class, 'package']);
$router->get('/my/renew', [SubscriberAppController::class, 'renewForm']);
$router->post('/my/renew', [SubscriberAppController::class, 'renewSubmit']);
$router->get('/announcements', [SubscriberAppController::class, 'announcements']);
$router->get('/my/payments', [SubscriberPaymentsController::class, 'index']);
$router->get('/support', [SupportController::class, 'index']);

$router->get('/install', [InstallController::class, 'index']);
$router->get('/dev/database', [DevController::class, 'database']);

$router->get('/admin/login', [AdminController::class, 'loginForm']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/logout', [AdminController::class, 'logout']);

$router->get('/admin/notifications', [AdminNotificationsController::class, 'index']);
$router->post('/admin/notifications/mark-read', [AdminNotificationsController::class, 'markRead']);
$router->post('/admin/notifications/archive', [AdminNotificationsController::class, 'archive']);
$router->post('/admin/notifications/mark-all-read', [AdminNotificationsController::class, 'markAllRead']);

$router->get('/admin/audit', [AdminPreMikroTikController::class, 'audit']);
$router->get('/admin/global-search', [AdminPreMikroTikController::class, 'globalSearch']);
$router->get('/admin/dashboard-widgets', [AdminPreMikroTikController::class, 'dashboardWidgets']);
$router->get('/admin/setup-wizard', [AdminPreMikroTikController::class, 'setupWizard']);
$router->get('/admin/write-safety', [AdminPreMikroTikController::class, 'writeSafety']);
$router->post('/admin/write-safety', [AdminPreMikroTikController::class, 'saveWriteSafety']);

$router->get('/admin/mikrotik-dry-run', [AdminMikroTikDryRunController::class, 'index']);
$router->post('/admin/mikrotik-dry-run/preview', [AdminMikroTikDryRunController::class, 'preview']);
$router->post('/admin/mikrotik-dry-run/execute', [AdminMikroTikDryRunController::class, 'execute']);

$router->get('/admin/security', [AdminSecurityController::class, 'index']);
$router->post('/admin/security/password', [AdminSecurityController::class, 'updatePassword']);

$router->get('/admin/media', [AdminMediaController::class, 'index']);
$router->post('/admin/media/upload', [AdminMediaController::class, 'upload']);
$router->post('/admin/media/assign', [AdminMediaController::class, 'assign']);
$router->post('/admin/media/clear', [AdminMediaController::class, 'clearAsset']);
$router->post('/admin/media/delete', [AdminMediaController::class, 'delete']);

$router->get('/admin/settings', [AdminSettingsController::class, 'index']);
$router->post('/admin/settings', [AdminSettingsController::class, 'update']);
$router->get('/admin/branding', [AdminSettingsController::class, 'index']);
$router->post('/admin/branding', [AdminSettingsController::class, 'update']);

$router->get('/admin/search', [AdminSearchController::class, 'index']);

$router->get('/admin/customers', [AdminController::class, 'customers']);
$router->get('/admin/customers/table', [AdminCustomerTableController::class, 'index']);
$router->post('/admin/customers/store', [AdminController::class, 'storeCustomer']);
$router->post('/admin/customers/import-mikrotik', [AdminCustomerImportController::class, 'fromMikroTik']);

$router->get('/admin/customers/sync', [AdminCustomerSyncController::class, 'index']);
$router->post('/admin/customers/sync/import-selected', [AdminCustomerSyncController::class, 'importSelected']);

$router->get('/admin/customers/profile', [AdminCustomerProfileController::class, 'show']);

$router->get('/admin/customers/timeline', [AdminCustomerTimelineController::class, 'index']);
$router->post('/admin/customers/timeline/note', [AdminCustomerTimelineController::class, 'storeNote']);

$router->get('/admin/customers/password', [AdminSubscriberPasswordController::class, 'show']);
$router->post('/admin/customers/password', [AdminSubscriberPasswordController::class, 'update']);

$router->get('/admin/customers/package', [AdminCustomerPackageController::class, 'edit']);
$router->post('/admin/customers/package/update', [AdminCustomerPackageController::class, 'update']);

$router->get('/admin/customers/renew', [AdminCustomerRenewalController::class, 'show']);
$router->post('/admin/customers/renew', [AdminCustomerRenewalController::class, 'renew']);

$router->get('/admin/customers/edit', [AdminController::class, 'editCustomer']);
$router->post('/admin/customers/update', [AdminController::class, 'updateCustomer']);
$router->post('/admin/customers/status', [AdminController::class, 'updateCustomerStatus']);
$router->get('/admin/customers/delete', [AdminController::class, 'confirmDeleteCustomer']);
$router->post('/admin/customers/delete', [AdminController::class, 'deleteCustomer']);
$router->get('/admin/customers/due', [AdminController::class, 'dueCustomers']);

$router->get('/admin/subscriptions', [AdminSubscriptionsController::class, 'index']);

$router->get('/admin/renewal-requests', [AdminRenewalRequestsController::class, 'index']);
$router->post('/admin/renewal-requests/update', [AdminRenewalRequestsController::class, 'update']);

$router->get('/admin/reports', [AdminReportsController::class, 'index']);
$router->get('/admin/logs', [AdminLogsController::class, 'index']);

$router->get('/admin/backup', [AdminBackupController::class, 'index']);
$router->get('/admin/backup/download', [AdminBackupController::class, 'download']);
$router->get('/admin/backup/full', [AdminBackupController::class, 'downloadFull']);
$router->get('/admin/backup/file', [AdminBackupController::class, 'downloadStored']);
$router->post('/admin/backup/restore', [AdminBackupController::class, 'restore']);
$router->post('/admin/backup/restore-full', [AdminBackupController::class, 'restoreFull']);
$router->post('/admin/cleanup/development', [AdminBackupController::class, 'cleanDevelopmentData']);

$router->get('/admin/export/customers.csv', [AdminExportController::class, 'customers']);
$router->get('/admin/export/payments.csv', [AdminExportController::class, 'payments']);
$router->get('/admin/export/subscriptions.csv', [AdminExportController::class, 'subscriptions']);
$router->get('/admin/export/logs.csv', [AdminExportController::class, 'logs']);

$router->get('/admin/packages', [AdminPackagesController::class, 'index']);
$router->post('/admin/packages', [AdminPackagesController::class, 'index']);
$router->post('/admin/packages/sync-routeros', [AdminPackagesController::class, 'syncFromRouterOS']);
$router->get('/admin/packages/edit', [AdminPackagesController::class, 'edit']);
$router->post('/admin/packages/update', [AdminPackagesController::class, 'update']);

$router->get('/admin/payments', [AdminController::class, 'payments']);
$router->post('/admin/payments/store', [AdminController::class, 'storePayment']);

$router->get('/admin/announcements', [AdminController::class, 'announcements']);
$router->post('/admin/announcements/store', [AdminController::class, 'storeAnnouncement']);
$router->get('/admin/announcements/edit', [AdminController::class, 'editAnnouncement']);
$router->post('/admin/announcements/update', [AdminController::class, 'updateAnnouncement']);
$router->post('/admin/announcements/delete', [AdminController::class, 'deleteAnnouncement']);

$router->get('/admin/qos', [AdminQosController::class, 'index']);
$router->get('/admin/qos/edit', [AdminQosController::class, 'edit']);
$router->post('/admin/qos/update', [AdminQosController::class, 'update']);

$router->get('/admin/system', [AdminSystemController::class, 'index']);

$router->get('/admin/health', [AdminHealthController::class, 'index']);

$router->get('/admin/router-setup', [AdminRouterSetupController::class, 'index']);
$router->post('/admin/router-setup', [AdminRouterSetupController::class, 'submit']);

$router->get('/admin/auto-match', [AdminAutoMatchController::class, 'index']);
$router->post('/admin/auto-match/import-users', [AdminAutoMatchController::class, 'importUsers']);
$router->post('/admin/auto-match/import-profiles', [AdminAutoMatchController::class, 'importProfiles']);
$router->post('/admin/auto-match/link-packages', [AdminAutoMatchController::class, 'linkPackages']);

$router->get('/admin/api/diagnostics', [AdminApiDiagnosticsController::class, 'index']);
$router->get('/admin/api/browser', [AdminApiBrowserController::class, 'index']);
$router->get('/admin/api/record', [AdminApiRecordController::class, 'show']);
$router->get('/admin/api/reset-counters', [AdminApiResetCountersController::class, 'confirm']);
$router->post('/admin/api/reset-counters/dry-run', [AdminApiResetCountersController::class, 'dryRun']);

$router->get('/admin/readiness', [AdminReadinessController::class, 'index']);

$router->get('/admin/routeros', [AdminRouterOsController::class, 'index']);
$router->get('/admin/routeros/active-users', [AdminRouterOsController::class, 'activeUsers']);
$router->get('/admin/routeros/discovery', [AdminRouterOsController::class, 'discovery']);
$router->get('/admin/routeros/users', [AdminRouterOsController::class, 'users']);
$router->get('/admin/routeros/profiles', [AdminRouterOsController::class, 'profiles']);
$router->get('/admin/user-manager-packages', [AdminUserManagerPackagesController::class, 'index']);
$router->post('/admin/user-manager-packages/import', [AdminUserManagerPackagesController::class, 'import']);
$router->get('/admin/package-push', [AdminPackagePushController::class, 'index']);
$router->post('/admin/package-push/preview', [AdminPackagePushController::class, 'preview']);
$router->post('/admin/package-push/execute', [AdminPackagePushController::class, 'execute']);
$router->get('/admin/package-assign', [AdminPackageAssignController::class, 'index']);
$router->post('/admin/package-assign/preview', [AdminPackageAssignController::class, 'preview']);
$router->post('/admin/package-assign/execute', [AdminPackageAssignController::class, 'execute']);