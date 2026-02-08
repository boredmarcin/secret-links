<?php require_once __DIR__ . '/layout.php'; ?>
<?php layout_header('404 - Nie znaleziono strony'); ?>
    <div class="max-w-md w-full text-center">
        <div class="display" style="font-size: 10rem; line-height: 0.85; color: rgba(17,17,17,0.08);">404</div>
        <h1 class="display text-3xl mb-3 -mt-4">Strona nie znaleziona</h1>
        <p class="text-sm mb-8" style="color: #999;">Wiadomość została już odczytana, wygasła lub nie istnieje.</p>
        <a href="<?php echo layout_base_path(); ?>/" class="btn-primary inline-flex" style="width: auto;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Wróć do strony głównej
        </a>
    </div>
<?php layout_footer(); ?>
