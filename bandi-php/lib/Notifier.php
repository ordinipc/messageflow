<?php
require_once __DIR__ . '/Dates.php';

/**
 * Digest dei nuovi bandi: testo, HTML ed invio via mail() o webhook.
 */
class BandiNotifier
{
    public static function digestTesto(array $bandi, $titolo = 'Nuovi bandi per docenti e insegnanti')
    {
        if (!$bandi) {
            return $titolo . "\n\nNessun nuovo bando trovato.";
        }

        $perLivello = ['universita' => [], 'scuola' => [], 'sconosciuto' => []];
        foreach ($bandi as $bando) {
            $chiave = isset($perLivello[$bando['livello']]) ? $bando['livello'] : 'sconosciuto';
            $perLivello[$chiave][] = $bando;
        }

        $sezioni = [];
        foreach ([['Università / AFAM', 'universita'], ['Scuola', 'scuola'], ['Da classificare', 'sconosciuto']] as $gruppo) {
            if (!$perLivello[$gruppo[1]]) {
                continue;
            }
            $righe = array_map([self::class, 'riga'], $perLivello[$gruppo[1]]);
            $sezioni[] = $gruppo[0] . ' (' . count($perLivello[$gruppo[1]]) . ")\n" . implode("\n\n", $righe);
        }

        return $titolo . ' — ' . count($bandi) . " risultati\n\n" . implode("\n\n", $sezioni);
    }

    private static function riga(array $bando)
    {
        $giorni = BandiDates::daysUntil($bando['scadenza']);
        $scadenza = $bando['scadenza']
            ? 'scade il ' . BandiDates::formatta($bando['scadenza']) . ($giorni !== null && $giorni >= 0 ? " (fra {$giorni} gg)" : ' (scaduto)')
            : 'scadenza non rilevata';

        $dettagli = array_filter([$bando['ente'], $bando['regione'], implode(', ', (array) $bando['classiConcorso'])]);

        return '• ' . $bando['titolo'] . "\n"
            . ($dettagli ? '  ' . implode(' · ', $dettagli) . "\n" : '')
            . '  ' . $scadenza . "\n  " . $bando['url'];
    }

    public static function digestHtml(array $bandi, $titolo = 'Nuovi bandi per docenti e insegnanti')
    {
        $e = function ($testo) {
            return htmlspecialchars((string) $testo, ENT_QUOTES, 'UTF-8');
        };

        if (!$bandi) {
            return '<h2>' . $e($titolo) . '</h2><p>Nessun nuovo bando trovato.</p>';
        }

        $voci = '';
        foreach ($bandi as $bando) {
            $giorni = BandiDates::daysUntil($bando['scadenza']);
            $meta = array_filter([$bando['ente'], $bando['regione'], implode(', ', (array) $bando['classiConcorso'])]);
            $scadenza = $bando['scadenza']
                ? 'Scade il ' . BandiDates::formatta($bando['scadenza']) . ($giorni !== null && $giorni >= 0 ? " (fra {$giorni} giorni)" : '')
                : 'Scadenza non rilevata';

            $voci .= '<li style="margin:0 0 16px 0">'
                . '<a href="' . $e($bando['url']) . '" style="font-weight:600;color:#1a4fd6;text-decoration:none">' . $e($bando['titolo']) . '</a>'
                . ($meta ? '<div style="color:#555;font-size:13px">' . $e(implode(' · ', $meta)) . '</div>' : '')
                . '<div style="color:#8a5a00;font-size:13px">' . $e($scadenza) . '</div>'
                . '</li>';
        }

        return '<h2 style="font-family:system-ui,sans-serif">' . $e($titolo) . '</h2>'
            . '<p style="font-family:system-ui,sans-serif;color:#555">' . count($bandi) . ' risultati</p>'
            . '<ul style="font-family:system-ui,sans-serif;padding-left:18px">' . $voci . '</ul>';
    }

    /** Invia il digest con la funzione mail() di PHP, disponibile su quasi tutti gli hosting. */
    public static function inviaEmail(array $bandi, $destinatario, $mittente = null, $titolo = null)
    {
        if (!$destinatario) {
            return ['inviata' => false, 'motivo' => 'destinatario non configurato'];
        }
        if (!function_exists('mail')) {
            return ['inviata' => false, 'motivo' => 'la funzione mail() non è disponibile su questo hosting'];
        }

        $titolo = $titolo ?: 'Bandi docenti: ' . count($bandi) . ' nuovi risultati';
        $confine = 'bandi-' . bin2hex(random_bytes(8));

        $intestazioni = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $confine . '"',
        ];
        if ($mittente) {
            $intestazioni[] = 'From: ' . $mittente;
        }

        $corpo = "--{$confine}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
            . self::digestTesto($bandi, $titolo) . "\r\n"
            . "--{$confine}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
            . self::digestHtml($bandi, $titolo) . "\r\n"
            . "--{$confine}--";

        $esito = @mail($destinatario, '=?UTF-8?B?' . base64_encode($titolo) . '?=', $corpo, implode("\r\n", $intestazioni));

        return $esito
            ? ['inviata' => true, 'destinatario' => $destinatario]
            : ['inviata' => false, 'motivo' => 'mail() ha restituito un errore (controlla la configurazione SMTP dell\'hosting)'];
    }

    /** Invia il digest a un webhook (Slack, Telegram, n8n, Zapier…). */
    public static function inviaWebhook(array $bandi, $url)
    {
        if (!$url) {
            return ['inviata' => false, 'motivo' => 'webhook non configurato'];
        }
        if (!function_exists('curl_init')) {
            return ['inviata' => false, 'motivo' => 'cURL non disponibile'];
        }

        $payload = json_encode([
            'testo' => self::digestTesto($bandi),
            'text' => self::digestTesto($bandi),
            'conteggio' => count($bandi),
            'bandi' => $bandi,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $status >= 200 && $status < 300
            ? ['inviata' => true, 'destinatario' => $url]
            : ['inviata' => false, 'motivo' => 'il webhook ha risposto ' . $status];
    }
}
