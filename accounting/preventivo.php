<?php
/**
 * PREVENTIVO - Pagina pubblica (link condivisibile via Email / WhatsApp)
 * Accesso tramite token: preventivo.php?t=TOKEN  |  PDF: preventivo.php?t=TOKEN&pdf=1
 * Non richiede login.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/lib-preventivi.php';

$token = trim($_GET['t'] ?? '');

function accPublicError($messaggio)
{
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Preventivo non disponibile</title><style>body{font-family:-apple-system,Segoe UI,Arial,sans-serif;background:#f7fafc;color:#1a202c;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}'
        . '.box{background:#fff;padding:40px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:420px}.i{font-size:3.5rem;margin-bottom:12px}p{color:#718096;margin-top:10px}</style></head>'
        . '<body><div class="box"><div class="i">🔍</div><h2>Preventivo non disponibile</h2><p>' . htmlspecialchars($messaggio) . '</p></div></body></html>';
    exit;
}

if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
    accPublicError('Il link non è valido.');
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    accPublicError('Servizio temporaneamente non disponibile.');
}

$stmt = $pdo->prepare("SELECT q.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
    c.address AS client_address, c.city AS client_city, c.zip AS client_zip, c.province AS client_province,
    c.vat_number AS client_vat, c.fiscal_code AS client_cf
    FROM acc_quotes q LEFT JOIN acc_clients c ON q.client_id = c.id
    WHERE q.public_token = ? LIMIT 1");
$stmt->execute([$token]);
$quote = $stmt->fetch();
if (!$quote) accPublicError('Il preventivo non esiste o è stato rimosso.');

$stmt = $pdo->prepare("SELECT * FROM acc_quote_items WHERE quote_id = ? ORDER BY sort_order, id");
$stmt->execute([$quote['id']]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM acc_companies WHERE customer_id = ? LIMIT 1");
$stmt->execute([$quote['customer_id']]);
$company = $stmt->fetch() ?: ['name' => '', 'address' => '', 'city' => '', 'zip' => '', 'province' => '', 'vat_number' => '', 'fiscal_code' => '', 'email' => '', 'phone' => '', 'pec' => '', 'bank_name' => '', 'bank_iban' => ''];

$client = [
    'name' => $quote['client_name'] ?? '',
    'address' => $quote['client_address'] ?? '',
    'city' => $quote['client_city'] ?? '',
    'zip' => $quote['client_zip'] ?? '',
    'province' => $quote['client_province'] ?? '',
    'vat_number' => $quote['client_vat'] ?? '',
    'fiscal_code' => $quote['client_cf'] ?? '',
    'email' => $quote['client_email'] ?? '',
];

// --- Download PDF ---
if (isset($_GET['pdf'])) {
    $pdfContent = accBuildQuotePdf($quote, $items, $company, $client);
    $pdfName = 'Preventivo-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', $quote['number']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $pdfName . '"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit;
}

// --- Accettazione / rifiuto da parte del cliente ---
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['action'] ?? '';
    $scaduto = $quote['valid_until'] && strtotime($quote['valid_until']) < strtotime('today');
    if (in_array($quote['status'], ['accepted', 'rejected', 'invoiced'], true)) {
        $flash = 'Il preventivo è già stato confermato.';
    } elseif ($scaduto) {
        $flash = 'Il preventivo è scaduto: contatta il fornitore per un aggiornamento.';
    } elseif ($azione === 'accept') {
        $pdo->prepare("UPDATE acc_quotes SET status='accepted', accepted_at=NOW() WHERE id=? AND public_token=?")->execute([$quote['id'], $token]);
        header('Location: ?t=' . urlencode($token) . '&done=accepted');
        exit;
    } elseif ($azione === 'reject') {
        $pdo->prepare("UPDATE acc_quotes SET status='rejected' WHERE id=? AND public_token=?")->execute([$quote['id'], $token]);
        header('Location: ?t=' . urlencode($token) . '&done=rejected');
        exit;
    }
}

$done = $_GET['done'] ?? '';
$info = accQuoteStatusInfo($quote['status'], $quote['valid_until']);
$scaduto = $quote['valid_until'] && strtotime($quote['valid_until']) < strtotime('today');
$deciso = in_array($quote['status'], ['accepted', 'rejected', 'invoiced'], true);
$imponibileNetto = (float)$quote['subtotal'] - (float)$quote['discount_amount'];
$waAzienda = accWhatsappNumber($company['phone'] ?? '');
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Preventivo <?php echo $h($quote['number']); ?><?php echo $company['name'] ? ' - ' . $h($company['name']) : ''; ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--primary:#4299e1;--success:#48bb78;--warning:#ed8936;--danger:#f56565;--dark:#1a202c;--gray:#718096;--light:#f7fafc;--border:#e2e8f0}
body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;background:var(--light);color:var(--dark);line-height:1.5;padding:20px 12px 60px}
.wrap{max-width:820px;margin:0 auto}
.doc{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.07);overflow:hidden}
.doc-head{padding:28px 32px;border-bottom:4px solid var(--primary);display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}
.company-name{font-size:1.25rem;font-weight:800}
.muted{color:var(--gray);font-size:.85rem}
.doc-title{font-size:1.7rem;font-weight:800;color:var(--primary);text-align:right}
.section{padding:24px 32px}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:24px 32px;background:var(--light)}
.label{font-size:.68rem;font-weight:700;color:var(--gray);letter-spacing:.5px;margin-bottom:6px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:.7rem;text-transform:uppercase;color:#fff;background:var(--dark);padding:11px 12px}
td{padding:12px;border-bottom:1px solid var(--border);font-size:.88rem;vertical-align:top}
.num{text-align:right;white-space:nowrap}
.totals{margin-left:auto;max-width:330px;margin-top:18px}
.totals .r{display:flex;justify-content:space-between;padding:6px 0;font-size:.9rem}
.totals .grand{display:flex;justify-content:space-between;padding:14px 16px;margin-top:10px;background:var(--primary);color:#fff;border-radius:10px;font-size:1.15rem;font-weight:800}
.badge{display:inline-block;padding:5px 14px;border-radius:20px;font-size:.75rem;font-weight:700}
.badge-green{background:rgba(72,187,120,.12);color:var(--success)}
.badge-orange{background:rgba(237,137,54,.12);color:var(--warning)}
.badge-red{background:rgba(245,101,101,.12);color:var(--danger)}
.badge-blue{background:rgba(66,153,225,.12);color:var(--primary)}
.badge-gray{background:rgba(113,128,150,.12);color:var(--gray)}
.actions{position:sticky;bottom:0;background:#fff;border-radius:16px;box-shadow:0 -2px 20px rgba(0,0,0,.08);padding:16px;margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
.btn{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border-radius:10px;font-weight:700;font-size:.9rem;text-decoration:none;border:none;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.btn-success{background:var(--success);color:#fff}
.btn-outline{background:#fff;border:2px solid var(--border);color:var(--dark)}
.alert{padding:14px 18px;border-radius:12px;margin-bottom:16px;font-size:.9rem;font-weight:600}
.alert-ok{background:rgba(72,187,120,.12);color:#276749;border:1px solid var(--success)}
.alert-info{background:rgba(66,153,225,.1);color:#2b6cb0;border:1px solid var(--primary)}
.notes p{margin-bottom:8px;font-size:.88rem}
.foot{text-align:center;color:var(--gray);font-size:.75rem;padding:22px}
@media(max-width:640px){.parties{grid-template-columns:1fr}.doc-title{text-align:left}.section,.doc-head,.parties{padding-left:18px;padding-right:18px}}
@media print{body{background:#fff;padding:0}.actions{display:none}.doc{box-shadow:none}}
</style>
</head>
<body>
<div class="wrap">

<?php if($done === 'accepted'): ?>
<div class="alert alert-ok">✅ Grazie! Il preventivo è stato accettato: il fornitore è stato avvisato e ti contatterà a breve.</div>
<?php elseif($done === 'rejected'): ?>
<div class="alert alert-info">Il preventivo è stato rifiutato. Grazie per la risposta.</div>
<?php elseif($flash): ?>
<div class="alert alert-info"><?php echo $h($flash); ?></div>
<?php elseif($scaduto && !$deciso): ?>
<div class="alert alert-info">⌛ Questo preventivo è scaduto il <?php echo date('d/m/Y', strtotime($quote['valid_until'])); ?>. Contatta il fornitore per un aggiornamento.</div>
<?php endif; ?>

<div class="doc">
    <div class="doc-head">
        <div>
            <div class="company-name"><?php echo $h($company['name'] ?: 'Preventivo'); ?></div>
            <div class="muted">
                <?php if($company['address']): ?><?php echo $h($company['address']); ?><br><?php endif; ?>
                <?php echo $h(trim(($company['zip'] ?? '') . ' ' . ($company['city'] ?? ''))); ?><br>
                <?php if($company['vat_number']): ?>P.IVA <?php echo $h($company['vat_number']); ?><br><?php endif; ?>
                <?php if($company['email']): ?><?php echo $h($company['email']); ?><?php endif; ?>
                <?php if($company['phone']): ?> · <?php echo $h($company['phone']); ?><?php endif; ?>
            </div>
        </div>
        <div>
            <div class="doc-title">PREVENTIVO</div>
            <div class="muted" style="text-align:right">
                N. <strong><?php echo $h($quote['number']); ?></strong><br>
                Data: <?php echo date('d/m/Y', strtotime($quote['date'])); ?><br>
                <?php if($quote['valid_until']): ?>Valido fino al: <?php echo date('d/m/Y', strtotime($quote['valid_until'])); ?><br><?php endif; ?>
                <span class="badge badge-<?php echo $info['badge']; ?>" style="margin-top:6px"><?php echo $info['icon'] . ' ' . $info['label']; ?></span>
            </div>
        </div>
    </div>

    <div class="parties">
        <div>
            <div class="label">SPETT.LE</div>
            <strong><?php echo $h($client['name'] ?: '-'); ?></strong>
            <div class="muted">
                <?php if($client['address']): ?><?php echo $h($client['address']); ?><br><?php endif; ?>
                <?php echo $h(trim($client['zip'] . ' ' . $client['city'])); ?><br>
                <?php if($client['vat_number']): ?>P.IVA <?php echo $h($client['vat_number']); ?><?php endif; ?>
            </div>
        </div>
        <?php if($quote['subject']): ?>
        <div>
            <div class="label">OGGETTO</div>
            <div style="font-size:.92rem"><?php echo nl2br($h($quote['subject'])); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Descrizione</th><th class="num">Qtà</th><th class="num">Prezzo</th><th class="num">IVA</th><th class="num">Totale</th></tr></thead>
            <tbody>
            <?php foreach($items as $it): ?>
            <tr>
                <td><?php echo nl2br($h($it['description'])); ?></td>
                <td class="num"><?php echo rtrim(rtrim(number_format($it['quantity'],2,',','.'),'0'),','); ?></td>
                <td class="num">€<?php echo number_format($it['unit_price'],2,',','.'); ?></td>
                <td class="num"><?php echo rtrim(rtrim(number_format($it['vat_rate'],2,',','.'),'0'),','); ?>%</td>
                <td class="num"><strong>€<?php echo number_format($it['total'],2,',','.'); ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="totals">
            <div class="r"><span class="muted">Imponibile</span><span>€<?php echo number_format($quote['subtotal'],2,',','.'); ?></span></div>
            <?php if((float)$quote['discount_amount'] > 0): ?>
            <div class="r" style="color:var(--success)"><span>Sconto <?php echo rtrim(rtrim(number_format($quote['discount_percent'],2,',','.'),'0'),','); ?>%</span><span>- €<?php echo number_format($quote['discount_amount'],2,',','.'); ?></span></div>
            <div class="r"><span class="muted">Imponibile netto</span><span>€<?php echo number_format($imponibileNetto,2,',','.'); ?></span></div>
            <?php endif; ?>
            <div class="r"><span class="muted">IVA</span><span>€<?php echo number_format($quote['vat_amount'],2,',','.'); ?></span></div>
            <div class="grand"><span>TOTALE</span><span>€<?php echo number_format($quote['total'],2,',','.'); ?></span></div>
        </div>
    </div>

    <?php if($quote['payment_terms_text'] || $quote['notes'] || $quote['terms'] || $company['bank_iban']): ?>
    <div class="section notes" style="border-top:1px solid var(--border)">
        <?php if($quote['payment_terms_text']): ?><p><strong>Modalità di pagamento:</strong> <?php echo $h($quote['payment_terms_text']); ?></p><?php endif; ?>
        <?php if($company['bank_iban']): ?><p><strong>IBAN:</strong> <?php echo $h(trim(($company['bank_name'] ?? '') . ' ' . $company['bank_iban'])); ?></p><?php endif; ?>
        <?php if($quote['notes']): ?><p><strong>Note:</strong> <?php echo nl2br($h($quote['notes'])); ?></p><?php endif; ?>
        <?php if($quote['terms']): ?><p class="muted"><strong>Condizioni:</strong> <?php echo nl2br($h($quote['terms'])); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="foot">
        <?php echo $h($company['name']); ?><?php if($company['vat_number']): ?> · P.IVA <?php echo $h($company['vat_number']); ?><?php endif; ?><?php if($company['pec']): ?> · PEC <?php echo $h($company['pec']); ?><?php endif; ?>
    </div>
</div>

<div class="actions">
    <a href="?t=<?php echo urlencode($token); ?>&pdf=1" class="btn btn-primary">📥 Scarica PDF</a>
    <button onclick="window.print()" class="btn btn-outline">🖨️ Stampa</button>
    <?php if($company['email']): ?><a href="mailto:<?php echo $h($company['email']); ?>?subject=<?php echo rawurlencode('Preventivo ' . $quote['number']); ?>" class="btn btn-outline">📧 Scrivici</a><?php endif; ?>
    <?php if($waAzienda): ?><a href="https://wa.me/<?php echo $h($waAzienda); ?>?text=<?php echo rawurlencode('Salve, vi scrivo per il preventivo ' . $quote['number']); ?>" target="_blank" rel="noopener" class="btn btn-outline">💬 WhatsApp</a><?php endif; ?>
    <?php if(!$deciso && !$scaduto): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Confermi di voler accettare il preventivo <?php echo $h($quote['number']); ?>?')">
        <input type="hidden" name="action" value="accept">
        <button class="btn btn-success">✅ Accetta preventivo</button>
    </form>
    <form method="POST" style="display:inline" onsubmit="return confirm('Vuoi rifiutare questo preventivo?')">
        <input type="hidden" name="action" value="reject">
        <button class="btn btn-outline">❌ Rifiuta</button>
    </form>
    <?php endif; ?>
</div>

</div>
</body>
</html>
