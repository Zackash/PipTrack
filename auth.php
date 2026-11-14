<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app_data.php';

function ledgerAuthFile(): string
{
    return ledgerDataDirectory() . DIRECTORY_SEPARATOR . 'users.json';
}

function ledgerAuthLockPath(): string
{
    return ledgerAuthFile() . '.lock';
}

function ledgerWithAuthLock(int $mode, callable $callback): mixed
{
    $lock = fopen(ledgerAuthLockPath(), 'c');
    if ($lock === false) {
        throw new RuntimeException('Unable to open the account lock.');
    }

    try {
        if (!flock($lock, $mode)) {
            throw new RuntimeException('Unable to lock the account registry.');
        }
        return $callback(ledgerReadAuthAccountsUnlocked());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function ledgerLegacyAuthAccount(): ?array
{
    $file = ledgerDataDirectory() . DIRECTORY_SEPARATOR . 'account.json';
    if (!is_file($file)) {
        return null;
    }

    $contents = file_get_contents($file);
    $legacy = $contents === false ? null : json_decode($contents, true);
    if (
        !is_array($legacy)
        || !is_string($legacy['username'] ?? null)
        || !is_string($legacy['password_hash'] ?? null)
    ) {
        throw new RuntimeException('The existing local account file is invalid.');
    }

    return [
        'id' => 'legacy',
        'username' => strtolower($legacy['username']),
        'email' => '',
        'phone' => '',
        'password_hash' => $legacy['password_hash'],
        'auth_version' => 1,
        'reset_code_hash' => null,
        'reset_expires' => 0,
        'reset_attempts' => 0,
        'reset_requested_at' => 0,
    ];
}

function ledgerReadAuthAccountsUnlocked(): array
{
    $accounts = [];
    $file = ledgerAuthFile();
    if (is_file($file)) {
        $contents = file_get_contents($file);
        $registry = $contents === false ? null : json_decode($contents, true);
        if (!is_array($registry) || !is_array($registry['accounts'] ?? null)) {
            throw new RuntimeException('The account registry is invalid.');
        }

        foreach ($registry['accounts'] as $id => $account) {
            if (
                !is_array($account)
                || !is_string($account['id'] ?? null)
                || (string) $id !== $account['id']
                || !is_string($account['username'] ?? null)
                || !is_string($account['email'] ?? null)
                || !is_string($account['phone'] ?? null)
                || !is_string($account['password_hash'] ?? null)
            ) {
                throw new RuntimeException('An account in the registry is invalid.');
            }
            $account += [
                'auth_version' => 1,
                'reset_code_hash' => null,
                'reset_expires' => 0,
                'reset_attempts' => 0,
                'reset_requested_at' => 0,
            ];
            $account['auth_version'] = (int) $account['auth_version'];
            $accounts[$account['id']] = $account;
        }
    }

    if (!isset($accounts['legacy'])) {
        $legacy = ledgerLegacyAuthAccount();
        if ($legacy !== null) {
            $accounts['legacy'] = $legacy;
        }
    }

    return $accounts;
}

function ledgerSaveAuthAccountsUnlocked(array $accounts): void
{
    $file = ledgerAuthFile();
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the account registry for updates.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the account registry for updates.');
        }
        $contents = json_encode(
            ['accounts' => $accounts],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($contents === false || !ftruncate($handle, 0) || rewind($handle) === false) {
            throw new RuntimeException('Unable to save the account registry.');
        }
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to save the account registry.');
            }
            $offset += $written;
        }
        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush the account registry.');
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    if (!chmod($file, 0600)) {
        throw new RuntimeException('Unable to secure the account registry file.');
    }
}

function ledgerAuthAccounts(): array
{
    return ledgerWithAuthLock(LOCK_SH, static fn(array $accounts): array => $accounts);
}

function ledgerAuthAccount(): ?array
{
    $id = $_SESSION['auth_user_id'] ?? null;
    if (!is_string($id)) {
        return null;
    }

    $accounts = ledgerAuthAccounts();
    return $accounts[$id] ?? null;
}

function ledgerFindAuthAccount(string $field, string $value): ?array
{
    foreach (ledgerAuthAccounts() as $account) {
        if (is_string($account[$field] ?? null) && $account[$field] !== '' && hash_equals($account[$field], $value)) {
            return $account;
        }
    }
    return null;
}

function ledgerCreateAuthAccount(string $username, string $email, string $phone, string $password): bool
{
    return ledgerWithAuthLock(LOCK_EX, static function (array $accounts) use ($username, $email, $phone, $password): bool {
        foreach ($accounts as $account) {
            if (
                hash_equals($account['username'], $username)
                || ($account['email'] !== '' && hash_equals($account['email'], $email))
                || ($account['phone'] !== '' && hash_equals($account['phone'], $phone))
            ) {
                return false;
            }
        }

        $id = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to secure the account password.');
        }
        $accounts[$id] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
            'auth_version' => 1,
            'reset_code_hash' => null,
            'reset_expires' => 0,
            'reset_attempts' => 0,
            'reset_requested_at' => 0,
        ];
        ledgerSaveAuthAccountsUnlocked($accounts);
        return true;
    });
}

function ledgerUpdateAuthContacts(string $id, string $email, string $phone): bool
{
    return ledgerWithAuthLock(LOCK_EX, static function (array $accounts) use ($id, $email, $phone): bool {
        if (!isset($accounts[$id])) {
            return false;
        }
        foreach ($accounts as $otherId => $account) {
            if (
                $otherId !== $id
                && (($account['email'] !== '' && hash_equals($account['email'], $email))
                    || ($account['phone'] !== '' && hash_equals($account['phone'], $phone)))
            ) {
                return false;
            }
        }
        $accounts[$id]['email'] = $email;
        $accounts[$id]['phone'] = $phone;
        ledgerSaveAuthAccountsUnlocked($accounts);
        return true;
    });
}

function ledgerStoreResetCode(string $id, string $codeHash, int $expires): string
{
    return ledgerWithAuthLock(LOCK_EX, static function (array $accounts) use ($id, $codeHash, $expires): string {
        if (!isset($accounts[$id])) {
            return 'missing';
        }
        if ((int) $accounts[$id]['reset_requested_at'] > time() - 60) {
            return 'rate_limited';
        }

        $accounts[$id]['reset_code_hash'] = $codeHash;
        $accounts[$id]['reset_expires'] = $expires;
        $accounts[$id]['reset_attempts'] = 0;
        $accounts[$id]['reset_requested_at'] = time();
        ledgerSaveAuthAccountsUnlocked($accounts);
        return 'stored';
    });
}

function ledgerClearResetCode(string $id): void
{
    ledgerWithAuthLock(LOCK_EX, static function (array $accounts) use ($id): null {
        if (isset($accounts[$id])) {
            $accounts[$id]['reset_code_hash'] = null;
            $accounts[$id]['reset_expires'] = 0;
            $accounts[$id]['reset_attempts'] = 0;
            ledgerSaveAuthAccountsUnlocked($accounts);
        }
        return null;
    });
}

function ledgerCompletePasswordReset(string $id, string $contactField, string $contact, string $code, string $password): string
{
    return ledgerWithAuthLock(
        LOCK_EX,
        static function (array $accounts) use ($id, $contactField, $contact, $code, $password): string {
            if (
                !isset($accounts[$id])
                || !hash_equals($accounts[$id][$contactField], $contact)
                || !is_string($accounts[$id]['reset_code_hash'])
            ) {
                return 'invalid';
            }
            if ((int) $accounts[$id]['reset_expires'] <= time()) {
                $accounts[$id]['reset_code_hash'] = null;
                $accounts[$id]['reset_expires'] = 0;
                $accounts[$id]['reset_attempts'] = 0;
                ledgerSaveAuthAccountsUnlocked($accounts);
                return 'expired';
            }
            if ((int) $accounts[$id]['reset_attempts'] >= 5) {
                return 'locked';
            }
            if (!password_verify($code, $accounts[$id]['reset_code_hash'])) {
                $accounts[$id]['reset_attempts']++;
                if ($accounts[$id]['reset_attempts'] >= 5) {
                    $accounts[$id]['reset_code_hash'] = null;
                    $accounts[$id]['reset_expires'] = 0;
                }
                ledgerSaveAuthAccountsUnlocked($accounts);
                return 'invalid';
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Unable to secure the new password.');
            }
            $accounts[$id]['password_hash'] = $passwordHash;
            $accounts[$id]['auth_version'] = (int) $accounts[$id]['auth_version'] + 1;
            $accounts[$id]['reset_code_hash'] = null;
            $accounts[$id]['reset_expires'] = 0;
            $accounts[$id]['reset_attempts'] = 0;
            ledgerSaveAuthAccountsUnlocked($accounts);
            return 'success';
        }
    );
}

function ledgerRecoveryContactRequired(?array $account): bool
{
    return $account !== null && ($account['email'] === '' || $account['phone'] === '');
}

function ledgerIsAuthenticated(?array $account = null): bool
{
    $account ??= ledgerAuthAccount();
    $id = $_SESSION['auth_user_id'] ?? null;
    $sessionVersion = $_SESSION['auth_version'] ?? null;

    $authenticated = $account !== null
        && is_string($id)
        && hash_equals($account['id'], $id)
        && is_int($sessionVersion)
        && $sessionVersion === (int) $account['auth_version'];
    if (!$authenticated) {
        return false;
    }

    $rememberMe = $_SESSION['auth_remember_me'] ?? false;
    $lastActivity = $_SESSION['auth_last_activity'] ?? time();
    if (!is_bool($rememberMe) || !is_int($lastActivity)) {
        return false;
    }
    $idleLimit = $rememberMe ? 30 * 24 * 60 * 60 : 24 * 60;
    if ($lastActivity < time() - $idleLimit) {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_version'], $_SESSION['auth_remember_me'], $_SESSION['auth_last_activity']);
        return false;
    }

    $_SESSION['auth_last_activity'] = time();
    return true;
}

function ledgerLogin(array $account, bool $rememberMe = false): void
{
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to secure the login session.');
    }
    $_SESSION['auth_user_id'] = $account['id'];
    $_SESSION['auth_version'] = (int) $account['auth_version'];
    $_SESSION['auth_remember_me'] = $rememberMe;
    $_SESSION['auth_last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if ($rememberMe) {
        $cookie = session_get_cookie_params();
        $options = [
            'expires' => time() + 30 * 24 * 60 * 60,
            'path' => $cookie['path'] !== '' ? $cookie['path'] : '/',
            'secure' => $cookie['secure'],
            'httponly' => true,
            'samesite' => $cookie['samesite'] ?? 'Strict',
        ];
        if ($cookie['domain'] !== '') {
            $options['domain'] = $cookie['domain'];
        }
        if (!setcookie(session_name(), session_id(), $options)) {
            throw new RuntimeException('Unable to persist the login session securely.');
        }
    }
}

function ledgerLogout(): void
{
    unset($_SESSION['auth_user_id'], $_SESSION['auth_version'], $_SESSION['auth_remember_me'], $_SESSION['auth_last_activity']);
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to end the login session securely.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ledgerRecoveryConfigurationError(string $channel): ?string
{
    if (!function_exists('curl_init')) {
        return 'PHP cURL is disabled for the web server. Enable extension=curl in the PHP configuration used to run this app, then restart the server.';
    }
    if ($channel === 'email') {
        foreach (['LEDGER_SMTP_USERNAME', 'LEDGER_SMTP_PASSWORD', 'LEDGER_SMTP_FROM_EMAIL'] as $name) {
            if (!is_string(getenv($name)) || trim((string) getenv($name)) === '') {
                return 'Email recovery is not configured. Set LEDGER_SMTP_USERNAME, LEDGER_SMTP_PASSWORD, and LEDGER_SMTP_FROM_EMAIL on the server.';
            }
        }
        if (!filter_var(getenv('LEDGER_SMTP_FROM_EMAIL'), FILTER_VALIDATE_EMAIL)) {
            return 'LEDGER_SMTP_FROM_EMAIL must be a valid email address.';
        }
        return null;
    }

    foreach (['LEDGER_AT_USERNAME', 'LEDGER_AT_API_KEY'] as $name) {
        if (!is_string(getenv($name)) || trim((string) getenv($name)) === '') {
            return 'SMS recovery is not configured. Set LEDGER_AT_USERNAME and LEDGER_AT_API_KEY on the server.';
        }
    }
    return null;
}

function ledgerSendRecoveryCode(string $channel, string $contact, string $code): void
{
    if ($channel === 'email') {
        ledgerSendRecoveryEmail($contact, $code);
        return;
    }
    ledgerSendRecoverySms($contact, $code);
}

function ledgerSendRecoveryEmail(string $email, string $code): void
{
    $host = trim((string) (getenv('LEDGER_SMTP_HOST') ?: 'smtp.gmail.com'));
    $port = (int) (getenv('LEDGER_SMTP_PORT') ?: '587');
    $username = (string) getenv('LEDGER_SMTP_USERNAME');
    $password = (string) getenv('LEDGER_SMTP_PASSWORD');
    $from = (string) getenv('LEDGER_SMTP_FROM_EMAIL');
    if (preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1 || !in_array($port, [465, 587], true)) {
        throw new RuntimeException('The SMTP host or port configuration is invalid.');
    }

    $message = implode("\r\n", [
        'To: ' . $email,
        'From: ' . $from,
        'Subject: PipTrack password reset code',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        '',
        'Your PipTrack password reset code is ' . $code . '. It expires in 10 minutes.',
        'If you did not request this code, you can ignore this message.',
        '',
    ]);
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('Unable to prepare the recovery email.');
    }
    if (fwrite($stream, $message) !== strlen($message) || !rewind($stream)) {
        fclose($stream);
        throw new RuntimeException('Unable to prepare the recovery email.');
    }

    $curl = curl_init(($port === 465 ? 'smtps://' : 'smtp://') . $host . ':' . $port);
    if ($curl === false) {
        fclose($stream);
        throw new RuntimeException('Unable to connect to the configured email service.');
    }
    curl_setopt_array($curl, [
        CURLOPT_USE_SSL => CURLUSESSL_ALL,
        CURLOPT_USERNAME => $username,
        CURLOPT_PASSWORD => $password,
        CURLOPT_MAIL_FROM => '<' . $from . '>',
        CURLOPT_MAIL_RCPT => ['<' . $email . '>'],
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $stream,
        CURLOPT_INFILESIZE => strlen($message),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $sent = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($stream);
    if ($sent === false || ($status !== 0 && ($status < 200 || $status >= 300))) {
        error_log('PipTrack SMTP delivery failed: ' . $error);
        throw new RuntimeException('The email provider could not send the reset code. Check the SMTP configuration.');
    }
}

function ledgerSendRecoverySms(string $phone, string $code): void
{
    $username = (string) getenv('LEDGER_AT_USERNAME');
    $apiKey = (string) getenv('LEDGER_AT_API_KEY');
    $payload = [
        'username' => $username,
        'to' => $phone,
        'message' => 'Your PipTrack password reset code is ' . $code . '. It expires in 10 minutes.',
    ];
    $sender = trim((string) getenv('LEDGER_AT_SENDER_ID'));
    if ($sender !== '') {
        $payload['from'] = $sender;
    }

    $curl = curl_init('https://api.africastalking.com/version1/messaging');
    if ($curl === false) {
        throw new RuntimeException('Unable to connect to the configured SMS service.');
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => [
            'apiKey: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('PipTrack SMS delivery failed: ' . $error);
        throw new RuntimeException("The SMS provider could not send the reset code. Check the Africa's Talking configuration.");
    }

    $decoded = json_decode($response, true);
    $deliveryStatus = $decoded['SMSMessageData']['Recipients'][0]['status'] ?? null;
    if (is_string($deliveryStatus) && stripos($deliveryStatus, 'success') === false) {
        throw new RuntimeException('The SMS provider could not deliver the reset code. Check the phone number and sender configuration.');
    }
}

function ledgerStartSession(): bool
{
    static $rememberMeAvailable = null;
    if ($rememberMeAvailable === null) {
        $rememberLifetime = 30 * 24 * 60 * 60;
        $configuredLifetime = (int) ini_get('session.gc_maxlifetime');
        $rememberMeAvailable = $configuredLifetime >= $rememberLifetime
            || ini_set('session.gc_maxlifetime', (string) $rememberLifetime) !== false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return $rememberMeAvailable;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);
    session_start();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (isset($_SESSION['auth_user_id'])) {
        $_SESSION['auth_remember_me'] ??= false;
        $_SESSION['auth_last_activity'] ??= time();
    }
    return $rememberMeAvailable;
}
