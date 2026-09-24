<?php

declare(strict_types=1);

namespace Controllers;

use Core\Csrf;
use Core\Flash;
use Models\AuditLog;
use Models\Notifier;
use Models\Permission;
use Models\UserRepository;
use Support\Mailer;

final class HomeController
{
    public function index(): void
    {
        // The root is the sign-in form, so there is nothing here for someone who
        // is already signed in.
        if (\is_logged_in()) {
            header('Location: ' . \url('dashboard'));
            exit;
        }

        \view('home/index', [
            'title' => 'Sign in',
            'accounts' => \demo_accounts(),
            'loginError' => (string) ($_GET['login_error'] ?? ''),
            'loginRequired' => ($_GET['login_required'] ?? '') === '1',
            'loggedOut' => ($_GET['logged_out'] ?? '') === '1',
        ]);
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirectHome();
        }

        if (!Csrf::check()) {
            $this->redirectHome('login_error=token#login');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $repository = new UserRepository();
        $user = $repository->findActiveByEmail($email);

        if ($user !== null) {
            $user = $repository->clearExpiredLock($user);
        }

        $lock = $repository->lockState($user);
        if ($lock['locked']) {
            AuditLog::record('auth.login_locked', 'user', $user === null ? null : (string) $user['id'], 'Login attempted on a locked account.', ['email' => $email]);
            $repository->logAttempt($email, false);
            $this->redirectHome('login_error=locked&minutes=' . $lock['minutes'] . '#login');
        }

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $repository->registerFailure($email, $user);
            AuditLog::record('auth.login_failed', 'user', null, 'Invalid login attempt.', ['email' => $email]);
            $this->redirectHome('login_error=1#login');
        }

        // A new session id at the moment privileges change closes the session
        // fixation hole that existed while the id was reused across login.
        session_regenerate_id(true);
        Csrf::rotate();

        $repository->touchLastLogin((int) $user['id']);
        $repository->logAttempt($email, true);

        $_SESSION['logistics_authenticated'] = true;
        $_SESSION['logistics_role'] = (string) $user['role_key'];
        $_SESSION['logistics_user_id'] = (int) $user['id'];
        $_SESSION['logistics_user_name'] = $user['full_name'];
        $_SESSION['logistics_user_email'] = $user['email'];
        // The photo is on every page, so it is read once here rather than
        // fetched again on each request.
        $_SESSION['logistics_user_avatar'] = $user['avatar_path'] ?? null;
        $_SESSION['logistics_prvg'] = (int) $user['prvg'] === 1 ? 1 : 2;
        $_SESSION['logistics_must_change_password'] = (int) $user['must_change_password'] === 1;
        unset($_SESSION['logistics_driver_id']);

        Permission::flush();
        AuditLog::record('auth.login', 'user', (string) $user['id']);
        Notifier::refreshOperationalAlerts();

        if ($_SESSION['logistics_must_change_password']) {
            Flash::warning('Your account still uses a one-time password. Please set a new one now.');
            header('Location: ' . \url(['account', 'password'], ['forced' => 1]));
            exit;
        }

        Flash::success('Welcome back, ' . $user['full_name'] . '.');
        header('Location: ' . \url('dashboard'));
        exit;
    }

    public function logout(): void
    {
        AuditLog::record('auth.logout', 'user', \current_user_id() !== null ? (string) \current_user_id() : null);

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->redirectHome('logged_out=1');
    }

    /**
     * Starts a password reset.
     *
     * The link is emailed to the address on the account. It is only printed on
     * screen when the email could not go out, so an installation with no mail
     * configured is still usable but never leaks the link when mail works.
     */
    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \view('auth/forgot', ['title' => 'Reset your password', 'token' => null, 'emailed' => false, 'sent' => false]);
            return;
        }

        if (!Csrf::check()) {
            \view('auth/forgot', ['title' => 'Reset your password', 'token' => null, 'emailed' => false, 'sent' => false, 'error' => 'Your security token expired. Please try again.']);
            return;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $repository = new UserRepository();
        $user = $repository->findActiveByEmail($email);

        $token = null;
        $emailed = false;

        if ($user !== null) {
            $token = $repository->createResetToken((int) $user['id']);
            AuditLog::record('auth.reset_requested', 'user', (string) $user['id'], null, ['email' => $email]);

            $link = Mailer::link('reset-password/' . $token);
            $result = Mailer::send([
                'key' => 'reset-' . substr(hash('sha256', $token), 0, 40),
                'category' => 'password_reset',
                'to' => (string) $user['email'],
                'to_name' => (string) $user['full_name'],
                'user_id' => (int) $user['id'],
                'subject' => 'Reset your LMS password',
                'heading' => 'Reset your password',
                'lines' => [
                    sprintf('Hello %s,', (string) $user['full_name']),
                    'Someone asked to reset the password on your LMS account. Use the button below to choose a new one.',
                    'The link works once and expires in one hour. If you did not ask for this, ignore this message and your password stays as it is.',
                ],
                'action' => ['label' => 'Choose a new password', 'url' => $link],
                'entity_type' => 'users',
                'entity_id' => (string) $user['id'],
            ]);
            $emailed = $result['sent'];
        }

        // The same answer either way, so the form cannot be used to discover
        // accounts. The link is only printed on screen when it could not be
        // emailed, which is what a machine with no mail configured needs.
        \view('auth/forgot', [
            'title' => 'Reset your password',
            'token' => $emailed ? null : $token,
            'emailed' => $emailed,
            'sent' => true,
            'email' => $email,
        ]);
    }

    public function resetPassword(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $repository = new UserRepository();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \view('auth/reset', ['title' => 'Choose a new password', 'token' => $token, 'error' => null]);
            return;
        }

        if (!Csrf::check()) {
            \view('auth/reset', ['title' => 'Choose a new password', 'token' => $token, 'error' => 'Your security token expired. Please try again.']);
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $error = $this->passwordProblem($password, $confirm);

        if ($error !== null) {
            \view('auth/reset', ['title' => 'Choose a new password', 'token' => $token, 'error' => $error]);
            return;
        }

        $userId = $repository->consumeResetToken($token);
        if ($userId === null) {
            \view('auth/reset', ['title' => 'Choose a new password', 'token' => $token, 'error' => 'This reset link is invalid or has already been used.']);
            return;
        }

        $repository->setPassword($userId, $password, false);
        AuditLog::record('auth.password_reset', 'user', (string) $userId);

        Flash::success('Your password was changed. Please sign in with the new one.');
        $this->redirectHome('#login');
    }

    /** The one place password rules are defined, used by reset and by the account page. */
    public static function passwordProblem(string $password, string $confirm): ?string
    {
        return match (true) {
            strlen($password) < 10 => 'The new password must be at least 10 characters long.',
            $password !== $confirm => 'The two passwords do not match.',
            preg_match('/[a-z]/', $password) !== 1 => 'The new password needs at least one lower-case letter.',
            preg_match('/[A-Z]/', $password) !== 1 => 'The new password needs at least one capital letter.',
            preg_match('/\d/', $password) !== 1 => 'The new password needs at least one digit.',
            in_array(strtolower($password), ['password', 'password123', 'logistics1'], true) => 'That password is too easy to guess.',
            default => null,
        };
    }

    private function redirectHome(string $query = ''): never
    {
        $suffix = $query === '' ? '' : (str_starts_with($query, '#') ? $query : '?' . $query);
        header('Location: ' . \url('') . $suffix);
        exit;
    }
}
