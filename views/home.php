<?php require_once __DIR__ . '/layout.php'; ?>
<?php layout_header(
    htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ' - Jednorazowe wiadomości',
    ['extraHead' => '<meta name="description" content="Wysyłaj bezpieczne, jednorazowe wiadomości, które znikają po przeczytaniu">']
); ?>
    <div class="max-w-2xl w-full">
        <div class="text-center mb-10">
            <h1 class="display text-5xl md:text-7xl tracking-wide mb-3"><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p class="text-sm" style="color: #999; font-weight: 300;">Jednorazowe wiadomości, które znikają po przeczytaniu</p>
        </div>

        <div class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm p-6 md:p-8">
            <form id="createForm" class="space-y-6">
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div>
                    <label for="message" class="mono block text-xs uppercase tracking-widest mb-3" style="color: #999; letter-spacing: 3px;">
                        Twoja tajna wiadomość
                    </label>
                    <textarea
                        id="message"
                        name="message"
                        rows="6"
                        class="input-field"
                        placeholder="Wpisz swoją wiadomość tutaj..."
                        maxlength="<?php echo MESSAGE_MAX_SIZE; ?>"
                        required></textarea>
                    <div class="mt-2 flex justify-between items-center">
                        <span class="mono text-xs uppercase tracking-wider" style="color: #999; letter-spacing: 2px;">Szyfrowanie end-to-end</span>
                        <span id="charCount" class="mono text-xs" style="color: #999;">0 / <?php echo MESSAGE_MAX_SIZE; ?></span>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="customExpiry" class="mono block text-xs uppercase tracking-widest mb-3" style="color: #999; letter-spacing: 3px;">
                            Czas wygaśnięcia
                        </label>
                        <select id="customExpiry" name="customExpiry" class="input-field" style="cursor: pointer;">
                            <option value="3600">1 godzina</option>
                            <option value="21600">6 godzin</option>
                            <option value="86400" selected>24 godziny (domyślnie)</option>
                            <option value="259200">3 dni</option>
                            <option value="604800">7 dni</option>
                        </select>
                        <p class="mono text-xs mt-2 uppercase tracking-wider" style="color: #999;">Po tym czasie wiadomość zostanie automatycznie usunięta</p>
                    </div>

                    <?php if (defined('ENABLE_TEST_MODE') && ENABLE_TEST_MODE): ?>
                    <label class="flex items-center cursor-pointer group">
                        <input
                            type="checkbox"
                            id="testMode"
                            name="testMode"
                            class="w-4 h-4 accent-[#2D5BFF]">
                        <span class="ml-3">
                            <span class="font-medium text-sm">Tryb testowy</span>
                            <span class="mono text-xs block uppercase tracking-wider" style="color: #999;">Wiadomość nie zniknie po odczycie</span>
                        </span>
                    </label>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Wygeneruj bezpieczny link
                </button>
            </form>

            <div id="loadingState" class="hidden">
                <div class="flex flex-col items-center justify-center py-12 fade-in">
                    <div class="spinner mb-4"></div>
                    <p class="mono text-xs uppercase tracking-widest" style="color: #999; letter-spacing: 3px;">Szyfrowanie wiadomości...</p>
                </div>
            </div>

            <div id="errorState" class="hidden">
                <div class="alert-error">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" style="color: #FF3B30;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold mb-1" style="color: #FF3B30;">Wystąpił błąd</h3>
                            <p id="errorMessage" class="text-sm" style="color: #666;"></p>
                            <button onclick="resetForm()" class="btn-text mt-3">
                                Spróbuj ponownie
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
            <div class="p-4 rounded-sm" style="background: #111; color: white;">
                <p class="mono text-xs uppercase tracking-widest mb-1" style="opacity: 0.5; letter-spacing: 2px;">Szyfrowanie</p>
                <p class="display text-xl">AES-256-CBC</p>
            </div>
            <div class="p-4 rounded-sm" style="background: #FF3B30; color: white;">
                <p class="mono text-xs uppercase tracking-widest mb-1" style="opacity: 0.6; letter-spacing: 2px;">Odczyt</p>
                <p class="display text-xl">Jednorazowy</p>
            </div>
            <div class="p-4 rounded-sm" style="background: #2D5BFF; color: white;">
                <p class="mono text-xs uppercase tracking-widest mb-1" style="opacity: 0.6; letter-spacing: 2px;">Prywatność</p>
                <p class="display text-xl">Bez logowania</p>
            </div>
        </div>
    </div>

    <div id="successState" class="hidden fixed inset-0 flex items-center justify-center p-4 z-50" style="background: rgba(17,17,17,0.6);" onclick="closeSuccessModal()">
        <div class="bg-[#FFFEF7] border-2 border-[#111] rounded-sm max-w-lg md:max-w-2xl w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="p-6 border-b-2 border-[#111]" style="background: rgba(200,255,0,0.1);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="mono text-xs uppercase tracking-widest mb-1" style="color: #999; letter-spacing: 3px;">Status</p>
                        <h2 class="display text-3xl">Link wygenerowany</h2>
                    </div>
                    <button onclick="closeSuccessModal()" class="p-2 hover:opacity-50 transition-opacity">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div class="border-2 rounded-sm p-4 mb-6" style="border-color: #FF3B30; background: rgba(255,59,48,0.03);">
                    <div class="flex items-start">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0 mt-0.5" style="color: #FF3B30;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p class="mono text-xs uppercase tracking-wider font-bold" style="color: #FF3B30; letter-spacing: 2px;">Ważne</p>
                            <p class="text-sm mt-1" style="color: #666;">Skopiuj link teraz - po zamknięciu tego okna nie będzie można go odzyskać. Wiadomość można odczytać tylko raz.</p>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <p class="mono text-xs uppercase tracking-widest mb-3" style="color: #999; letter-spacing: 3px;">Twój bezpieczny link</p>
                    <div class="border-2 border-[#111] rounded-sm p-3">
                        <div class="flex items-center gap-3">
                            <input
                                type="text"
                                id="generatedLink"
                                readonly
                                class="flex-1 bg-transparent font-mono text-sm focus:outline-none cursor-pointer break-all"
                                style="color: #2D5BFF;"
                                onclick="this.select()">
                            <button
                                id="copyBtn"
                                class="btn-primary whitespace-nowrap"
                                style="width: auto;"
                                onclick="copyToClipboard()">
                                <svg id="copyIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <svg id="checkIcon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span id="copyText">Kopiuj</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border-2 rounded-sm p-4" style="border-color: rgba(17,17,17,0.1);">
                        <p class="mono text-xs uppercase tracking-wider mb-1" style="color: #999; letter-spacing: 2px;">Wygaśnięcie</p>
                        <p id="expiryInfo" class="text-sm font-medium"></p>
                    </div>
                    <div class="border-2 rounded-sm p-4" style="border-color: rgba(17,17,17,0.1);">
                        <p class="mono text-xs uppercase tracking-wider mb-1" style="color: #999; letter-spacing: 2px;">Szyfrowanie</p>
                        <p class="text-sm font-medium">AES-256-CBC</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php layout_footer('<script src="' . layout_base_path() . '/public/js/app.js"></script>'); ?>
