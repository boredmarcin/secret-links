# Secret Links

Jednorazowe, szyfrowane wiadomości, które znikają po przeczytaniu.

Self-hosted PHP aplikacja do bezpiecznego udostępniania poufnych informacji. Wiadomość jest szyfrowana end-to-end (AES-256-CBC) — klucz deszyfrujący nigdy nie trafia na serwer.

## Jak to działa

1. Wpisujesz wiadomość i klikasz **Wygeneruj bezpieczny link**
2. Aplikacja szyfruje treść i generuje unikalny link z kluczem w `#fragment`
3. Wysyłasz link odbiorcy
4. Odbiorca otwiera link — wiadomość jest deszyfrowana w przeglądarce i natychmiast usuwana z serwera

Klucz szyfrowania jest przekazywany w hash fragment URL (`#`), który **nigdy nie jest wysyłany na serwer** — ani w logach, ani w żądaniach HTTP.

## Bezpieczeństwo

- **AES-256-CBC** z kluczem derywowanym przez PBKDF2 (100 000 iteracji, SHA-256)
- **End-to-end encryption** — serwer przechowuje tylko zaszyfrowane dane
- **Jednorazowy odczyt** — wiadomość jest usuwana natychmiast po odczytaniu
- **Automatyczne wygasanie** — nieodczytane wiadomości są usuwane po ustawionym czasie
- **Burn delay** — countdown przed zniszczeniem daje czas na skopiowanie treści
- **CSRF protection** z tokenami sesji
- **Rate limiting** — ochrona przed spamem
- **CSP headers** — Content Security Policy bez `unsafe-eval`
- **Brak bazy danych** — wiadomości przechowywane w plikach JSON

## Wymagania

- PHP 8.0+
- Rozszerzenia: `openssl`, `json`, `mbstring`
- Apache z `mod_rewrite` lub PHP built-in server
- Uprawnienia zapisu do katalogów `storage/` i root projektu

## Instalacja

### Szybka (instalator webowy)

1. Skopiuj pliki na serwer
2. Otwórz `https://twoja-domena.com/install.php` w przeglądarce
3. Przejdź przez kreator konfiguracji
4. **Usuń `install.php` po zakończeniu!**

### Ręczna

```bash
# Sklonuj repozytorium
git clone https://github.com/twoj-user/secret-links.git
cd secret-links

# Skopiuj i edytuj konfigurację
cp config.example.php config.php
nano config.php

# Utwórz katalogi storage
mkdir -p storage/messages/active storage/messages/expired

# Ustaw uprawnienia
chmod 755 storage/messages/active storage/messages/expired
chmod 644 config.php
```

### PHP Development Server

```bash
php -S localhost:8000 index.php
```

### Apache

Plik `.htaccess` jest dołączony i zawiera:
- Rewrite rules (routing przez `index.php`)
- Security headers (CSP, X-Frame-Options, HSTS)
- Blokowanie dostępu do `storage/`, `src/`, `views/`
- Kompresja gzip i cache statycznych plików

## Konfiguracja

Edytuj `config.php`:

```php
define('APP_NAME', 'Secret Links');          // Nazwa wyświetlana w UI
define('APP_URL', 'https://example.com');    // Pełny URL aplikacji
define('APP_ENV', 'production');             // production | development

define('MESSAGE_DEFAULT_EXPIRY', 86400);     // Domyślne wygasanie (sekundy), 86400 = 24h
define('MESSAGE_MAX_SIZE', 10000);           // Max długość wiadomości (znaki)
define('MESSAGE_BURN_DELAY', 50);            // Countdown przed usunięciem (sekundy)
define('ENABLE_TEST_MODE', false);           // Tryb testowy (wiadomości nie znikają)

define('RATE_LIMIT_REQUESTS', 10);           // Max żądań na okno czasowe
define('RATE_LIMIT_WINDOW', 60);             // Okno rate limit (sekundy)
```

## Struktura projektu

```
secret-links/
├── index.php              # Router — punkt wejścia
├── install.php            # Instalator webowy (usuń po instalacji!)
├── config.php             # Konfiguracja (generowana przez instalator)
├── config.example.php     # Przykładowa konfiguracja
├── .htaccess              # Konfiguracja Apache
├── src/
│   ├── Encryption.php     # AES-256-CBC + PBKDF2
│   ├── FileStorage.php    # Zapis/odczyt plików JSON
│   └── SecretMessage.php  # Logika biznesowa
├── views/
│   ├── layout.php         # Bazowy layout HTML
│   ├── home.php           # Formularz tworzenia wiadomości
│   ├── message.php        # Strona odczytu wiadomości
│   ├── error.php          # Strona błędu
│   └── 404.php            # Nie znaleziono
├── public/
│   ├── css/app.css        # Style
│   └── js/
│       ├── app.js         # Logika formularza
│       └── message.js     # Deszyfrowanie client-side (CryptoJS)
└── storage/
    └── messages/
        ├── active/        # Aktywne wiadomości (JSON)
        └── expired/       # Wygasłe/odczytane wiadomości
```

## API

### `POST /api/create`

Tworzy nową zaszyfrowaną wiadomość.

```json
{
  "content": "Treść wiadomości",
  "test_mode": false,
  "custom_expiry": 86400,
  "csrf_token": "..."
}
```

Odpowiedź:

```json
{
  "success": true,
  "data": {
    "url": "https://example.com/m/abc123#encryption-key",
    "expires_at": "2025-01-02 12:00:00",
    "test_mode": false
  }
}
```

### `GET /api/message/{id}`

Pobiera i usuwa zaszyfrowaną wiadomość (jednorazowy odczyt).

## Technologie

- **Backend:** PHP 8.0+ (bez frameworka, bez bazy danych)
- **Szyfrowanie:** AES-256-CBC, PBKDF2 (100k iteracji), CryptoJS (client-side)
- **Frontend:** Tailwind CSS (CDN), vanilla JavaScript
- **Fonty:** Bebas Neue, Space Mono, Outfit (Google Fonts)
- **Storage:** Pliki JSON na dysku

## Licencja

MIT

## Autor

[boredmarcin.com](https://boredmarcin.com)
