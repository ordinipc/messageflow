<?php
/**
 * LIBRERIA PREVENTIVI - Contabilita Pro
 * Generatore PDF nativo (nessuna dipendenza esterna) + helper invio Email/WhatsApp
 */

if (!defined('ACC_QUOTE_LIB')) {
    define('ACC_QUOTE_LIB', 1);

/* ============================================================
 * GENERATORE PDF MINIMALE (A4, Helvetica, WinAnsi)
 * Coordinate in punti con origine in ALTO A SINISTRA.
 * ============================================================ */
class AccPdf
{
    const PW = 595.28;   // A4 larghezza
    const PH = 841.89;   // A4 altezza

    private $pages = [];
    private $buf = '';
    private $open = false;

    private static $wHelv = [
        32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,
        44=>278,45=>333,46=>278,47=>278,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,
        65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,
        77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,
        89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,
        97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,
        108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,
        119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,
    ];
    private static $wBold = [
        32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>238,40=>333,41=>333,42=>389,43=>584,
        44=>278,45=>333,46=>278,47=>278,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,64=>975,
        65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>556,75=>722,76=>611,
        77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,
        89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,96=>333,
        97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,104=>611,105=>278,106=>278,107=>556,
        108=>278,109=>889,110=>611,111=>611,112=>611,113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,
        119=>778,120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584,
    ];

    public function addPage()
    {
        if ($this->open) { $this->pages[] = $this->buf; }
        $this->buf = '';
        $this->open = true;
    }

    public function pageNo() { return count($this->pages) + 1; }

    /* --- conversione testo --- */
    private function conv($s)
    {
        $s = (string)$s;
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
            if ($c !== false) return $c;
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        }
        return preg_replace('/[^\x20-\x7E]/', '', $s);
    }

    private function esc($s)
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }

    /* --- misure --- */
    public function textWidth($txt, $size, $bold = false)
    {
        $t = $this->conv($txt);
        $tab = $bold ? self::$wBold : self::$wHelv;
        $w = 0;
        $len = strlen($t);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($t[$i]);
            $w += isset($tab[$c]) ? $tab[$c] : 556;
        }
        return $w * $size / 1000;
    }

    /** Spezza il testo su piu righe entro maxW */
    public function wrap($txt, $maxW, $size, $bold = false)
    {
        $out = [];
        $paragraphs = preg_split('/\r\n|\r|\n/', (string)$txt);
        foreach ($paragraphs as $par) {
            $words = preg_split('/\s+/', trim($par));
            $line = '';
            foreach ($words as $w) {
                if ($w === '') continue;
                $try = ($line === '') ? $w : $line . ' ' . $w;
                if ($this->textWidth($try, $size, $bold) <= $maxW) {
                    $line = $try;
                } else {
                    if ($line !== '') $out[] = $line;
                    // parola singola troppo lunga: taglio forzato
                    while ($this->textWidth($w, $size, $bold) > $maxW && strlen($w) > 1) {
                        $cut = strlen($w);
                        while ($cut > 1 && $this->textWidth(substr($w, 0, $cut), $size, $bold) > $maxW) $cut--;
                        $out[] = substr($w, 0, $cut);
                        $w = substr($w, $cut);
                    }
                    $line = $w;
                }
            }
            $out[] = $line;
        }
        if (empty($out)) $out[] = '';
        return $out;
    }

    /* --- primitive di disegno --- */
    private function col($rgb)
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    public function rect($x, $y, $w, $h, $rgb)
    {
        $this->buf .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $this->col($rgb), $x, self::PH - $y - $h, $w, $h);
    }

    public function line($x1, $y1, $x2, $y2, $rgb = [226, 232, 240], $width = 0.6)
    {
        $this->buf .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $this->col($rgb), $width, $x1, self::PH - $y1, $x2, self::PH - $y2);
    }

    /** Testo con ancoraggio: L (sinistra), R (destra), C (centro). $y = baseline dall'alto */
    public function text($x, $y, $txt, $size = 10, $bold = false, $rgb = [26, 32, 44], $align = 'L')
    {
        $t = $this->esc($this->conv($txt));
        if ($t === '') return;
        if ($align === 'R') $x -= $this->textWidth($txt, $size, $bold);
        elseif ($align === 'C') $x -= $this->textWidth($txt, $size, $bold) / 2;
        $this->buf .= sprintf("BT %s rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->col($rgb), $bold ? 'F2' : 'F1', $size, $x, self::PH - $y, $t);
    }

    /** Blocco di testo a capo automatico, ritorna la Y finale */
    public function textBlock($x, $y, $txt, $maxW, $size = 9, $bold = false, $rgb = [26, 32, 44], $lh = 1.35)
    {
        foreach ($this->wrap($txt, $maxW, $size, $bold) as $l) {
            $this->text($x, $y, $l, $size, $bold, $rgb);
            $y += $size * $lh;
        }
        return $y;
    }

    /* --- output --- */
    public function output()
    {
        if ($this->open) { $this->pages[] = $this->buf; $this->open = false; }
        if (empty($this->pages)) $this->pages[] = '';

        $total = count($this->pages);
        $objects = [];
        $kids = [];
        $firstPageObj = 5;
        for ($i = 0; $i < $total; $i++) $kids[] = ($firstPageObj + $i * 2) . ' 0 R';

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $total >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        for ($i = 0; $i < $total; $i++) {
            $pObj = $firstPageObj + $i * 2;
            $cObj = $pObj + 1;
            $content = str_replace('{{NB}}', (string)$total, $this->pages[$i]);
            $objects[$pObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . sprintf('%.2F %.2F', self::PW, self::PH) .
                "] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $cObj 0 R >>";
            $objects[$cObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $maxId = max(array_keys($objects));
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$i]) ? $offsets[$i] : 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $pdf;
    }
}

/* ============================================================
 * HELPER
 * ============================================================ */

function accMoney($n)
{
    return '€ ' . number_format((float)$n, 2, ',', '.');
}

function accQuoteStatuses()
{
    return [
        'draft'    => ['label' => 'Bozza',     'badge' => 'gray',   'icon' => '📝'],
        'sent'     => ['label' => 'Inviato',   'badge' => 'blue',   'icon' => '📨'],
        'accepted' => ['label' => 'Accettato', 'badge' => 'green',  'icon' => '✅'],
        'rejected' => ['label' => 'Rifiutato', 'badge' => 'red',    'icon' => '❌'],
        'invoiced' => ['label' => 'Fatturato', 'badge' => 'green',  'icon' => '🧾'],
    ];
}

function accQuoteStatusInfo($status, $validUntil = null)
{
    $all = accQuoteStatuses();
    if ($status === 'sent' && $validUntil && strtotime($validUntil) < strtotime('today')) {
        return ['label' => 'Scaduto', 'badge' => 'orange', 'icon' => '⌛'];
    }
    return isset($all[$status]) ? $all[$status] : $all['draft'];
}

/** URL pubblico (condivisibile) del preventivo */
function accQuotePublicUrl($token)
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/app/accounting/index.php')), '/');
    return $scheme . '://' . $host . $dir . '/preventivo.php?t=' . urlencode($token);
}

/** Numero WhatsApp normalizzato (solo cifre, prefisso 39 se mancante) */
function accWhatsappNumber($phone, $defaultPrefix = '39')
{
    $p = preg_replace('/[^0-9+]/', '', (string)$phone);
    if ($p === '') return '';
    if (strpos($p, '+') === 0) return substr($p, 1);
    if (strpos($p, '00') === 0) return substr($p, 2);
    if (strlen($p) <= 10) return $defaultPrefix . $p;
    return $p;
}

/** Messaggio precompilato per WhatsApp / Email */
function accQuoteMessage($quote, $company, $link, $channel = 'whatsapp')
{
    $azienda = $company['name'] ?: 'la nostra azienda';
    $valido = $quote['valid_until'] ? ' Il preventivo è valido fino al ' . date('d/m/Y', strtotime($quote['valid_until'])) . '.' : '';
    $apertura = $channel === 'whatsapp'
        ? "ti invio il preventivo n. "
        : "in allegato trovi il preventivo n. ";
    $testo = "Ciao " . ($quote['client_name'] ?: '') . ",\n\n"
        . $apertura . $quote['number'] . " del " . date('d/m/Y', strtotime($quote['date'])) . "\n"
        . "Importo totale: " . accMoney($quote['total']) . "." . $valido . "\n\n";
    if ($link) $testo .= "Puoi visualizzarlo, scaricarlo in PDF e accettarlo qui:\n" . $link . "\n\n";
    $testo .= "Restiamo a disposizione per qualsiasi chiarimento.\n\n"
        . "Cordiali saluti,\n" . $azienda;
    if ($channel === 'whatsapp') {
        $testo = str_replace('€ ', '€', $testo);
    }
    return $testo;
}

/**
 * Genera il PDF del preventivo.
 * @return string contenuto binario PDF
 */
function accBuildQuotePdf($quote, $items, $company, $client)
{
    $pdf = new AccPdf();

    $primary = [66, 153, 225];
    $dark    = [26, 32, 44];
    $gray    = [113, 128, 150];
    $lightBg = [247, 250, 252];
    $border  = [226, 232, 240];

    $ml = 40;                 // margine sinistro
    $mr = AccPdf::PW - 40;    // limite destro
    $contentW = $mr - $ml;

    $drawHeader = function ($pdf, $first) use ($quote, $company, $primary, $dark, $gray, $ml, $mr) {
        $pdf->rect(0, 0, AccPdf::PW, 6, $primary);
        $y = 46;
        $pdf->text($ml, $y, $company['name'] ?: 'La Mia Azienda', 15, true, $dark);
        $y += 15;
        $righe = [];
        if (!empty($company['address'])) $righe[] = $company['address'];
        $citta = trim(($company['zip'] ?? '') . ' ' . ($company['city'] ?? '') . ' ' . (($company['province'] ?? '') ? '(' . $company['province'] . ')' : ''));
        if ($citta !== '') $righe[] = $citta;
        if (!empty($company['vat_number'])) $righe[] = 'P.IVA ' . $company['vat_number'];
        elseif (!empty($company['fiscal_code'])) $righe[] = 'C.F. ' . $company['fiscal_code'];
        $contatti = [];
        if (!empty($company['email'])) $contatti[] = $company['email'];
        if (!empty($company['phone'])) $contatti[] = 'Tel. ' . $company['phone'];
        if ($contatti) $righe[] = implode(' - ', $contatti);
        foreach ($righe as $r) { $pdf->text($ml, $y, $r, 8.5, false, $gray); $y += 11; }

        // Titolo a destra
        $pdf->text($mr, 46, 'PREVENTIVO', 22, true, $primary, 'R');
        $pdf->text($mr, 64, 'N. ' . $quote['number'], 11, true, $dark, 'R');
        $pdf->text($mr, 78, 'Data: ' . date('d/m/Y', strtotime($quote['date'])), 9, false, $gray, 'R');
        if ($quote['valid_until']) {
            $pdf->text($mr, 91, 'Valido fino al: ' . date('d/m/Y', strtotime($quote['valid_until'])), 9, false, $gray, 'R');
        }
        return max($y, 105);
    };

    $drawFooter = function ($pdf) use ($company, $gray, $border, $ml, $mr) {
        $yf = AccPdf::PH - 48;
        $pdf->line($ml, $yf, $mr, $yf, $border, 0.6);
        $parti = [];
        if (!empty($company['name'])) $parti[] = $company['name'];
        if (!empty($company['vat_number'])) $parti[] = 'P.IVA ' . $company['vat_number'];
        if (!empty($company['pec'])) $parti[] = 'PEC ' . $company['pec'];
        $pdf->text($ml, $yf + 14, implode(' - ', $parti), 7.5, false, $gray);
        $pdf->text($mr, $yf + 14, 'Pagina ' . $pdf->pageNo() . ' di {{NB}}', 7.5, false, $gray, 'R');
    };

    $pdf->addPage();
    $y = $drawHeader($pdf, true);
    $drawFooter($pdf);

    // ---- Box destinatario ----
    $boxX = 310;
    $boxW = $mr - $boxX;
    $boxY = $y + 14;
    $righeCli = [];
    if (!empty($client['address'])) $righeCli[] = $client['address'];
    $cittaCli = trim(($client['zip'] ?? '') . ' ' . ($client['city'] ?? '') . ' ' . (($client['province'] ?? '') ? '(' . $client['province'] . ')' : ''));
    if ($cittaCli !== '') $righeCli[] = $cittaCli;
    if (!empty($client['vat_number'])) $righeCli[] = 'P.IVA ' . $client['vat_number'];
    if (!empty($client['fiscal_code']) && empty($client['vat_number'])) $righeCli[] = 'C.F. ' . $client['fiscal_code'];
    if (!empty($client['email'])) $righeCli[] = $client['email'];
    $boxH = 40 + count($righeCli) * 11;
    $pdf->rect($boxX, $boxY, $boxW, $boxH, $lightBg);
    $pdf->rect($boxX, $boxY, 3, $boxH, $primary);
    $ty = $boxY + 16;
    $pdf->text($boxX + 12, $ty, 'SPETT.LE', 7.5, true, $gray);
    $ty += 14;
    $pdf->text($boxX + 12, $ty, $client['name'] ?: 'Cliente', 10.5, true, $dark);
    $ty += 13;
    foreach ($righeCli as $r) { $pdf->text($boxX + 12, $ty, $r, 8.5, false, $gray); $ty += 11; }

    // ---- Oggetto ----
    $y = $boxY;
    if (!empty($quote['subject'])) {
        $pdf->text($ml, $y + 16, 'OGGETTO', 7.5, true, $gray);
        $y = $pdf->textBlock($ml, $y + 30, $quote['subject'], 250, 9.5, false, $dark);
    }
    $y = max($y, $boxY + $boxH) + 26;

    // ---- Tabella articoli ----
    $colDesc = $ml;
    $colQty  = 352;
    $colPrice = 425;
    $colVat  = 470;
    $colTot  = $mr;
    $descW   = 300;

    $tableHeader = function ($pdf, $y) use ($dark, $colDesc, $colQty, $colPrice, $colVat, $colTot, $ml, $mr) {
        $pdf->rect($ml, $y, $mr - $ml, 22, $dark);
        $pdf->text($colDesc + 8, $y + 15, 'DESCRIZIONE', 8, true, [255, 255, 255]);
        $pdf->text($colQty, $y + 15, 'QTA', 8, true, [255, 255, 255], 'R');
        $pdf->text($colPrice, $y + 15, 'PREZZO', 8, true, [255, 255, 255], 'R');
        $pdf->text($colVat, $y + 15, 'IVA', 8, true, [255, 255, 255], 'R');
        $pdf->text($colTot - 8, $y + 15, 'TOTALE', 8, true, [255, 255, 255], 'R');
        return $y + 22;
    };

    $y = $tableHeader($pdf, $y);

    $rowIdx = 0;
    foreach ($items as $it) {
        $lines = $pdf->wrap($it['description'], $descW, 9);
        $rowH = max(24, count($lines) * 12 + 12);

        if ($y + $rowH > AccPdf::PH - 90) {          // nuova pagina
            $pdf->addPage();
            $y = $drawHeader($pdf, false);
            $drawFooter($pdf);
            $y += 14;
            $y = $tableHeader($pdf, $y);
        }

        if ($rowIdx % 2 === 1) $pdf->rect($ml, $y, $mr - $ml, $rowH, $lightBg);
        $ly = $y + 15;
        foreach ($lines as $l) { $pdf->text($colDesc + 8, $ly, $l, 9, false, $dark); $ly += 12; }
        $qta = rtrim(rtrim(number_format((float)$it['quantity'], 2, ',', '.'), '0'), ',');
        $pdf->text($colQty, $y + 15, $qta, 9, false, $dark, 'R');
        $pdf->text($colPrice, $y + 15, number_format((float)$it['unit_price'], 2, ',', '.'), 9, false, $dark, 'R');
        $pdf->text($colVat, $y + 15, rtrim(rtrim(number_format((float)$it['vat_rate'], 2, ',', '.'), '0'), ',') . '%', 9, false, $dark, 'R');
        $pdf->text($colTot - 8, $y + 15, number_format((float)$it['total'], 2, ',', '.'), 9, true, $dark, 'R');
        $y += $rowH;
        $pdf->line($ml, $y, $mr, $y, $border, 0.5);
        $rowIdx++;
    }

    // ---- Totali ----
    $y += 18;
    $totBoxX = 330;
    $rows = [];
    $rows[] = ['Imponibile', accMoney($quote['subtotal'])];
    if ((float)$quote['discount_amount'] > 0) {
        $etichetta = 'Sconto' . ((float)$quote['discount_percent'] > 0 ? ' (' . rtrim(rtrim(number_format((float)$quote['discount_percent'], 2, ',', '.'), '0'), ',') . '%)' : '');
        $rows[] = [$etichetta, '- ' . accMoney($quote['discount_amount'])];
    }
    $rows[] = ['IVA', accMoney($quote['vat_amount'])];
    if ((float)$quote['stamp_duty'] > 0) $rows[] = ['Marca da bollo', accMoney($quote['stamp_duty'])];

    $blockH = count($rows) * 16 + 34;
    if ($y + $blockH > AccPdf::PH - 90) {
        $pdf->addPage();
        $y = $drawHeader($pdf, false) + 14;
        $drawFooter($pdf);
    }
    foreach ($rows as $r) {
        $pdf->text($totBoxX, $y + 11, $r[0], 9.5, false, $gray);
        $pdf->text($mr, $y + 11, $r[1], 9.5, false, $dark, 'R');
        $y += 16;
    }
    $y += 4;
    $pdf->rect($totBoxX - 10, $y, $mr - $totBoxX + 10, 30, $primary);
    $pdf->text($totBoxX, $y + 20, 'TOTALE', 11, true, [255, 255, 255]);
    $pdf->text($mr - 10, $y + 20, accMoney($quote['total']), 13, true, [255, 255, 255], 'R');
    $y += 46;

    // ---- Validita / note / condizioni ----
    if ($quote['valid_until']) {
        $pdf->rect($ml, $y, 260, 24, [255, 250, 235]);
        $pdf->text($ml + 10, $y + 16, 'Offerta valida fino al ' . date('d/m/Y', strtotime($quote['valid_until'])), 9, true, [180, 110, 20]);
        $y += 34;
    }

    $sezioni = [];
    if (!empty($quote['payment_terms_text'])) $sezioni[] = ['MODALITA DI PAGAMENTO', $quote['payment_terms_text']];
    if (!empty($quote['notes'])) $sezioni[] = ['NOTE', $quote['notes']];
    if (!empty($quote['terms'])) $sezioni[] = ['CONDIZIONI', $quote['terms']];
    if (!empty($company['bank_iban'])) {
        $sezioni[] = ['COORDINATE BANCARIE', trim(($company['bank_name'] ?? '') . ' - IBAN ' . $company['bank_iban'], ' -')];
    }

    foreach ($sezioni as $s) {
        $need = 20 + count($pdf->wrap($s[1], $contentW, 9)) * 12;
        if ($y + $need > AccPdf::PH - 90) {
            $pdf->addPage();
            $y = $drawHeader($pdf, false) + 14;
            $drawFooter($pdf);
        }
        $pdf->text($ml, $y, $s[0], 7.5, true, $gray);
        $y = $pdf->textBlock($ml, $y + 14, $s[1], $contentW, 9, false, $dark) + 10;
    }

    // ---- Firma per accettazione ----
    if ($y + 70 > AccPdf::PH - 90) {
        $pdf->addPage();
        $y = $drawHeader($pdf, false) + 14;
        $drawFooter($pdf);
    }
    $y += 12;
    $pdf->text($ml, $y, 'PER ACCETTAZIONE', 7.5, true, $gray);
    $y += 40;
    $pdf->line($ml, $y, $ml + 190, $y, $gray, 0.6);
    $pdf->line($ml + 230, $y, $ml + 420, $y, $gray, 0.6);
    $pdf->text($ml, $y + 12, 'Data', 8, false, $gray);
    $pdf->text($ml + 230, $y + 12, 'Timbro e firma del cliente', 8, false, $gray);

    return $pdf->output();
}

/**
 * Invio email (HTML + allegato PDF) senza dipendenze esterne.
 * @return bool
 */
function accSendMailWithPdf($to, $subject, $htmlBody, $textBody, $fromName, $fromEmail, $replyTo = '', $pdfContent = null, $pdfName = 'preventivo.pdf')
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if (!$fromEmail || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $fromEmail = 'noreply@' . $host;
    }
    $fromName = preg_replace('/[\r\n]/', '', $fromName ?: 'Preventivo');
    $subject = preg_replace('/[\r\n]/', '', $subject);

    $boundaryMix = '=_mix_' . md5(uniqid('', true));
    $boundaryAlt = '=_alt_' . md5(uniqid('', true));
    $eol = "\r\n";

    $headers  = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>' . $eol;
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= 'Reply-To: ' . $replyTo . $eol;
    $headers .= 'MIME-Version: 1.0' . $eol;
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundaryMix . '"' . $eol;

    $body  = '--' . $boundaryMix . $eol;
    $body .= 'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"' . $eol . $eol;

    $body .= '--' . $boundaryAlt . $eol;
    $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
    $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
    $body .= chunk_split(base64_encode($textBody)) . $eol;

    $body .= '--' . $boundaryAlt . $eol;
    $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
    $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
    $body .= chunk_split(base64_encode($htmlBody)) . $eol;
    $body .= '--' . $boundaryAlt . '--' . $eol . $eol;

    if ($pdfContent) {
        $body .= '--' . $boundaryMix . $eol;
        $body .= 'Content-Type: application/pdf; name="' . $pdfName . '"' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol;
        $body .= 'Content-Disposition: attachment; filename="' . $pdfName . '"' . $eol . $eol;
        $body .= chunk_split(base64_encode($pdfContent)) . $eol;
    }
    $body .= '--' . $boundaryMix . '--';

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, $body, $headers);
}

/** Corpo HTML dell'email preventivo */
function accQuoteEmailHtml($quote, $company, $link, $messaggio)
{
    $h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $btn = $link ? '<p style="text-align:center;margin:28px 0">
        <a href="' . $h($link) . '" style="background:#4299e1;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block">Visualizza il preventivo online</a></p>' : '';
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f7fafc;font-family:-apple-system,Segoe UI,Arial,sans-serif;color:#1a202c">
<div style="max-width:600px;margin:0 auto;background:#fff">
  <div style="background:#4299e1;padding:24px 30px;color:#fff">
    <div style="font-size:1.3rem;font-weight:700">' . $h($company['name'] ?: 'Preventivo') . '</div>
    <div style="opacity:.9;font-size:.9rem;margin-top:4px">Preventivo n. ' . $h($quote['number']) . '</div>
  </div>
  <div style="padding:30px">
    <div style="white-space:pre-line;line-height:1.6;font-size:.95rem">' . $h($messaggio) . '</div>
    ' . $btn . '
    <table style="width:100%;border-collapse:collapse;margin-top:20px;font-size:.9rem">
      <tr><td style="padding:8px 0;color:#718096">Numero</td><td style="padding:8px 0;text-align:right;font-weight:600">' . $h($quote['number']) . '</td></tr>
      <tr><td style="padding:8px 0;color:#718096">Data</td><td style="padding:8px 0;text-align:right">' . date('d/m/Y', strtotime($quote['date'])) . '</td></tr>' .
      ($quote['valid_until'] ? '<tr><td style="padding:8px 0;color:#718096">Valido fino al</td><td style="padding:8px 0;text-align:right">' . date('d/m/Y', strtotime($quote['valid_until'])) . '</td></tr>' : '') . '
      <tr><td style="padding:12px 0;border-top:2px solid #e2e8f0;font-weight:700">Totale</td><td style="padding:12px 0;border-top:2px solid #e2e8f0;text-align:right;font-weight:700;font-size:1.1rem;color:#4299e1">' . $h(accMoney($quote['total'])) . '</td></tr>
    </table>
  </div>
  <div style="padding:18px 30px;background:#f7fafc;color:#718096;font-size:.75rem;text-align:center">
    ' . $h($company['name']) . ($company['vat_number'] ? ' - P.IVA ' . $h($company['vat_number']) : '') . '
  </div>
</div></body></html>';
}

}
