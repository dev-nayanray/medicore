<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Development mail transport — writes rendered emails to
 * storage/logs/mail.log so reset links are inspectable without a mail
 * server. Swap send() for PHPMailer/Symfony Mailer in production; the
 * call-site contract stays identical.
 */
final class MailService
{
    /**
     * @param array<string, mixed> $data template variables
     * @return array{sent: bool, dev_link?: string} delivery report
     */
    public static function send(string $to, string $subject, string $template, array $data = []): array
    {
        $body = self::render($template, $data);

        $entry = sprintf(
            "[%s] to: %s | subject: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body,
            str_repeat('─', 70)
        );

        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
        Logger::info('Mail logged (dev transport)', ['to' => $to, 'subject' => $subject]);

        // In local dev, expose the action link so testers can proceed
        // without reading the log file.
        $report = ['sent' => true];
        if (config('app.env') !== 'production' && isset($data['link']) && is_string($data['link'])) {
            $report['dev_link'] = $data['link'];
        }
        return $report;
    }

    private static function render(string $template, array $data): string
    {
        return match ($template) {
            'password-reset' => sprintf(
                "Hello %s,\n\nWe received a request to reset your MediCore password.\n\nReset link (valid %s minutes, single use):\n%s\n\nIf you did not request this, ignore this email — your password stays unchanged.",
                $data['name'] ?? 'there',
                (string) ($data['ttl'] ?? 60),
                (string) ($data['link'] ?? '')
            ),
            default => sprintf("MediCore email [%s]\n%s", $template, json_encode($data, JSON_PRETTY_PRINT)),
        };
    }
}
