<?php

/**
 * Lists every English UI key used in the app, so lang/ms.json and lang/zh.json can be checked and filled.
 *   php scripts/i18n-keys.php            → JSON array of keys
 *   php scripts/i18n-keys.php --missing  → keys missing from lang/ms.json or lang/zh.json (exit 1 if any)
 *
 * Keys: string literals passed to t() in resources/js and to __() / trans_choice() / @lang in app/ and resources/views,
 * plus label values that are translated at render time (title:/label:/body:/what:/status: and *_LABELS maps).
 */
$root = dirname(__DIR__);
$keys = [];
$add = function (string $key) use (&$keys): void {
    $key = stripcslashes($key);
    $looksLikeText = preg_match('/^[\p{L}\p{N}:“+(]/u', $key) && ! preg_match('/=>|route\(|\$\w|^\($/', $key);
    if (trim($key) !== '' && ! str_contains($key, '${') && $looksLikeText) {
        $keys[$key] = true;
    }
};
$files = fn (string $dir, string $pattern) => new RegexIterator(
    new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)), $pattern);

$string = '(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"|`((?:[^`\\\\$]|\\\\.)*)`)';
foreach ($files('resources/js', '/\.tsx?$/') as $file) {
    $path = (string) $file;
    if (preg_match('#/(components/ui|actions|routes|wayfinder)/#', $path)) {
        continue;
    }
    $src = (string) file_get_contents($path);
    preg_match_all('/\bt\(\s*'.$string.'/s', $src, $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        $add($hit[1] !== '' ? $hit[1] : ($hit[2] ?? '').($hit[3] ?? ''));
    }
    preg_match_all('/\b(?:title|label|body|what|status|description)\s*:\s*'.$string.'/s', $src, $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        $value = $hit[1] !== '' ? $hit[1] : ($hit[2] ?? '').($hit[3] ?? '');
        if (preg_match('/^[A-Z+]/u', $value)) {
            $add($value);
        }
    }
    if (preg_match_all('/(?:_LABELS|[A-Za-z]*[Ll]abels)[^=]*=\s*\{(.*?)\};()()/s', $src, $blocks)) {
        foreach (array_merge($blocks[1], $blocks[2], $blocks[3]) as $block) {
            preg_match_all('/:\s*'.$string.'/s', $block, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $add($hit[1] !== '' ? $hit[1] : ($hit[2] ?? '').($hit[3] ?? ''));
            }
        }
    }
}
foreach (['app', 'resources/views'] as $dir) {
    foreach ($files($dir, '/\.php$/') as $file) {
        $src = (string) file_get_contents((string) $file);
        preg_match_all('/(?:__|trans_choice|@lang)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")/s', $src, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $add($hit[1] !== '' ? $hit[1] : ($hit[2] ?? ''));
        }
    }
}
// Notification messages: string literals passed to toClient()/toContractor() and the rule messages in JobFlow::ensure().
foreach (['app/Services/JobNotifier.php', 'app/Services/JobFlow.php'] as $rel) {
    $src = (string) file_get_contents("$root/$rel");
    preg_match_all('/(?:toClient|toContractor|ensure)\((.*?)\);/s', $src, $calls);
    foreach ($calls[1] as $args) {
        preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'/s', $args, $m);
        foreach ($m[1] as $literal) {
            if (preg_match('/\s/', $literal) || preg_match('/^:\w+ (signed|rejected)$/', $literal) || preg_match('/^[A-Z]/', $literal)) {
                $add($literal);
            }
        }
    }
}

// Activity labels (ActivityLog::LABELS), shown in the feeds and the PDF record.
preg_match('/const LABELS = \[(.*?)\];/s', (string) file_get_contents("$root/app/Models/ActivityLog.php"), $block);
preg_match_all("/=> '((?:[^'\\\\]|\\\\.)*)'/", $block[1] ?? '', $labels);
foreach ($labels[1] as $label) {
    $add($label);
}

// Laravel's own emails (verify email, reset password), sent in each user's language.
foreach ([
    'Verify Email Address',
    'Verify your email address',
    'Please click the button below to verify your email address.',
    'If you did not create an account, no further action is required.',
    'Reset Password',
    'Reset your password',
    'You are receiving this email because we received a password reset request for your account.',
    'This password reset link will expire in :count minutes.',
    'If you did not request a password reset, no further action is required.',
    'Whoops!',
    'Hello!',
    'Regards,',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:",
    'All rights reserved.',
] as $framework) {
    $keys[$framework] = true;
}

// Set outside components and translated where shown (alert-error renders t(error)).
foreach (['Failed to fetch QR code', 'Failed to fetch a setup key', 'Failed to fetch recovery codes'] as $computed) {
    $add($computed);
}

$keys = array_keys($keys);
sort($keys);

if (in_array('--missing', $argv, true)) {
    $missing = [];
    foreach (['ms', 'zh'] as $locale) {
        $have = json_decode((string) @file_get_contents("$root/lang/$locale.json"), true) ?: [];
        foreach ($keys as $key) {
            if (! isset($have[$key])) {
                $missing[$locale][] = $key;
            }
        }
    }
    echo json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
    exit($missing === [] ? 0 : 1);
}

echo json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
