<?php
declare(strict_types=1);
function language(): string {
    $lang = $_SESSION['language'] ?? setting('language', 'de');
    return in_array($lang, ['en','de','fr'], true) ? $lang : 'de';
}
function dictionary(string $lang): array {
    static $locales;
    $locales ??= json_decode(file_get_contents(__DIR__.'/locales.json'),true,512,JSON_THROW_ON_ERROR);
    return $locales[$lang] ?? $locales['en'];
}
function t(string $key): string { return dictionary(language())[$key] ?? $key; }

