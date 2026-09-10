<?php
/**
 * Caricamento delle fonti: sources.json (predefinite) più
 * sources.local.json (le tue: la tua scuola, il tuo ateneo, il tuo USP).
 */
class BandiSources
{
    public static function carica(array $opzioni = [])
    {
        $base = self::leggi($opzioni['file'] ?? __DIR__ . '/../sources.json');
        $locali = self::leggi($opzioni['fileLocale'] ?? __DIR__ . '/../sources.local.json');

        $unite = [];
        foreach ($base['sources'] ?? [] as $fonte) {
            $unite[$fonte['id']] = $fonte;
        }
        foreach ($locali['sources'] ?? [] as $fonte) {
            // Una fonte locale con lo stesso id sostituisce quella predefinita.
            $unite[$fonte['id']] = array_merge($unite[$fonte['id']] ?? [], $fonte);
        }

        $fonti = array_map([self::class, 'valida'], array_values($unite));

        if (($opzioni['soloAttive'] ?? true) !== false) {
            $fonti = array_values(array_filter($fonti, function ($f) {
                return $f['attiva'] !== false;
            }));
        }
        if (!empty($opzioni['ids'])) {
            $volute = array_map('trim', (array) $opzioni['ids']);
            $fonti = array_values(array_filter($fonti, function ($f) use ($volute) {
                return in_array($f['id'], $volute, true);
            }));
        }
        if (!empty($opzioni['livello']) && $opzioni['livello'] !== 'tutti') {
            $fonti = array_values(array_filter($fonti, function ($f) use ($opzioni) {
                return $f['livello'] === $opzioni['livello'] || $f['livello'] === 'misto';
            }));
        }

        return $fonti;
    }

    private static function leggi($file)
    {
        if (!is_file($file)) {
            return [];
        }
        $dati = json_decode((string) file_get_contents($file), true);
        if (!is_array($dati)) {
            throw new RuntimeException('File fonti non valido: ' . basename($file) . ' (' . json_last_error_msg() . ')');
        }
        return $dati;
    }

    private static function valida($fonte)
    {
        if (empty($fonte['id'])) {
            throw new RuntimeException('Fonte senza campo "id"');
        }
        if (empty($fonte['url'])) {
            throw new RuntimeException('Fonte "' . $fonte['id'] . '": campo "url" mancante');
        }
        if (!in_array($fonte['tipo'] ?? '', ['rss', 'html', 'json'], true)) {
            throw new RuntimeException('Fonte "' . $fonte['id'] . '": "tipo" deve essere rss, html o json');
        }
        if (!filter_var($fonte['url'], FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Fonte "' . $fonte['id'] . '": URL non valido');
        }

        return array_merge([
            'nome' => $fonte['id'],
            'livello' => 'misto',
            'attiva' => true,
            'verificata' => false,
        ], $fonte);
    }
}
