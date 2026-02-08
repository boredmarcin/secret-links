<?php require_once __DIR__ . '/layout.php'; ?>
<?php
$bodyAttrs = 'data-message-id="' . htmlspecialchars($messageId, ENT_QUOTES, 'UTF-8') . '"'
    . ' data-test-mode="' . ($isTestMode ? '1' : '0') . '"'
    . ' data-burn-delay="' . (int)MESSAGE_BURN_DELAY . '"'
    . ' data-max-size="' . (int)MESSAGE_MAX_SIZE . '"'
    . ' data-app-url="' . htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') . '"';

layout_header(
    htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ' - Odczytaj wiadomość',
    [
        'noindex' => true,
        'extraHead' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" integrity="sha384-S3wQ/l0OsbJoFeJC81UIr3JOlx/OzNJpRt1bV+yhpWQxPAahfpQtpxBSfn+Isslc" crossorigin="anonymous"></script>',
        'bodyAttrs' => $bodyAttrs
    ]
);
?>
    <div class="max-w-2xl w-full">
        <div class="text-center mb-10">
            <h1 class="display text-4xl md:text-5xl tracking-wide mb-2"><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p class="mono text-xs uppercase tracking-widest" style="color: #999; letter-spacing: 3px;">Tajna wiadomość</p>
        </div>

        <div id="loadingState" class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-8">
            <div class="flex flex-col items-center justify-center py-8">
                <div class="spinner mb-4"></div>
                <p class="display text-xl mb-1">Ładowanie wiadomości</p>
                <p class="mono text-xs uppercase tracking-wider" style="color: #999;">Deszyfrowanie treści</p>
            </div>
        </div>

        <div id="messageContainer" class="hidden bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-6 md:p-8 message-reveal">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="mono text-xs uppercase tracking-widest mb-1" style="color: #999; letter-spacing: 3px;">Status</p>
                    <h2 class="display text-2xl">Wiadomość odszyfrowana</h2>
                </div>
                <span id="testModeBadge" class="hidden mono text-xs uppercase tracking-wider px-3 py-1 border-2 rounded-sm" style="border-color: #C8FF00; background: rgba(200,255,0,0.1); letter-spacing: 1px;">
                    Tryb testowy
                </span>
            </div>

            <div id="messageContent" class="border-2 rounded-sm p-6 mb-6" style="border-color: rgba(17,17,17,0.1); background: white;">
                <div id="decryptedMessage" class="whitespace-pre-wrap break-words" style="line-height: 1.7;"></div>
            </div>

            <div id="messageWarning" class="hidden">
                <div class="border-2 rounded-sm p-4 mb-4" style="border-color: #FF3B30; background: rgba(255,59,48,0.03);">
                    <div class="flex items-start">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0 mt-0.5" style="color: #FF3B30;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-sm">Ta wiadomość zniknie za <span id="countdown" class="font-bold mono" role="timer" aria-live="polite" style="color: #FF3B30;"><?php echo MESSAGE_BURN_DELAY; ?></span> sekund!</p>
                            <p class="text-sm mt-1" style="color: #999;">Po tym czasie nie będzie można jej już odczytać.</p>
                            <div class="mt-3">
                                <div class="w-full rounded-sm h-1" style="background: rgba(17,17,17,0.08);">
                                    <div id="progressBar" class="h-1 rounded-sm" style="width: 100%; background: #FF3B30;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="testModeInfo" class="hidden">
                <div class="border-2 rounded-sm p-4 mb-4" style="border-color: #C8FF00; background: rgba(200,255,0,0.05);">
                    <div class="flex items-start">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0 mt-0.5" style="color: #111;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-sm">Tryb testowy aktywny</p>
                            <p class="text-sm mt-1" style="color: #999;">Ta wiadomość nie zostanie usunięta po odczycie. Możesz ją odświeżyć, aby zobaczyć ponownie.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-4">
                <button
                    onclick="copyMessage()"
                    class="flex-1 btn-secondary flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Kopiuj treść
                </button>
                <a href="<?php echo layout_base_path(); ?>/" class="flex-1 btn-primary text-center">
                    Utwórz nową wiadomość
                </a>
            </div>
        </div>

        <div id="errorContainer" class="hidden bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-8">
            <div class="text-center py-8">
                <div class="display text-6xl mb-4" style="color: #FF3B30;">!</div>
                <h2 class="display text-2xl mb-2">Nie można odczytać wiadomości</h2>
                <p id="errorMessage" class="text-sm mb-6" style="color: #999;">Wiadomość nie istnieje, została już odczytana lub wygasła.</p>
                <a href="<?php echo layout_base_path(); ?>/" class="btn-primary inline-flex" style="width: auto;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Utwórz nową wiadomość
                </a>
            </div>
        </div>

        <div id="destroyedContainer" class="hidden bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-8">
            <div class="text-center py-8">
                <div class="display text-6xl mb-4" style="color: #C8FF00;">&#10003;</div>
                <h2 class="display text-2xl mb-2">Wiadomość zniszczona</h2>
                <p class="text-sm mb-6" style="color: #999;">Wiadomość została bezpiecznie usunięta i nie można jej już odczytać.</p>
                <a href="<?php echo layout_base_path(); ?>/" class="btn-primary inline-flex" style="width: auto;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Utwórz nową wiadomość
                </a>
            </div>
        </div>
    </div>

<?php layout_footer('<script src="' . layout_base_path() . '/public/js/message.js"></script>'); ?>
