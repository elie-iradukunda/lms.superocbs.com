<?php

declare(strict_types=1);

namespace Controllers;

use Core\Database;
use Core\Flash;
use Models\DriverSupport;
use Models\Uploads;

/**
 * The office side of driver support (section 16): the OM approves and
 * forwards, or rejects with a reason; whoever it went to marks it resolved.
 */
final class SupportController
{
    public function form(array $params): void
    {
        $request = $this->load($params);
        $users = Database::connection()->prepare(
            "SELECT u.id, u.full_name, r.role_name FROM users u INNER JOIN roles r ON r.id = u.role_id
              WHERE u.status = 'active' AND u.deleted_at IS NULL AND (u.company_id = ? OR u.company_id IS NULL)
                AND r.role_key IN ('finance', 'management', 'logistics_manager', 'warehouse_manager')
              ORDER BY r.role_name, u.full_name"
        );
        $users->execute([(int) $request['company_id']]);
        \view('support/decide', [
            'title' => $request['request_code'],
            'request' => $request,
            'users' => $users->fetchAll(\PDO::FETCH_ASSOC),
            'photos' => array_map([Uploads::class, 'url'], json_decode((string) ($request['photos'] ?? ''), true) ?: []),
        ]);
    }

    public function decide(array $params): void
    {
        $request = $this->load($params);
        try {
            if (($_POST['decision'] ?? '') === 'resolve') {
                DriverSupport::resolve((int) $request['id'], (string) ($_POST['note'] ?? ''));
                Flash::success('Resolved; the driver was told.');
            } else {
                if (!\role_can('support_requests', 'approve')) {
                    throw new \RuntimeException('The OM decides on support requests.');
                }
                $message = DriverSupport::decide(
                    (int) $request['id'],
                    ($_POST['decision'] ?? '') === 'approve',
                    (string) ($_POST['note'] ?? ''),
                    ($_POST['forward_role'] ?? '') ?: null,
                    ($_POST['assignee'] ?? '') !== '' ? (int) $_POST['assignee'] : null
                );
                Flash::success($message);
            }
        } catch (\Throwable $exception) {
            Flash::error($exception->getMessage());
            $this->redirect(\url(['support_requests', $request['id'], 'decide']));
        }
        $this->redirect(\url(['support_requests', $request['id']]));
    }

    private function load(array $params): array
    {
        if (\Models\LogisticsData::find('support_requests', (int) ($params['id'] ?? 0), \current_context()) === null) {
            Flash::error('That support request does not exist.');
            $this->redirect(\url('support_requests'));
        }

        return DriverSupport::find((int) $params['id']);
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
