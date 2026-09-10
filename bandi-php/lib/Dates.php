<?php
/**
 * Interpretazione delle date italiane e delle formule di scadenza dei bandi.
 */
class BandiDates
{
    const MESI = [
        'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4, 'maggio' => 5, 'giugno' => 6,
        'luglio' => 7, 'agosto' => 8, 'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12,
        'gen' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mag' => 5, 'giu' => 6,
        'lug' => 7, 'ago' => 8, 'set' => 9, 'sett' => 9, 'ott' => 10, 'nov' => 11, 'dic' => 12,
    ];

    const FORMULE = [
        'scadenza', 'scade', 'scadono', 'entro e non oltre', 'entro le ore', 'entro il',
        'termine di presentazione', 'termine per la presentazione', 'termine ultimo',
        'presentazione delle domande', 'presentazione della domanda', 'domande entro',
    ];

    /** Costruisce una data UTC verificando che esista davvero (niente 31 febbraio). */
    private static function componi($anno, $mese, $giorno, $ora = 0, $minuto = 0)
    {
        if (!$anno || !$mese || !$giorno) {
            return null;
        }
        if ($mese < 1 || $mese > 12 || $giorno < 1 || $giorno > 31) {
            return null;
        }
        if (!checkdate((int) $mese, (int) $giorno, (int) $anno)) {
            return null;
        }
        return sprintf('%04d-%02d-%02dT%02d:%02d:00Z', $anno, $mese, $giorno, $ora, $minuto);
    }

    private static function normalizzaAnno($valore)
    {
        $anno = (int) $valore;
        if ($anno >= 1000) {
            return $anno;
        }
        if ($anno >= 0 && $anno < 100) {
            return 2000 + $anno;
        }
        return null;
    }

    /**
     * Formati riconosciuti: "12 marzo 2026", "12/03/2026", "12-03-26",
     * "2026-03-12", con orario opzionale ("ore 12:00").
     *
     * @return string|null data ISO 8601 in UTC
     */
    public static function parse($input)
    {
        $grezzo = trim((string) $input);
        if ($grezzo === '') {
            return null;
        }

        // Date con fuso orario (feed RSS/Atom): le interpreta direttamente PHP,
        // altrimenti l'offset andrebbe perso e la data slitterebbe di un'ora.
        if (preg_match('/\d{1,2}:\d{2}(?::\d{2})?\s*(?:z|gmt|utc|[+-]\d{2}:?\d{2})\b/i', $grezzo)) {
            $conFuso = date_create($grezzo, new DateTimeZone('UTC'));
            if ($conFuso) {
                return $conFuso->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            }
        }

        $testo = mb_strtolower(preg_replace('/\s+/u', ' ', $grezzo), 'UTF-8');

        $ora = 0;
        $minuto = 0;
        if (preg_match('/(?:ore\s*)?\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u', $testo, $t)) {
            $ora = (int) $t[1];
            $minuto = (int) $t[2];
        }

        if (preg_match('/\b(\d{1,2})\s*(?:°|º)?\s+(?:del\s+mese\s+di\s+)?([a-zà]+)\s+(\d{4})\b/u', $testo, $m)) {
            if (isset(self::MESI[$m[2]])) {
                return self::componi((int) $m[3], self::MESI[$m[2]], (int) $m[1], $ora, $minuto);
            }
        }

        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $testo, $m)) {
            return self::componi((int) $m[1], (int) $m[2], (int) $m[3], $ora, $minuto);
        }

        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/u', $testo, $m)) {
            return self::componi(self::normalizzaAnno($m[3]), (int) $m[2], (int) $m[1], $ora, $minuto);
        }

        $fallback = date_create($grezzo, new DateTimeZone('UTC'));
        return $fallback ? $fallback->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') : null;
    }

    private static function dataNellaFinestra($finestra)
    {
        $modelli = [
            '/\b\d{1,2}\s+[a-zA-Zà]+\s+\d{4}\b/u',
            '/\b\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/u',
            '/\b\d{4}-\d{1,2}-\d{1,2}\b/u',
        ];

        foreach ($modelli as $modello) {
            if (!preg_match($modello, $finestra, $m)) {
                continue;
            }
            // L'orario può precedere la data: "entro le ore 12:00 del 5 maggio 2026".
            $conOra = preg_match('/ore\s*([01]?\d|2[0-3])[:.]([0-5]\d)/u', $finestra, $t)
                ? $m[0] . ' ore ' . $t[1] . ':' . $t[2]
                : $m[0];
            $data = self::parse($conOra);
            if ($data) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Cerca la scadenza in un testo libero: prende la data più vicina a una
     * delle formule tipiche dei bandi, preferendo quelle non ancora passate.
     *
     * @return array{deadline: string|null, evidence: string|null}
     */
    public static function extractDeadline($testo, $riferimento = null)
    {
        $sorgente = preg_replace('/\s+/u', ' ', (string) $testo);
        if (trim($sorgente) === '') {
            return ['deadline' => null, 'evidence' => null];
        }

        $riferimento = $riferimento ? strtotime($riferimento) : time();
        $minuscolo = mb_strtolower($sorgente, 'UTF-8');
        $candidati = [];

        foreach (self::FORMULE as $formula) {
            $da = 0;
            while (($posizione = mb_strpos($minuscolo, $formula, $da, 'UTF-8')) !== false) {
                $da = $posizione + mb_strlen($formula, 'UTF-8');
                $finestra = mb_substr($sorgente, $posizione, 140, 'UTF-8');
                $data = self::dataNellaFinestra($finestra);
                if ($data) {
                    $candidati[] = ['deadline' => $data, 'evidence' => trim($finestra)];
                }
            }
        }

        if (!$candidati) {
            return ['deadline' => null, 'evidence' => null];
        }

        $futuri = array_values(array_filter($candidati, function ($c) use ($riferimento) {
            return strtotime($c['deadline']) >= $riferimento - 86400;
        }));
        $pool = $futuri ?: $candidati;

        usort($pool, function ($a, $b) {
            return strtotime($a['deadline']) <=> strtotime($b['deadline']);
        });

        return $pool[0];
    }

    /** Giorni mancanti alla scadenza (negativi se già passata). */
    public static function daysUntil($data, $riferimento = null)
    {
        if (!$data) {
            return null;
        }
        $target = strtotime($data);
        if ($target === false) {
            return null;
        }
        $ora = $riferimento ? strtotime($riferimento) : time();
        return (int) ceil(($target - $ora) / 86400);
    }

    /** Data in formato italiano per la stampa a video. */
    public static function formatta($data)
    {
        if (!$data) {
            return null;
        }
        $timestamp = strtotime($data);
        return $timestamp === false ? null : date('d/m/Y', $timestamp);
    }
}
