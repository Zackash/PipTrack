<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

function dataFile(): string
{
    return ledgerUserDataDirectory() . DIRECTORY_SEPARATOR . 'trades.json';
}

function strategiesFile(): string
{
    return dirname(dataFile()) . DIRECTORY_SEPARATOR . 'strategies.json';
}

function weeklyPlansFile(): string
{
    return dirname(dataFile()) . DIRECTORY_SEPARATOR . 'weekly-plans.json';
}

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fetchEconomicCalendar(): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for the live economic calendar.');
    }

    $caBundle = ini_get('curl.cainfo');
    if (!is_string($caBundle) || $caBundle === '' || !is_file($caBundle)) {
        $caBundle = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'apache'
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'curl-ca-bundle.crt';
    }

    $handle = curl_init('https://nfs.faireconomy.media/ff_calendar_thisweek.json');
    if ($handle === false) {
        throw new RuntimeException('Unable to initialize the Forex Factory calendar request.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'PipTrack Economic Calendar/1.0',
    ];
    if (is_string($caBundle) && is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }

    $configured = curl_setopt_array($handle, $options);
    if (!$configured) {
        throw new RuntimeException('Unable to configure the Forex Factory calendar request.');
    }

    $response = curl_exec($handle);
    $requestError = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if (!is_string($response)) {
        throw new RuntimeException('The Forex Factory calendar request failed: ' . ($requestError ?: 'unknown network error'));
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('The Forex Factory calendar returned HTTP ' . $status . '.');
    }
    if (strlen($response) > 2_000_000) {
        throw new RuntimeException('The Forex Factory calendar response is too large.');
    }

    try {
        $records = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('The Forex Factory calendar returned invalid JSON.', 0, $error);
    }
    if (!is_array($records) || !array_is_list($records)) {
        throw new RuntimeException('The Forex Factory calendar response has an unexpected format.');
    }

    $events = [];
    foreach ($records as $record) {
        if (!is_array($record)
            || !is_string($record['title'] ?? null)
            || !is_string($record['country'] ?? null)
            || !is_string($record['date'] ?? null)
            || !is_string($record['impact'] ?? null)
        ) {
            throw new RuntimeException('The Forex Factory calendar contains an invalid event.');
        }

        $title = trim($record['title']);
        $currency = trim($record['country']);
        $impact = trim($record['impact']);
        if ($title === '' || $currency === '' || $impact === '') {
            throw new RuntimeException('The Forex Factory calendar contains an incomplete event.');
        }

        try {
            $date = new DateTimeImmutable($record['date']);
        } catch (Throwable $error) {
            throw new RuntimeException('The Forex Factory calendar contains an invalid event date.', 0, $error);
        }

        $optionalText = static function (mixed $value): string {
            return is_string($value) || is_int($value) || is_float($value)
                ? trim((string) $value)
                : '';
        };
        $events[] = [
            'title' => $title,
            'currency' => $currency,
            'date' => $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
            'impact' => $impact,
            'actual' => $optionalText($record['actual'] ?? ''),
            'forecast' => $optionalText($record['forecast'] ?? ''),
            'previous' => $optionalText($record['previous'] ?? ''),
        ];
    }

    usort($events, static fn(array $left, array $right): int => strcmp($left['date'], $right['date']));
    return $events;
}

function readRecords(string $file, string $label): array
{
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the ' . $label . ' data file.');
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Unable to lock the ' . $label . ' data file.');
        }
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $records = json_decode($contents, true);
    if (!is_array($records)) {
        throw new RuntimeException('The ' . $label . ' data file contains invalid JSON.');
    }

    return $records;
}

function writeRecords(array $records, string $file, string $label): void
{
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open the ' . $label . ' data file.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the ' . $label . ' data file.');
        }
        $contents = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($contents === false || !ftruncate($handle, 0) || rewind($handle) === false) {
            throw new RuntimeException('Unable to save the ' . $label . ' data file.');
        }
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to save the ' . $label . ' data file.');
            }
            $offset += $written;
        }
        if (!fflush($handle)) {
            throw new RuntimeException('Unable to flush the ' . $label . ' data file.');
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function readTrades(): array
{
    return readRecords(dataFile(), 'trade journal');
}

function writeTrades(array $trades): void
{
    writeRecords($trades, dataFile(), 'trade journal');
}

function readStrategies(): array
{
    return readRecords(strategiesFile(), 'strategy library');
}

function writeStrategies(array $strategies): void
{
    writeRecords($strategies, strategiesFile(), 'strategy library');
}

function readWeeklyPlans(): array
{
    return readRecords(weeklyPlansFile(), 'weekly analysis');
}

function writeWeeklyPlans(array $plans): void
{
    writeRecords($plans, weeklyPlansFile(), 'weekly analysis');
}

function inputData(): array
{
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        throw new RuntimeException('Unable to read the request body.');
    }
    try {
        $raw = stream_get_contents($stream, 36 * 1024 * 1024 + 1);
    } finally {
        fclose($stream);
    }
    if ($raw === false || strlen($raw) > 36 * 1024 * 1024) {
        respond(413, ['error' => 'Request body is too large.']);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(400, ['error' => 'Request body must be valid JSON.']);
    }
    return $data;
}

function validateScreenshotList(mixed $screenshots, string $owner = 'trade stage'): array
{
    if (!is_array($screenshots) || count($screenshots) > 4) {
        respond(422, ['error' => 'Add no more than 4 screenshots to each ' . $owner . '.']);
    }

    $validated = [];
    foreach ($screenshots as $screenshot) {
        if (!is_array($screenshot)) {
            respond(422, ['error' => 'A screenshot has invalid data.']);
        }

        $name = basename(str_replace('\\', '/', textValue($screenshot, 'name', 160, true)));
        $dataUrl = $screenshot['data'] ?? null;
        if (!is_string($dataUrl) || preg_match('/^data:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/]*={0,2})$/D', $dataUrl, $matches) !== 1) {
            respond(422, ['error' => 'Screenshots must be PNG, JPG, or WebP images.']);
        }
        if (strlen($matches[2]) > 2796204) {
            respond(422, ['error' => 'Each screenshot must be 2 MB or smaller.']);
        }

        $image = base64_decode($matches[2], true);
        if ($image === false || strlen($image) > 2 * 1024 * 1024) {
            respond(422, ['error' => 'Each screenshot must be a valid image no larger than 2 MB.']);
        }

        $mime = $matches[1];
        $isPng = $mime === 'png' && str_starts_with($image, "\x89PNG\r\n\x1a\n");
        $isJpeg = $mime === 'jpeg' && str_starts_with($image, "\xff\xd8\xff");
        $isWebp = $mime === 'webp' && strlen($image) >= 12 && substr($image, 0, 4) === 'RIFF' && substr($image, 8, 4) === 'WEBP';
        if (!$isPng && !$isJpeg && !$isWebp) {
            respond(422, ['error' => 'The uploaded file does not match a supported image format.']);
        }

        $validated[] = ['name' => $name, 'data' => $dataUrl];
    }

    return $validated;
}

function validateScreenshots(array $data, ?array $existing): array
{
    if (!array_key_exists('screenshots', $data)) {
        $previousScreenshots = $existing['screenshots'] ?? [];
        if (array_is_list($previousScreenshots)) {
            return ['before' => $previousScreenshots, 'during' => [], 'after' => []];
        }
        return [
            'before' => $previousScreenshots['before'] ?? [],
            'during' => $previousScreenshots['during'] ?? [],
            'after' => $previousScreenshots['after'] ?? [],
        ];
    }

    $screenshots = $data['screenshots'];
    if (!is_array($screenshots)) {
        respond(422, ['error' => 'Screenshot data must be grouped by trade stage.']);
    }
    if (array_is_list($screenshots)) {
        $screenshots = ['before' => $screenshots];
    }

    $validated = [];
    foreach (['before', 'during', 'after'] as $stage) {
        $validated[$stage] = validateScreenshotList($screenshots[$stage] ?? []);
    }
    foreach ($screenshots as $stage => $_) {
        if (!in_array($stage, ['before', 'during', 'after'], true)) {
            respond(422, ['error' => 'Unknown screenshot stage.']);
        }
    }
    return $validated;
}

function textValue(array $data, string $key, int $maxLength, bool $required = false): string
{
    $value = $data[$key] ?? '';
    if (!is_string($value) && !is_numeric($value)) {
        respond(422, ['error' => 'Invalid ' . str_replace('_', ' ', $key) . '.']);
    }
    $value = trim((string) $value);
    if ($required && $value === '' || mbLength($value) > $maxLength) {
        respond(422, ['error' => 'Check the ' . str_replace('_', ' ', $key) . ' field.']);
    }
    return $value;
}

function mbLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function numberValue(array $data, string $key, bool $required = false, bool $allowZero = false): ?float
{
    $value = $data[$key] ?? null;
    if ($value === null || $value === '') {
        if ($required) {
            respond(422, ['error' => str_replace('_', ' ', $key) . ' is required.']);
        }
        return null;
    }

    if (!is_numeric($value)) {
        respond(422, ['error' => 'Invalid number for ' . str_replace('_', ' ', $key) . '.']);
    }
    $number = (float) $value;
    if (!is_finite($number) || ($allowZero ? $number < 0 : $number <= 0)) {
        respond(422, ['error' => str_replace('_', ' ', $key) . ' must be ' . ($allowZero ? 'zero or greater.' : 'greater than zero.')]);
    }
    return $number;
}

function validDate(array $data, string $key, bool $required = false): ?string
{
    $value = textValue($data, $key, 30, $required);
    if ($value === '') {
        return null;
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        respond(422, ['error' => 'Invalid ' . str_replace('_', ' ', $key) . '.']);
    }
    return $date->format(DateTimeInterface::ATOM);
}

function normalizeTrade(array $data, ?array $existing = null): array
{
    $symbol = strtoupper(textValue($data, 'symbol', 20, true));
    if (preg_match('/^[A-Z0-9][A-Z0-9.\/:_-]*$/', $symbol) !== 1) {
        respond(422, ['error' => 'Use letters, numbers, dots, slashes, colons, underscores, or hyphens for the symbol.']);
    }

    $side = $data['side'] ?? 'long';
    if (!in_array($side, ['long', 'short'], true)) {
        respond(422, ['error' => 'Direction must be long or short.']);
    }
    $status = $data['status'] ?? 'closed';
    if (!in_array($status, ['open', 'closed'], true)) {
        respond(422, ['error' => 'Status must be open or closed.']);
    }

    $markets = ['Stocks', 'Forex', 'Futures', 'Crypto', 'Options', 'Other'];
    $market = $data['market'] ?? 'Stocks';
    if (!is_string($market) || !in_array($market, $markets, true)) {
        respond(422, ['error' => 'Choose a valid market.']);
    }

    $entryDate = validDate($data, 'entry_date', true);
    $exitDate = validDate($data, 'exit_date');

    if ($status === 'closed' && $exitDate === null) {
        respond(422, ['error' => 'An exit date is required for a closed trade.']);
    }
    if ($status === 'open' && $exitDate !== null) {
        respond(422, ['error' => 'Open trades cannot have an exit date.']);
    }
    if ($exitDate !== null && $entryDate !== null && new DateTimeImmutable($exitDate) < new DateTimeImmutable($entryDate)) {
        respond(422, ['error' => 'Exit date cannot be earlier than entry date.']);
    }

    $emotion = textValue($data, 'emotion', 40);
    $followedPlan = $data['followed_plan'] ?? '';
    if (!in_array($followedPlan, ['', 'yes', 'no'], true)) {
        respond(422, ['error' => 'Plan adherence must be yes, no, or blank.']);
    }
    $outcome = $data['outcome'] ?? '';
    if (!is_string($outcome) || !in_array($outcome, ['', 'win', 'loss', 'breakeven'], true)) {
        respond(422, ['error' => 'Choose win, loss, or breakeven for the trade outcome.']);
    }
    if ($status === 'closed' && $outcome === '') {
        respond(422, ['error' => 'Choose an outcome for a closed trade.']);
    }
    if ($status === 'open' && $outcome !== '') {
        respond(422, ['error' => 'Open trades cannot have an outcome yet.']);
    }

    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(12)),
        'symbol' => $symbol,
        'market' => $market,
        'side' => $side,
        'status' => $status,
        'setup' => array_key_exists('setup', $data)
            ? textValue($data, 'setup', 80)
            : ($existing['setup'] ?? ''),
        'entry_date' => $entryDate,
        'exit_date' => $exitDate,
        'emotion' => $emotion,
        'followed_plan' => $followedPlan,
        'outcome' => $outcome,
        'notes' => textValue($data, 'notes', 4000),
        'screenshots' => validateScreenshots($data, $existing),
        'pnl' => null,
        'created_at' => $existing['created_at'] ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'updated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ];
}

function normalizeStrategy(array $data, ?array $existing = null): array
{
    $market = textValue($data, 'market', 20);
    if (!in_array($market, ['', 'Stocks', 'Forex', 'Futures', 'Crypto', 'Options', 'Other'], true)) {
        respond(422, ['error' => 'Choose a valid market.']);
    }

    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(12)),
        'name' => textValue($data, 'name', 80, true),
        'market' => $market,
        'entry_rules' => textValue($data, 'entry_rules', 3000, true),
        'exit_rules' => textValue($data, 'exit_rules', 3000),
        'risk_management' => textValue($data, 'risk_management', 2000),
        'notes' => textValue($data, 'notes', 3000),
        'created_at' => $existing['created_at'] ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'updated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ];
}

function normalizeWeeklyPlan(array $data, ?array $existing = null): array
{
    $pair = strtoupper(textValue($data, 'pair', 20, true));
    if (preg_match('/^[A-Z0-9][A-Z0-9.\/:_-]*$/', $pair) !== 1) {
        respond(422, ['error' => 'Use letters, numbers, dots, slashes, colons, underscores, or hyphens for the pair or instrument.']);
    }

    $weekStartValue = textValue($data, 'week_start', 10, true);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartValue) !== 1) {
        respond(422, ['error' => 'Choose a valid date for the week.']);
    }
    $weekStart = DateTimeImmutable::createFromFormat('!Y-m-d', $weekStartValue);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        $weekStart === false
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $weekStart->format('Y-m-d') !== $weekStartValue
    ) {
        respond(422, ['error' => 'Choose a valid date for the week.']);
    }
    $weekday = (int) $weekStart->format('N');
    $weekStart = $weekStart->modify('-' . ($weekday - 1) . ' days');

    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(12)),
        'pair' => $pair,
        'week_start' => $weekStart->format('Y-m-d'),
        'key_levels' => textValue($data, 'key_levels', 4000, true),
        'weekly_outcome' => textValue($data, 'weekly_outcome', 3000),
        'screenshots' => validateScreenshotList(
            array_key_exists('screenshots', $data) ? $data['screenshots'] : ($existing['screenshots'] ?? []),
            'weekly plan'
        ),
        'created_at' => $existing['created_at'] ?? (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'updated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ];
}

try {
    $rememberMeAvailable = ledgerStartSession();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $resource = $_GET['resource'] ?? 'trades';
    if (!is_string($resource) || !in_array($resource, ['auth', 'trades', 'strategies', 'weekly-analysis', 'economic-calendar'], true)) {
        respond(404, ['error' => 'Unknown API resource.']);
    }

    if ($resource === 'auth') {
        $account = ledgerAuthAccount();
        if ($method === 'GET') {
            respond(200, [
                'authenticated' => ledgerIsAuthenticated($account),
                'signup_available' => true,
            ]);
        }

        if (!in_array($method, ['POST', 'DELETE'], true)) {
            header('Allow: GET, POST, DELETE');
            respond(405, ['error' => 'Method not allowed.']);
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            respond(403, ['error' => 'Your session expired. Refresh the page and try again.']);
        }

        if ($method === 'POST') {
            $data = inputData();
            $action = $data['action'] ?? 'login';
            if (!is_string($action)) {
                respond(422, ['error' => 'Choose a valid account action.']);
            }

            if ($action === 'signup') {
                if ($account !== null && ledgerIsAuthenticated($account)) {
                    respond(409, ['error' => 'Sign out before creating another account.']);
                }
                $username = strtolower(textValue($data, 'username', 32, true));
                if (preg_match('/^[a-z0-9._-]{3,32}$/', $username) !== 1) {
                    respond(422, ['error' => 'Use 3–32 letters, numbers, dots, underscores, or hyphens for the username.']);
                }
                $email = strtolower(textValue($data, 'email', 254, true));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    respond(422, ['error' => 'Enter a valid email address.']);
                }
                $phone = preg_replace('/[\s().-]+/', '', textValue($data, 'phone', 32, true));
                if (!is_string($phone) || preg_match('/^\+[1-9][0-9]{6,14}$/', $phone) !== 1) {
                    respond(422, ['error' => 'Enter a phone number in international format, for example +254712345678.']);
                }
                $password = $data['password'] ?? null;
                if (!is_string($password) || mbLength($password) < 8 || strlen($password) > 72) {
                    respond(422, ['error' => 'Use a password of at least 8 characters and no more than 72 bytes.']);
                }
                $confirmation = $data['password_confirmation'] ?? null;
                if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
                    respond(422, ['error' => 'The passwords do not match.']);
                }
                if (!ledgerCreateAuthAccount($username, $email, $phone, $password)) {
                    respond(409, ['error' => 'That username, email, or phone number is already registered.']);
                }
                $account = ledgerFindAuthAccount('username', $username);
                if ($account === null) {
                    throw new RuntimeException('The new account could not be loaded.');
                }
                ledgerLogin($account);
                respond(201, ['authenticated' => true]);
            }

            if ($action === 'login') {
                $username = strtolower(textValue($data, 'username', 254, true));
                $password = $data['password'] ?? null;
                $rememberMe = $data['remember_me'] ?? null;
                if ($rememberMe !== null && $rememberMe !== '1') {
                    respond(422, ['error' => 'Choose a valid remember-me option.']);
                }
                if ($rememberMe === '1' && !$rememberMeAvailable) {
                    respond(503, ['error' => 'Remember me is unavailable because persistent sessions are not enabled on this server.']);
                }
                if (!is_string($password) || strlen($password) > 72) {
                    respond(422, ['error' => 'Enter a valid password.']);
                }
                $account = ledgerFindAuthAccount('username', $username);
                if ($account === null || !password_verify($password, $account['password_hash'])) {
                    respond(401, ['error' => 'The username or password is incorrect.']);
                }
                ledgerLogin($account, $rememberMe === '1');
                respond(200, ['authenticated' => true]);
            }

            if ($action === 'request_reset') {
                $channel = $data['channel'] ?? null;
                if (!is_string($channel) || !in_array($channel, ['email', 'phone'], true)) {
                    respond(422, ['error' => 'Choose email or phone for code delivery.']);
                }
                $configurationError = ledgerRecoveryConfigurationError($channel);
                if ($configurationError !== null) {
                    respond(503, ['error' => $configurationError]);
                }
                $field = $channel === 'email' ? 'email' : 'phone';
                $contact = strtolower(textValue($data, 'contact', 254, true));
                if ($channel === 'email' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                    respond(422, ['error' => 'Enter a valid email address.']);
                }
                if ($channel === 'phone') {
                    $contact = preg_replace('/[\s().-]+/', '', $contact);
                    if (!is_string($contact) || preg_match('/^\+[1-9][0-9]{6,14}$/', $contact) !== 1) {
                        respond(422, ['error' => 'Enter a phone number in international format.']);
                    }
                }
                $account = ledgerFindAuthAccount($field, $contact);
                if ($account === null) {
                    respond(202, ['success' => true, 'message' => 'If an account matches, a reset code has been sent.']);
                }
                $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
                $codeHash = password_hash($code, PASSWORD_DEFAULT);
                if ($codeHash === false) {
                    throw new RuntimeException('Unable to secure the password reset code.');
                }
                $stored = ledgerStoreResetCode($account['id'], $codeHash, time() + 600);
                if ($stored === 'rate_limited') {
                    respond(429, ['error' => 'Wait one minute before requesting another reset code.']);
                }
                if ($stored !== 'stored') {
                    respond(202, ['success' => true, 'message' => 'If an account matches, a reset code has been sent.']);
                }
                try {
                    ledgerSendRecoveryCode($channel, $contact, $code);
                } catch (RuntimeException $deliveryError) {
                    ledgerClearResetCode($account['id']);
                    respond(502, ['error' => $deliveryError->getMessage()]);
                }
                respond(202, ['success' => true, 'message' => 'If an account matches, a reset code has been sent.']);
            }

            if ($action === 'reset_password') {
                $channel = $data['channel'] ?? null;
                if (!is_string($channel) || !in_array($channel, ['email', 'phone'], true)) {
                    respond(422, ['error' => 'Choose email or phone for code verification.']);
                }
                $field = $channel === 'email' ? 'email' : 'phone';
                $contact = strtolower(textValue($data, 'contact', 254, true));
                if ($channel === 'email' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                    respond(422, ['error' => 'Enter a valid email address.']);
                }
                if ($channel === 'phone') {
                    $contact = preg_replace('/[\s().-]+/', '', $contact);
                    if (!is_string($contact) || preg_match('/^\+[1-9][0-9]{6,14}$/', $contact) !== 1) {
                        respond(422, ['error' => 'Enter a phone number in international format.']);
                    }
                }
                $code = $data['code'] ?? null;
                if (!is_string($code) || preg_match('/^[0-9]{8}$/', $code) !== 1) {
                    respond(422, ['error' => 'Enter the 8-digit reset code.']);
                }
                $password = $data['password'] ?? null;
                if (!is_string($password) || mbLength($password) < 8 || strlen($password) > 72) {
                    respond(422, ['error' => 'Use a password of at least 8 characters and no more than 72 bytes.']);
                }
                $confirmation = $data['password_confirmation'] ?? null;
                if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
                    respond(422, ['error' => 'The passwords do not match.']);
                }
                $account = ledgerFindAuthAccount($field, $contact);
                if ($account === null) {
                    respond(422, ['error' => 'The reset code is invalid or expired. Request a new code.']);
                }
                $result = ledgerCompletePasswordReset(
                    $account['id'],
                    $field,
                    $contact,
                    $code,
                    $password
                );
                if ($result !== 'success') {
                    respond(422, ['error' => $result === 'expired' || $result === 'locked'
                        ? 'The reset code expired or has too many attempts. Request a new code.'
                        : 'The reset code is invalid or expired. Request a new code.']);
                }
                respond(200, ['success' => true]);
            }

            if ($action === 'update_contacts') {
                if (!ledgerIsAuthenticated($account)) {
                    respond(401, ['error' => 'Sign in to update recovery contacts.']);
                }
                $email = strtolower(textValue($data, 'email', 254, true));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    respond(422, ['error' => 'Enter a valid email address.']);
                }
                $phone = preg_replace('/[\s().-]+/', '', textValue($data, 'phone', 32, true));
                if (!is_string($phone) || preg_match('/^\+[1-9][0-9]{6,14}$/', $phone) !== 1) {
                    respond(422, ['error' => 'Enter a phone number in international format, for example +254712345678.']);
                }
                if (!ledgerUpdateAuthContacts($account['id'], $email, $phone)) {
                    respond(409, ['error' => 'That email or phone number is already registered.']);
                }
                respond(200, ['success' => true]);
            }

            respond(422, ['error' => 'Choose a valid account action.']);
        }

        if (!ledgerIsAuthenticated($account)) {
            respond(401, ['error' => 'You are not signed in.']);
        }
        ledgerLogout();
        respond(200, ['success' => true]);
    }

    if (!ledgerIsAuthenticated()) {
        respond(401, ['error' => 'Sign in to continue.']);
    }

    if ($resource === 'economic-calendar' && $method !== 'GET') {
        header('Allow: GET');
        respond(405, ['error' => 'Method not allowed.']);
    }

    if ($method === 'GET') {
        if ($resource === 'economic-calendar') {
            try {
                respond(200, ['events' => fetchEconomicCalendar()]);
            } catch (RuntimeException $error) {
                error_log('Economic calendar API: ' . $error->getMessage());
                respond(502, ['error' => $error->getMessage()]);
            }
        }
        if ($resource === 'strategies') {
            $strategies = readStrategies();
            usort($strategies, static fn(array $a, array $b): int => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
            respond(200, ['strategies' => $strategies]);
        }
        if ($resource === 'weekly-analysis') {
            $plans = readWeeklyPlans();
            usort($plans, static fn(array $a, array $b): int => strcmp($b['week_start'] ?? '', $a['week_start'] ?? '') ?: strcmp($a['pair'] ?? '', $b['pair'] ?? ''));
            respond(200, ['weeklyPlans' => $plans]);
        }
        $trades = readTrades();
        usort($trades, static fn(array $a, array $b): int => strcmp($b['entry_date'] ?? '', $a['entry_date'] ?? ''));
        respond(200, ['trades' => $trades]);
    }

    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        respond(403, ['error' => 'Your session expired. Refresh the page and try again.']);
    }

    $storageFile = $resource === 'strategies'
        ? strategiesFile()
        : ($resource === 'weekly-analysis' ? weeklyPlansFile() : dataFile());
    $transactionLock = fopen($storageFile . '.lock', 'c');
    if ($transactionLock === false || !flock($transactionLock, LOCK_EX)) {
        $label = $resource === 'strategies'
            ? 'strategy library'
            : ($resource === 'weekly-analysis' ? 'weekly analysis' : 'trade journal');
        respond(500, ['error' => 'Unable to lock the ' . $label . ' for updates.']);
    }

    if ($resource === 'strategies') {
        $strategies = readStrategies();
        if ($method === 'POST') {
            $strategy = normalizeStrategy(inputData());
            array_unshift($strategies, $strategy);
            writeStrategies($strategies);
            respond(201, ['strategy' => $strategy]);
        }

        if ($method === 'PUT') {
            $data = inputData();
            $id = $data['id'] ?? '';
            if (!is_string($id) || preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
                respond(400, ['error' => 'A valid strategy ID is required.']);
            }
            foreach ($strategies as $index => $existing) {
                if (($existing['id'] ?? '') === $id) {
                    $strategy = normalizeStrategy($data, $existing);
                    $strategies[$index] = $strategy;
                    writeStrategies($strategies);
                    respond(200, ['strategy' => $strategy]);
                }
            }
            respond(404, ['error' => 'Strategy not found.']);
        }

        if ($method === 'DELETE') {
            $data = inputData();
            $id = $data['id'] ?? '';
            if (!is_string($id) || preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
                respond(400, ['error' => 'A valid strategy ID is required.']);
            }
            $updated = array_values(array_filter($strategies, static fn(array $strategy): bool => ($strategy['id'] ?? '') !== $id));
            if (count($updated) === count($strategies)) {
                respond(404, ['error' => 'Strategy not found.']);
            }
            writeStrategies($updated);
            respond(200, ['success' => true]);
        }

        header('Allow: GET, POST, PUT, DELETE');
        respond(405, ['error' => 'Method not allowed.']);
    }

    if ($resource === 'weekly-analysis') {
        $plans = readWeeklyPlans();
        if ($method === 'POST') {
            $plan = normalizeWeeklyPlan(inputData());
            array_unshift($plans, $plan);
            writeWeeklyPlans($plans);
            respond(201, ['weeklyPlan' => $plan]);
        }

        if ($method === 'PUT') {
            $data = inputData();
            $id = $data['id'] ?? '';
            if (!is_string($id) || preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
                respond(400, ['error' => 'A valid weekly plan ID is required.']);
            }
            foreach ($plans as $index => $existing) {
                if (($existing['id'] ?? '') === $id) {
                    $plan = normalizeWeeklyPlan($data, $existing);
                    $plans[$index] = $plan;
                    writeWeeklyPlans($plans);
                    respond(200, ['weeklyPlan' => $plan]);
                }
            }
            respond(404, ['error' => 'Weekly plan not found.']);
        }

        if ($method === 'DELETE') {
            $data = inputData();
            $id = $data['id'] ?? '';
            if (!is_string($id) || preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
                respond(400, ['error' => 'A valid weekly plan ID is required.']);
            }
            $updated = array_values(array_filter($plans, static fn(array $plan): bool => ($plan['id'] ?? '') !== $id));
            if (count($updated) === count($plans)) {
                respond(404, ['error' => 'Weekly plan not found.']);
            }
            writeWeeklyPlans($updated);
            respond(200, ['success' => true]);
        }

        header('Allow: GET, POST, PUT, DELETE');
        respond(405, ['error' => 'Method not allowed.']);
    }

    $trades = readTrades();
    if ($method === 'POST') {
        $trade = normalizeTrade(inputData());
        array_unshift($trades, $trade);
        writeTrades($trades);
        respond(201, ['trade' => $trade]);
    }

    if ($method === 'PUT') {
        $data = inputData();
        $id = $data['id'] ?? '';
        if (!is_string($id) || preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
            respond(400, ['error' => 'A valid trade ID is required.']);
        }
        foreach ($trades as $index => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $trade = normalizeTrade($data, $existing);
                $trades[$index] = $trade;
                writeTrades($trades);
                respond(200, ['trade' => $trade]);
            }
        }
        respond(404, ['error' => 'Trade not found.']);
    }

    if ($method === 'DELETE') {
        $data = inputData();
        $id = $data['id'] ?? '';
        if (!is_string($id) || preg_match('/^[a-f0-9]{24}$/', $id) !== 1) {
            respond(400, ['error' => 'A valid trade ID is required.']);
        }
        $updated = array_values(array_filter($trades, static fn(array $trade): bool => ($trade['id'] ?? '') !== $id));
        if (count($updated) === count($trades)) {
            respond(404, ['error' => 'Trade not found.']);
        }
        writeTrades($updated);
        respond(200, ['success' => true]);
    }

    header('Allow: GET, POST, PUT, DELETE');
    respond(405, ['error' => 'Method not allowed.']);
} catch (Throwable $error) {
    error_log('Trading journal API: ' . $error->getMessage());
    respond(500, ['error' => 'The journal could not complete this request. Check the server log for details.']);
}
