<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function pt_current_build(): string
{
    try {
        $gitHead = __DIR__ . '/../.git/HEAD';
        if (is_readable($gitHead)) {
            $head = trim((string)file_get_contents($gitHead));
            if (str_starts_with($head, 'ref:')) {
                $ref = trim(substr($head, 4));
                $refFile = __DIR__ . '/../.git/' . $ref;
                if (is_readable($refFile)) {
                    $h = trim((string)file_get_contents($refFile));
                    if (preg_match('/^[0-9a-f]{5,40}$/', $h)) {
                        return 'git-' . $h;
                    }
                }
                $packed = __DIR__ . '/../.git/packed-refs';
                if (is_readable($packed)) {
                    foreach (file($packed, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                        if ($line === '' || $line[0] === '#' || $line[0] === '^') {
                            continue;
                        }
                        $parts = preg_split('/\s+/', $line);
                        if (count($parts) === 2 && $parts[1] === $ref) {
                            return 'git-' . $parts[0];
                        }
                    }
                }
            } elseif (preg_match('/^[0-9a-f]{5,40}$/', $head)) {
                return 'git-' . $head;
            }
        }
    } catch (\Throwable $e) {
    }
    try {
        $max = 0;
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/..', \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            if (strpos($f->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
                $m = $f->getMTime();
                if ($m > $max) {
                    $max = $m;
                }
            }
        }
        if ($max > 0) {
            return 'mtime-' . $max;
        }
    } catch (\Throwable $e) {
    }
    return 'v-' . (defined('PLUGIN_PROTOCOLO_VERSION') ? PLUGIN_PROTOCOLO_VERSION : '1');
}

echo json_encode([
    'build'   => pt_current_build(),
    'version' => defined('PLUGIN_PROTOCOLO_VERSION') ? PLUGIN_PROTOCOLO_VERSION : null,
], JSON_UNESCAPED_UNICODE);
