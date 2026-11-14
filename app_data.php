<?php
declare(strict_types=1);

function ledgerDataDirectory(): string
{
    $configuredDirectory = getenv('LEDGER_DATA_DIR');
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $applicationDirectory = realpath(__DIR__) ?: __DIR__;
    $isUnderDocumentRoot = $documentRoot !== false
        && str_starts_with(
            strtolower($applicationDirectory . DIRECTORY_SEPARATOR),
            strtolower(rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        );

    $directory = is_string($configuredDirectory) && trim($configuredDirectory) !== ''
        ? rtrim(trim($configuredDirectory), DIRECTORY_SEPARATOR)
        : ($isUnderDocumentRoot
            ? dirname($documentRoot) . DIRECTORY_SEPARATOR . '.ledger-' . substr(hash('sha256', $applicationDirectory), 0, 12)
            : __DIR__ . DIRECTORY_SEPARATOR . 'storage');

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the trade journal data directory.');
    }

    $resolvedDirectory = realpath($directory);
    if ($resolvedDirectory === false) {
        throw new RuntimeException('Unable to resolve the trade journal data directory.');
    }
    if ($documentRoot !== false) {
        $root = strtolower(rtrim($documentRoot, DIRECTORY_SEPARATOR));
        $dataDirectory = strtolower(rtrim($resolvedDirectory, DIRECTORY_SEPARATOR));
        if ($dataDirectory === $root || str_starts_with($dataDirectory . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The trade journal data directory must be outside the web root.');
        }
    }

    return $resolvedDirectory;
}

function ledgerUserDataDirectory(): string
{
    $userId = $_SESSION['auth_user_id'] ?? null;
    if (!is_string($userId)) {
        throw new RuntimeException('An authenticated account is required to access journal data.');
    }
    if ($userId === 'legacy') {
        return ledgerDataDirectory();
    }
    if (preg_match('/^[a-f0-9]{32}$/', $userId) !== 1) {
        throw new RuntimeException('The authenticated account identifier is invalid.');
    }

    $directory = ledgerDataDirectory()
        . DIRECTORY_SEPARATOR . 'users'
        . DIRECTORY_SEPARATOR . $userId;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the account data directory.');
    }

    $resolvedDirectory = realpath($directory);
    if ($resolvedDirectory === false) {
        throw new RuntimeException('Unable to resolve the account data directory.');
    }
    return $resolvedDirectory;
}
