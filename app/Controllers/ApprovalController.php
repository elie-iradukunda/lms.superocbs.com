<?php

declare(strict_types=1);

namespace Controllers;

use Models\Approvals;

/** What is waiting for my signature, what I raised that is waiting, and what I signed. */
final class ApprovalController
{
    public function index(): void
    {
        $userId = (int) \current_user_id();

        \view('approvals/index', [
            'title' => 'Approvals',
            // The request chain (sections 8-9) first: what waits for my signature.
            'requests' => \Models\RequestForms::inbox(),
            'journals' => array_values(array_filter(\Models\ManualJournals::waiting(), static fn (array $journal): bool => \Models\ManualJournals::stepFor($journal) !== null)),
            'waiting' => Approvals::inbox(\current_role()),
            'raised' => Approvals::raisedBy($userId),
            'decided' => Approvals::decidedBy($userId),
        ]);
    }
}
