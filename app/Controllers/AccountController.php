<?php

declare(strict_types=1);

namespace Controllers;

use Core\Flash;
use Models\AuditLog;
use Models\Avatar;
use Models\UserRepository;

/**
 * The Profile and Settings menu entries used to be dead links (`href="#"`), and
 * there was no way at all for a user to change their own password.
 */
final class AccountController
{
    public function profile(): void
    {
        $user = (new UserRepository())->findById((int) \current_user_id());
        if ($user === null) {
            Flash::error('Your account could not be loaded.');
            header('Location: ' . \url('dashboard'));
            exit;
        }

        \view('account/profile', [
            'title' => 'My profile',
            'user' => $user,
            'driverId' => \current_driver_id(),
        ]);
    }

    public function updateProfile(): void
    {
        $repository = new UserRepository();
        $userId = (int) \current_user_id();

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $jobTitle = trim((string) ($_POST['job_title'] ?? ''));

        if ($fullName === '') {
            Flash::error('Your name cannot be blank.');
            header('Location: ' . \url('account'));
            exit;
        }

        // An unticked checkbox is simply absent from the request.
        $notifyByEmail = ($_POST['notify_by_email'] ?? '0') === '1';

        $existingPhoto = Avatar::forUser($userId);
        $photo = $existingPhoto;
        $photoNote = '';

        if (($_POST['remove_photo'] ?? '') === '1') {
            Avatar::forget($existingPhoto);
            $photo = null;
            $photoNote = ' Your photo was removed.';
        } else {
            $stored = Avatar::store($_FILES['avatar'] ?? null, $existingPhoto);
            if ($stored['error'] !== null) {
                // The name and the rest are still saved: losing a typed change
                // because a photo was the wrong format helps nobody.
                $repository->updateProfile($userId, $fullName, $phone, $jobTitle, $notifyByEmail, $existingPhoto);
                $_SESSION['logistics_user_name'] = $fullName;
                Flash::error($stored['error']);
                header('Location: ' . \url('account'));
                exit;
            }
            if ($stored['path'] !== $existingPhoto) {
                $photoNote = ' Your photo was updated.';
            }
            $photo = $stored['path'];
        }

        $repository->updateProfile($userId, $fullName, $phone, $jobTitle, $notifyByEmail, $photo);
        $_SESSION['logistics_user_name'] = $fullName;
        $_SESSION['logistics_user_avatar'] = $photo;

        AuditLog::record('account.profile_updated', 'users', (string) $userId);
        Flash::success('Your profile was updated.' . $photoNote);

        header('Location: ' . \url('account'));
        exit;
    }

    public function password(): void
    {
        \view('account/password', [
            'title' => 'Change password',
            'forced' => \must_change_password(),
            'error' => null,
        ]);
    }

    public function updatePassword(): void
    {
        $repository = new UserRepository();
        $userId = (int) \current_user_id();

        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');

        $error = null;
        if (!$repository->verifyPassword($userId, $current)) {
            $error = 'Your current password is not correct.';
        } elseif ($current === $password) {
            $error = 'The new password must be different from the current one.';
        } else {
            $error = HomeController::passwordProblem($password, $confirm);
        }

        if ($error !== null) {
            AuditLog::record('account.password_change_failed', 'users', (string) $userId, $error);
            \view('account/password', ['title' => 'Change password', 'forced' => \must_change_password(), 'error' => $error]);
            return;
        }

        $repository->setPassword($userId, $password, false);
        $_SESSION['logistics_must_change_password'] = false;
        session_regenerate_id(true);

        AuditLog::record('account.password_changed', 'users', (string) $userId);
        Flash::success('Your password was changed.');

        header('Location: ' . \url('account'));
        exit;
    }
}
