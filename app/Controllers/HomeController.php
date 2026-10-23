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

        $repository->logAttempt($email, true);

        // Approvers (and anyone Admin marks) confirm a code sent by email before
        // the session opens, so nobody can sign for someone else with a stolen
        // password (specification, section 9).
        if ($this->needsTwoStep($user)) {
            $code = (string) random_int(100000, 999999);
            $_SESSION['logistics_two_step'] = [
                'user_id' => (int) $user['id'],
                'hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires' => time() + 600,
                'tries' => 0,
            ];
            $result = Mailer::send([
                'key' => 'two-step-' . $user['id'] . '-' . time(),
                'category' => 'two_step_login',
                'to' => (string) $user['email'],
                'to_name' => (string) $user['full_name'],
                'user_id' => (int) $user['id'],
                'subject' => 'Your LMS sign-in code',
                'heading' => 'Your sign-in code',
                'lines' => [
                    sprintf('Hello %s,', (string) $user['full_name']),
                    'Use this code to finish signing in: ' . $code,
                    'It works once and expires in 10 minutes. If you did not try to sign in, change your password.',
                ],
                'entity_type' => 'users',
                'entity_id' => (string) $user['id'],
            ]);
            // With no mail configured the code can only be shown on a test
            // installation (APP_DEBUG=1), never in production.
            $_SESSION['logistics_two_step']['shown'] = !$result['sent'] && (bool) \config('app.debug', false) ? $code : null;
            $_SESSION['logistics_two_step']['emailed'] = (bool) $result['sent'];
            AuditLog::record('auth.two_step_sent', 'user', (string) $user['id']);
            header('Location: ' . \url(['login', 'verify']));
            exit;
        }

        $this->openSession($user);
    }

    /** The second step: the code that was emailed. */
    public function verify(): void
    {
        $pending = $_SESSION['logistics_two_step'] ?? null;
        if (!is_array($pending) || (int) ($pending['expires'] ?? 0) < time()) {
            unset($_SESSION['logistics_two_step']);
            $this->redirectHome('login_error=expired#login');
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')) ?? '';
            $_SESSION['logistics_two_step']['tries'] = (int) $pending['tries'] + 1;
            if (Csrf::check() && $code !== '' && password_verify($code, (string) $pending['hash'])) {
                unset($_SESSION['logistics_two_step']);
                $user = (new UserRepository())->findById((int) $pending['user_id']);
                if ($user === null) {
                    $this->redirectHome('login_error=1#login');
                }
                $this->openSession($user);
            }
            if ($_SESSION['logistics_two_step']['tries'] >= 5) {
                unset($_SESSION['logistics_two_step']);
                AuditLog::record('auth.two_step_failed', 'user', (string) $pending['user_id']);
                $this->redirectHome('login_error=1#login');
            }
            $error = 'That code is not right. Check the latest email and try again.';
        }

        \view('auth/verify', [
            'title' => 'Enter your sign-in code',
            'error' => $error,
            'shown' => $pending['shown'] ?? null,
            'emailed' => (bool) ($pending['emailed'] ?? false),
        ]);
    }

    private function needsTwoStep(array $user): bool
    {
        if ((int) ($user['two_step_login'] ?? 0) === 1) {
            return true;
        }
        if (\Models\Settings::int('two_step_for_approvers', 1) !== 1) {
            return false;
        }

        // The roles that sign money: they verify, authorize and pay.
        return in_array((string) $user['role_key'], ['finance', 'management', 'cashier'], true);
    }

    private function openSession(array $user): never
    {
        $repository = new UserRepository();

        // A new session id at the moment privileges change closes the session
        // fixation hole that existed while the id was reused across login.
        session_regenerate_id(true);
        Csrf::rotate();

        $repository->touchLastLogin((int) $user['id']);

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
        // Each person works inside their own company (section 3).
        $_SESSION['logistics_home_company_id'] = (int) ($user['company_id'] ?? 0) ?: \Models\Company::defaultId();
        $_SESSION['logistics_company_id'] = $_SESSION['logistics_home_company_id'];
        $_SESSION['logistics_last_activity'] = time();
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
            strlen($password) < self::minimumLength() => 'The new password must be at least ' . self::minimumLength() . ' characters long.',
            $password !== $confirm => 'The two passwords do not match.',
            preg_match('/[a-z]/', $password) !== 1 => 'The new password needs at least one lower-case letter.',
            preg_match('/[A-Z]/', $password) !== 1 => 'The new password needs at least one capital letter.',
            preg_match('/\d/', $password) !== 1 => 'The new password needs at least one digit.',
            in_array(strtolower($password), ['password', 'password123', 'logistics1'], true) => 'That password is too easy to guess.',
            default => null,
        };
    }

    /** Admin sets it under Settings > Security; never below 8. */
    public static function minimumLength(): int
    {
        return max(8, \Models\Settings::int('password_min_length', 10));
    }

    private function redirectHome(string $query = ''): never
    {
        $suffix = $query === '' ? '' : (str_starts_with($query, '#') ? $query : '?' . $query);
        header('Location: ' . \url('') . $suffix);
        exit;
    }
}
