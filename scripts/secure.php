<?php

declare(strict_types=1);

/**
 * Closes the doors the seed left open.
 *
 * Every account the seed creates ships with a password printed in a file that
 * is in the repository. That is right for a system nobody has deployed yet and
 * wrong the moment it is reachable from outside, because the password to the
 * super-admin account is then public knowledge.
 *
 * This finds every account still on one of those passwords and requires it to
 * be changed at the next sign-in. It does not lock anybody out and it does not
 * invent passwords nobody can be told: the person signs in as they always have
 * and is made to choose a new one before they can go any further.
 *
 *   php scripts/secure.php           show which accounts are still open
 *   php scripts/secure.php --apply   require each of them to change
 *
 * Run it before the system is reachable from outside, and again after any
 * reseed. It changes nothing without --apply.
 */

require __DIR__ . '/../bootstrap.php';

use Core\Database;

/** The passwords the seed files ship with. */
const SEEDED_PASSWORDS = [
    'Admin@123',
    'Password@123',
    'Logistics@123',
    'Fleet@123',
    'Finance@123',
    'Driver@123',
    'Warehouse@123',
    'admin123',
    'password',
];

$apply = in_array('--apply', array_slice($argv, 1), true);
$pdo = Database::connection();

$open = [];
foreach ($pdo->query('SELECT id, full_name, email, password_hash, must_change_password FROM users WHERE deleted_at IS NULL') as $user) {
    foreach (SEEDED_PASSWORDS as $guess) {
        if (password_verify($guess, (string) $user['password_hash'])) {
            $open[] = $user;
            break;
        }
    }
}

if ($open === []) {
    echo "No account is on a seeded password. Nothing to close.\n";
    exit(0);
}

printf("%d account(s) are still on a password that ships with the code:\n\n", count($open));

foreach ($open as $user) {
    printf(
        "  %-28s %-34s %s\n",
        $user['full_name'],
        $user['email'],
        (int) $user['must_change_password'] === 1 ? 'must change at next sign-in' : 'CAN SIGN IN AND STAY'
    );
}

if (!$apply) {
    echo "\nNothing was changed. Run with --apply to require each of them to choose a new password.\n";
    echo "Anyone whose password is already one of these will be asked to change it the next time they sign in.\n";
    exit(1);
}

$statement = $pdo->prepare('UPDATE users SET must_change_password = 1 WHERE id = ?');
foreach ($open as $user) {
    $statement->execute([(int) $user['id']]);
}

printf("\n%d account(s) must now choose a new password before they can use the system.\n", count($open));
echo "Nobody is locked out: they sign in as before and are taken straight to the change-password page.\n";
