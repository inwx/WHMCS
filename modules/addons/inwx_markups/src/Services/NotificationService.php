<?php

namespace INWX\Markups\Services;

/**
 * NotificationService.
 *
 * Handles email notifications for pricing changes.
 */
class NotificationService
{
    /**
     * Send admin email overview.
     *
     * @param string $subject Email subject
     * @param string $body    Email body (plain text)
     */
    public static function sendAdminEmail(string $subject, string $body): void
    {
        logActivity('INWX Markups: sendAdminEmail called with subject: ' . $subject);
        try {
            // Get all active admin users using WHMCS API
            $result = localAPI('GetAdminUsers', []);
            logActivity('INWX Markups: GetAdminUsers result: ' . json_encode($result));

            if (empty($result['admin_users'])) {
                logActivity('INWX Markups: No admin users found, cannot send notification');
                return;
            }

            // Send to all active admins
            $recipients = [];
            foreach ($result['admin_users'] as $admin) {
                // Skip disabled admins
                if (!empty($admin['isDisabled'])) {
                    continue;
                }

                if (!empty($admin['email'])) {
                    $recipients[] = $admin['email'];
                }
            }

            if (empty($recipients)) {
                logActivity('INWX Markups: No active admin emails found');
                return;
            }

            logActivity('INWX Markups: Attempting to send email to: ' . implode(', ', $recipients));

            // Use WHMCS sendAdminNotification function
            sendAdminNotification('system', $subject, $body);

            logActivity('INWX Markups: Notification sent to ' . count($recipients) . ' admin(s)');
        } catch (\Throwable $e) {
            logActivity('INWX Markups: Failed to send email notification: ' . $e->getMessage());
        }
    }
}
