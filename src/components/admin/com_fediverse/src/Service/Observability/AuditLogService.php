<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Service\Observability;

use Joomla\CMS\Factory;

/**
 * AuditLogService Class
 *
 * Write Fediverse administrator actions to Joomla's User Action Log.
 *
 * @since  __DEPLOY_VERSION__
 */
final class AuditLogService
{
    /**
     * Initialize the audit log service.
     *
     * @params ?object $application Joomla application override for tests.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private ?object $application = null)
    {
    }

    /**
     * Record an audit log entry.
     *
     * @params string $messageLanguageKey Action-log language key.
     * @params string $context Action-log context, grouped by extension.
     * @params array $message Placeholder payload for the message template.
     * @params ?int $userId Acting Joomla user id.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function record(
        string $messageLanguageKey,
        string $context,
        array $message,
        ?int $userId = null
    ): void {
        $messageLanguageKey = trim($messageLanguageKey);
        $context            = trim($context);

        if ($messageLanguageKey === '' || $context === '' || $message === []) {
            return;
        }

        try {
            $application = $this->application ?? Factory::getApplication();
            $component   = $application->bootComponent('com_actionlogs');
            $mvcFactory  = $component->getMVCFactory();
            $model       = $mvcFactory->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

            if (!is_object($model) || !method_exists($model, 'addLog')) {
                return;
            }

            $model->addLog(
                [$this->normalizeMessage($message)],
                strtoupper($messageLanguageKey),
                $context,
                $userId ?? 0
            );
        } catch (\Throwable) {
            // Logging must not block the underlying administrator action.
        }
    }

    /**
     * Convert action-log placeholders to scalars accepted by Joomla's renderer.
     *
     * @params array<string, mixed> $message Canonical event payload.
     *
     * @return  array<string, scalar|null>  Normalized message payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function normalizeMessage(array $message): array
    {
        $normalized = [];

        foreach ($message as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $normalized[$key] = (string) json_encode($value, JSON_UNESCAPED_SLASHES);

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
