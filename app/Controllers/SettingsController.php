<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\Settings;

/**
 * Company settings. Currency, tax rate, invoice prefix, alert windows and the
 * login lockout policy used to be constants inside the code.
 */
final class SettingsController
{
    public function index(): void
    {
        // Branding (who built the system) is credited in the footer automatically;
        // it is not something day-to-day company admins need to edit here.
        $grouped = [];
        foreach (Settings::editable() as $setting) {
            if ($setting['setting_group'] === 'Branding') {
                continue;
            }
            $grouped[$setting['setting_group']][] = $setting;
        }

        // Company-facing groups first (as tabs), then anything else alphabetically after them.
        $priority = ['Company', 'Finance', 'Operations', 'Security'];
        $rank = static function (string $group) use ($priority): int {
            $position = array_search($group, $priority, true);
            return $position === false ? 99 : $position;
        };
        uksort($grouped, static fn (string $a, string $b): int => $rank($a) <=> $rank($b) ?: $a <=> $b);

        \view('admin/settings', [
            'title' => 'Company settings',
            'grouped' => $grouped,
            'canEdit' => \can_edit('settings'),
        ]);
    }

    public function update(): void
    {
        $submitted = $_POST['settings'] ?? [];
        if (!is_array($submitted)) {
            $submitted = [];
        }

        $allowed = array_column(Settings::editable(), 'input_type', 'setting_key');
        $changed = 0;
        $problems = [];

        foreach ($submitted as $key => $value) {
            $key = (string) $key;
            if (!isset($allowed[$key])) {
                continue;
            }

            $value = trim((string) $value);

            if ($allowed[$key] === 'number' && $value !== '' && !is_numeric($value)) {
                $problems[] = str_replace('_', ' ', $key) . ' must be a number.';
                continue;
            }

            Settings::put($key, mb_substr($value, 0, 255));
            $changed++;
        }

        if ($problems !== []) {
            Flash::error(implode(' ', $problems));
        }

        if ($changed > 0) {
            AuditLog::record('settings.updated', 'settings', null, null, ['changed' => $changed]);
            Flash::success(sprintf('%d setting(s) saved.', $changed));
        }

        header('Location: ' . \url('settings'));
        exit;
    }
}
