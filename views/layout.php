<?php
/**
 * Shared layout helpers for views.
 * Usage: require_once __DIR__ . '/layout.php'; then call layout_header() and layout_footer().
 */

function layout_base_path(): string {
    return htmlspecialchars(parse_url(APP_URL, PHP_URL_PATH) ?? '', ENT_QUOTES, 'UTF-8');
}

function layout_header(string $title, array $options = []): void {
    $appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    $basePath = layout_base_path();
    $robots = $options['noindex'] ?? false;
    $extraHead = $options['extraHead'] ?? '';
    $bodyAttrs = $options['bodyAttrs'] ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <?php echo $extraHead; ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/public/css/app.css">
    <?php if ($robots): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
</head>
<body class="min-h-screen flex flex-col items-center p-4 md:p-8"<?php if ($bodyAttrs) echo ' ' . $bodyAttrs; ?>>
    <main class="flex-1 flex items-center justify-center w-full">
<?php
}

function layout_footer(string $extraScripts = ''): void {
?>
    </main>
    <footer class="text-center py-6 w-full">
        <p class="footer-credit"><a href="https://boredmarcin.com" target="_blank" rel="noopener noreferrer">boredmarcin.com <svg class="inline w-3 h-3 ml-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a></p>
    </footer>
    <?php echo $extraScripts; ?>
</body>
</html>
<?php
}
