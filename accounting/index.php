<?php
/**
 * CONTABILITA PRO - Gestionale Fatturazione COMPLETO
 * Con: Righe fattura, Upload XML, Download PDF/XML, Export Commercialista
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: /app/login.php?redirect=/app/accounting/');
    exit;
}

$customerId = $_SESSION['customer_id'];
$customerEmail = $_SESSION['customer_email'] ?? '';
$customerName = $_SESSION['customer_name'] ?? 'Utente';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die('Errore connessione database');
}

// Carica settings piattaforma (WhatsApp, Email supporto)
$supportWhatsapp = '';
$supportEmail = 'support@messageflow.it';
try {
    $psStmt = $pdo->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('support_whatsapp', 'support_email')");
    if ($psStmt) {
        while ($psRow = $psStmt->fetch()) {
            if ($psRow['setting_key'] === 'support_whatsapp') $supportWhatsapp = $psRow['setting_value'];
            if ($psRow['setting_key'] === 'support_email') $supportEmail = $psRow['setting_value'];
        }
    }
} catch(Exception $e) {}

// Controlla abbonamento in subscriptions
$subscription = false;
try {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE customer_id = ? AND plan LIKE 'accounting_%' AND status IN ('active', 'trialing', 'canceling') LIMIT 1");
    $stmt->execute([$customerId]);
    $subscription = $stmt->fetch();
} catch (Exception $e) {
    $subscription = false;
}

// Fallback: controlla anche la tabella licenses
if (!$subscription) {
    try {
        $licStmt = $pdo->prepare("SELECT * FROM licenses WHERE (customer_id = ? OR customer_email = ?) AND plan LIKE 'accounting_%' AND status = 'active' LIMIT 1");
        $licStmt->execute([$customerId, $customerEmail]);
        $license = $licStmt->fetch();
        if ($license) {
            $subscription = [
                'id' => $license['id'],
                'customer_id' => $customerId,
                'plan' => $license['plan'],
                'status' => 'active',
                'trial_end' => null,
                'current_period_end' => null,
                'created_at' => $license['created_at'],
            ];
        }
    } catch (Exception $e) {}
}

if (!$subscription) {
    header('Location: /app/dashboard.php?error=no_subscription&msg=accounting');
    exit;
}

$plan = $subscription['plan'];
$isPro = (strpos($plan, '_pro') !== false || strpos($plan, '_annual') !== false);
$isTrialing = ($subscription['status'] === 'trialing');

// Crea tabelle
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        vat_number VARCHAR(20),
        fiscal_code VARCHAR(20),
        address TEXT,
        city VARCHAR(100),
        zip VARCHAR(10),
        province VARCHAR(5),
        sdi_code VARCHAR(7) DEFAULT '0000000',
        pec VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        bank_name VARCHAR(100),
        bank_iban VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        type VARCHAR(20) DEFAULT 'client',
        name VARCHAR(255) NOT NULL,
        vat_number VARCHAR(20),
        fiscal_code VARCHAR(20),
        address TEXT,
        city VARCHAR(100),
        zip VARCHAR(10),
        province VARCHAR(5),
        sdi_code VARCHAR(7),
        pec VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        payment_terms INT DEFAULT 30,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        client_id INT,
        type VARCHAR(20) DEFAULT 'active',
        number VARCHAR(50),
        date DATE,
        due_date DATE,
        subtotal DECIMAL(10,2) DEFAULT 0,
        vat_amount DECIMAL(10,2) DEFAULT 0,
        total DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'draft',
        payment_status VARCHAR(20) DEFAULT 'pending',
        notes TEXT,
        xml_file VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        description TEXT,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(10,2) DEFAULT 0,
        vat_rate DECIMAL(5,2) DEFAULT 22,
        total DECIMAL(10,2) DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        date DATE,
        type VARCHAR(20) DEFAULT 'income',
        category VARCHAR(100),
        description TEXT,
        amount DECIMAL(10,2) DEFAULT 0,
        payment_method VARCHAR(50),
        document_ref VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_deadlines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        invoice_id INT,
        client_id INT,
        type VARCHAR(20) DEFAULT 'receivable',
        description VARCHAR(255),
        amount DECIMAL(10,2) DEFAULT 0,
        due_date DATE,
        status VARCHAR(20) DEFAULT 'pending',
        paid_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Azienda default
$company = false;
try {
    $stmt = $pdo->prepare("SELECT * FROM acc_companies WHERE customer_id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $company = $stmt->fetch();
    if (!$company) {
        $pdo->prepare("INSERT INTO acc_companies (customer_id, name) VALUES (?, 'La Mia Azienda')")->execute([$customerId]);
        $stmt->execute([$customerId]);
        $company = $stmt->fetch();
    }
} catch (Exception $e) {
    $company = ['name' => 'La Mia Azienda', 'vat_number' => '', 'fiscal_code' => '', 'address' => '', 'city' => '', 'zip' => '', 'province' => '', 'sdi_code' => '0000000', 'pec' => '', 'email' => '', 'phone' => '', 'bank_name' => '', 'bank_iban' => ''];
}

// Stats
$revenueMonth = 0;
$toCollect = 0;
$invoicesMonth = 0;
$upcomingDeadlines = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM acc_invoices WHERE customer_id=? AND type='active' AND MONTH(`date`)=MONTH(NOW())");
    $stmt->execute([$customerId]);
    $revenueMonth = $stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM acc_invoices WHERE customer_id=? AND type='active' AND payment_status='pending'");
    $stmt->execute([$customerId]);
    $toCollect = $stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acc_invoices WHERE customer_id=? AND type='active' AND MONTH(`date`)=MONTH(NOW())");
    $stmt->execute([$customerId]);
    $invoicesMonth = $stmt->fetchColumn();
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM acc_deadlines WHERE customer_id=? AND status='pending' AND due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$customerId]);
    $upcomingDeadlines = $stmt->fetchColumn();
} catch (Exception $e) {}

// Nuovi campi contabilità - Regime fiscale e Marca da bollo
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN tax_regime VARCHAR(50) DEFAULT 'ordinario'"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN stamp_duty DECIMAL(10,2) DEFAULT 2.00"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN stamp_duty_threshold DECIMAL(10,2) DEFAULT 77.47"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN default_vat_rate DECIMAL(5,2) DEFAULT 22.00"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN vat_rates TEXT"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN stamp_duty DECIMAL(10,2) DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN vat_rate DECIMAL(5,2) DEFAULT 22"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN xml_file VARCHAR(255)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN due_date DATE"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch(Exception $e) {}
try { $pdo->exec("SET NAMES utf8mb4"); } catch(Exception $e) {}

// Nuovi campi per regime forfettario - ATECO e INPS
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN ateco_code VARCHAR(10)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN ateco_description VARCHAR(255)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN coefficient_percent DECIMAL(5,2)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN inps_management VARCHAR(50)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN tax_rate_percent DECIMAL(5,2) DEFAULT 15"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN startup_discount TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}

// Nuovi campi per regime ordinario/semplificato
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN regione VARCHAR(50) DEFAULT 'lombardia'"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN irap_applicable TINYINT(1) DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN tipo_attivita VARCHAR(50) DEFAULT 'servizi'"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN anno_ingresso_minimi INT DEFAULT 2015"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN cassa_professionale VARCHAR(50)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN bank_name VARCHAR(100)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_companies ADD COLUMN bank_iban VARCHAR(50)"); } catch(Exception $e) {}

// === ALTER TABLE per tutte le tabelle (colonne che potrebbero mancare) ===
// acc_invoices
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN client_id INT"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN vat_amount DECIMAL(10,2) DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN payment_status VARCHAR(20) DEFAULT 'pending'"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_invoices ADD COLUMN notes TEXT"); } catch(Exception $e) {}

// acc_transactions
try { $pdo->exec("ALTER TABLE acc_transactions ADD COLUMN payment_method VARCHAR(50)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_transactions ADD COLUMN document_ref VARCHAR(100)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_transactions ADD COLUMN category VARCHAR(100)"); } catch(Exception $e) {}

// acc_clients
try { $pdo->exec("ALTER TABLE acc_clients ADD COLUMN sdi_code VARCHAR(7)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_clients ADD COLUMN pec VARCHAR(255)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_clients ADD COLUMN email VARCHAR(255)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_clients ADD COLUMN phone VARCHAR(50)"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_clients ADD COLUMN payment_terms INT DEFAULT 30"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_clients ADD COLUMN notes TEXT"); } catch(Exception $e) {}

// acc_deadlines
try { $pdo->exec("ALTER TABLE acc_deadlines ADD COLUMN invoice_id INT"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_deadlines ADD COLUMN client_id INT"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE acc_deadlines ADD COLUMN paid_date DATE"); } catch(Exception $e) {}

// acc_invoice_items
try { $pdo->exec("ALTER TABLE acc_invoice_items ADD COLUMN vat_rate DECIMAL(5,2) DEFAULT 22"); } catch(Exception $e) {}

// Tabella pagamenti fiscali F24
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_fiscal_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        fiscal_year INT NOT NULL,
        deadline_key VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) DEFAULT 0,
        paid_date DATE,
        notes VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_deadline (customer_id, fiscal_year, deadline_key)
    )");
} catch(Exception $e) {}

// ==================== PREVENTIVI ====================
require_once __DIR__ . '/lib-preventivi.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_quotes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        client_id INT,
        number VARCHAR(50),
        date DATE,
        valid_until DATE,
        subject VARCHAR(255),
        subtotal DECIMAL(10,2) DEFAULT 0,
        discount_percent DECIMAL(5,2) DEFAULT 0,
        discount_amount DECIMAL(10,2) DEFAULT 0,
        vat_amount DECIMAL(10,2) DEFAULT 0,
        stamp_duty DECIMAL(10,2) DEFAULT 0,
        total DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'draft',
        notes TEXT,
        terms TEXT,
        payment_terms_text VARCHAR(255),
        public_token VARCHAR(64),
        invoice_id INT,
        sent_at DATETIME NULL,
        sent_channel VARCHAR(20),
        accepted_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_quote_customer (customer_id),
        INDEX idx_quote_token (public_token)
    ) DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS acc_quote_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quote_id INT NOT NULL,
        description TEXT,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(10,2) DEFAULT 0,
        vat_rate DECIMAL(5,2) DEFAULT 22,
        total DECIMAL(10,2) DEFAULT 0,
        sort_order INT DEFAULT 0,
        INDEX idx_qitem_quote (quote_id)
    ) DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ALTER difensivi (installazioni precedenti)
foreach ([
    "ALTER TABLE acc_quotes ADD COLUMN subject VARCHAR(255)",
    "ALTER TABLE acc_quotes ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0",
    "ALTER TABLE acc_quotes ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE acc_quotes ADD COLUMN stamp_duty DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE acc_quotes ADD COLUMN terms TEXT",
    "ALTER TABLE acc_quotes ADD COLUMN payment_terms_text VARCHAR(255)",
    "ALTER TABLE acc_quotes ADD COLUMN public_token VARCHAR(64)",
    "ALTER TABLE acc_quotes ADD COLUMN invoice_id INT",
    "ALTER TABLE acc_quotes ADD COLUMN sent_at DATETIME NULL",
    "ALTER TABLE acc_quotes ADD COLUMN sent_channel VARCHAR(20)",
    "ALTER TABLE acc_quotes ADD COLUMN accepted_at DATETIME NULL",
    "ALTER TABLE acc_quote_items ADD COLUMN sort_order INT DEFAULT 0",
] as $sqlAlter) {
    try { $pdo->exec($sqlAlter); } catch (Exception $e) {}
}

// Statistiche preventivi
$quotesPending = 0;      // inviati e ancora in attesa di risposta
$quotesPendingValue = 0;
$quotesAcceptedYear = 0; // valore accettato nell'anno
try {
    $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total),0) FROM acc_quotes WHERE customer_id=? AND status='sent'");
    $stmt->execute([$customerId]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $quotesPending = (int)$row[0];
    $quotesPendingValue = (float)$row[1];
} catch (Exception $e) {}
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM acc_quotes WHERE customer_id=? AND status IN ('accepted','invoiced') AND YEAR(`date`)=YEAR(NOW())");
    $stmt->execute([$customerId]);
    $quotesAcceptedYear = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

/** Carica un preventivo dell'utente corrente con righe, cliente e azienda */
function accLoadQuote($pdo, $customerId, $quoteId)
{
    $stmt = $pdo->prepare("SELECT q.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
        c.address AS client_address, c.city AS client_city, c.zip AS client_zip, c.province AS client_province,
        c.vat_number AS client_vat, c.fiscal_code AS client_cf, c.sdi_code AS client_sdi, c.pec AS client_pec
        FROM acc_quotes q LEFT JOIN acc_clients c ON q.client_id = c.id
        WHERE q.id = ? AND q.customer_id = ? LIMIT 1");
    $stmt->execute([$quoteId, $customerId]);
    $quote = $stmt->fetch();
    if (!$quote) return null;
    $stmt = $pdo->prepare("SELECT * FROM acc_quote_items WHERE quote_id = ? ORDER BY sort_order, id");
    $stmt->execute([$quoteId]);
    $quote['items'] = $stmt->fetchAll();
    return $quote;
}

/** Dati cliente nel formato atteso dal generatore PDF */
function accQuoteClientData($quote)
{
    return [
        'name' => $quote['client_name'] ?? '',
        'address' => $quote['client_address'] ?? '',
        'city' => $quote['client_city'] ?? '',
        'zip' => $quote['client_zip'] ?? '',
        'province' => $quote['client_province'] ?? '',
        'vat_number' => $quote['client_vat'] ?? '',
        'fiscal_code' => $quote['client_cf'] ?? '',
        'email' => $quote['client_email'] ?? '',
    ];
}

/** Calcola i totali a partire dalle righe + sconto percentuale */
function accQuoteTotals($items, $discountPercent = 0)
{
    $subtotal = 0;
    $vat = 0;
    $discountPercent = max(0, min(100, (float)$discountPercent));
    foreach ($items as $it) {
        $rowTotal = (float)$it['qty'] * (float)$it['price'];
        $rowNet = $rowTotal * (1 - $discountPercent / 100);
        $subtotal += $rowTotal;
        $vat += $rowNet * (float)($it['vat'] ?? 22) / 100;
    }
    $discountAmount = $subtotal * $discountPercent / 100;
    return [
        'subtotal' => round($subtotal, 2),
        'discount_amount' => round($discountAmount, 2),
        'vat_amount' => round($vat, 2),
        'total' => round($subtotal - $discountAmount + $vat, 2),
    ];
}


// Regimi fiscali disponibili
$taxRegimes = [
    'ordinario' => ['name' => 'Regime Ordinario', 'desc' => 'IVA standard con liquidazione periodica', 'icon' => '🏢'],
    'forfettario' => ['name' => 'Regime Forfettario', 'desc' => 'Imposta sostitutiva 15% (o 5% primi 5 anni)', 'icon' => '📋'],
    'semplificato' => ['name' => 'Regime Semplificato', 'desc' => 'Contabilità semplificata per piccole imprese', 'icon' => '📝'],
    'minimo' => ['name' => 'Regime dei Minimi', 'desc' => 'Regime fiscale agevolato (cessato, in esaurimento)', 'icon' => '📌'],
];

// Gestioni INPS disponibili
$inpsManagements = [
    'separata' => ['name' => 'Gestione Separata INPS', 'rate' => 26.07, 'desc' => 'Per professionisti senza cassa (no minimali)', 'icon' => '👤'],
    'commercianti' => ['name' => 'INPS Commercianti', 'rate' => 24.48, 'desc' => 'Per attività commerciali (con minimali ~4.400€/anno)', 'icon' => '🏪'],
    'artigiani' => ['name' => 'INPS Artigiani', 'rate' => 24.00, 'desc' => 'Per attività artigianali (con minimali ~4.200€/anno)', 'icon' => '🔧'],
    'cassa' => ['name' => 'Cassa Professionale', 'rate' => 0, 'desc' => 'Avvocati, Commercialisti, Ingegneri, ecc.', 'icon' => '🎓'],
    'nessuna' => ['name' => 'Nessuna/Altra', 'rate' => 0, 'desc' => 'Gestione diversa o non applicabile', 'icon' => '➖'],
];

// Casse Professionali specifiche con aliquote 2024
$casseProfessionali = [
    'inarcassa' => ['name' => 'INARCASSA (Ingegneri e Architetti)', 'sogg' => 14.50, 'integ' => 4.00, 'matern' => 0.22],
    'cassa_forense' => ['name' => 'Cassa Forense (Avvocati)', 'sogg' => 15.00, 'integ' => 4.00, 'matern' => 0.25],
    'cnpadc' => ['name' => 'CNPADC (Commercialisti)', 'sogg' => 12.00, 'integ' => 4.00, 'matern' => 0.22],
    'enpacl' => ['name' => 'ENPACL (Consulenti Lavoro)', 'sogg' => 12.00, 'integ' => 4.00, 'matern' => 0.22],
    'cassa_geometri' => ['name' => 'Cassa Geometri (CIPAG)', 'sogg' => 18.50, 'integ' => 5.00, 'matern' => 0.22],
    'enpam' => ['name' => 'ENPAM (Medici e Odontoiatri)', 'sogg' => 12.50, 'integ' => 2.00, 'matern' => 0.00],
    'enpav' => ['name' => 'ENPAV (Veterinari)', 'sogg' => 14.00, 'integ' => 2.00, 'matern' => 0.50],
    'enpapi' => ['name' => 'ENPAPI (Infermieri)', 'sogg' => 16.00, 'integ' => 4.00, 'matern' => 0.00],
    'inpgi' => ['name' => 'INPGI (Giornalisti)', 'sogg' => 12.00, 'integ' => 4.00, 'matern' => 0.00],
    'enpaf' => ['name' => 'ENPAF (Farmacisti)', 'sogg' => 8.50, 'integ' => 0.00, 'matern' => 0.00],
    'cassa_notariato' => ['name' => 'Cassa Notariato', 'sogg' => 26.00, 'integ' => 0.00, 'matern' => 0.00],
    'enpab' => ['name' => 'ENPAB (Biologi)', 'sogg' => 15.00, 'integ' => 4.00, 'matern' => 0.50],
    'enpap' => ['name' => 'ENPAP (Psicologi)', 'sogg' => 10.00, 'integ' => 2.00, 'matern' => 0.50],
    'eppi' => ['name' => 'EPPI (Periti Industriali)', 'sogg' => 18.00, 'integ' => 5.00, 'matern' => 0.22],
    'epap' => ['name' => 'EPAP (Agronomi, Forestali, Attuari, Chimici, Geologi)', 'sogg' => 12.00, 'integ' => 2.00, 'matern' => 0.50],
];

// Limiti Regime Forfettario 2024
$limitiForfettario = [
    'ricavi_max' => 85000,           // Limite massimo ricavi/compensi
    'ricavi_uscita' => 100000,       // Sopra questo esce immediatamente
    'lavoro_dipendente' => 30000,    // Limite reddito lavoro dipendente anno precedente
    'beni_strumentali' => 20000,     // Limite costo beni strumentali
    'collaboratori' => 20000,        // Limite spese collaboratori/dipendenti
];

// Limiti Regime Semplificato 2024
$limitiSemplificato = [
    'servizi' => 500000,             // Limite per prestazioni di servizi
    'commercio' => 800000,           // Limite per altre attività
];

// Scaglioni IRPEF 2024 per regime ordinario/semplificato
$irpefScaglioni = [
    ['min' => 0, 'max' => 28000, 'rate' => 23, 'desc' => 'Fino a €28.000'],
    ['min' => 28000, 'max' => 50000, 'rate' => 35, 'desc' => 'Da €28.000 a €50.000'],
    ['min' => 50000, 'max' => PHP_INT_MAX, 'rate' => 43, 'desc' => 'Oltre €50.000'],
];

// Addizionali regionali medie per regione
$addizionaliRegionali = [
    'lombardia' => 1.23, 'piemonte' => 1.62, 'veneto' => 1.23, 'emilia_romagna' => 1.33,
    'toscana' => 1.42, 'lazio' => 1.73, 'campania' => 2.03, 'puglia' => 1.33,
    'sicilia' => 1.23, 'sardegna' => 1.23, 'calabria' => 1.73, 'liguria' => 1.23,
    'marche' => 1.23, 'abruzzo' => 1.73, 'friuli' => 0.70, 'trentino' => 1.23,
    'umbria' => 1.23, 'basilicata' => 1.23, 'molise' => 1.73, 'valle_aosta' => 1.23,
    'default' => 1.40
];

// Aliquote IRAP per settore (media)
$irapAliquote = [
    'ordinario' => 3.90,
    'agricoltura' => 1.90,
    'banche' => 4.65,
    'assicurazioni' => 5.90,
    'amministrazioni' => 8.50,
];

// Codici ATECO COMPLETI con coefficienti di redditività 2024
// Fonte: Allegato 4 Legge 190/2014 e successive modifiche
$atecoGroups = [
    '40' => [
        'name' => '40% - Commercio, Alloggio e Ristorazione',
        'codes' => [
            // COMMERCIO AL DETTAGLIO - Alimentari
            '47.11' => 'Ipermercati, supermercati, minimarket',
            '47.19' => 'Commercio al dettaglio in altri esercizi non specializzati',
            '47.21' => 'Frutta e verdura',
            '47.22' => 'Carni e prodotti a base di carne',
            '47.23' => 'Pesci, crostacei e molluschi',
            '47.24' => 'Pane, pasticceria e dolciumi',
            '47.25' => 'Bevande (enoteche, wine bar)',
            '47.26' => 'Tabaccherie e rivendite generi di monopolio',
            '47.29' => 'Altri prodotti alimentari in esercizi specializzati',
            // COMMERCIO AL DETTAGLIO - Non Alimentari
            '47.30' => 'Carburante per autotrazione (distributori)',
            '47.41' => 'Computer, periferiche e software',
            '47.42' => 'Telefonia e telecomunicazioni',
            '47.43' => 'Apparecchiature audio/video (hi-fi, TV)',
            '47.51' => 'Tessuti per abbigliamento e arredamento',
            '47.52' => 'Ferramenta, vernici, vetro piano',
            '47.53' => 'Tappeti, tende, tappezzerie',
            '47.54' => 'Elettrodomestici',
            '47.59' => 'Mobili, illuminazione, casalinghi',
            '47.61' => 'Librerie',
            '47.62' => 'Giornali, cartoleria, articoli da regalo',
            '47.63' => 'CD, DVD, dischi, videogiochi',
            '47.64' => 'Articoli sportivi, biciclette, camping',
            '47.65' => 'Giocattoli e giochi',
            '47.71' => 'Abbigliamento (boutique, negozi moda)',
            '47.72' => 'Calzature, pelletteria, valigeria',
            '47.73' => 'Farmacie',
            '47.74' => 'Articoli medicali e ortopedici',
            '47.75' => 'Profumerie e cosmetici',
            '47.76' => 'Fiori, piante, semi, fertilizzanti',
            '47.77' => 'Orologi e gioielleria',
            '47.78.1' => 'Ottica e fotografia',
            '47.78.2' => 'Oggettistica, bigiotteria, articoli regalo',
            '47.78.3' => 'Combustibili uso domestico',
            '47.78.4' => 'Armi e munizioni',
            '47.78.5' => 'Saponi, detersivi, casalinghi',
            '47.78.9' => 'Altri prodotti nca',
            '47.79' => 'Articoli di seconda mano (vintage, usato)',
            '47.91' => 'E-commerce (vendita online, per corrispondenza)',
            '47.99' => 'Altro commercio al dettaglio ambulante',
            // COMMERCIO ALL\'INGROSSO
            '46.21' => 'Cereali, tabacco grezzo, sementi, mangimi',
            '46.22' => 'Fiori e piante',
            '46.23' => 'Animali vivi',
            '46.24' => 'Pelli, cuoio e pellicce',
            '46.31' => 'Frutta e ortaggi freschi',
            '46.32' => 'Carne e salumi',
            '46.33' => 'Latticini, uova, oli alimentari',
            '46.34' => 'Bevande (vino, birra, liquori)',
            '46.35' => 'Tabacco',
            '46.36' => 'Zucchero, cioccolato, dolciumi',
            '46.37' => 'Caffè, tè, cacao, spezie',
            '46.38' => 'Pesce, crostacei, molluschi',
            '46.39' => 'Alimentari non specializzato',
            '46.41' => 'Tessuti',
            '46.42' => 'Abbigliamento e calzature',
            '46.43' => 'Elettrodomestici, radio, TV',
            '46.44' => 'Porcellane, cristalleria',
            '46.45' => 'Profumi e cosmetici',
            '46.46' => 'Prodotti farmaceutici',
            '46.47' => 'Mobili, tappeti, illuminazione',
            '46.48' => 'Orologi e gioielleria',
            '46.49' => 'Altri beni di consumo',
            '46.51' => 'Computer e software',
            '46.52' => 'Componenti elettronici',
            '46.61' => 'Macchine e attrezzature agricole',
            '46.62' => 'Macchine utensili',
            '46.63' => 'Macchine per miniere e costruzioni',
            '46.64' => 'Macchine industria tessile',
            '46.65' => 'Mobili e attrezzature per ufficio',
            '46.66' => 'Altre macchine per ufficio',
            '46.69' => 'Altre macchine e attrezzature',
            '46.71' => 'Combustibili, petrolio, lubrificanti',
            '46.72' => 'Metalli, minerali metalliferi',
            '46.73' => 'Legname, materiali da costruzione',
            '46.74' => 'Ferramenta, idraulica, riscaldamento',
            '46.75' => 'Prodotti chimici',
            '46.76' => 'Altri prodotti intermedi',
            '46.77' => 'Rottami e materiali di recupero',
            '46.90' => 'Commercio ingrosso non specializzato',
            // AUTOVEICOLI E MOTOCICLI
            '45.11' => 'Vendita autovetture e autoveicoli leggeri',
            '45.19' => 'Vendita altri autoveicoli (camion, bus)',
            '45.20' => 'Manutenzione e riparazione auto (officine)',
            '45.31' => 'Ricambi auto ingrosso',
            '45.32' => 'Ricambi auto dettaglio',
            '45.40' => 'Moto: vendita, manutenzione, ricambi',
            // ALLOGGIO
            '55.10' => 'Alberghi e hotel',
            '55.20' => 'Appartamenti e case vacanze, residence',
            '55.30' => 'Campeggi e aree camper',
            '55.90' => 'B&B, affittacamere, agriturismi, ostelli',
            // RISTORAZIONE
            '56.10.1' => 'Ristoranti, trattorie, pizzerie',
            '56.10.2' => 'Fast food, tavole calde',
            '56.10.3' => 'Gelaterie, pasticcerie con somministrazione',
            '56.10.4' => 'Ristorazione ambulante (food truck)',
            '56.10.5' => 'Ristorazione su treni e navi',
            '56.21' => 'Catering per eventi e banchetti',
            '56.29' => 'Mense aziendali e scolastiche',
            '56.30' => 'Bar, caffetterie, pub (senza cucina)',
            // INDUSTRIE ALIMENTARI
            '10.1' => 'Lavorazione e conservazione carne',
            '10.2' => 'Lavorazione e conservazione pesce',
            '10.3' => 'Lavorazione frutta e ortaggi',
            '10.4' => 'Produzione oli e grassi',
            '10.5' => 'Industria lattiero-casearia',
            '10.6' => 'Lavorazione granaglie, amidi',
            '10.7' => 'Prodotti da forno (panifici, biscottifici)',
            '10.8' => 'Altri prodotti alimentari',
            '10.9' => 'Mangimi',
            '11.0' => 'Industria delle bevande',
        ]
    ],
    '54' => [
        'name' => '54% - Commercio Ambulante NON Alimentare',
        'codes' => [
            '47.82.01' => 'Ambulante tessuti, biancheria casa',
            '47.82.02' => 'Ambulante abbigliamento e accessori moda',
            '47.89.01' => 'Ambulante fiori, piante, sementi',
            '47.89.02' => 'Ambulante macchine e attrezzature agricole',
            '47.89.03' => 'Ambulante profumi, cosmetici, saponi',
            '47.89.04' => 'Ambulante chincaglieria, bigiotteria, accessori',
            '47.89.05' => 'Ambulante mobili da giardino, tende',
            '47.89.06' => 'Ambulante tappeti, stuoie',
            '47.89.07' => 'Ambulante elettrodomestici, radio, TV',
            '47.89.09' => 'Ambulante altri prodotti nca',
        ]
    ],
    '62' => [
        'name' => '62% - Intermediari del Commercio (Agenti e Rappresentanti)',
        'codes' => [
            '46.11' => 'Agenti materie prime agricole, animali, tessili',
            '46.12' => 'Agenti combustibili, minerali, metalli, chimici',
            '46.13' => 'Agenti legname, materiali costruzione',
            '46.14' => 'Agenti macchinari, impianti industriali',
            '46.15' => 'Agenti mobili, articoli casa, ferramenta',
            '46.16' => 'Agenti tessili, abbigliamento, calzature, pelletteria',
            '46.17' => 'Agenti alimentari, bevande, tabacco',
            '46.18' => 'Agenti specializzati in altri prodotti',
            '46.19' => 'Agenti prodotti vari (procacciatori)',
        ]
    ],
    '67' => [
        'name' => '67% - Commercio Ambulante ALIMENTARE',
        'codes' => [
            '47.81.01' => 'Ambulante frutta e verdura',
            '47.81.02' => 'Ambulante pesce, crostacei, molluschi',
            '47.81.03' => 'Ambulante carne',
            '47.81.04' => 'Ambulante formaggi e latticini',
            '47.81.05' => 'Ambulante uova',
            '47.81.06' => 'Ambulante oli e grassi alimentari',
            '47.81.07' => 'Ambulante bevande',
            '47.81.09' => 'Ambulante altri prodotti alimentari',
        ]
    ],
    '78' => [
        'name' => '78% - Servizi, Professioni e Attività Tecniche',
        'codes' => [
            // INFORMATICA
            '62.01' => 'Sviluppo software, programmazione',
            '62.02' => 'Consulenza informatica e IT',
            '62.03' => 'Gestione strutture informatiche, hosting',
            '62.09' => 'Altri servizi IT',
            '63.11' => 'Elaborazione dati, hosting',
            '63.12' => 'Portali web, siti internet',
            '63.91' => 'Agenzie di stampa',
            '63.99' => 'Altri servizi informazione',
            // LEGALI E CONTABILI
            '69.10.1' => 'Avvocati',
            '69.10.2' => 'Notai',
            '69.10.3' => 'Consulenti del lavoro',
            '69.20.1' => 'Commercialisti, ragionieri, periti commerciali',
            '69.20.2' => 'Consulenza fiscale e tributaria',
            '69.20.3' => 'Revisori contabili',
            // CONSULENZA AZIENDALE
            '70.10' => 'Holding non finanziarie',
            '70.21' => 'Pubbliche relazioni, comunicazione',
            '70.22' => 'Consulenza gestionale, management consulting',
            // ARCHITETTURA E INGEGNERIA
            '71.11' => 'Architetti',
            '71.12.1' => 'Ingegneri',
            '71.12.2' => 'Geometri',
            '71.12.3' => 'Periti industriali',
            '71.12.4' => 'Geologi',
            '71.12.5' => 'Agronomi, agrotecnici',
            '71.20' => 'Collaudi, analisi tecniche, certificazioni',
            // RICERCA
            '72.1' => 'Ricerca e sviluppo scienze naturali',
            '72.2' => 'Ricerca e sviluppo scienze sociali',
            // PUBBLICITÀ E MARKETING
            '73.11' => 'Agenzie pubblicitarie',
            '73.12' => 'Concessionarie pubblicità',
            '73.20' => 'Ricerche di mercato, sondaggi',
            // DESIGN E CREATIVITÀ
            '74.10.1' => 'Designer di moda e stilisti',
            '74.10.2' => 'Designer industriale e di prodotto',
            '74.10.3' => 'Grafici, web designer',
            '74.10.21' => 'Attività di disegno tecnico',
            '74.10.29' => 'Altre attività di design (UI/UX, interaction design)',
            '74.10.9' => 'Altre attività di design',
            '74.12.09' => 'Altre attività di progettazione grafica e comunicazione',
            '74.20' => 'Fotografi',
            '74.30' => 'Traduttori e interpreti',
            '74.90.1' => 'Consulenza agraria e ambientale',
            '74.90.2' => 'Broker, periti assicurativi',
            '74.90.91' => 'Attività tecniche svolte da periti industriali',
            '74.90.92' => 'Attività di consulenza tecnica',
            '74.90.93' => 'Altre attività di consulenza tecnica nca',
            '74.90.94' => 'Agenzie ed agenti per brevetti',
            '74.90.99' => 'Altre attività professionali nca',
            '74.90.9' => 'Altre attività professionali nca',
            // VETERINARIA
            '75.00' => 'Veterinari',
            // NOLEGGIO
            '77.1' => 'Noleggio autoveicoli (autonoleggio)',
            '77.2' => 'Noleggio beni per uso personale (bici, sci, attrezzature)',
            '77.3' => 'Noleggio macchine e attrezzature',
            '77.4' => 'Licenze e diritti proprietà intellettuale',
            // VIAGGI E TURISMO
            '79.11' => 'Agenzie di viaggio',
            '79.12' => 'Tour operator',
            '79.90' => 'Servizi prenotazione, guide turistiche',
            // SICUREZZA
            '80.10' => 'Vigilanza privata',
            '80.20' => 'Sistemi sicurezza, antifurti',
            '80.30' => 'Investigazioni private',
            // SERVIZI EDIFICI
            '81.10' => 'Facility management',
            '81.2' => 'Pulizie e sanificazione',
            '81.30' => 'Giardinaggio, cura verde',
            // SUPPORTO IMPRESE
            '82.11' => 'Servizi segreteria, supporto ufficio',
            '82.19' => 'Fotocopie, stampa, servizi documentali',
            '82.20' => 'Call center',
            '82.30' => 'Organizzazione eventi, fiere, congressi',
            '82.91' => 'Recupero crediti',
            '82.92' => 'Imballaggio e confezionamento',
            '82.99' => 'Altri servizi supporto imprese',
            // ISTRUZIONE
            '85.5' => 'Scuole guida, corsi sportivi, lingue',
            '85.52' => 'Formazione artistica (danza, musica, teatro)',
            '85.59' => 'Altri servizi istruzione (formazione, coaching)',
            '85.60' => 'Supporto all\'istruzione',
            // SANITÀ
            '86.21' => 'Medici di base',
            '86.22' => 'Medici specialisti',
            '86.23' => 'Dentisti, odontoiatri',
            '86.90.1' => 'Fisioterapisti',
            '86.90.2' => 'Infermieri',
            '86.90.3' => 'Logopedisti, osteopati',
            '86.90.4' => 'Psicologi',
            '86.90.9' => 'Altre professioni sanitarie',
            // ASSISTENZA SOCIALE
            '87' => 'Strutture assistenza residenziale',
            '88.1' => 'Assistenza anziani e disabili',
            '88.91' => 'Asili nido',
            '88.99' => 'Altre attività assistenza sociale',
            // ARTE E SPETTACOLO
            '90.01' => 'Artisti, attori, musicisti',
            '90.02' => 'Attività supporto spettacoli',
            '90.03' => 'Scrittori, sceneggiatori, compositori',
            '90.04' => 'Gestione teatri, sale concerti',
            '91' => 'Biblioteche, archivi, musei',
            '92.00' => 'Lotterie, scommesse, giochi',
            // SPORT
            '93.1' => 'Impianti sportivi, palestre, piscine',
            '93.12' => 'Club sportivi',
            '93.13' => 'Personal trainer, istruttori fitness',
            '93.19' => 'Altre attività sportive',
            '93.2' => 'Parchi divertimento, sale giochi',
            // SERVIZI ALLA PERSONA
            '96.01' => 'Lavanderie, tintorie',
            '96.02.01' => 'Parrucchieri',
            '96.02.02' => 'Estetiste, centri estetici',
            '96.02.03' => 'Tatuatori, piercing',
            '96.03' => 'Pompe funebri',
            '96.04' => 'Centri benessere, spa, terme',
            '96.09.01' => 'Agenzie matrimoniali',
            '96.09.02' => 'Astrologi, cartomanti',
            '96.09.03' => 'Dog sitter, pet sitter',
            '96.09.09' => 'Altri servizi alla persona',
        ]
    ],
    '86' => [
        'name' => '86% - Costruzioni e Immobiliare',
        'codes' => [
            // COSTRUZIONE EDIFICI
            '41.10' => 'Sviluppo progetti immobiliari (promoter)',
            '41.20' => 'Costruzione edifici residenziali e non',
            // INGEGNERIA CIVILE
            '42.11' => 'Strade, autostrade, piste aeroportuali',
            '42.12' => 'Ferrovie, metropolitane',
            '42.13' => 'Ponti, gallerie, viadotti',
            '42.21' => 'Acquedotti, gasdotti, oleodotti',
            '42.22' => 'Reti elettriche, telecomunicazioni',
            '42.91' => 'Opere idrauliche, marittime',
            '42.99' => 'Altre opere ingegneria civile',
            // LAVORI SPECIALIZZATI
            '43.11' => 'Demolizioni',
            '43.12' => 'Preparazione cantiere, scavi',
            '43.13' => 'Trivellazioni, perforazioni',
            '43.21' => 'Impianti elettrici (elettricisti)',
            '43.22.01' => 'Impianti idraulici (idraulici)',
            '43.22.02' => 'Impianti riscaldamento, climatizzazione',
            '43.22.03' => 'Impianti gas',
            '43.29.01' => 'Impianti ascensori, scale mobili',
            '43.29.02' => 'Impianti antincendio',
            '43.29.09' => 'Altri impianti',
            '43.31' => 'Intonacatura',
            '43.32' => 'Posa infissi (serramentisti)',
            '43.33' => 'Pavimenti, piastrelle, parquet',
            '43.34.01' => 'Tinteggiatura, imbianchini',
            '43.34.02' => 'Vetrerie, vetrinisti',
            '43.39' => 'Altri lavori completamento edifici',
            '43.91' => 'Coperture, tetti, impermeabilizzazioni',
            '43.99.01' => 'Noleggio macchine edili con operatore',
            '43.99.02' => 'Fondazioni speciali',
            '43.99.03' => 'Ponteggi, impalcature',
            '43.99.09' => 'Altri lavori costruzione specializzati',
            // IMMOBILIARE
            '68.10' => 'Compravendita immobili propri',
            '68.20.01' => 'Locazione immobili propri residenziali',
            '68.20.02' => 'Locazione immobili propri non residenziali',
            '68.31' => 'Agenzie immobiliari, mediazione',
            '68.32.01' => 'Amministratori condominio',
            '68.32.02' => 'Gestione immobili per conto terzi',
        ]
    ],
];

// Lista piatta dei codici ATECO per JavaScript
$atecoList = [];
foreach ($atecoGroups as $coeff => $group) {
    foreach ($group['codes'] as $code => $desc) {
        $atecoList[] = [
            'code' => $code,
            'description' => $desc,
            'coefficient' => (int)$coeff,
            'group' => $group['name']
        ];
    }
}




// Onboarding Accounting
try { $pdo->exec("CREATE TABLE IF NOT EXISTS acc_onboarding (id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL UNIQUE, completed TINYINT(1) DEFAULT 0, completed_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e) {}
$showOnboarding = false;
try {
    $stmt = $pdo->prepare("SELECT completed FROM acc_onboarding WHERE customer_id=?");
    $stmt->execute([$customerId]);
    $ob = $stmt->fetch();
    if (!$ob) { $pdo->prepare("INSERT INTO acc_onboarding (customer_id) VALUES (?)")->execute([$customerId]); $showOnboarding = true; }
    else { $showOnboarding = !$ob['completed']; }
} catch (Exception $e) {}

$page = $_GET['page'] ?? 'dashboard';
$success = '';
$error = '';
if (!empty($_GET['ok'])) $success = htmlspecialchars($_GET['ok'], ENT_QUOTES, 'UTF-8');
if (!empty($_GET['err'])) $error = htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8');

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'complete_onboarding') {
        try { $pdo->prepare("UPDATE acc_onboarding SET completed=1, completed_at=NOW() WHERE customer_id=?")->execute([$customerId]); } catch(Exception $e) {}
        header('Location: ?page=dashboard'); exit;
    }

    $action = $_POST['action'] ?? '';
    
    // Salva cliente/fornitore
    if ($action === 'save_client') {
        try {
            $type = $_POST['type'] ?? 'client';
            $stmt = $pdo->prepare("INSERT INTO acc_clients (customer_id, type, name, vat_number, fiscal_code, address, city, zip, province, sdi_code, pec, email, phone, payment_terms, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$customerId, $type, $_POST['name'], $_POST['vat_number']??'', $_POST['fiscal_code']??'', $_POST['address']??'', $_POST['city']??'', $_POST['zip']??'', $_POST['province']??'', $_POST['sdi_code']??'', $_POST['pec']??'', $_POST['email']??'', $_POST['phone']??'', $_POST['payment_terms']??30, $_POST['notes']??'']);
            header('Location: ?page=' . ($type==='supplier' ? 'suppliers' : 'clients')); exit;
        } catch (Exception $e) {
            $error = 'Errore salvataggio cliente: ' . $e->getMessage();
        }
    }
    
    // Salva fattura con righe
    if ($action === 'save_invoice') {
        try {
            $type = $_POST['invoice_type'] ?? 'active';
            $clientId = (int)($_POST['client_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $dueDate = $_POST['due_date'] ?: null;
            $notes = $_POST['notes'] ?? '';
            $status = $_POST['status'] ?? 'sent';
        
        // Genera numero
        $prefix = $type === 'active' ? 'FV' : 'FA';
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED)) as last_num FROM acc_invoices WHERE customer_id = ? AND type = ? AND number LIKE ?");
        $stmt->execute([$customerId, $type, "$prefix-$year/%"]);
        $lastNum = $stmt->fetch()['last_num'] ?? 0;
        $number = "$prefix-$year/" . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        
        // Calcola totali dalle righe
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        $subtotal = 0;
        $vatAmount = 0;
        foreach ($items as $item) {
            $itemTotal = $item['qty'] * $item['price'];
            $subtotal += $itemTotal;
            $vatAmount += $itemTotal * ($item['vat'] ?? 22) / 100;
        }
        $total = $subtotal + $vatAmount;
        
        $paymentStatus = ($status === 'paid') ? 'paid' : 'pending';
        
        $stmt = $pdo->prepare("INSERT INTO acc_invoices (customer_id, client_id, type, number, `date`, due_date, subtotal, vat_amount, total, status, payment_status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$customerId, $clientId, $type, $number, $date, $dueDate, $subtotal, $vatAmount, $total, $status, $paymentStatus, $notes]);
        $invoiceId = $pdo->lastInsertId();
        
        // Salva righe fattura
        $stmtItem = $pdo->prepare("INSERT INTO acc_invoice_items (invoice_id, description, quantity, unit_price, vat_rate, total) VALUES (?,?,?,?,?,?)");
        foreach ($items as $item) {
            $itemTotal = $item['qty'] * $item['price'];
            $stmtItem->execute([$invoiceId, $item['desc'], $item['qty'], $item['price'], $item['vat'] ?? 22, $itemTotal]);
        }
        
        // Crea scadenza se Pro e data scadenza presente
        if ($isPro && $dueDate && $paymentStatus !== 'paid') {
            $dlType = $type === 'active' ? 'receivable' : 'payable';
            $stmt = $pdo->prepare("INSERT INTO acc_deadlines (customer_id, invoice_id, client_id, type, description, amount, due_date) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$customerId, $invoiceId, $clientId, $dlType, "Fattura $number", $total, $dueDate]);
        }
        
        header('Location: ?page=' . ($type==='passive' ? 'passive' : 'invoices')); exit;
        } catch (Exception $e) {
            $error = 'Errore salvataggio fattura: ' . $e->getMessage();
        }
    }
    
    // Upload XML fattura (attiva o passiva - rileva automaticamente)
    if ($action === 'upload_xml') {
        if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
            try {
                $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
                // Rimuovi namespace per parsing più semplice
                $xmlClean = preg_replace('/(<\/?)ns2:/', '$1', $xmlContent);
                $xmlClean = preg_replace('/xmlns:ns2="[^"]*"/', '', $xmlClean);
                $xml = @simplexml_load_string($xmlClean);
                
                if (!$xml) throw new Exception('File XML non valido o non leggibile');
                
                // Salva file
                $fileName = $_FILES['xml_file']['name'];
                $uploadDir = __DIR__ . '/../../uploads/xml/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $savedPath = $uploadDir . time() . '_' . $fileName;
                move_uploaded_file($_FILES['xml_file']['tmp_name'], $savedPath);
                
                // Estrai dati generali
                $body = $xml->FatturaElettronicaBody;
                $header = $xml->FatturaElettronicaHeader;
                $datiGen = $body->DatiGenerali->DatiGeneraliDocumento;
                
                $number = (string)($datiGen->Numero ?? 'N/D');
                $date = (string)($datiGen->Data ?? date('Y-m-d'));
                $totalDoc = (float)($datiGen->ImportoTotaleDocumento ?? 0);
                $bollo = (float)($datiGen->DatiBollo->ImportoBollo ?? 0);
                
                // Rileva tipo: confronta P.IVA cedente con P.IVA azienda
                $cedentePiva = (string)($header->CedentePrestatore->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? '');
                $myPiva = $company['vat_number'] ?? '';
                
                // Se la P.IVA del cedente è uguale alla mia → fattura ATTIVA (emessa da me)
                $isActive = (!empty($myPiva) && $cedentePiva === $myPiva);
                $type = $isActive ? 'active' : 'passive';
                
                // Estrai dati controparte (cliente se attiva, fornitore se passiva)
                if ($isActive) {
                    $cp = $header->CessionarioCommittente;
                } else {
                    $cp = $header->CedentePrestatore;
                }
                $cpName = (string)($cp->DatiAnagrafici->Anagrafica->Denominazione ?? '');
                if (!$cpName) {
                    $cpName = trim((string)($cp->DatiAnagrafici->Anagrafica->Nome ?? '') . ' ' . (string)($cp->DatiAnagrafici->Anagrafica->Cognome ?? ''));
                }
                $cpPiva = (string)($cp->DatiAnagrafici->IdFiscaleIVA->IdCodice ?? '');
                $cpCf = (string)($cp->DatiAnagrafici->CodiceFiscale ?? '');
                $cpAddress = trim((string)($cp->Sede->Indirizzo ?? '') . ' ' . (string)($cp->Sede->NumeroCivico ?? ''));
                $cpCity = (string)($cp->Sede->Comune ?? '');
                $cpZip = (string)($cp->Sede->CAP ?? '');
                $cpProv = (string)($cp->Sede->Provincia ?? '');
                
                // Cerca o crea cliente/fornitore
                $clientId = 0;
                $cpType = $isActive ? 'client' : 'supplier';
                if ($cpPiva || $cpCf) {
                    $stmtFind = $pdo->prepare("SELECT id FROM acc_clients WHERE customer_id = ? AND (vat_number = ? OR fiscal_code = ?) LIMIT 1");
                    $stmtFind->execute([$customerId, $cpPiva, $cpCf]);
                    $found = $stmtFind->fetch();
                    if ($found) {
                        $clientId = $found['id'];
                    } else {
                        // Crea automaticamente
                        $stmtIns = $pdo->prepare("INSERT INTO acc_clients (customer_id, type, name, vat_number, fiscal_code, address, city, zip, province) VALUES (?,?,?,?,?,?,?,?,?)");
                        $stmtIns->execute([$customerId, $cpType, $cpName, $cpPiva, $cpCf, $cpAddress, $cpCity, $cpZip, $cpProv]);
                        $clientId = $pdo->lastInsertId();
                    }
                }
                
                // Calcola totali da righe
                $subtotal = 0;
                $vatAmount = 0;
                $lines = [];
                foreach ($body->DatiBeniServizi->DettaglioLinee as $linea) {
                    $desc = (string)($linea->Descrizione ?? '');
                    $qty = (float)($linea->Quantita ?? 1);
                    $price = (float)($linea->PrezzoUnitario ?? 0);
                    $lineTotal = (float)($linea->PrezzoTotale ?? $qty * $price);
                    $vatRate = (float)($linea->AliquotaIVA ?? 0);
                    
                    $subtotal += $lineTotal;
                    $vatAmount += $lineTotal * $vatRate / 100;
                    
                    $lines[] = ['desc' => $desc, 'qty' => $qty, 'price' => $price, 'total' => $lineTotal, 'vat' => $vatRate];
                }
                
                $total = $totalDoc ?: ($subtotal + $vatAmount);
                
                // Scadenza pagamento
                $dueDate = null;
                if (isset($body->DatiPagamento->DettaglioPagamento->DataRiferimentoTerminiPagamento)) {
                    $dueDate = (string)$body->DatiPagamento->DettaglioPagamento->DataRiferimentoTerminiPagamento;
                }
                
                // Controlla duplicato
                $stmtDup = $pdo->prepare("SELECT id FROM acc_invoices WHERE customer_id = ? AND number = ? AND type = ? AND `date` = ? LIMIT 1");
                $stmtDup->execute([$customerId, $number, $type, $date]);
                if ($stmtDup->fetch()) throw new Exception("Fattura $number del $date già importata");
                
                // Inserisci fattura
                $prefix = $isActive ? 'FV' : 'FA';
                $stmtInv = $pdo->prepare("INSERT INTO acc_invoices (customer_id, client_id, type, number, `date`, due_date, subtotal, vat_amount, total, stamp_duty, status, payment_status, xml_file, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmtInv->execute([$customerId, $clientId, $type, $number, $date, $dueDate, $subtotal, $vatAmount, $total, $bollo, 'sent', 'pending', basename($savedPath), 'Importata da XML SDI']);
                $invoiceId = $pdo->lastInsertId();
                
                // Inserisci righe
                $stmtItem = $pdo->prepare("INSERT INTO acc_invoice_items (invoice_id, description, quantity, unit_price, vat_rate, total) VALUES (?,?,?,?,?,?)");
                foreach ($lines as $line) {
                    $stmtItem->execute([$invoiceId, $line['desc'], $line['qty'], $line['price'], $line['vat'], $line['total']]);
                }
                
                // Crea scadenza se data presente
                if ($dueDate) {
                    $dlType = $isActive ? 'receivable' : 'payable';
                    $pdo->prepare("INSERT INTO acc_deadlines (customer_id, invoice_id, client_id, type, description, amount, due_date) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$customerId, $invoiceId, $clientId, $dlType, ($isActive ? 'Fattura emessa' : 'Fattura ricevuta') . " $number", $total, $dueDate]);
                }
                
                $typeLabel = $isActive ? 'emessa (attiva)' : 'ricevuta (passiva)';
                $success = "Fattura $typeLabel n. $number del $date importata — Cliente: $cpName — Totale: €" . number_format($total, 2, ',', '.') . " — " . count($lines) . " righe";
                
            } catch (Exception $e) {
                $error = 'Errore import XML: ' . $e->getMessage();
            }
        }
    }
    
    // Salva movimento prima nota
    if ($action === 'save_transaction' && $isPro) {
        try {
            $stmt = $pdo->prepare("INSERT INTO acc_transactions (customer_id, `date`, type, category, description, amount, payment_method, document_ref) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$customerId, $_POST['date']??date('Y-m-d'), $_POST['trans_type']??'income', $_POST['category']??'', $_POST['description']??'', (float)($_POST['amount']??0), $_POST['payment_method']??'', $_POST['document_ref']??'']);
            header('Location: ?page=prima-nota'); exit;
        } catch (Exception $e) {
            $error = 'Errore salvataggio movimento: ' . $e->getMessage();
        }
    }
    
    // Salva azienda
    
    if ($action === 'save_regime') {
        $regime = $_POST['tax_regime'] ?? 'ordinario';
        $defaultVat = floatval($_POST['default_vat_rate'] ?? 22);
        $stampDuty = floatval($_POST['stamp_duty'] ?? 2);
        $stampThreshold = floatval($_POST['stamp_duty_threshold'] ?? 77.47);
        $vatRatesInput = array_filter(array_map('floatval', explode(',', $_POST['vat_rates'] ?? '4,5,10,22')));
        
        // Campi comuni a tutti i regimi
        $atecoCode = trim($_POST['ateco_code'] ?? '');
        $atecoDesc = trim($_POST['ateco_description'] ?? '');
        $coefficient = floatval($_POST['coefficient_percent'] ?? 78);
        $inpsMgmt = $_POST['inps_management'] ?? 'separata';
        $taxRate = floatval($_POST['tax_rate_percent'] ?? 15);
        $startupDiscount = isset($_POST['startup_discount']) ? 1 : 0;
        $cassaProfessionale = $_POST['cassa_professionale'] ?? '';
        
        // Campi specifici regime ordinario/semplificato
        $regione = $_POST['regione'] ?? 'lombardia';
        $irapApplicable = isset($_POST['irap_applicable']) ? intval($_POST['irap_applicable']) : 1;
        $tipoAttivita = $_POST['tipo_attivita'] ?? $_POST['tipo_attivita_semp'] ?? 'servizi';
        $annoIngressoMinimi = intval($_POST['anno_ingresso_minimi'] ?? 2015);
        
        try {
            $pdo->prepare("UPDATE acc_companies SET 
                tax_regime=?, default_vat_rate=?, stamp_duty=?, stamp_duty_threshold=?, vat_rates=?,
                ateco_code=?, ateco_description=?, coefficient_percent=?, inps_management=?, tax_rate_percent=?, startup_discount=?,
                regione=?, irap_applicable=?, tipo_attivita=?, anno_ingresso_minimi=?, cassa_professionale=?
                WHERE customer_id=?")->execute([
                    $regime, $defaultVat, $stampDuty, $stampThreshold, json_encode($vatRatesInput),
                    $atecoCode, $atecoDesc, $coefficient, $inpsMgmt, $taxRate, $startupDiscount,
                    $regione, $irapApplicable, $tipoAttivita, $annoIngressoMinimi, $cassaProfessionale,
                    $customerId
                ]);
        } catch (Exception $e) {
            // Colonne potrebbero non esistere ancora, try fallback
            try {
                $pdo->prepare("UPDATE acc_companies SET tax_regime=? WHERE customer_id=?")->execute([$regime, $customerId]);
            } catch (Exception $e2) {}
        }
        header('Location: ?page=company&saved=regime'); exit;
    }

    if ($action === 'save_company') {
        try {
            $stmt = $pdo->prepare("UPDATE acc_companies SET name=?, vat_number=?, fiscal_code=?, address=?, city=?, zip=?, province=?, sdi_code=?, pec=?, email=?, phone=?, bank_name=?, bank_iban=? WHERE customer_id=?");
            $stmt->execute([$_POST['name'], $_POST['vat_number']??'', $_POST['fiscal_code']??'', $_POST['address']??'', $_POST['city']??'', $_POST['zip']??'', $_POST['province']??'', $_POST['sdi_code']??'', $_POST['pec']??'', $_POST['email']??'', $_POST['phone']??'', $_POST['bank_name']??'', $_POST['bank_iban']??'', $customerId]);
            header('Location: ?page=company&saved=company'); exit;
        } catch (Exception $e) {
            $error = 'Errore salvataggio azienda: ' . $e->getMessage();
        }
    }
    
    // Segna scadenza come pagata
    if ($action === 'mark_paid' && $isPro) {
        try {
            $stmt = $pdo->prepare("UPDATE acc_deadlines SET status='paid', paid_date=NOW() WHERE id=? AND customer_id=?");
            $stmt->execute([$_POST['deadline_id'], $customerId]);
            $stmt = $pdo->prepare("SELECT invoice_id FROM acc_deadlines WHERE id=?"); $stmt->execute([$_POST['deadline_id']]);
            if ($invId = $stmt->fetchColumn()) {
                $pdo->prepare("UPDATE acc_invoices SET payment_status='paid' WHERE id=? AND customer_id=?")->execute([$invId, $customerId]);
            }
            header('Location: ?page=scadenzario'); exit;
        } catch (Exception $e) {
            $error = 'Errore: ' . $e->getMessage();
        }
    }
    
    // Segna scadenza fiscale come pagata/non pagata
    if ($action === 'toggle_fiscal_payment') {
        header('Content-Type: application/json');
        $year = intval($_POST['fiscal_year'] ?? 0);
        $key = trim($_POST['deadline_key'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$year || !$key) { echo json_encode(['success' => false, 'error' => 'Dati mancanti']); exit; }
        
        try {
            // Controlla se esiste già
            $stmt = $pdo->prepare("SELECT id FROM acc_fiscal_payments WHERE customer_id = ? AND fiscal_year = ? AND deadline_key = ?");
            $stmt->execute([$customerId, $year, $key]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Già pagato → rimuovi (toggle off)
                $pdo->prepare("DELETE FROM acc_fiscal_payments WHERE id = ?")->execute([$existing['id']]);
                echo json_encode(['success' => true, 'paid' => false]);
            } else {
                // Non pagato → segna come pagato
                $pdo->prepare("INSERT INTO acc_fiscal_payments (customer_id, fiscal_year, deadline_key, amount, paid_date, notes) VALUES (?,?,?,?,?,?)")
                    ->execute([$customerId, $year, $key, $amount, $paidDate, $notes]);
                echo json_encode(['success' => true, 'paid' => true]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // ==================== PREVENTIVI: salvataggio ====================
    if ($action === 'save_quote') {
        try {
            $quoteId = (int)($_POST['quote_id'] ?? 0);
            $clientId = (int)($_POST['client_id'] ?? 0);
            $qDate = $_POST['date'] ?: date('Y-m-d');
            $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
            $subject = trim($_POST['subject'] ?? '');
            $qNotes = trim($_POST['notes'] ?? '');
            $qTerms = trim($_POST['terms'] ?? '');
            $qPayment = trim($_POST['payment_terms_text'] ?? '');
            $discount = max(0, min(100, (float)($_POST['discount_percent'] ?? 0)));
            $qStatus = $_POST['status'] ?? 'draft';
            if (!in_array($qStatus, ['draft', 'sent', 'accepted', 'rejected'], true)) $qStatus = 'draft';

            $qItems = json_decode($_POST['items_json'] ?? '[]', true);
            if (!is_array($qItems) || count($qItems) === 0) throw new Exception('Aggiungi almeno una riga al preventivo');

            $tot = accQuoteTotals($qItems, $discount);

            if ($quoteId) {
                $chk = $pdo->prepare("SELECT id FROM acc_quotes WHERE id=? AND customer_id=?");
                $chk->execute([$quoteId, $customerId]);
                if (!$chk->fetch()) throw new Exception('Preventivo non trovato');
                $pdo->prepare("UPDATE acc_quotes SET client_id=?, `date`=?, valid_until=?, subject=?, subtotal=?, discount_percent=?, discount_amount=?, vat_amount=?, total=?, status=?, notes=?, terms=?, payment_terms_text=? WHERE id=? AND customer_id=?")
                    ->execute([$clientId, $qDate, $validUntil, $subject, $tot['subtotal'], $discount, $tot['discount_amount'], $tot['vat_amount'], $tot['total'], $qStatus, $qNotes, $qTerms, $qPayment, $quoteId, $customerId]);
                $pdo->prepare("DELETE FROM acc_quote_items WHERE quote_id=?")->execute([$quoteId]);
            } else {
                $year = date('Y', strtotime($qDate));
                $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED)) FROM acc_quotes WHERE customer_id=? AND number LIKE ?");
                $stmt->execute([$customerId, "PR-$year/%"]);
                $lastNum = (int)$stmt->fetchColumn();
                $number = "PR-$year/" . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
                $token = bin2hex(random_bytes(16));
                $pdo->prepare("INSERT INTO acc_quotes (customer_id, client_id, number, `date`, valid_until, subject, subtotal, discount_percent, discount_amount, vat_amount, total, status, notes, terms, payment_terms_text, public_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$customerId, $clientId, $number, $qDate, $validUntil, $subject, $tot['subtotal'], $discount, $tot['discount_amount'], $tot['vat_amount'], $tot['total'], $qStatus, $qNotes, $qTerms, $qPayment, $token]);
                $quoteId = (int)$pdo->lastInsertId();
            }

            $stmtItem = $pdo->prepare("INSERT INTO acc_quote_items (quote_id, description, quantity, unit_price, vat_rate, total, sort_order) VALUES (?,?,?,?,?,?,?)");
            $ord = 0;
            foreach ($qItems as $it) {
                $rowTotal = (float)$it['qty'] * (float)$it['price'];
                $stmtItem->execute([$quoteId, $it['desc'], (float)$it['qty'], (float)$it['price'], (float)($it['vat'] ?? 22), round($rowTotal, 2), $ord++]);
            }

            header('Location: ?page=quote_detail&id=' . $quoteId . '&ok=' . urlencode('Preventivo salvato'));
            exit;
        } catch (Exception $e) {
            $error = 'Errore salvataggio preventivo: ' . $e->getMessage();
        }
    }

    // Cambio stato preventivo
    if ($action === 'quote_status') {
        try {
            $quoteId = (int)($_POST['quote_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? '';
            if (!in_array($newStatus, ['draft', 'sent', 'accepted', 'rejected'], true)) throw new Exception('Stato non valido');
            $accepted = $newStatus === 'accepted' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE acc_quotes SET status=?, accepted_at=? WHERE id=? AND customer_id=?")
                ->execute([$newStatus, $accepted, $quoteId, $customerId]);
            header('Location: ?page=quote_detail&id=' . $quoteId . '&ok=' . urlencode('Stato aggiornato'));
            exit;
        } catch (Exception $e) {
            $error = 'Errore aggiornamento stato: ' . $e->getMessage();
        }
    }

    // Duplica preventivo
    if ($action === 'duplicate_quote') {
        try {
            $quoteId = (int)($_POST['quote_id'] ?? 0);
            $q = accLoadQuote($pdo, $customerId, $quoteId);
            if (!$q) throw new Exception('Preventivo non trovato');
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED)) FROM acc_quotes WHERE customer_id=? AND number LIKE ?");
            $stmt->execute([$customerId, "PR-$year/%"]);
            $number = "PR-$year/" . str_pad(((int)$stmt->fetchColumn()) + 1, 4, '0', STR_PAD_LEFT);
            $validUntil = $q['valid_until'] ? date('Y-m-d', strtotime('+30 days')) : null;
            $pdo->prepare("INSERT INTO acc_quotes (customer_id, client_id, number, `date`, valid_until, subject, subtotal, discount_percent, discount_amount, vat_amount, total, status, notes, terms, payment_terms_text, public_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,'draft',?,?,?,?)")
                ->execute([$customerId, $q['client_id'], $number, date('Y-m-d'), $validUntil, $q['subject'], $q['subtotal'], $q['discount_percent'], $q['discount_amount'], $q['vat_amount'], $q['total'], $q['notes'], $q['terms'], $q['payment_terms_text'], bin2hex(random_bytes(16))]);
            $newId = (int)$pdo->lastInsertId();
            $stmtItem = $pdo->prepare("INSERT INTO acc_quote_items (quote_id, description, quantity, unit_price, vat_rate, total, sort_order) VALUES (?,?,?,?,?,?,?)");
            foreach ($q['items'] as $i => $it) {
                $stmtItem->execute([$newId, $it['description'], $it['quantity'], $it['unit_price'], $it['vat_rate'], $it['total'], $i]);
            }
            header('Location: ?page=quote_detail&id=' . $newId . '&ok=' . urlencode('Preventivo duplicato'));
            exit;
        } catch (Exception $e) {
            $error = 'Errore duplicazione: ' . $e->getMessage();
        }
    }

    // Converti preventivo in fattura
    if ($action === 'quote_to_invoice') {
        try {
            $quoteId = (int)($_POST['quote_id'] ?? 0);
            $q = accLoadQuote($pdo, $customerId, $quoteId);
            if (!$q) throw new Exception('Preventivo non trovato');
            if (!empty($q['invoice_id'])) throw new Exception('Preventivo già convertito in fattura');

            $year = date('Y');
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED)) FROM acc_invoices WHERE customer_id=? AND type='active' AND number LIKE ?");
            $stmt->execute([$customerId, "FV-$year/%"]);
            $invNumber = "FV-$year/" . str_pad(((int)$stmt->fetchColumn()) + 1, 4, '0', STR_PAD_LEFT);

            $termini = (int)($_POST['payment_days'] ?? 30);
            $invDate = date('Y-m-d');
            $invDue = $termini > 0 ? date('Y-m-d', strtotime("+$termini days")) : null;
            $imponibile = round((float)$q['subtotal'] - (float)$q['discount_amount'], 2);

            $pdo->prepare("INSERT INTO acc_invoices (customer_id, client_id, type, number, `date`, due_date, subtotal, vat_amount, total, status, payment_status, notes) VALUES (?,?,'active',?,?,?,?,?,?,'sent','pending',?)")
                ->execute([$customerId, $q['client_id'], $invNumber, $invDate, $invDue, $imponibile, $q['vat_amount'], $q['total'], trim(($q['notes'] ? $q['notes'] . ' - ' : '') . 'Da preventivo ' . $q['number'])]);
            $invoiceId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO acc_invoice_items (invoice_id, description, quantity, unit_price, vat_rate, total) VALUES (?,?,?,?,?,?)");
            $sconto = (float)$q['discount_percent'];
            foreach ($q['items'] as $it) {
                $prezzo = round((float)$it['unit_price'] * (1 - $sconto / 100), 2);
                $stmtItem->execute([$invoiceId, $it['description'], $it['quantity'], $prezzo, $it['vat_rate'], round($prezzo * (float)$it['quantity'], 2)]);
            }

            if ($isPro && $invDue) {
                $pdo->prepare("INSERT INTO acc_deadlines (customer_id, invoice_id, client_id, type, description, amount, due_date) VALUES (?,?,?,'receivable',?,?,?)")
                    ->execute([$customerId, $invoiceId, $q['client_id'], "Fattura $invNumber", $q['total'], $invDue]);
            }

            $pdo->prepare("UPDATE acc_quotes SET status='invoiced', invoice_id=?, accepted_at=COALESCE(accepted_at, NOW()) WHERE id=? AND customer_id=?")
                ->execute([$invoiceId, $quoteId, $customerId]);

            header('Location: ?page=invoice_detail&id=' . $invoiceId);
            exit;
        } catch (Exception $e) {
            $error = 'Errore conversione in fattura: ' . $e->getMessage();
        }
    }

    // Invio preventivo via email (con PDF allegato)
    if ($action === 'send_quote_email') {
        try {
            $quoteId = (int)($_POST['quote_id'] ?? 0);
            $q = accLoadQuote($pdo, $customerId, $quoteId);
            if (!$q) throw new Exception('Preventivo non trovato');
            $to = trim($_POST['to_email'] ?? '');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new Exception('Indirizzo email non valido');

            $link = $q['public_token'] ? accQuotePublicUrl($q['public_token']) : '';
            $messaggio = trim($_POST['message'] ?? '') ?: accQuoteMessage($q, $company, $link, 'email');
            $oggetto = trim($_POST['subject_email'] ?? '') ?: ('Preventivo ' . $q['number'] . ' - ' . ($company['name'] ?: ''));

            $pdfContent = accBuildQuotePdf($q, $q['items'], $company, accQuoteClientData($q));
            $pdfName = 'Preventivo-' . str_replace('/', '-', $q['number']) . '.pdf';
            $htmlMail = accQuoteEmailHtml($q, $company, $link, $messaggio);

            $mittente = $company['email'] ?: $customerEmail;
            $inviata = accSendMailWithPdf($to, $oggetto, $htmlMail, $messaggio, $company['name'] ?: 'Preventivo', $mittente, $mittente, $pdfContent, $pdfName);

            if (!empty($_POST['copy_to_me']) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                accSendMailWithPdf($customerEmail, '[Copia] ' . $oggetto, $htmlMail, $messaggio, $company['name'] ?: 'Preventivo', $mittente, $mittente, $pdfContent, $pdfName);
            }

            if ($inviata) {
                $newStatus = in_array($q['status'], ['accepted', 'rejected', 'invoiced'], true) ? $q['status'] : 'sent';
                $pdo->prepare("UPDATE acc_quotes SET status=?, sent_at=NOW(), sent_channel='email' WHERE id=? AND customer_id=?")
                    ->execute([$newStatus, $quoteId, $customerId]);
                header('Location: ?page=quote_detail&id=' . $quoteId . '&ok=' . urlencode('Preventivo inviato a ' . $to));
                exit;
            }
            header('Location: ?page=quote_detail&id=' . $quoteId . '&err=' . urlencode('Invio email non riuscito: il server non ha accettato il messaggio. Usa il pulsante WhatsApp o scarica il PDF e allegalo manualmente.'));
            exit;
        } catch (Exception $e) {
            $error = 'Errore invio email: ' . $e->getMessage();
        }
    }

    // Segna preventivo come inviato (usato dopo la condivisione WhatsApp)
    if ($action === 'mark_quote_sent') {
        header('Content-Type: application/json');
        try {
            $quoteId = (int)($_POST['quote_id'] ?? 0);
            $canale = in_array($_POST['channel'] ?? '', ['whatsapp', 'email', 'link'], true) ? $_POST['channel'] : 'link';
            $stmt = $pdo->prepare("SELECT status FROM acc_quotes WHERE id=? AND customer_id=?");
            $stmt->execute([$quoteId, $customerId]);
            $cur = $stmt->fetchColumn();
            if ($cur === false) throw new Exception('Preventivo non trovato');
            $newStatus = in_array($cur, ['accepted', 'rejected', 'invoiced'], true) ? $cur : 'sent';
            $pdo->prepare("UPDATE acc_quotes SET status=?, sent_at=NOW(), sent_channel=? WHERE id=? AND customer_id=?")
                ->execute([$newStatus, $canale, $quoteId, $customerId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Elimina
    if ($action === 'delete') {
        try {
            $table = $_POST['table'] ?? '';
            $allowed = ['acc_clients', 'acc_invoices', 'acc_transactions', 'acc_deadlines', 'acc_quotes'];
            if (in_array($table, $allowed)) {
                $deleteId = (int)$_POST['delete_id'];
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id=? AND customer_id=?");
                $stmt->execute([$deleteId, $customerId]);
                if ($table === 'acc_invoices') {
                    $pdo->prepare("DELETE FROM acc_invoice_items WHERE invoice_id=?")->execute([$deleteId]);
                }
                if ($table === 'acc_quotes') {
                    $pdo->prepare("DELETE FROM acc_quote_items WHERE quote_id=?")->execute([$deleteId]);
                }
            }
            header('Location: ?page=' . ($_POST['return_page'] ?? 'dashboard')); exit;
        } catch (Exception $e) {
            $error = 'Errore eliminazione: ' . $e->getMessage();
        }
    }
}

// Download / anteprima PDF preventivo
if (isset($_GET['quote_pdf'])) {
    $q = accLoadQuote($pdo, $customerId, (int)$_GET['quote_pdf']);
    if ($q) {
        $pdfContent = accBuildQuotePdf($q, $q['items'], $company, accQuoteClientData($q));
        $pdfName = 'Preventivo-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', $q['number']) . '.pdf';
        $disposition = isset($_GET['inline']) ? 'inline' : 'attachment';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdfContent;
        exit;
    }
    header('Location: ?page=quotes&err=' . urlencode('Preventivo non trovato'));
    exit;
}

// Export CSV per commercialista
if (isset($_GET['export']) && $isPro) {
    $exportType = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_' . $exportType . '_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    if ($exportType === 'fatture_attive') {
        fputcsv($output, ['Numero', 'Data', 'Cliente', 'Imponibile', 'IVA', 'Totale', 'Stato'], ';');
        $stmt = $pdo->prepare("SELECT i.*, c.name as client_name FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id = c.id WHERE i.customer_id = ? AND i.type = 'active' ORDER BY i.`date` DESC");
        $stmt->execute([$customerId]);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['number'], $row['date'], $row['client_name'], $row['subtotal'], $row['vat_amount'], $row['total'], $row['payment_status']], ';');
        }
    } elseif ($exportType === 'fatture_passive') {
        fputcsv($output, ['Numero', 'Data', 'Fornitore', 'Totale', 'Stato'], ';');
        $stmt = $pdo->prepare("SELECT i.*, c.name as supplier_name FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id = c.id WHERE i.customer_id = ? AND i.type = 'passive' ORDER BY i.`date` DESC");
        $stmt->execute([$customerId]);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['number'], $row['date'], $row['supplier_name'], $row['total'], $row['payment_status']], ';');
        }
    } elseif ($exportType === 'prima_nota') {
        fputcsv($output, ['Data', 'Tipo', 'Categoria', 'Descrizione', 'Importo', 'Metodo'], ';');
        $stmt = $pdo->prepare("SELECT * FROM acc_transactions WHERE customer_id = ? ORDER BY `date` DESC");
        $stmt->execute([$customerId]);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['date'], $row['type'], $row['category'], $row['description'], $row['amount'], $row['payment_method']], ';');
        }
    } elseif ($exportType === 'preventivi') {
        fputcsv($output, ['Numero', 'Data', 'Valido fino al', 'Cliente', 'Imponibile', 'Sconto', 'IVA', 'Totale', 'Stato'], ';');
        $stmt = $pdo->prepare("SELECT q.*, c.name as client_name FROM acc_quotes q LEFT JOIN acc_clients c ON q.client_id = c.id WHERE q.customer_id = ? ORDER BY q.`date` DESC");
        $stmt->execute([$customerId]);
        while ($row = $stmt->fetch()) {
            $info = accQuoteStatusInfo($row['status'], $row['valid_until']);
            fputcsv($output, [$row['number'], $row['date'], $row['valid_until'], $row['client_name'], $row['subtotal'], $row['discount_amount'], $row['vat_amount'], $row['total'], $info['label']], ';');
        }
    } elseif ($exportType === 'clienti') {
        fputcsv($output, ['Nome', 'P.IVA', 'C.F.', 'Indirizzo', 'Città', 'Email', 'Telefono'], ';');
        $stmt = $pdo->prepare("SELECT * FROM acc_clients WHERE customer_id = ? AND type = 'client' ORDER BY name");
        $stmt->execute([$customerId]);
        while ($row = $stmt->fetch()) {
            fputcsv($output, [$row['name'], $row['vat_number'], $row['fiscal_code'], $row['address'], $row['city'], $row['email'], $row['phone']], ';');
        }
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilità Pro</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary:#4299e1;--primary-dark:#3182ce;--success:#48bb78;--warning:#ed8936;--danger:#f56565;--dark:#1a202c;--gray:#718096;--light:#f7fafc;--border:#e2e8f0}
        body{font-family:-apple-system,sans-serif;background:var(--light);min-height:100vh}
        .app{display:flex;min-height:100vh}
        .sidebar{width:220px;background:var(--dark);color:#fff;position:fixed;height:100vh;display:flex;flex-direction:column}
        .sidebar-header{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
        .sidebar nav{flex:1;overflow-y:auto;padding-bottom:10px}
        .sidebar-footer{padding:15px 20px;border-top:1px solid rgba(255,255,255,.1);background:rgba(0,0,0,.2);flex-shrink:0}
        .sidebar-logo{font-size:1.2rem;font-weight:800}
        .sidebar-plan{font-size:.7rem;color:var(--primary);margin-top:5px}
        .nav-section{padding:15px 0}
        .nav-section-title{font-size:.65rem;text-transform:uppercase;color:var(--gray);padding:0 20px;margin-bottom:8px}
        .nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.7);text-decoration:none;border-left:3px solid transparent;font-size:.85rem}
        .nav-item:hover,.nav-item.active{background:rgba(255,255,255,.1);color:#fff;border-left-color:var(--primary)}
        .nav-item.disabled{opacity:.4;cursor:not-allowed}
        .nav-item .badge{margin-left:auto;background:var(--danger);padding:2px 6px;border-radius:8px;font-size:.65rem}
        .nav-item .pro{margin-left:auto;background:var(--warning);padding:2px 5px;border-radius:3px;font-size:.55rem;font-weight:700}
        .user-menu{padding:15px 20px;border-top:1px solid rgba(255,255,255,.1);background:var(--dark);flex-shrink:0}
        .main{flex:1;margin-left:220px;padding:25px}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
        .page-title{font-size:1.4rem;color:var(--dark)}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:15px;margin-bottom:20px}
        .stat{background:#fff;padding:15px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .stat-icon{font-size:1.3rem;margin-bottom:5px}
        .stat-value{font-size:1.3rem;font-weight:800;color:var(--dark)}
        .stat-label{color:var(--gray);font-size:.75rem;margin-top:3px}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-weight:600;text-decoration:none;border:none;cursor:pointer;font-size:.85rem}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-success{background:var(--success);color:#fff}
        .btn-outline{background:transparent;border:2px solid var(--border);color:var(--dark)}
        .btn-sm{padding:6px 12px;font-size:.75rem}
        .card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
        .card-header{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
        .card-header h3{font-size:1rem}
        .card-body{padding:0}
        .card-body.pad{padding:20px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 15px;text-align:left;border-bottom:1px solid var(--border);font-size:.8rem}
        th{background:var(--light);font-weight:600;color:var(--gray);font-size:.7rem;text-transform:uppercase}
        .badge{display:inline-block;padding:3px 10px;border-radius:15px;font-size:.65rem;font-weight:600}
        .badge-green{background:rgba(72,187,120,.1);color:var(--success)}
        .badge-orange{background:rgba(237,137,54,.1);color:var(--warning)}
        .badge-red{background:rgba(245,101,101,.1);color:var(--danger)}
        .badge-blue{background:rgba(66,153,225,.12);color:var(--primary)}
        .badge-gray{background:rgba(113,128,150,.12);color:var(--gray)}
        .quote-filters{display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap}
        .quote-filter{padding:7px 14px;border-radius:20px;background:#fff;border:1px solid var(--border);color:var(--gray);text-decoration:none;font-size:.78rem;font-weight:600}
        .quote-filter:hover{border-color:var(--primary);color:var(--primary)}
        .quote-filter.active{background:var(--primary);border-color:var(--primary);color:#fff}
        .quote-filters .badge,td .badge{white-space:nowrap}
        .quote-actions-bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;background:#fff;padding:12px 15px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .empty{text-align:center;padding:40px;color:var(--gray)}
        .empty-icon{font-size:3rem;margin-bottom:10px;opacity:.4}
        .banner{padding:10px 20px;border-radius:10px;margin-bottom:20px;font-size:.85rem}
        .trial-banner{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;display:flex;justify-content:space-between;align-items:center}
        .trial-banner a{background:#fff;color:var(--primary);padding:5px 12px;border-radius:5px;text-decoration:none;font-weight:600;font-size:.8rem}
        .upgrade-banner{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .upgrade-banner a{color:#fff;text-decoration:underline}
        .alert{padding:12px 18px;border-radius:8px;margin-bottom:15px}
        .alert-success{background:rgba(72,187,120,.1);color:var(--success);border:1px solid var(--success)}
        .alert-error{background:rgba(245,101,101,.1);color:var(--danger);border:1px solid var(--danger)}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:.8rem}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border:2px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--primary)}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
        .modal.active{display:flex}
        .modal-content{background:#fff;border-radius:12px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto}
        .modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#fff;z-index:10}
        .modal-header h3{font-size:1.1rem}
        .modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--gray)}
        .modal-body{padding:22px}
        .modal-footer{padding:15px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
        .invoice-items-table{width:100%;border:1px solid var(--border);border-radius:8px;margin-bottom:15px}
        .invoice-items-table th,.invoice-items-table td{padding:8px 10px;font-size:.8rem}
        .invoice-items-table input{width:100%;padding:6px;border:1px solid var(--border);border-radius:4px;font-size:.8rem}
        .invoice-items-table .qty-input{width:60px}
        .invoice-items-table .price-input{width:80px}
        .invoice-items-table .vat-input{width:60px}
        .add-item-btn{background:var(--light);border:2px dashed var(--border);padding:10px;text-align:center;cursor:pointer;border-radius:8px;color:var(--gray);margin-bottom:15px}
        .add-item-btn:hover{border-color:var(--primary);color:var(--primary)}
        .invoice-totals{background:var(--light);padding:15px;border-radius:8px;margin-bottom:15px}
        .invoice-totals .row{display:flex;justify-content:space-between;padding:5px 0}
        .invoice-totals .total{font-size:1.2rem;font-weight:700;border-top:1px solid var(--border);padding-top:10px;margin-top:10px}
        @media(max-width:900px){.sidebar{width:60px}.sidebar-header span,.nav-section-title,.nav-item span:not(:first-child),.sidebar-plan,.user-menu>div{display:none}.nav-item{justify-content:center;padding:12px}.main{margin-left:60px}}
        @media(max-width:600px){.sidebar{display:none}.main{margin-left:0}.form-row,.form-row-3{grid-template-columns:1fr}}
    </style>
</head>
<body>
<!-- News Ticker -->
<div id="newsTicker" data-target="accounting" data-style="dark"></div>
<script src="/js/news-ticker.js"></script>

<div class="app">
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">📊 <span>Contabilità</span></div>
        <div class="sidebar-plan"><?php echo $isPro ? '⭐ Pro' : 'Starter'; ?></div>
    </div>
    <nav>
        <div class="nav-section">
            <div class="nav-section-title">Principale</div>
            <a href="?page=dashboard" class="nav-item <?php echo $page==='dashboard'?'active':''; ?>"><span>📊</span><span>Dashboard</span></a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Fatturazione</div>
            <a href="?page=invoices" class="nav-item <?php echo $page==='invoices'?'active':''; ?>"><span>📄</span><span>Fatture Emesse</span></a>
            <a href="?page=quotes" class="nav-item <?php echo ($page==='quotes'||$page==='quote_detail')?'active':''; ?>"><span>📋</span><span>Preventivi</span><?php if($quotesPending>0): ?><span class="badge" style="background:var(--primary)"><?php echo $quotesPending; ?></span><?php endif; ?></a>
            <?php if($isPro): ?>
            <a href="?page=passive" class="nav-item <?php echo $page==='passive'?'active':''; ?>"><span>📥</span><span>Fatture Ricevute</span></a>
            <?php else: ?>
            <a href="#" class="nav-item disabled" onclick="showUpgrade();return false"><span>📥</span><span>Fatture Ricevute</span><span class="pro">PRO</span></a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Contabilità</div>
            <?php if($isPro): ?>
            <a href="?page=prima-nota" class="nav-item <?php echo $page==='prima-nota'?'active':''; ?>"><span>📝</span><span>Prima Nota</span></a>
            <a href="?page=scadenzario" class="nav-item <?php echo $page==='scadenzario'?'active':''; ?>"><span>📅</span><span>Scadenzario</span><?php if($upcomingDeadlines>0): ?><span class="badge"><?php echo $upcomingDeadlines; ?></span><?php endif; ?></a>
            <?php else: ?>
            <a href="#" class="nav-item disabled" onclick="showUpgrade();return false"><span>📝</span><span>Prima Nota</span><span class="pro">PRO</span></a>
            <a href="#" class="nav-item disabled" onclick="showUpgrade();return false"><span>📅</span><span>Scadenzario</span><span class="pro">PRO</span></a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Anagrafiche</div>
            <a href="?page=clients" class="nav-item <?php echo $page==='clients'?'active':''; ?>"><span>👥</span><span>Clienti</span></a>
            <?php if($isPro): ?>
            <a href="?page=suppliers" class="nav-item <?php echo $page==='suppliers'?'active':''; ?>"><span>🏭</span><span>Fornitori</span></a>
            <?php else: ?>
            <a href="#" class="nav-item disabled" onclick="showUpgrade();return false"><span>🏭</span><span>Fornitori</span><span class="pro">PRO</span></a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Impostazioni</div>
            <a href="?page=company" class="nav-item <?php echo $page==='company'?'active':''; ?>"><span>🏢</span><span>Dati Azienda</span></a>
            <?php if($isPro): ?>
            <a href="?page=export" class="nav-item <?php echo $page==='export'?'active':''; ?>"><span>📤</span><span>Export</span></a>
            <a href="?page=fiscal" class="nav-item <?php echo $page==='fiscal'?'active':''; ?>"><span>🧮</span><span>Riepilogo Fiscale</span></a>
            <?php else: ?>
            <a href="#" class="nav-item disabled" onclick="showUpgrade();return false"><span>📤</span><span>Export</span><span class="pro">PRO</span></a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <div class="nav-section-title">Supporto</div>
            <a href="?page=help" class="nav-item <?php echo $page==='help'?'active':''; ?>"><span>❓</span><span>Aiuto</span></a>
        </div>
    </nav>
    <div class="user-menu">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:30px;height:30px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.75rem"><?php echo strtoupper(substr($customerName,0,1)); ?></div>
            <div style="font-size:.8rem"><?php echo htmlspecialchars($customerName); ?></div>
        </div>
        <a href="/app/dashboard.php" style="color:var(--gray);text-decoration:none;font-size:.7rem;display:block;margin-top:10px">← Dashboard</a>
    </div>
</aside>

<main class="main">
<?php if($isTrialing): ?>
<div class="banner trial-banner"><span>🧪 Prova - <?php echo max(0,(new DateTime($subscription['trial_end']))->diff(new DateTime())->days); ?> giorni</span><a href="/checkout-saas.html?plan=accounting_pro">Attiva</a></div>
<?php elseif(!$isPro): ?>
<div class="banner upgrade-banner">🚀 <strong>Passa a Pro</strong>: Fatture passive, Prima nota, Scadenzario, Export commercialista - <a href="/checkout-saas.html?plan=accounting_pro">Upgrade €25/mese</a></div>
<?php endif; ?>

<?php if($success): ?><div class="alert alert-success">✅ <?php echo $success; ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error">❌ <?php echo $error; ?></div><?php endif; ?>

<?php if($page === 'dashboard'): ?>
<div class="page-header"><div><h1 class="page-title">Dashboard</h1></div><div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-outline" onclick="openQuoteModal()">📋 Nuovo Preventivo</button><button class="btn btn-primary" onclick="openModal('invoiceModal')">➕ Nuova Fattura</button></div></div>
<div class="stats">
    <div class="stat"><div class="stat-icon">💰</div><div class="stat-value">€<?php echo number_format($revenueMonth,0,',','.'); ?></div><div class="stat-label">Fatturato Mese</div></div>
    <div class="stat"><div class="stat-icon">📄</div><div class="stat-value"><?php echo $invoicesMonth; ?><?php if(!$isPro): ?>/50<?php endif; ?></div><div class="stat-label">Fatture Mese</div></div>
    <div class="stat"><div class="stat-icon">⏳</div><div class="stat-value">€<?php echo number_format($toCollect,0,',','.'); ?></div><div class="stat-label">Da Incassare</div></div>
    <div class="stat"><div class="stat-icon">📅</div><div class="stat-value"><?php echo $upcomingDeadlines; ?></div><div class="stat-label">Scadenze</div></div>
    <div class="stat"><div class="stat-icon">📋</div><div class="stat-value"><?php echo $quotesPending; ?></div><div class="stat-label">Preventivi in attesa</div></div>
</div>
<div class="card"><div class="card-header"><h3>📄 Ultime Fatture</h3><a href="?page=invoices" class="btn btn-sm btn-outline">Tutte</a></div><div class="card-body">
<?php $stmt=$pdo->prepare("SELECT i.*,c.name as client_name FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id=c.id WHERE i.customer_id=? AND i.type='active' ORDER BY i.id DESC LIMIT 5");$stmt->execute([$customerId]);$invs=$stmt->fetchAll(); ?>
<?php if(empty($invs)): ?>
<div class="empty"><div class="empty-icon">📄</div><h3>Nessuna fattura</h3><button class="btn btn-primary" style="margin-top:15px" onclick="openModal('invoiceModal')">Crea</button></div>
<?php else: ?>
<table><thead><tr><th>Numero</th><th>Cliente</th><th>Totale</th><th>Stato</th></tr></thead><tbody>
<?php foreach($invs as $i): ?>
<tr><td><strong><?php echo htmlspecialchars($i['number']?:'BOZZA'); ?></strong></td><td><?php echo htmlspecialchars($i['client_name']?:'-'); ?></td><td>€<?php echo number_format($i['total'],2,',','.'); ?></td><td><span class="badge badge-<?php echo $i['payment_status']==='paid'?'green':'orange'; ?>"><?php echo $i['payment_status']==='paid'?'Pagata':'In attesa'; ?></span></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<div class="card"><div class="card-header"><h3>📋 Ultimi Preventivi</h3><a href="?page=quotes" class="btn btn-sm btn-outline">Tutti</a></div><div class="card-body">
<?php $stmt=$pdo->prepare("SELECT q.*,c.name as client_name FROM acc_quotes q LEFT JOIN acc_clients c ON q.client_id=c.id WHERE q.customer_id=? ORDER BY q.id DESC LIMIT 5");$stmt->execute([$customerId]);$dashQuotes=$stmt->fetchAll(); ?>
<?php if(empty($dashQuotes)): ?>
<div class="empty"><div class="empty-icon">📋</div><h3>Nessun preventivo</h3><p style="margin-top:6px;font-size:.85rem">Crea un preventivo, invialo via email o WhatsApp e trasformalo in fattura con un clic.</p><button class="btn btn-primary" style="margin-top:15px" onclick="openQuoteModal()">Crea Preventivo</button></div>
<?php else: ?>
<table><thead><tr><th>Numero</th><th>Cliente</th><th>Totale</th><th>Stato</th></tr></thead><tbody>
<?php foreach($dashQuotes as $q): $dInfo = accQuoteStatusInfo($q['status'], $q['valid_until']); ?>
<tr><td><a href="?page=quote_detail&id=<?php echo $q['id']; ?>" style="font-weight:700;color:var(--primary);text-decoration:none"><?php echo htmlspecialchars($q['number']); ?></a></td><td><?php echo htmlspecialchars($q['client_name']?:'-'); ?></td><td>€<?php echo number_format($q['total'],2,',','.'); ?></td><td><span class="badge badge-<?php echo $dInfo['badge']; ?>"><?php echo $dInfo['icon'].' '.$dInfo['label']; ?></span></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'invoices'): ?>
<div class="page-header"><div><h1 class="page-title">Fatture Emesse</h1></div><div style="display:flex;gap:8px"><button class="btn btn-outline" onclick="openModal('uploadXmlModal')">📤 Importa XML</button><button class="btn btn-primary" onclick="openModal('invoiceModal')">➕ Nuova Fattura</button></div></div>
<?php $stmt=$pdo->prepare("SELECT i.*,c.name as client_name FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id=c.id WHERE i.customer_id=? AND i.type='active' ORDER BY i.`date` DESC");$stmt->execute([$customerId]);$invs=$stmt->fetchAll(); ?>
<div class="card"><div class="card-body">
<?php if(empty($invs)): ?>
<div class="empty"><div class="empty-icon">📄</div><h3>Nessuna fattura</h3><button class="btn btn-primary" style="margin-top:15px" onclick="openModal('invoiceModal')">Crea Fattura</button></div>
<?php else: ?>
<table><thead><tr><th>Numero</th><th>Data</th><th>Cliente</th><th>Imponibile</th><th>IVA</th><th>Totale</th><th>Stato</th><th></th></tr></thead><tbody>
<?php foreach($invs as $i): ?>
<tr>
    <td><strong><?php echo htmlspecialchars($i['number']?:'BOZZA'); ?></strong></td>
    <td><?php echo $i['date']?date('d/m/Y',strtotime($i['date'])):'-'; ?></td>
    <td><?php echo htmlspecialchars($i['client_name']?:'-'); ?></td>
    <td>€<?php echo number_format($i['subtotal'],2,',','.'); ?></td>
    <td>€<?php echo number_format($i['vat_amount'],2,',','.'); ?></td>
    <td><strong>€<?php echo number_format($i['total'],2,',','.'); ?></strong></td>
    <td><span class="badge badge-<?php echo $i['payment_status']==='paid'?'green':'orange'; ?>"><?php echo $i['payment_status']==='paid'?'Pagata':'In attesa'; ?></span></td>
    <td>
        <a href="?page=invoice_detail&id=<?php echo $i['id']; ?>" class="btn btn-sm btn-outline">👁️</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Eliminare?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_invoices"><input type="hidden" name="delete_id" value="<?php echo $i['id']; ?>"><input type="hidden" name="return_page" value="invoices"><button class="btn btn-sm btn-outline">🗑️</button></form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'invoice_detail'): ?>
<?php
$invId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, c.name as client_name, c.vat_number as client_vat, c.address as client_address, c.city as client_city FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id = c.id WHERE i.id = ? AND i.customer_id = ?");
$stmt->execute([$invId, $customerId]);
$invoice = $stmt->fetch();
if (!$invoice) { header('Location: ?page=invoices'); exit; }
$stmt = $pdo->prepare("SELECT * FROM acc_invoice_items WHERE invoice_id = ?");
$stmt->execute([$invId]);
$items = $stmt->fetchAll();
?>
<div class="page-header"><div><h1 class="page-title">Fattura <?php echo htmlspecialchars($invoice['number']); ?></h1></div>
<div style="display:flex;gap:10px">
    <a href="?page=download_pdf&id=<?php echo $invId; ?>" class="btn btn-outline">📥 PDF</a>
    <a href="?page=invoices" class="btn btn-outline">← Indietro</a>
</div></div>
<div class="card"><div class="card-body pad">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <div><strong>Da:</strong><br><?php echo htmlspecialchars($company['name']); ?><br><?php echo htmlspecialchars($company['address']); ?><br><?php echo htmlspecialchars($company['city']); ?><br>P.IVA: <?php echo htmlspecialchars($company['vat_number']); ?></div>
    <div><strong>A:</strong><br><?php echo htmlspecialchars($invoice['client_name']); ?><br><?php echo htmlspecialchars($invoice['client_address']); ?><br><?php echo htmlspecialchars($invoice['client_city']); ?><br>P.IVA: <?php echo htmlspecialchars($invoice['client_vat']); ?></div>
</div>
<div style="margin-bottom:20px"><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($invoice['date'])); ?> | <strong>Scadenza:</strong> <?php echo $invoice['due_date'] ? date('d/m/Y', strtotime($invoice['due_date'])) : '-'; ?></div>
<table style="margin-bottom:20px"><thead><tr><th>Descrizione</th><th>Qtà</th><th>Prezzo</th><th>IVA</th><th>Totale</th></tr></thead><tbody>
<?php foreach($items as $it): ?>
<tr><td><?php echo htmlspecialchars($it['description']); ?></td><td><?php echo $it['quantity']; ?></td><td>€<?php echo number_format($it['unit_price'],2,',','.'); ?></td><td><?php echo $it['vat_rate']; ?>%</td><td>€<?php echo number_format($it['total'],2,',','.'); ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<div style="text-align:right">
    <div>Imponibile: €<?php echo number_format($invoice['subtotal'],2,',','.'); ?></div>
    <div>IVA: €<?php echo number_format($invoice['vat_amount'],2,',','.'); ?></div>
    <div style="font-size:1.3rem;font-weight:700;margin-top:10px">Totale: €<?php echo number_format($invoice['total'],2,',','.'); ?></div>
</div>
</div></div>

<?php elseif($page === 'quotes'): ?>
<!-- ==================== PREVENTIVI: ELENCO ==================== -->
<?php
$filtro = $_GET['filter'] ?? 'all';
$statiValidi = ['draft', 'sent', 'accepted', 'rejected', 'invoiced'];
$sqlFiltro = in_array($filtro, $statiValidi, true) ? " AND q.status = " . $pdo->quote($filtro) : '';
$stmt = $pdo->prepare("SELECT q.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone
    FROM acc_quotes q LEFT JOIN acc_clients c ON q.client_id = c.id
    WHERE q.customer_id = ?" . $sqlFiltro . " ORDER BY q.`date` DESC, q.id DESC");
$stmt->execute([$customerId]);
$quotes = $stmt->fetchAll();

$totQuotes = 0; $totAccepted = 0; $valoreInviati = 0; $valoreAccettati = 0;
try {
    $st = $pdo->prepare("SELECT status, COUNT(*) n, COALESCE(SUM(total),0) v FROM acc_quotes WHERE customer_id=? GROUP BY status");
    $st->execute([$customerId]);
    while ($r = $st->fetch()) {
        $totQuotes += $r['n'];
        if ($r['status'] === 'sent') $valoreInviati += $r['v'];
        if (in_array($r['status'], ['accepted', 'invoiced'], true)) { $totAccepted += $r['n']; $valoreAccettati += $r['v']; }
    }
} catch (Exception $e) {}
$conversione = $totQuotes > 0 ? round($totAccepted / $totQuotes * 100) : 0;

$quotesJs = [];
foreach ($quotes as $q) {
    $link = $q['public_token'] ? accQuotePublicUrl($q['public_token']) : '';
    $quotesJs[$q['id']] = [
        'number' => $q['number'],
        'client' => $q['client_name'],
        'email' => $q['client_email'],
        'phone' => accWhatsappNumber($q['client_phone']),
        'total' => accMoney($q['total']),
        'link' => $link,
        'msgWa' => accQuoteMessage(array_merge($q, ['client_name' => $q['client_name']]), $company, $link, 'whatsapp'),
        'msgMail' => accQuoteMessage(array_merge($q, ['client_name' => $q['client_name']]), $company, $link, 'email'),
        'subject' => 'Preventivo ' . $q['number'] . ($company['name'] ? ' - ' . $company['name'] : ''),
    ];
}
?>
<div class="page-header">
    <div><h1 class="page-title">📋 Preventivi</h1><p style="color:var(--gray);font-size:.85rem">Crea, invia e trasforma i preventivi in fatture</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if($isPro): ?><a href="?export=preventivi" class="btn btn-outline">📥 CSV</a><?php endif; ?>
        <button class="btn btn-primary" onclick="openQuoteModal()">➕ Nuovo Preventivo</button>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="stat-icon">📋</div><div class="stat-value"><?php echo $totQuotes; ?></div><div class="stat-label">Preventivi totali</div></div>
    <div class="stat"><div class="stat-icon">📨</div><div class="stat-value">€<?php echo number_format($valoreInviati,0,',','.'); ?></div><div class="stat-label">In attesa di risposta</div></div>
    <div class="stat"><div class="stat-icon">✅</div><div class="stat-value">€<?php echo number_format($valoreAccettati,0,',','.'); ?></div><div class="stat-label">Accettati</div></div>
    <div class="stat"><div class="stat-icon">📈</div><div class="stat-value"><?php echo $conversione; ?>%</div><div class="stat-label">Tasso di conversione</div></div>
</div>

<div class="quote-filters">
    <?php
    $filtri = ['all' => 'Tutti', 'draft' => '📝 Bozze', 'sent' => '📨 Inviati', 'accepted' => '✅ Accettati', 'rejected' => '❌ Rifiutati', 'invoiced' => '🧾 Fatturati'];
    foreach ($filtri as $k => $label):
    ?>
    <a href="?page=quotes&filter=<?php echo $k; ?>" class="quote-filter <?php echo $filtro===$k?'active':''; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
<?php if(empty($quotes)): ?>
<div class="empty">
    <div class="empty-icon">📋</div>
    <h3>Nessun preventivo</h3>
    <p style="margin-top:8px">Crea il tuo primo preventivo: potrai inviarlo via email o WhatsApp e scaricarlo in PDF.</p>
    <button class="btn btn-primary" style="margin-top:15px" onclick="openQuoteModal()">➕ Crea Preventivo</button>
</div>
<?php else: ?>
<div style="overflow-x:auto">
<table>
<thead><tr><th>Numero</th><th>Data</th><th>Cliente</th><th>Validità</th><th>Totale</th><th>Stato</th><th style="width:190px">Azioni</th></tr></thead>
<tbody>
<?php foreach($quotes as $q): $info = accQuoteStatusInfo($q['status'], $q['valid_until']); ?>
<tr>
    <td><a href="?page=quote_detail&id=<?php echo $q['id']; ?>" style="font-weight:700;color:var(--primary);text-decoration:none"><?php echo htmlspecialchars($q['number']); ?></a>
        <?php if($q['subject']): ?><br><small style="color:var(--gray)"><?php echo htmlspecialchars(mb_strimwidth($q['subject'],0,42,'…')); ?></small><?php endif; ?></td>
    <td><?php echo $q['date'] ? date('d/m/Y', strtotime($q['date'])) : '-'; ?></td>
    <td><?php echo htmlspecialchars($q['client_name'] ?: '-'); ?></td>
    <td><?php echo $q['valid_until'] ? date('d/m/Y', strtotime($q['valid_until'])) : '-'; ?></td>
    <td><strong>€<?php echo number_format($q['total'],2,',','.'); ?></strong></td>
    <td><span class="badge badge-<?php echo $info['badge']; ?>"><?php echo $info['icon'].' '.$info['label']; ?></span></td>
    <td style="white-space:nowrap">
        <a href="?page=quote_detail&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline" title="Apri">👁️</a>
        <a href="?quote_pdf=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline" title="Scarica PDF">📄</a>
        <button class="btn btn-sm btn-outline" onclick="openQuoteEmail(<?php echo $q['id']; ?>)" title="Invia via email">📧</button>
        <button class="btn btn-sm btn-outline" onclick="openQuoteWhatsapp(<?php echo $q['id']; ?>)" title="Invia su WhatsApp">💬</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('Eliminare il preventivo <?php echo htmlspecialchars($q['number']); ?>?')">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_quotes">
            <input type="hidden" name="delete_id" value="<?php echo $q['id']; ?>"><input type="hidden" name="return_page" value="quotes">
            <button class="btn btn-sm btn-outline" title="Elimina">🗑️</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div></div>
<script>var ACC_QUOTES = <?php echo json_encode($quotesJs, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script>


<?php elseif($page === 'quote_detail'): ?>
<!-- ==================== PREVENTIVI: DETTAGLIO ==================== -->
<?php
$quote = accLoadQuote($pdo, $customerId, (int)($_GET['id'] ?? 0));
if (!$quote):
?>
<div class="empty" style="padding:60px"><div class="empty-icon">🔍</div><h3>Preventivo non trovato</h3><a href="?page=quotes" class="btn btn-primary" style="margin-top:15px">← Torna ai preventivi</a></div>
<?php else:
$info = accQuoteStatusInfo($quote['status'], $quote['valid_until']);
$link = $quote['public_token'] ? accQuotePublicUrl($quote['public_token']) : '';
$quotesJs = [$quote['id'] => [
    'number' => $quote['number'],
    'client' => $quote['client_name'],
    'email' => $quote['client_email'],
    'phone' => accWhatsappNumber($quote['client_phone']),
    'total' => accMoney($quote['total']),
    'link' => $link,
    'msgWa' => accQuoteMessage($quote, $company, $link, 'whatsapp'),
    'msgMail' => accQuoteMessage($quote, $company, $link, 'email'),
    'subject' => 'Preventivo ' . $quote['number'] . ($company['name'] ? ' - ' . $company['name'] : ''),
]];
$editJs = [
    'id' => $quote['id'],
    'client_id' => $quote['client_id'],
    'date' => $quote['date'],
    'valid_until' => $quote['valid_until'],
    'subject' => $quote['subject'],
    'discount_percent' => (float)$quote['discount_percent'],
    'status' => $quote['status'] === 'invoiced' ? 'accepted' : $quote['status'],
    'notes' => $quote['notes'],
    'terms' => $quote['terms'],
    'payment_terms_text' => $quote['payment_terms_text'],
    'items' => array_map(function($i){ return ['desc'=>$i['description'],'qty'=>(float)$i['quantity'],'price'=>(float)$i['unit_price'],'vat'=>(float)$i['vat_rate']]; }, $quote['items']),
];
$imponibileNetto = (float)$quote['subtotal'] - (float)$quote['discount_amount'];
?>
<div class="page-header">
    <div>
        <a href="?page=quotes" style="color:var(--gray);text-decoration:none;font-size:.8rem">← Preventivi</a>
        <h1 class="page-title">Preventivo <?php echo htmlspecialchars($quote['number']); ?> <span class="badge badge-<?php echo $info['badge']; ?>" style="vertical-align:middle"><?php echo $info['icon'].' '.$info['label']; ?></span></h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-outline" onclick="openQuoteEdit()">✏️ Modifica</button>
        <a href="?quote_pdf=<?php echo $quote['id']; ?>" class="btn btn-outline">📄 Scarica PDF</a>
        <button class="btn btn-outline" onclick="openQuoteWhatsapp(<?php echo $quote['id']; ?>)">💬 WhatsApp</button>
        <button class="btn btn-primary" onclick="openQuoteEmail(<?php echo $quote['id']; ?>)">📧 Invia Email</button>
    </div>
</div>

<div class="quote-actions-bar">
    <?php if($quote['status'] !== 'accepted' && $quote['status'] !== 'invoiced'): ?>
    <form method="POST" style="display:inline"><input type="hidden" name="action" value="quote_status"><input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>"><input type="hidden" name="new_status" value="accepted"><button class="btn btn-sm btn-success">✅ Segna accettato</button></form>
    <?php endif; ?>
    <?php if($quote['status'] !== 'rejected' && $quote['status'] !== 'invoiced'): ?>
    <form method="POST" style="display:inline"><input type="hidden" name="action" value="quote_status"><input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>"><input type="hidden" name="new_status" value="rejected"><button class="btn btn-sm btn-outline">❌ Segna rifiutato</button></form>
    <?php endif; ?>
    <?php if(empty($quote['invoice_id'])): ?>
    <button class="btn btn-sm btn-outline" onclick="openModal('toInvoiceModal')">🧾 Converti in fattura</button>
    <?php else: ?>
    <a href="?page=invoice_detail&id=<?php echo $quote['invoice_id']; ?>" class="btn btn-sm btn-outline">🧾 Vai alla fattura</a>
    <?php endif; ?>
    <form method="POST" style="display:inline"><input type="hidden" name="action" value="duplicate_quote"><input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>"><button class="btn btn-sm btn-outline">📑 Duplica</button></form>
    <form method="POST" style="display:inline" onsubmit="return confirm('Eliminare questo preventivo?')">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_quotes">
        <input type="hidden" name="delete_id" value="<?php echo $quote['id']; ?>"><input type="hidden" name="return_page" value="quotes">
        <button class="btn btn-sm btn-outline">🗑️ Elimina</button>
    </form>
</div>

<?php if($link): ?>
<div class="card"><div class="card-body pad" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:220px">
        <div style="font-weight:600;font-size:.85rem;margin-bottom:4px">🔗 Link pubblico per il cliente</div>
        <input type="text" id="quoteLink" readonly value="<?php echo htmlspecialchars($link); ?>" style="width:100%;padding:8px;border:2px solid var(--border);border-radius:8px;font-size:.8rem;background:var(--light)">
        <div style="color:var(--gray);font-size:.75rem;margin-top:5px">Il cliente può aprire il preventivo, scaricarlo in PDF e accettarlo online.</div>
    </div>
    <button class="btn btn-outline" onclick="copyQuoteLink()">📋 Copia link</button>
    <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="btn btn-outline">👁️ Anteprima</a>
</div></div>
<?php endif; ?>

<div class="card">
<div class="card-header"><h3>Documento</h3><div style="color:var(--gray);font-size:.8rem">
    Creato il <?php echo date('d/m/Y', strtotime($quote['created_at'])); ?>
    <?php if($quote['sent_at']): ?> · Inviato il <?php echo date('d/m/Y H:i', strtotime($quote['sent_at'])); ?><?php if($quote['sent_channel']): ?> (<?php echo htmlspecialchars($quote['sent_channel']); ?>)<?php endif; ?><?php endif; ?>
    <?php if($quote['accepted_at']): ?> · Accettato il <?php echo date('d/m/Y', strtotime($quote['accepted_at'])); ?><?php endif; ?>
</div></div>
<div class="card-body pad">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:25px;margin-bottom:25px">
        <div>
            <div style="font-size:.7rem;color:var(--gray);font-weight:700;margin-bottom:6px">DA</div>
            <strong><?php echo htmlspecialchars($company['name']); ?></strong><br>
            <span style="color:var(--gray);font-size:.85rem">
            <?php echo htmlspecialchars($company['address']); ?><br>
            <?php echo htmlspecialchars(trim($company['zip'].' '.$company['city'])); ?><br>
            <?php if($company['vat_number']): ?>P.IVA <?php echo htmlspecialchars($company['vat_number']); ?><?php endif; ?>
            </span>
        </div>
        <div>
            <div style="font-size:.7rem;color:var(--gray);font-weight:700;margin-bottom:6px">SPETT.LE</div>
            <strong><?php echo htmlspecialchars($quote['client_name'] ?: '-'); ?></strong><br>
            <span style="color:var(--gray);font-size:.85rem">
            <?php echo htmlspecialchars($quote['client_address']); ?><br>
            <?php echo htmlspecialchars(trim($quote['client_zip'].' '.$quote['client_city'])); ?><br>
            <?php if($quote['client_vat']): ?>P.IVA <?php echo htmlspecialchars($quote['client_vat']); ?><?php endif; ?>
            <?php if($quote['client_email']): ?><br><?php echo htmlspecialchars($quote['client_email']); ?><?php endif; ?>
            </span>
        </div>
    </div>

    <div style="margin-bottom:18px;font-size:.9rem">
        <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($quote['date'])); ?>
        <?php if($quote['valid_until']): ?> &nbsp;|&nbsp; <strong>Valido fino al:</strong> <?php echo date('d/m/Y', strtotime($quote['valid_until'])); ?><?php endif; ?>
        <?php if($quote['subject']): ?><br><strong>Oggetto:</strong> <?php echo htmlspecialchars($quote['subject']); ?><?php endif; ?>
    </div>

    <div style="overflow-x:auto">
    <table style="border:1px solid var(--border);border-radius:8px">
    <thead><tr><th>Descrizione</th><th style="text-align:right">Qtà</th><th style="text-align:right">Prezzo</th><th style="text-align:right">IVA</th><th style="text-align:right">Totale</th></tr></thead>
    <tbody>
    <?php foreach($quote['items'] as $it): ?>
    <tr>
        <td><?php echo nl2br(htmlspecialchars($it['description'])); ?></td>
        <td style="text-align:right"><?php echo rtrim(rtrim(number_format($it['quantity'],2,',','.'),'0'),','); ?></td>
        <td style="text-align:right">€<?php echo number_format($it['unit_price'],2,',','.'); ?></td>
        <td style="text-align:right"><?php echo rtrim(rtrim(number_format($it['vat_rate'],2,',','.'),'0'),','); ?>%</td>
        <td style="text-align:right"><strong>€<?php echo number_format($it['total'],2,',','.'); ?></strong></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:20px">
        <div style="min-width:280px">
            <div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:var(--gray)">Imponibile</span><span>€<?php echo number_format($quote['subtotal'],2,',','.'); ?></span></div>
            <?php if((float)$quote['discount_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;color:var(--success)"><span>Sconto <?php echo rtrim(rtrim(number_format($quote['discount_percent'],2,',','.'),'0'),','); ?>%</span><span>- €<?php echo number_format($quote['discount_amount'],2,',','.'); ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:var(--gray)">Imponibile netto</span><span>€<?php echo number_format($imponibileNetto,2,',','.'); ?></span></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:var(--gray)">IVA</span><span>€<?php echo number_format($quote['vat_amount'],2,',','.'); ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:12px 0;border-top:2px solid var(--border);margin-top:8px;font-size:1.2rem;font-weight:800"><span>TOTALE</span><span style="color:var(--primary)">€<?php echo number_format($quote['total'],2,',','.'); ?></span></div>
        </div>
    </div>

    <?php if($quote['payment_terms_text'] || $quote['notes'] || $quote['terms']): ?>
    <div style="margin-top:25px;padding-top:20px;border-top:1px solid var(--border);font-size:.85rem;line-height:1.6">
        <?php if($quote['payment_terms_text']): ?><p><strong>Modalità di pagamento:</strong> <?php echo htmlspecialchars($quote['payment_terms_text']); ?></p><?php endif; ?>
        <?php if($quote['notes']): ?><p style="margin-top:8px"><strong>Note:</strong> <?php echo nl2br(htmlspecialchars($quote['notes'])); ?></p><?php endif; ?>
        <?php if($quote['terms']): ?><p style="margin-top:8px;color:var(--gray)"><strong>Condizioni:</strong> <?php echo nl2br(htmlspecialchars($quote['terms'])); ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Modal conversione in fattura -->
<div class="modal" id="toInvoiceModal">
<div class="modal-content" style="max-width:460px">
<div class="modal-header"><h3>🧾 Converti in fattura</h3><button class="modal-close" onclick="closeModal('toInvoiceModal')">&times;</button></div>
<form method="POST"><div class="modal-body">
<input type="hidden" name="action" value="quote_to_invoice">
<input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
<p style="color:var(--gray);font-size:.85rem;margin-bottom:15px">Viene creata una fattura emessa con le stesse righe (sconto già applicato ai prezzi) e con il numero progressivo successivo.</p>
<div class="form-group"><label>Termini di pagamento (giorni)</label><input type="number" name="payment_days" value="30" min="0"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('toInvoiceModal')">Annulla</button><button type="submit" class="btn btn-primary">🧾 Crea fattura</button></div></form>
</div></div>

<script>
var ACC_QUOTES = <?php echo json_encode($quotesJs, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
var ACC_QUOTE_EDIT = <?php echo json_encode($editJs, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
</script>
<?php endif; ?>


<?php elseif($page === 'passive' && $isPro): ?>
<div class="page-header"><div><h1 class="page-title">Fatture Ricevute</h1></div>
<div style="display:flex;gap:10px">
    <button class="btn btn-outline" onclick="openModal('uploadXmlModal')">📤 Importa XML</button>
    <button class="btn btn-primary" onclick="openModal('passiveModal')">➕ Registra</button>
</div></div>
<?php $stmt=$pdo->prepare("SELECT i.*,c.name as supplier_name FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id=c.id WHERE i.customer_id=? AND i.type='passive' ORDER BY i.`date` DESC");$stmt->execute([$customerId]);$invs=$stmt->fetchAll(); ?>
<div class="card"><div class="card-body">
<?php if(empty($invs)): ?>
<div class="empty"><div class="empty-icon">📥</div><h3>Nessuna fattura</h3><p>Importa fatture XML o registrale manualmente</p></div>
<?php else: ?>
<table><thead><tr><th>Numero</th><th>Data</th><th>Fornitore</th><th>Totale</th><th>Stato</th><th></th></tr></thead><tbody>
<?php foreach($invs as $i): ?>
<tr>
    <td><strong><?php echo htmlspecialchars($i['number']); ?></strong><?php if($i['xml_file']): ?> <span class="badge badge-green">XML</span><?php endif; ?></td>
    <td><?php echo date('d/m/Y', strtotime($i['date'])); ?></td>
    <td><?php echo htmlspecialchars($i['supplier_name']?:'-'); ?></td>
    <td><strong>€<?php echo number_format($i['total'],2,',','.'); ?></strong></td>
    <td><span class="badge badge-<?php echo $i['payment_status']==='paid'?'green':'orange'; ?>"><?php echo $i['payment_status']==='paid'?'Pagata':'Da pagare'; ?></span></td>
    <td><form method="POST" style="display:inline" onsubmit="return confirm('Eliminare?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_invoices"><input type="hidden" name="delete_id" value="<?php echo $i['id']; ?>"><input type="hidden" name="return_page" value="passive"><button class="btn btn-sm btn-outline">🗑️</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'prima-nota' && $isPro): ?>
<div class="page-header"><div><h1 class="page-title">Prima Nota</h1></div><button class="btn btn-primary" onclick="openModal('transModal')">➕ Movimento</button></div>
<?php $stmt=$pdo->prepare("SELECT type,SUM(amount) as tot FROM acc_transactions WHERE customer_id=? AND MONTH(`date`)=MONTH(NOW()) GROUP BY type");$stmt->execute([$customerId]);$tots=['income'=>0,'expense'=>0];while($r=$stmt->fetch())$tots[$r['type']]=$r['tot']; ?>
<div class="stats" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat"><div class="stat-icon">📈</div><div class="stat-value">€<?php echo number_format($tots['income'],0,',','.'); ?></div><div class="stat-label">Entrate</div></div>
    <div class="stat"><div class="stat-icon">📉</div><div class="stat-value">€<?php echo number_format($tots['expense'],0,',','.'); ?></div><div class="stat-label">Uscite</div></div>
    <div class="stat"><div class="stat-icon">💰</div><div class="stat-value">€<?php echo number_format($tots['income']-$tots['expense'],0,',','.'); ?></div><div class="stat-label">Saldo</div></div>
</div>
<?php $stmt=$pdo->prepare("SELECT * FROM acc_transactions WHERE customer_id=? ORDER BY `date` DESC LIMIT 50");$stmt->execute([$customerId]);$trans=$stmt->fetchAll(); ?>
<div class="card"><div class="card-body">
<?php if(empty($trans)): ?>
<div class="empty"><div class="empty-icon">📝</div><h3>Nessun movimento</h3><button class="btn btn-primary" style="margin-top:15px" onclick="openModal('transModal')">Nuovo</button></div>
<?php else: ?>
<table><thead><tr><th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrizione</th><th>Importo</th><th></th></tr></thead><tbody>
<?php foreach($trans as $t): ?>
<tr>
    <td><?php echo date('d/m/Y',strtotime($t['date'])); ?></td>
    <td><span class="badge badge-<?php echo $t['type']==='income'?'green':'red'; ?>"><?php echo $t['type']==='income'?'Entrata':'Uscita'; ?></span></td>
    <td><?php echo htmlspecialchars($t['category']?:'-'); ?></td>
    <td><?php echo htmlspecialchars($t['description']); ?></td>
    <td style="font-weight:700;color:<?php echo $t['type']==='income'?'var(--success)':'var(--danger)'; ?>"><?php echo $t['type']==='income'?'+':'-'; ?>€<?php echo number_format($t['amount'],2,',','.'); ?></td>
    <td><form method="POST" style="display:inline" onsubmit="return confirm('Eliminare?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_transactions"><input type="hidden" name="delete_id" value="<?php echo $t['id']; ?>"><input type="hidden" name="return_page" value="prima-nota"><button class="btn btn-sm btn-outline">🗑️</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'scadenzario' && $isPro): ?>
<div class="page-header"><h1 class="page-title">Scadenzario</h1></div>
<?php $stmt=$pdo->prepare("SELECT d.*,c.name as client_name FROM acc_deadlines d LEFT JOIN acc_clients c ON d.client_id=c.id WHERE d.customer_id=? ORDER BY d.status,d.due_date");$stmt->execute([$customerId]);$dls=$stmt->fetchAll(); ?>
<div class="card"><div class="card-body">
<?php if(empty($dls)): ?>
<div class="empty"><div class="empty-icon">📅</div><h3>Nessuna scadenza</h3><p>Le scadenze vengono create dalle fatture</p></div>
<?php else: ?>
<table><thead><tr><th>Tipo</th><th>Descrizione</th><th>Importo</th><th>Scadenza</th><th>Stato</th><th></th></tr></thead><tbody>
<?php foreach($dls as $d): $over=$d['status']==='pending'&&strtotime($d['due_date'])<strtotime('today'); ?>
<tr>
    <td><span class="badge badge-<?php echo $d['type']==='receivable'?'green':'orange'; ?>"><?php echo $d['type']==='receivable'?'Incasso':'Pagamento'; ?></span></td>
    <td><strong><?php echo htmlspecialchars($d['description']); ?></strong><?php if($d['client_name']): ?><br><small style="color:var(--gray)"><?php echo htmlspecialchars($d['client_name']); ?></small><?php endif; ?></td>
    <td><strong>€<?php echo number_format($d['amount'],2,',','.'); ?></strong></td>
    <td><?php echo date('d/m/Y',strtotime($d['due_date'])); ?></td>
    <td><?php if($d['status']==='paid'): ?><span class="badge badge-green">✅ Pagata</span><?php elseif($over): ?><span class="badge badge-red">⚠️ Scaduta</span><?php else: ?><span class="badge badge-orange">⏳</span><?php endif; ?></td>
    <td><?php if($d['status']==='pending'): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="deadline_id" value="<?php echo $d['id']; ?>"><button class="btn btn-sm btn-success">✅</button></form><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'clients'): ?>
<div class="page-header"><h1 class="page-title">Clienti</h1><button class="btn btn-primary" onclick="openModal('clientModal')">➕ Nuovo</button></div>
<?php $stmt=$pdo->prepare("SELECT * FROM acc_clients WHERE customer_id=? AND type='client' ORDER BY name");$stmt->execute([$customerId]);$cls=$stmt->fetchAll(); ?>
<div class="card"><div class="card-body">
<?php if(empty($cls)): ?>
<div class="empty"><div class="empty-icon">👥</div><h3>Nessun cliente</h3><button class="btn btn-primary" style="margin-top:15px" onclick="openModal('clientModal')">Aggiungi</button></div>
<?php else: ?>
<table><thead><tr><th>Nome</th><th>P.IVA/CF</th><th>Città</th><th>Email</th><th></th></tr></thead><tbody>
<?php foreach($cls as $c): ?>
<tr>
    <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
    <td><?php echo htmlspecialchars($c['vat_number']?:$c['fiscal_code']?:'-'); ?></td>
    <td><?php echo htmlspecialchars($c['city']?:'-'); ?></td>
    <td><?php echo htmlspecialchars($c['email']?:'-'); ?></td>
    <td><form method="POST" style="display:inline" onsubmit="return confirm('Eliminare?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_clients"><input type="hidden" name="delete_id" value="<?php echo $c['id']; ?>"><input type="hidden" name="return_page" value="clients"><button class="btn btn-sm btn-outline">🗑️</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'suppliers' && $isPro): ?>
<div class="page-header"><h1 class="page-title">Fornitori</h1><button class="btn btn-primary" onclick="openModal('supplierModal')">➕ Nuovo</button></div>
<?php $stmt=$pdo->prepare("SELECT * FROM acc_clients WHERE customer_id=? AND type='supplier' ORDER BY name");$stmt->execute([$customerId]);$sups=$stmt->fetchAll(); ?>
<div class="card"><div class="card-body">
<?php if(empty($sups)): ?>
<div class="empty"><div class="empty-icon">🏭</div><h3>Nessun fornitore</h3><button class="btn btn-primary" style="margin-top:15px" onclick="openModal('supplierModal')">Aggiungi</button></div>
<?php else: ?>
<table><thead><tr><th>Nome</th><th>P.IVA</th><th>Città</th><th>Email</th><th></th></tr></thead><tbody>
<?php foreach($sups as $s): ?>
<tr>
    <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
    <td><?php echo htmlspecialchars($s['vat_number']?:'-'); ?></td>
    <td><?php echo htmlspecialchars($s['city']?:'-'); ?></td>
    <td><?php echo htmlspecialchars($s['email']?:'-'); ?></td>
    <td><form method="POST" style="display:inline" onsubmit="return confirm('Eliminare?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="acc_clients"><input type="hidden" name="delete_id" value="<?php echo $s['id']; ?>"><input type="hidden" name="return_page" value="suppliers"><button class="btn btn-sm btn-outline">🗑️</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div></div>

<?php elseif($page === 'company'): ?>
<div class="page-header"><h1 class="page-title">Dati Azienda</h1></div>
<?php if(isset($_GET['saved']) && $_GET['saved']==='company'): ?>
<div style="background:rgba(72,187,120,.1);color:var(--success);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid var(--success)">✅ Dati azienda salvati!</div>
<?php endif; ?>

        <!-- REGIME FISCALE E IVA -->
        <?php if(isset($_GET['saved']) && $_GET['saved']==='regime'): ?>
        <div style="background:rgba(72,187,120,.1);color:var(--success);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid var(--success)">✅ Regime fiscale e IVA aggiornati!</div>
        <?php endif; ?>
        
        <div class="card" style="margin-bottom:25px">
            <div class="card-header"><h3>🏛️ Regime Fiscale e IVA</h3></div>
            <div class="card-body pad">
                <form method="POST">
                    <input type="hidden" name="action" value="save_regime">
                    <div class="form-group">
                        <label>Regime Fiscale</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:8px">
                        <?php foreach($taxRegimes as $key => $regime): 
                            $currentRegime = $company['tax_regime'] ?? 'ordinario';
                            $isSelected = ($currentRegime === $key);
                        ?>
                        <label style="display:flex;gap:10px;padding:15px;border:2px solid <?php echo $isSelected ? 'var(--primary)' : 'var(--border)'; ?>;border-radius:12px;cursor:pointer;background:<?php echo $isSelected ? 'rgba(102,126,234,.05)' : '#fff'; ?>">
                            <input type="radio" name="tax_regime" value="<?php echo $key; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="display:none">
                            <span style="font-size:1.5rem"><?php echo $regime['icon']; ?></span>
                            <div>
                                <strong><?php echo $regime['name']; ?></strong>
                                <div style="font-size:.8rem;color:var(--gray);margin-top:3px"><?php echo $regime['desc']; ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-row" style="margin-top:20px">
                        <div class="form-group">
                            <label>Aliquota IVA Predefinita</label>
                            <select name="default_vat_rate">
                                <?php $dv = $company['default_vat_rate'] ?? 22; ?>
                                <option value="4" <?php echo $dv==4?'selected':''; ?>>4% - Minima (alimentari base)</option>
                                <option value="5" <?php echo $dv==5?'selected':''; ?>>5% - Ridotta</option>
                                <option value="10" <?php echo $dv==10?'selected':''; ?>>10% - Ridotta (alimentari, turismo)</option>
                                <option value="22" <?php echo $dv==22?'selected':''; ?>>22% - Ordinaria</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Aliquote IVA Disponibili</label>
                            <input type="text" name="vat_rates" value="<?php echo implode(',', json_decode($company['vat_rates'] ?? '[4,5,10,22]', true) ?: [4,5,10,22]); ?>">
                            <p class="form-help">Separate da virgola</p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>💰 Costo Marca da Bollo (€)</label>
                            <input type="number" name="stamp_duty" step="0.01" value="<?php echo number_format($company['stamp_duty'] ?? 2, 2, '.', ''); ?>">
                            <p class="form-help">Importo della marca da bollo sulle fatture esenti IVA</p>
                        </div>
                        <div class="form-group">
                            <label>Soglia Marca da Bollo (€)</label>
                            <input type="number" name="stamp_duty_threshold" step="0.01" value="<?php echo number_format($company['stamp_duty_threshold'] ?? 77.47, 2, '.', ''); ?>">
                            <p class="form-help">Bollo obbligatorio per fatture esenti sopra questa soglia</p>
                        </div>
                    </div>
                    
                    <!-- SEZIONE FORFETTARIO - visibile solo se regime = forfettario -->
                    <div id="forfettarioSection" style="display:<?php echo ($company['tax_regime'] ?? '') === 'forfettario' ? 'block' : 'none'; ?>;margin-top:25px;padding-top:25px;border-top:2px solid var(--border)">
                        <h4 style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.5rem">📋</span> Parametri Regime Forfettario
                        </h4>
                        
                        <div style="background:rgba(102,126,234,.05);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid var(--primary)">
                            <strong>💡 Come funziona il forfettario:</strong>
                            <p style="margin-top:8px;font-size:.9rem;color:var(--gray)">
                                Reddito Imponibile = Fatturato × Coefficiente di Redditività<br>
                                Imposta = Reddito Imponibile × Aliquota (15% o 5%)<br>
                                Contributi INPS = Reddito Imponibile × Aliquota INPS
                            </p>
                        </div>
                        
                        <!-- CODICE ATECO -->
                        <div class="form-group">
                            <label>🏷️ Codice ATECO</label>
                            <select name="ateco_code" id="atecoSelect" onchange="updateAteco()" style="width:100%">
                                <option value="">-- Seleziona attività --</option>
                                <?php foreach ($atecoGroups as $coeff => $group): ?>
                                <optgroup label="<?php echo $group['name']; ?>">
                                    <?php foreach ($group['codes'] as $code => $desc): 
                                        $selected = ($company['ateco_code'] ?? '') === (string)$code ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $code; ?>" data-coeff="<?php echo $coeff; ?>" data-desc="<?php echo htmlspecialchars($desc); ?>" <?php echo $selected; ?>>
                                        <?php echo $code; ?> - <?php echo $desc; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="ateco_description" id="atecoDescription" value="<?php echo htmlspecialchars($company['ateco_description'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📊 Coefficiente di Redditività (%)</label>
                                <input type="number" name="coefficient_percent" id="coefficientInput" step="1" min="0" max="100" value="<?php echo (int)($company['coefficient_percent'] ?? 78); ?>" readonly style="background:#f0f0f0">
                                <p class="form-help">Calcolato automaticamente dal codice ATECO</p>
                            </div>
                            <div class="form-group">
                                <label>💶 Aliquota Imposta Sostitutiva (%)</label>
                                <select name="tax_rate_percent">
                                    <?php $tr = $company['tax_rate_percent'] ?? 15; ?>
                                    <option value="5" <?php echo $tr==5?'selected':''; ?>>5% - Startup (primi 5 anni)</option>
                                    <option value="15" <?php echo $tr==15?'selected':''; ?>>15% - Ordinaria</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom:20px">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                                <input type="checkbox" name="startup_discount" value="1" <?php echo ($company['startup_discount'] ?? 0) ? 'checked' : ''; ?> style="width:20px;height:20px">
                                <span>🚀 Nuova attività (applica aliquota ridotta 5% per i primi 5 anni)</span>
                            </label>
                        </div>
                        
                        <!-- LIMITI REGIME FORFETTARIO -->
                        <div style="background:rgba(237,137,54,.1);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #ed8936">
                            <strong>⚠️ Limiti Regime Forfettario 2024:</strong>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px;font-size:.85rem">
                                <div>📊 Ricavi max: <strong>€85.000</strong></div>
                                <div>🚫 Uscita immediata: oltre <strong>€100.000</strong></div>
                                <div>💼 Reddito dipendente: max <strong>€30.000</strong></div>
                                <div>🛠️ Beni strumentali: max <strong>€20.000</strong></div>
                            </div>
                        </div>
                        
                        <!-- GESTIONE INPS -->
                        <div class="form-group">
                            <label>🏛️ Gestione Previdenziale INPS</label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:8px">
                            <?php foreach($inpsManagements as $key => $mgmt): 
                                $currentInps = $company['inps_management'] ?? 'separata';
                                $isSelected = ($currentInps === $key);
                            ?>
                            <label style="display:flex;gap:10px;padding:12px 15px;border:2px solid <?php echo $isSelected ? 'var(--primary)' : 'var(--border)'; ?>;border-radius:10px;cursor:pointer;background:<?php echo $isSelected ? 'rgba(102,126,234,.05)' : '#fff'; ?>" onclick="toggleCassaSelect('<?php echo $key; ?>')">
                                <input type="radio" name="inps_management" value="<?php echo $key; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="display:none">
                                <span style="font-size:1.3rem"><?php echo $mgmt['icon']; ?></span>
                                <div style="flex:1">
                                    <strong style="font-size:.9rem"><?php echo $mgmt['name']; ?></strong>
                                    <?php if($mgmt['rate'] > 0): ?>
                                    <span style="background:var(--primary);color:white;padding:2px 8px;border-radius:10px;font-size:.7rem;margin-left:5px"><?php echo $mgmt['rate']; ?>%</span>
                                    <?php endif; ?>
                                    <div style="font-size:.75rem;color:var(--gray);margin-top:2px"><?php echo $mgmt['desc']; ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- SELEZIONE CASSA PROFESSIONALE SPECIFICA -->
                        <div id="cassaProfessionaleSelect" class="form-group" style="display:<?php echo ($company['inps_management'] ?? '') === 'cassa' ? 'block' : 'none'; ?>">
                            <label>🎓 Seleziona la tua Cassa Professionale</label>
                            <select name="cassa_professionale" id="cassaProfSelect" onchange="updateCassaRate()">
                                <option value="">-- Seleziona cassa --</option>
                                <?php foreach($casseProfessionali as $key => $cassa): 
                                    $selected = ($company['cassa_professionale'] ?? '') === $key ? 'selected' : '';
                                    $totale = $cassa['sogg'] + $cassa['integ'] + $cassa['matern'];
                                ?>
                                <option value="<?php echo $key; ?>" data-sogg="<?php echo $cassa['sogg']; ?>" data-integ="<?php echo $cassa['integ']; ?>" data-matern="<?php echo $cassa['matern']; ?>" <?php echo $selected; ?>>
                                    <?php echo $cassa['name']; ?> (<?php echo number_format($totale, 2); ?>%)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="cassaDetails" style="margin-top:10px;padding:10px;background:#f0f0f0;border-radius:8px;font-size:.85rem;display:none">
                                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
                                    <div><strong>Soggettivo</strong><br><span id="cassaSogg">0%</span></div>
                                    <div><strong>Integrativo</strong><br><span id="cassaInteg">0%</span></div>
                                    <div><strong>Maternità</strong><br><span id="cassaMatern">0%</span></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SIMULATORE TASSE -->
                        <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:20px;border-radius:12px;margin-top:20px">
                            <h4 style="margin-bottom:15px">🧮 Simulatore Tasse Forfettario</h4>
                            <div class="form-group" style="margin-bottom:15px">
                                <label style="color:rgba(255,255,255,.8)">Fatturato Annuo Previsto (€)</label>
                                <input type="number" id="simFatturato" value="30000" style="background:rgba(255,255,255,.9);color:#333;border:none" oninput="calcSimulation()">
                            </div>
                            <div id="simResults" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px">
                                <div style="background:rgba(255,255,255,.15);padding:12px;border-radius:8px;text-align:center">
                                    <div style="font-size:.75rem;opacity:.8">Reddito Imponibile</div>
                                    <div style="font-size:1.3rem;font-weight:700" id="simReddito">€ 23.400</div>
                                </div>
                                <div style="background:rgba(255,255,255,.15);padding:12px;border-radius:8px;text-align:center">
                                    <div style="font-size:.75rem;opacity:.8">Imposta Sostitutiva</div>
                                    <div style="font-size:1.3rem;font-weight:700" id="simImposta">€ 3.510</div>
                                </div>
                                <div style="background:rgba(255,255,255,.15);padding:12px;border-radius:8px;text-align:center">
                                    <div style="font-size:.75rem;opacity:.8">Contributi INPS</div>
                                    <div style="font-size:1.3rem;font-weight:700" id="simInps">€ 6.100</div>
                                </div>
                                <div style="background:rgba(72,187,120,.3);padding:12px;border-radius:8px;text-align:center">
                                    <div style="font-size:.75rem;opacity:.8">Netto Stimato</div>
                                    <div style="font-size:1.3rem;font-weight:700" id="simNetto">€ 20.390</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SEZIONE ORDINARIO - visibile solo se regime = ordinario -->
                    <div id="ordinarioSection" style="display:<?php echo ($company['tax_regime'] ?? 'ordinario') === 'ordinario' ? 'block' : 'none'; ?>;margin-top:25px;padding-top:25px;border-top:2px solid var(--border)">
                        <h4 style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.5rem">🏢</span> Parametri Regime Ordinario
                        </h4>
                        
                        <div style="background:rgba(66,153,225,.1);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #4299e1">
                            <strong>💡 Come funziona il regime ordinario:</strong>
                            <p style="margin-top:8px;font-size:.9rem;color:var(--gray)">
                                IRPEF a scaglioni: 23% fino a €28.000, 35% fino a €50.000, 43% oltre<br>
                                + Addizionali regionali/comunali (~2%)<br>
                                + IRAP 3,9% (se applicabile) + IVA periodica + INPS
                            </p>
                        </div>
                        
                        <!-- CODICE ATECO per Ordinario -->
                        <div class="form-group">
                            <label>🏷️ Codice ATECO</label>
                            <select name="ateco_code" id="atecoSelectOrd" onchange="updateAtecoOrd()" style="width:100%">
                                <option value="">-- Seleziona attività --</option>
                                <?php foreach ($atecoGroups as $coeff => $group): ?>
                                <optgroup label="<?php echo $group['name']; ?>">
                                    <?php foreach ($group['codes'] as $code => $desc): 
                                        $selected = ($company['ateco_code'] ?? '') === (string)$code ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $code; ?>" data-coeff="<?php echo $coeff; ?>" data-desc="<?php echo htmlspecialchars($desc); ?>" <?php echo $selected; ?>>
                                        <?php echo $code; ?> - <?php echo $desc; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>🏛️ Regione (per addizionale)</label>
                                <select name="regione">
                                    <?php $reg = $company['regione'] ?? 'default'; ?>
                                    <option value="lombardia" <?php echo $reg=='lombardia'?'selected':''; ?>>Lombardia (1,23%)</option>
                                    <option value="piemonte" <?php echo $reg=='piemonte'?'selected':''; ?>>Piemonte (1,62%)</option>
                                    <option value="veneto" <?php echo $reg=='veneto'?'selected':''; ?>>Veneto (1,23%)</option>
                                    <option value="emilia_romagna" <?php echo $reg=='emilia_romagna'?'selected':''; ?>>Emilia-Romagna (1,33%)</option>
                                    <option value="toscana" <?php echo $reg=='toscana'?'selected':''; ?>>Toscana (1,42%)</option>
                                    <option value="lazio" <?php echo $reg=='lazio'?'selected':''; ?>>Lazio (1,73%)</option>
                                    <option value="campania" <?php echo $reg=='campania'?'selected':''; ?>>Campania (2,03%)</option>
                                    <option value="puglia" <?php echo $reg=='puglia'?'selected':''; ?>>Puglia (1,33%)</option>
                                    <option value="sicilia" <?php echo $reg=='sicilia'?'selected':''; ?>>Sicilia (1,23%)</option>
                                    <option value="sardegna" <?php echo $reg=='sardegna'?'selected':''; ?>>Sardegna (1,23%)</option>
                                    <option value="calabria" <?php echo $reg=='calabria'?'selected':''; ?>>Calabria (1,73%)</option>
                                    <option value="liguria" <?php echo $reg=='liguria'?'selected':''; ?>>Liguria (1,23%)</option>
                                    <option value="marche" <?php echo $reg=='marche'?'selected':''; ?>>Marche (1,23%)</option>
                                    <option value="abruzzo" <?php echo $reg=='abruzzo'?'selected':''; ?>>Abruzzo (1,73%)</option>
                                    <option value="friuli" <?php echo $reg=='friuli'?'selected':''; ?>>Friuli V.G. (0,70%)</option>
                                    <option value="trentino" <?php echo $reg=='trentino'?'selected':''; ?>>Trentino A.A. (1,23%)</option>
                                    <option value="umbria" <?php echo $reg=='umbria'?'selected':''; ?>>Umbria (1,23%)</option>
                                    <option value="basilicata" <?php echo $reg=='basilicata'?'selected':''; ?>>Basilicata (1,23%)</option>
                                    <option value="molise" <?php echo $reg=='molise'?'selected':''; ?>>Molise (1,73%)</option>
                                    <option value="valle_aosta" <?php echo $reg=='valle_aosta'?'selected':''; ?>>Valle d\'Aosta (1,23%)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>🏭 IRAP Applicabile?</label>
                                <select name="irap_applicable">
                                    <?php $irap = $company['irap_applicable'] ?? '1'; ?>
                                    <option value="1" <?php echo $irap=='1'?'selected':''; ?>>Sì - Aliquota 3,9%</option>
                                    <option value="0" <?php echo $irap=='0'?'selected':''; ?>>No - Esente</option>
                                </select>
                                <p class="form-help">Professionisti senza organizzazione stabile sono esenti</p>
                            </div>
                        </div>
                        
                        <!-- GESTIONE INPS per Ordinario -->
                        <div class="form-group">
                            <label>🏛️ Gestione Previdenziale INPS</label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:8px">
                            <?php foreach($inpsManagements as $key => $mgmt): 
                                $currentInps = $company['inps_management'] ?? 'separata';
                                $isSelected = ($currentInps === $key);
                            ?>
                            <label style="display:flex;gap:10px;padding:12px 15px;border:2px solid <?php echo $isSelected ? '#4299e1' : 'var(--border)'; ?>;border-radius:10px;cursor:pointer;background:<?php echo $isSelected ? 'rgba(66,153,225,.05)' : '#fff'; ?>">
                                <input type="radio" name="inps_management" value="<?php echo $key; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="display:none" class="inpsRadioOrd">
                                <span style="font-size:1.3rem"><?php echo $mgmt['icon']; ?></span>
                                <div style="flex:1">
                                    <strong style="font-size:.9rem"><?php echo $mgmt['name']; ?></strong>
                                    <?php if($mgmt['rate'] > 0): ?>
                                    <span style="background:#4299e1;color:white;padding:2px 8px;border-radius:10px;font-size:.7rem;margin-left:5px"><?php echo $mgmt['rate']; ?>%</span>
                                    <?php endif; ?>
                                    <div style="font-size:.75rem;color:var(--gray);margin-top:2px"><?php echo $mgmt['desc']; ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- SIMULATORE ORDINARIO -->
                        <div style="background:linear-gradient(135deg,#4299e1,#3182ce);color:white;padding:20px;border-radius:12px;margin-top:20px">
                            <h4 style="margin-bottom:15px">🧮 Simulatore Tasse Regime Ordinario</h4>
                            <div class="form-group" style="margin-bottom:15px">
                                <label style="color:rgba(255,255,255,.8)">Reddito Imponibile Annuo (€)</label>
                                <input type="number" id="simRedditoOrd" value="40000" style="background:rgba(255,255,255,.9);color:#333;border:none" oninput="calcSimulationOrd()">
                            </div>
                            <div id="simResultsOrd" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
                                <div style="background:rgba(255,255,255,.15);padding:10px;border-radius:8px;text-align:center">
                                    <div style="font-size:.7rem;opacity:.8">IRPEF</div>
                                    <div style="font-size:1.1rem;font-weight:700" id="simIrpef">€ 10.700</div>
                                </div>
                                <div style="background:rgba(255,255,255,.15);padding:10px;border-radius:8px;text-align:center">
                                    <div style="font-size:.7rem;opacity:.8">Add. Reg.</div>
                                    <div style="font-size:1.1rem;font-weight:700" id="simAddReg">€ 492</div>
                                </div>
                                <div style="background:rgba(255,255,255,.15);padding:10px;border-radius:8px;text-align:center">
                                    <div style="font-size:.7rem;opacity:.8">IRAP</div>
                                    <div style="font-size:1.1rem;font-weight:700" id="simIrap">€ 1.560</div>
                                </div>
                                <div style="background:rgba(255,255,255,.15);padding:10px;border-radius:8px;text-align:center">
                                    <div style="font-size:.7rem;opacity:.8">INPS</div>
                                    <div style="font-size:1.1rem;font-weight:700" id="simInpsOrd">€ 10.428</div>
                                </div>
                                <div style="background:rgba(72,187,120,.3);padding:10px;border-radius:8px;text-align:center">
                                    <div style="font-size:.7rem;opacity:.8">Totale Tasse</div>
                                    <div style="font-size:1.1rem;font-weight:700" id="simTotaleOrd">€ 23.180</div>
                                </div>
                                <div style="background:rgba(72,187,120,.5);padding:10px;border-radius:8px;text-align:center">
                                    <div style="font-size:.7rem;opacity:.8">Netto</div>
                                    <div style="font-size:1.1rem;font-weight:700" id="simNettoOrd">€ 16.820</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SEZIONE SEMPLIFICATO - visibile solo se regime = semplificato -->
                    <div id="semplificatoSection" style="display:<?php echo ($company['tax_regime'] ?? '') === 'semplificato' ? 'block' : 'none'; ?>;margin-top:25px;padding-top:25px;border-top:2px solid var(--border)">
                        <h4 style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.5rem">📝</span> Parametri Regime Semplificato
                        </h4>
                        
                        <div style="background:rgba(72,187,120,.1);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #48bb78">
                            <strong>💡 Come funziona il regime semplificato:</strong>
                            <p style="margin-top:8px;font-size:.9rem;color:var(--gray)">
                                Stesso calcolo del regime ordinario (IRPEF a scaglioni + INPS)<br>
                                Ma contabilità semplificata: registri IVA, no bilancio, no libro giornale<br>
                                Limite ricavi: €500.000 (servizi) o €800.000 (commercio)
                            </p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📊 Tipo Attività</label>
                                <select name="tipo_attivita_semp">
                                    <?php $tipo = $company['tipo_attivita'] ?? 'servizi'; ?>
                                    <option value="servizi" <?php echo $tipo=='servizi'?'selected':''; ?>>Servizi (limite €500.000)</option>
                                    <option value="commercio" <?php echo $tipo=='commercio'?'selected':''; ?>>Commercio (limite €800.000)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>🏛️ Gestione INPS</label>
                                <select name="inps_management">
                                    <?php $inps = $company['inps_management'] ?? 'separata'; ?>
                                    <option value="separata" <?php echo $inps=='separata'?'selected':''; ?>>Gestione Separata (26,07%)</option>
                                    <option value="commercianti" <?php echo $inps=='commercianti'?'selected':''; ?>>INPS Commercianti (24,48%)</option>
                                    <option value="artigiani" <?php echo $inps=='artigiani'?'selected':''; ?>>INPS Artigiani (24,00%)</option>
                                    <option value="cassa" <?php echo $inps=='cassa'?'selected':''; ?>>Cassa Professionale</option>
                                </select>
                            </div>
                        </div>
                        
                        <p style="color:var(--gray);font-size:.85rem;margin-top:10px">
                            ℹ️ Il calcolo delle imposte è identico al regime ordinario. La differenza sta negli adempimenti contabili semplificati.
                        </p>
                    </div>
                    
                    <!-- SEZIONE MINIMI - visibile solo se regime = minimo -->
                    <div id="minimoSection" style="display:<?php echo ($company['tax_regime'] ?? '') === 'minimo' ? 'block' : 'none'; ?>;margin-top:25px;padding-top:25px;border-top:2px solid var(--border)">
                        <h4 style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
                            <span style="font-size:1.5rem">📌</span> Regime dei Minimi (in esaurimento)
                        </h4>
                        
                        <div style="background:rgba(237,137,54,.1);padding:15px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #ed8936">
                            <strong>⚠️ Regime in esaurimento:</strong>
                            <p style="margin-top:8px;font-size:.9rem;color:var(--gray)">
                                Non è più possibile accedere a questo regime dal 2016.<br>
                                Chi era già nel regime può continuare fino a scadenza naturale (5 anni o 35 anni di età).<br>
                                Imposta sostitutiva: <strong>5%</strong> sul reddito
                            </p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Anno di Ingresso nel Regime</label>
                                <input type="number" name="anno_ingresso_minimi" value="<?php echo $company['anno_ingresso_minimi'] ?? 2015; ?>" min="2008" max="2015">
                            </div>
                            <div class="form-group">
                                <label>🏛️ Gestione INPS</label>
                                <select name="inps_management">
                                    <?php $inps = $company['inps_management'] ?? 'separata'; ?>
                                    <option value="separata" <?php echo $inps=='separata'?'selected':''; ?>>Gestione Separata (26,07%)</option>
                                    <option value="cassa" <?php echo $inps=='cassa'?'selected':''; ?>>Cassa Professionale</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" style="margin-top:15px">💾 Salva Regime e IVA</button>
                </form>
                
                <script>
                // Toggle sezioni regime
                document.querySelectorAll('input[name="tax_regime"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        // Nascondi tutte le sezioni
                        document.getElementById('forfettarioSection').style.display = 'none';
                        document.getElementById('ordinarioSection').style.display = 'none';
                        document.getElementById('semplificatoSection').style.display = 'none';
                        document.getElementById('minimoSection').style.display = 'none';
                        
                        // Mostra la sezione corretta
                        if (this.value === 'forfettario') {
                            document.getElementById('forfettarioSection').style.display = 'block';
                        } else if (this.value === 'ordinario') {
                            document.getElementById('ordinarioSection').style.display = 'block';
                        } else if (this.value === 'semplificato') {
                            document.getElementById('semplificatoSection').style.display = 'block';
                        } else if (this.value === 'minimo') {
                            document.getElementById('minimoSection').style.display = 'block';
                        }
                    });
                });
                
                // Aggiorna coefficiente da ATECO
                function updateAteco() {
                    const sel = document.getElementById('atecoSelect');
                    const opt = sel.options[sel.selectedIndex];
                    if (opt && opt.dataset.coeff) {
                        document.getElementById('coefficientInput').value = opt.dataset.coeff;
                        document.getElementById('atecoDescription').value = opt.dataset.desc || '';
                        calcSimulation();
                    }
                }
                
                // Simulatore tasse
                function calcSimulation() {
                    const fatturato = parseFloat(document.getElementById('simFatturato').value) || 0;
                    const coeff = parseFloat(document.getElementById('coefficientInput').value) || 78;
                    const taxRate = parseFloat(document.querySelector('select[name="tax_rate_percent"]').value) || 15;
                    
                    // Trova aliquota INPS selezionata
                    const inpsRates = {separata: 26.07, commercianti: 24.48, artigiani: 24.00, cassa: 0, nessuna: 0};
                    const inpsSel = document.querySelector('input[name="inps_management"]:checked');
                    let inpsRate = inpsSel ? (inpsRates[inpsSel.value] || 0) : 26.07;
                    
                    // Se è cassa professionale, usa l'aliquota specifica
                    if (inpsSel && inpsSel.value === 'cassa') {
                        const cassaSel = document.getElementById('cassaProfSelect');
                        if (cassaSel && cassaSel.value) {
                            const opt = cassaSel.options[cassaSel.selectedIndex];
                            inpsRate = parseFloat(opt.dataset.sogg || 0) + parseFloat(opt.dataset.integ || 0) + parseFloat(opt.dataset.matern || 0);
                        }
                    }
                    
                    const reddito = fatturato * (coeff / 100);
                    const imposta = reddito * (taxRate / 100);
                    const inps = reddito * (inpsRate / 100);
                    const netto = fatturato - imposta - inps;
                    
                    // Verifica limite forfettario
                    let warning = '';
                    if (fatturato > 85000) {
                        warning = fatturato > 100000 ? '🚫 USCITA IMMEDIATA!' : '⚠️ Vicino al limite!';
                    }
                    
                    document.getElementById('simReddito').textContent = '€ ' + reddito.toLocaleString('it-IT', {maximumFractionDigits:0});
                    document.getElementById('simImposta').textContent = '€ ' + imposta.toLocaleString('it-IT', {maximumFractionDigits:0});
                    document.getElementById('simInps').textContent = '€ ' + inps.toLocaleString('it-IT', {maximumFractionDigits:0}) + (inpsSel?.value === 'cassa' && inpsRate > 0 ? ' (' + inpsRate.toFixed(1) + '%)' : '');
                    document.getElementById('simNetto').textContent = '€ ' + netto.toLocaleString('it-IT', {maximumFractionDigits:0}) + (warning ? ' ' + warning : '');
                }
                
                // Ricalcola quando cambia qualcosa
                document.querySelector('select[name="tax_rate_percent"]')?.addEventListener('change', calcSimulation);
                document.querySelectorAll('input[name="inps_management"]').forEach(r => r.addEventListener('change', calcSimulation));
                
                // Toggle selezione cassa professionale
                function toggleCassaSelect(inpsType) {
                    const cassaDiv = document.getElementById('cassaProfessionaleSelect');
                    if (cassaDiv) {
                        cassaDiv.style.display = inpsType === 'cassa' ? 'block' : 'none';
                        if (inpsType !== 'cassa') {
                            document.getElementById('cassaDetails').style.display = 'none';
                        }
                    }
                    calcSimulation();
                }
                
                // Aggiorna dettagli cassa professionale
                function updateCassaRate() {
                    const sel = document.getElementById('cassaProfSelect');
                    const details = document.getElementById('cassaDetails');
                    if (sel && sel.value && details) {
                        const opt = sel.options[sel.selectedIndex];
                        document.getElementById('cassaSogg').textContent = opt.dataset.sogg + '%';
                        document.getElementById('cassaInteg').textContent = opt.dataset.integ + '%';
                        document.getElementById('cassaMatern').textContent = opt.dataset.matern + '%';
                        details.style.display = 'block';
                    } else if (details) {
                        details.style.display = 'none';
                    }
                    calcSimulation();
                }
                
                // Calcolo iniziale
                calcSimulation();
                
                // ========== SIMULATORE REGIME ORDINARIO ==========
                function updateAtecoOrd() {
                    const sel = document.getElementById('atecoSelectOrd');
                    if (sel) {
                        const opt = sel.options[sel.selectedIndex];
                        // Per l'ordinario il codice ATECO serve solo per classificazione, non per coefficiente
                    }
                }
                
                function calcSimulationOrd() {
                    const reddito = parseFloat(document.getElementById('simRedditoOrd')?.value) || 0;
                    
                    // Calcolo IRPEF a scaglioni 2024
                    let irpef = 0;
                    if (reddito > 0) {
                        if (reddito <= 28000) {
                            irpef = reddito * 0.23;
                        } else if (reddito <= 50000) {
                            irpef = 28000 * 0.23 + (reddito - 28000) * 0.35;
                        } else {
                            irpef = 28000 * 0.23 + (50000 - 28000) * 0.35 + (reddito - 50000) * 0.43;
                        }
                    }
                    
                    // Addizionale regionale
                    const regioneRates = {
                        lombardia: 1.23, piemonte: 1.62, veneto: 1.23, emilia_romagna: 1.33,
                        toscana: 1.42, lazio: 1.73, campania: 2.03, puglia: 1.33,
                        sicilia: 1.23, sardegna: 1.23, calabria: 1.73, liguria: 1.23,
                        marche: 1.23, abruzzo: 1.73, friuli: 0.70, trentino: 1.23,
                        umbria: 1.23, basilicata: 1.23, molise: 1.73, valle_aosta: 1.23
                    };
                    const regSel = document.querySelector('select[name="regione"]');
                    const regRate = regSel ? (regioneRates[regSel.value] || 1.40) : 1.40;
                    const addRegionale = reddito * (regRate / 100);
                    
                    // IRAP
                    const irapSel = document.querySelector('select[name="irap_applicable"]');
                    const irapApplicabile = irapSel ? (irapSel.value === '1') : true;
                    const irap = irapApplicabile ? reddito * 0.039 : 0;
                    
                    // INPS
                    const inpsRates = {separata: 26.07, commercianti: 24.48, artigiani: 24.00, cassa: 0, nessuna: 0};
                    const inpsSel = document.querySelector('.inpsRadioOrd:checked') || document.querySelector('input[name="inps_management"]:checked');
                    const inpsRate = inpsSel ? (inpsRates[inpsSel.value] || 0) : 26.07;
                    const inps = reddito * (inpsRate / 100);
                    
                    const totale = irpef + addRegionale + irap + inps;
                    const netto = reddito - totale;
                    
                    // Aggiorna UI
                    if (document.getElementById('simIrpef')) {
                        document.getElementById('simIrpef').textContent = '€ ' + irpef.toLocaleString('it-IT', {maximumFractionDigits:0});
                        document.getElementById('simAddReg').textContent = '€ ' + addRegionale.toLocaleString('it-IT', {maximumFractionDigits:0});
                        document.getElementById('simIrap').textContent = irapApplicabile ? '€ ' + irap.toLocaleString('it-IT', {maximumFractionDigits:0}) : 'Esente';
                        document.getElementById('simInpsOrd').textContent = '€ ' + inps.toLocaleString('it-IT', {maximumFractionDigits:0});
                        document.getElementById('simTotaleOrd').textContent = '€ ' + totale.toLocaleString('it-IT', {maximumFractionDigits:0});
                        document.getElementById('simNettoOrd').textContent = '€ ' + netto.toLocaleString('it-IT', {maximumFractionDigits:0});
                    }
                }
                
                // Event listeners per ordinario
                document.querySelector('select[name="regione"]')?.addEventListener('change', calcSimulationOrd);
                document.querySelector('select[name="irap_applicable"]')?.addEventListener('change', calcSimulationOrd);
                document.querySelectorAll('.inpsRadioOrd').forEach(r => r.addEventListener('change', calcSimulationOrd));
                
                // Calcolo iniziale ordinario
                calcSimulationOrd();
                </script>
            </div>
        </div>

        <div class="card" style="margin-top:30px"><div class="card-header"><h3>🏢 Dati Azienda</h3></div><div class="card-body pad">
<form method="POST">
    <input type="hidden" name="action" value="save_company">
    <div class="form-row"><div class="form-group"><label>Ragione Sociale *</label><input type="text" name="name" value="<?php echo htmlspecialchars($company['name']); ?>" required></div><div class="form-group"><label>P.IVA</label><input type="text" name="vat_number" value="<?php echo htmlspecialchars($company['vat_number']??''); ?>"></div></div>
    <div class="form-row"><div class="form-group"><label>C.F.</label><input type="text" name="fiscal_code" value="<?php echo htmlspecialchars($company['fiscal_code']??''); ?>"></div><div class="form-group"><label>SDI</label><input type="text" name="sdi_code" value="<?php echo htmlspecialchars($company['sdi_code']??''); ?>" maxlength="7"></div></div>
    <div class="form-group"><label>Indirizzo</label><input type="text" name="address" value="<?php echo htmlspecialchars($company['address']??''); ?>"></div>
    <div class="form-row-3"><div class="form-group"><label>Città</label><input type="text" name="city" value="<?php echo htmlspecialchars($company['city']??''); ?>"></div><div class="form-group"><label>CAP</label><input type="text" name="zip" value="<?php echo htmlspecialchars($company['zip']??''); ?>"></div><div class="form-group"><label>Prov</label><input type="text" name="province" value="<?php echo htmlspecialchars($company['province']??''); ?>" maxlength="2"></div></div>
    <div class="form-row"><div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($company['email']??''); ?>"></div><div class="form-group"><label>PEC</label><input type="email" name="pec" value="<?php echo htmlspecialchars($company['pec']??''); ?>"></div></div>
    <div class="form-row"><div class="form-group"><label>Telefono</label><input type="text" name="phone" value="<?php echo htmlspecialchars($company['phone']??''); ?>"></div><div class="form-group"><label>Banca</label><input type="text" name="bank_name" value="<?php echo htmlspecialchars($company['bank_name']??''); ?>"></div></div>
    <div class="form-group"><label>IBAN</label><input type="text" name="bank_iban" value="<?php echo htmlspecialchars($company['bank_iban']??''); ?>"></div>
    <button type="submit" class="btn btn-primary">💾 Salva</button>
</form>
</div></div>

<?php elseif($page === 'export' && $isPro): ?>
<div class="page-header"><h1 class="page-title">📤 Export per Commercialista</h1></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px">
    <div class="card"><div class="card-body pad" style="text-align:center"><div style="font-size:3rem;margin-bottom:15px">📄</div><h3>Fatture Emesse</h3><p style="color:var(--gray);margin:10px 0 20px;font-size:.85rem">Esporta tutte le fatture attive</p><a href="?export=fatture_attive" class="btn btn-primary">📥 Scarica CSV</a></div></div>
    <div class="card"><div class="card-body pad" style="text-align:center"><div style="font-size:3rem;margin-bottom:15px">📥</div><h3>Fatture Ricevute</h3><p style="color:var(--gray);margin:10px 0 20px;font-size:.85rem">Esporta tutte le fatture passive</p><a href="?export=fatture_passive" class="btn btn-primary">📥 Scarica CSV</a></div></div>
    <div class="card"><div class="card-body pad" style="text-align:center"><div style="font-size:3rem;margin-bottom:15px">📝</div><h3>Prima Nota</h3><p style="color:var(--gray);margin:10px 0 20px;font-size:.85rem">Esporta tutti i movimenti</p><a href="?export=prima_nota" class="btn btn-primary">📥 Scarica CSV</a></div></div>
    <div class="card"><div class="card-body pad" style="text-align:center"><div style="font-size:3rem;margin-bottom:15px">📋</div><h3>Preventivi</h3><p style="color:var(--gray);margin:10px 0 20px;font-size:.85rem">Esporta tutti i preventivi</p><a href="?export=preventivi" class="btn btn-primary">📥 Scarica CSV</a></div></div>
    <div class="card"><div class="card-body pad" style="text-align:center"><div style="font-size:3rem;margin-bottom:15px">👥</div><h3>Anagrafica Clienti</h3><p style="color:var(--gray);margin:10px 0 20px;font-size:.85rem">Esporta tutti i clienti</p><a href="?export=clienti" class="btn btn-primary">📥 Scarica CSV</a></div></div>
</div>

<?php elseif($page === 'fiscal'): ?>
<?php
// === RIEPILOGO FISCALE ANNUALE ===
$fiscalYear = intval($_GET['year'] ?? date('Y'));
$prevYear = $fiscalYear - 1;

// Fatturato ANNO CORRENTE (per grafico e stats)
try {
    $stmtRev = $pdo->prepare("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count, 
        COALESCE(SUM(stamp_duty),0) as bolli,
        COALESCE(SUM(subtotal),0) as subtotal
        FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ? AND status != 'cancelled'");
    $stmtRev->execute([$customerId, $fiscalYear]);
    $yearData = $stmtRev->fetch();
} catch (Exception $e) {
    $stmtRev = $pdo->prepare("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count, 0 as bolli, COALESCE(SUM(total),0) as subtotal
        FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ?");
    $stmtRev->execute([$customerId, $fiscalYear]);
    $yearData = $stmtRev->fetch();
}
$yearRevenue = floatval($yearData['revenue']);
$yearInvoices = intval($yearData['count']);
$yearBolli = floatval($yearData['bolli'] ?? 0);
$yearSubtotal = floatval($yearData['subtotal'] ?? $yearRevenue);

// Fatturato ANNO PRECEDENTE (per calcolo tasse da pagare quest'anno)
try {
    $stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count, 
        COALESCE(SUM(stamp_duty),0) as bolli
        FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ? AND status != 'cancelled'");
    $stmtPrev->execute([$customerId, $prevYear]);
    $prevData = $stmtPrev->fetch();
} catch (Exception $e) {
    $stmtPrev = $pdo->prepare("SELECT COALESCE(SUM(total),0) as revenue, COUNT(*) as count, 0 as bolli
        FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ?");
    $stmtPrev->execute([$customerId, $prevYear]);
    $prevData = $stmtPrev->fetch();
}
$prevYearRevenue = floatval($prevData['revenue']);
$prevYearInvoices = intval($prevData['count']);
$prevYearBolli = floatval($prevData['bolli'] ?? 0);

// Fatture passive (costi)
$stmtCosts = $pdo->prepare("SELECT COALESCE(SUM(total),0) as costs, COUNT(*) as count FROM acc_invoices WHERE customer_id = ? AND type = 'passive' AND YEAR(`date`) = ?");
$stmtCosts->execute([$customerId, $fiscalYear]);
$costData = $stmtCosts->fetch();
$yearCosts = floatval($costData['costs']);

// Fatturato per mese
$stmtMonthly = $pdo->prepare("SELECT MONTH(`date`) as m, COALESCE(SUM(total),0) as rev, COUNT(*) as cnt 
    FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ? AND status != 'cancelled'
    GROUP BY MONTH(`date`) ORDER BY m");
$stmtMonthly->execute([$customerId, $fiscalYear]);
$monthlyData = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);
$monthlyMap = [];
foreach ($monthlyData as $md) $monthlyMap[intval($md['m'])] = $md;

// Da incassare
$stmtToCollect = $pdo->prepare("SELECT COALESCE(SUM(total),0) as amount, COUNT(*) as cnt FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ? AND payment_status = 'pending'");
$stmtToCollect->execute([$customerId, $fiscalYear]);
$toCollect = $stmtToCollect->fetch();

// Parametri regime
$regime = $company['tax_regime'] ?? 'forfettario';
$coefficient = floatval($company['coefficient_percent'] ?? 78);
$taxRate = floatval($company['tax_rate_percent'] ?? 15);
$startupDiscount = intval($company['startup_discount'] ?? 0);
$inpsMgmt = $company['inps_management'] ?? 'separata';
$effectiveTaxRate = $startupDiscount ? 5 : $taxRate;

// INPS rate
$inpsRate = 0; $inpsMinimali = 0;
if ($inpsMgmt === 'separata') { $inpsRate = 26.07; $inpsMinimali = 0; }
elseif ($inpsMgmt === 'commercianti') { $inpsRate = 24.48; $inpsMinimali = 4400; }
elseif ($inpsMgmt === 'artigiani') { $inpsRate = 24; $inpsMinimali = 4200; }

// === CALCOLI ANNO CORRENTE (previsione) ===
$redditoImponibile = round($yearRevenue * $coefficient / 100, 2);
$impostaSostitutiva = round($redditoImponibile * $effectiveTaxRate / 100, 2);
$contributiInps = max($inpsMinimali, round($redditoImponibile * $inpsRate / 100, 2));
$nettoStimato = round($yearRevenue - $impostaSostitutiva - $contributiInps, 2);

// === CALCOLI ANNO PRECEDENTE (tasse da pagare quest'anno) ===
$prevRedditoImponibile = round($prevYearRevenue * $coefficient / 100, 2);
$prevImpostaSostitutiva = round($prevRedditoImponibile * $effectiveTaxRate / 100, 2);
$prevContributiInps = max($inpsMinimali, round($prevRedditoImponibile * $inpsRate / 100, 2));

// === ANNO ANCORA PRIMA (per calcolare acconti già versati l'anno precedente) ===
$prevPrevYear = $fiscalYear - 2;
try {
    $stmtPP = $pdo->prepare("SELECT COALESCE(SUM(total),0) as revenue FROM acc_invoices WHERE customer_id = ? AND type = 'active' AND YEAR(`date`) = ? AND status != 'cancelled'");
    $stmtPP->execute([$customerId, $prevPrevYear]);
    $ppData = $stmtPP->fetch();
} catch (Exception $e) { $ppData = ['revenue' => 0]; }
$ppRevenue = floatval($ppData['revenue']);
$ppReddito = round($ppRevenue * $coefficient / 100, 2);
$ppImposta = round($ppReddito * $effectiveTaxRate / 100, 2);
$ppInps = max($inpsMinimali, round($ppReddito * $inpsRate / 100, 2));

// Acconti GIA' VERSATI nell'anno precedente (basati sull'anno ancora prima)
$accontiImpostaGiaVersati = $ppImposta; // 40% + 60% = 100% dell'imposta anno-2
$accontiInpsGiaVersati = $ppInps; // acconti INPS già versati

// SALDO IMPOSTA anno precedente = imposta dovuta - acconti già versati
$saldoImposta = max(0, round($prevImpostaSostitutiva - $accontiImpostaGiaVersati, 2));

// SALDO INPS anno precedente
$saldoInps = max(0, round($prevContributiInps - $accontiInpsGiaVersati, 2));

// ACCONTI per l'anno corrente (basati su anno precedente)
$accontoImposta1 = round($prevImpostaSostitutiva * 0.40, 2); // 40% a giugno
$accontoImposta2 = round($prevImpostaSostitutiva * 0.60, 2); // 60% a novembre
$accontoInps1 = round($prevContributiInps * 0.40, 2); // 40% a giugno
$accontoInps2 = round($prevContributiInps * 0.60, 2); // 60% a novembre

// Totale da pagare quest'anno
$totaleDaPagare = $saldoImposta + $accontoImposta1 + $accontoImposta2 + $saldoInps + $accontoInps1 + $accontoInps2 + $prevYearBolli;

// Carica pagamenti fiscali già effettuati
$paidPayments = [];
try {
    $stmtPaid = $pdo->prepare("SELECT deadline_key, paid_date, notes FROM acc_fiscal_payments WHERE customer_id = ? AND fiscal_year = ?");
    $stmtPaid->execute([$customerId, $fiscalYear]);
    while ($pp = $stmtPaid->fetch(PDO::FETCH_ASSOC)) {
        $paidPayments[$pp['deadline_key']] = $pp;
    }
} catch (Exception $e) {}

$mesiNomi = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
?>
<div class="page-header">
    <div>
        <h1 class="page-title">🧮 Riepilogo Fiscale <?=$fiscalYear?></h1>
        <p style="color:var(--gray);font-size:.85rem;margin-top:4px">
            Regime: <strong><?=ucfirst($regime)?></strong> · Coefficiente: <strong><?=$coefficient?>%</strong> · Imposta: <strong><?=$effectiveTaxRate?>%</strong>
            <?php if($startupDiscount):?> <span style="background:#c6f6d5;color:#276749;padding:2px 8px;border-radius:6px;font-size:.75rem">🌟 Startup 5%</span><?php endif;?>
        </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <a href="?page=fiscal&year=<?=$fiscalYear-1?>" class="btn btn-outline" style="padding:8px 14px">← <?=$fiscalYear-1?></a>
        <span style="font-weight:800;font-size:1.1rem;min-width:60px;text-align:center"><?=$fiscalYear?></span>
        <?php if($fiscalYear < intval(date('Y'))):?>
        <a href="?page=fiscal&year=<?=$fiscalYear+1?>" class="btn btn-outline" style="padding:8px 14px"><?=$fiscalYear+1?> →</a>
        <?php endif;?>
    </div>
</div>

<!-- STATS PRINCIPALI -->
<div class="stats">
    <div class="stat"><div class="stat-icon">💰</div><div class="stat-value">€<?=number_format($yearRevenue,0,',','.')?></div><div class="stat-label">Fatturato <?=$fiscalYear?></div></div>
    <div class="stat"><div class="stat-icon">📄</div><div class="stat-value"><?=$yearInvoices?></div><div class="stat-label">Fatture <?=$fiscalYear?></div></div>
    <div class="stat"><div class="stat-icon">🏦</div><div class="stat-value" style="color:var(--danger)">€<?=number_format($totaleDaPagare,0,',','.')?></div><div class="stat-label">Da pagare <?=$fiscalYear?></div></div>
    <div class="stat"><div class="stat-icon">💚</div><div class="stat-value">€<?=number_format($nettoStimato,0,',','.')?></div><div class="stat-label">Netto Stimato <?=$fiscalYear?></div></div>
</div>

<?php if($prevYearRevenue > 0): ?>
<!-- ALERT TASSE ANNO PRECEDENTE -->
<div style="background:linear-gradient(135deg,rgba(229,62,62,.08),rgba(237,137,54,.08));border:1px solid rgba(229,62,62,.2);border-radius:14px;padding:20px;margin-bottom:20px">
    <h3 style="font-size:1rem;margin-bottom:12px">⚠️ Tasse <?=$prevYear?> da pagare nel <?=$fiscalYear?></h3>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
        <div style="background:#fff;padding:12px;border-radius:10px;text-align:center">
            <div style="font-size:.75rem;color:var(--gray)">Fatturato <?=$prevYear?></div>
            <div style="font-size:1.3rem;font-weight:800">€<?=number_format($prevYearRevenue,0,',','.')?></div>
            <div style="font-size:.7rem;color:var(--gray)"><?=$prevYearInvoices?> fatture</div>
        </div>
        <div style="background:#fff;padding:12px;border-radius:10px;text-align:center">
            <div style="font-size:.75rem;color:var(--gray)">Saldo imposta <?=$prevYear?></div>
            <div style="font-size:1.3rem;font-weight:800;color:var(--danger)">€<?=number_format($saldoImposta,2,',','.')?></div>
            <div style="font-size:.7rem;color:var(--gray)">Imposta €<?=number_format($prevImpostaSostitutiva,0)?> - Acconti €<?=number_format($accontiImpostaGiaVersati,0)?></div>
        </div>
        <div style="background:#fff;padding:12px;border-radius:10px;text-align:center">
            <div style="font-size:.75rem;color:var(--gray)">Acconti <?=$fiscalYear?> (imposta)</div>
            <div style="font-size:1.3rem;font-weight:800;color:var(--warning)">€<?=number_format($accontoImposta1 + $accontoImposta2,2,',','.')?></div>
            <div style="font-size:.7rem;color:var(--gray)">Giu 40% + Nov 60%</div>
        </div>
        <div style="background:#fff;padding:12px;border-radius:10px;text-align:center">
            <div style="font-size:.75rem;color:var(--gray)">INPS (saldo + acconti)</div>
            <div style="font-size:1.3rem;font-weight:800;color:#3182ce">€<?=number_format($saldoInps + $accontoInps1 + $accontoInps2,2,',','.')?></div>
            <div style="font-size:.7rem;color:var(--gray)">Saldo + anticipo <?=$fiscalYear?></div>
        </div>
    </div>
    <?php if($prevYearBolli > 0):?>
    <div style="margin-top:10px;font-size:.85rem;color:var(--warning)">📋 + Bollo virtuale <?=$prevYear?>: <strong>€<?=number_format($prevYearBolli,2,',','.')?></strong> — scadenza 30/04/<?=$fiscalYear?> (cod. 2501)</div>
    <?php endif;?>
    <div style="margin-top:12px;padding:10px 14px;background:rgba(229,62,62,.06);border-radius:10px;text-align:center">
        <span style="font-size:.85rem;color:var(--gray)">TOTALE DA PAGARE NEL <?=$fiscalYear?>:</span>
        <span style="font-size:1.4rem;font-weight:800;color:var(--danger);margin-left:10px">€<?=number_format($totaleDaPagare,2,',','.')?></span>
    </div>
</div>
<?php endif; ?>

<!-- CALCOLO DETTAGLIATO TASSE -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <div class="card">
        <div class="card-header"><h3>🧮 Previsione Tasse <?=$fiscalYear?></h3><span style="font-size:.8rem;color:var(--gray)">Si pagheranno nel <?=$fiscalYear+1?></span></div>
        <div class="card-body">
            <table style="width:100%">
                <tr><td style="padding:10px 0;color:var(--gray)">Fatturato <?=$fiscalYear?> (finora)</td><td style="text-align:right;font-weight:600;padding:10px 0">€<?=number_format($yearRevenue,2,',','.')?></td></tr>
                <tr><td style="padding:10px 0;color:var(--gray)">Coefficiente redditività (<?=$coefficient?>%)</td><td style="text-align:right;font-weight:600;padding:10px 0">×<?=number_format($coefficient/100,2,',','.')?></td></tr>
                <tr style="border-top:2px solid var(--border)"><td style="padding:10px 0;font-weight:700">Reddito imponibile</td><td style="text-align:right;font-weight:800;padding:10px 0;color:var(--primary)">€<?=number_format($redditoImponibile,2,',','.')?></td></tr>
                <tr><td style="padding:10px 0;color:var(--gray)">Imposta sostitutiva (<?=$effectiveTaxRate?>%)</td><td style="text-align:right;font-weight:600;padding:10px 0;color:var(--danger)">- €<?=number_format($impostaSostitutiva,2,',','.')?></td></tr>
                <tr><td style="padding:10px 0;color:var(--gray)">Contributi INPS (<?=number_format($inpsRate,2)?>%)</td><td style="text-align:right;font-weight:600;padding:10px 0;color:var(--danger)">- €<?=number_format($contributiInps,2,',','.')?></td></tr>
                <tr style="border-top:3px solid var(--primary)"><td style="padding:12px 0;font-weight:800;font-size:1.1rem">NETTO STIMATO <?=$fiscalYear?></td><td style="text-align:right;font-weight:800;font-size:1.3rem;padding:12px 0;color:var(--success)">€<?=number_format($nettoStimato,2,',','.')?></td></tr>
            </table>
            
            <?php if($yearRevenue > 0): ?>
            <div style="margin-top:15px;padding:12px;background:rgba(102,126,234,.06);border-radius:10px;font-size:.85rem">
                <strong>📅 Nel <?=$fiscalYear+1?> pagherai circa:</strong>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:8px;text-align:center">
                    <div style="background:var(--card2);padding:8px;border-radius:8px">
                        <div style="font-size:.72rem;color:var(--gray)">Saldo imposta</div>
                        <div style="font-weight:700;color:var(--danger)">€<?=number_format(max(0,$impostaSostitutiva - ($accontoImposta1 + $accontoImposta2)),2,',','.')?></div>
                    </div>
                    <div style="background:var(--card2);padding:8px;border-radius:8px">
                        <div style="font-size:.72rem;color:var(--gray)">Acconti <?=$fiscalYear+1?></div>
                        <div style="font-weight:700;color:var(--warning)">€<?=number_format($impostaSostitutiva,2,',','.')?></div>
                    </div>
                    <div style="background:var(--card2);padding:8px;border-radius:8px">
                        <div style="font-size:.72rem;color:var(--gray)">INPS</div>
                        <div style="font-weight:700;color:#3182ce">€<?=number_format($contributiInps,2,',','.')?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="margin-top:10px;padding:10px;background:rgba(102,126,234,.04);border-radius:8px;font-size:.78rem;color:var(--gray)">
                ℹ️ Previsione basata sul fatturato <?=$fiscalYear?> ad oggi. Gli acconti <?=$fiscalYear?> (pagati quest'anno) verranno scalati dal saldo <?=$fiscalYear?> il prossimo anno.
            </div>
        </div>
    </div>
    
    <!-- SCADENZE F24 -->
    <div class="card">
        <div class="card-header"><h3>📅 Scadenze F24 — <?=$fiscalYear?></h3><span style="font-size:.8rem;color:var(--gray)">Saldo <?=$prevYear?> + Acconti <?=$fiscalYear?></span></div>
        <input type="hidden" name="fiscal_year_val" value="<?=$fiscalYear?>">
        <div class="card-body" style="padding:0">
            <?php
            $today = date('Y-m-d');
            $deadlines = [];
            
            // Bollo virtuale anno precedente (30 aprile)
            if ($prevYearBolli > 0) {
                $deadlines[] = ['date' => "$fiscalYear-04-30", 'label' => 'Bollo virtuale ' . $prevYear, 'type' => 'f24', 'amount' => $prevYearBolli, 'note' => 'Codice tributo 2501 — bolli fatture ' . $prevYear, 'code' => '2501'];
            }
            
            if ($inpsMgmt === 'separata') {
                // GESTIONE SEPARATA: 2 acconti + saldo
                // Giugno: Saldo INPS anno precedente + Acconto 1° anno corrente
                $deadlines[] = ['date' => "$fiscalYear-06-30", 'label' => 'Saldo INPS ' . $prevYear, 'type' => 'inps', 'amount' => $saldoInps, 'note' => "INPS €" . number_format($prevContributiInps,2,',','.') . " - Acconti versati €" . number_format($accontiInpsGiaVersati,2,',','.'), 'code' => 'P10'];
                $deadlines[] = ['date' => "$fiscalYear-06-30", 'label' => 'Acconto INPS 1° ' . $fiscalYear . ' (40%)', 'type' => 'inps', 'amount' => $accontoInps1, 'note' => "40% di €" . number_format($prevContributiInps,2,',','.'), 'code' => 'PXX'];
                $deadlines[] = ['date' => "$fiscalYear-11-30", 'label' => 'Acconto INPS 2° ' . $fiscalYear . ' (60%)', 'type' => 'inps', 'amount' => $accontoInps2, 'note' => "60% di €" . number_format($prevContributiInps,2,',','.'), 'code' => 'PXX'];
            } else {
                // COMMERCIANTI/ARTIGIANI: contributi fissi trimestrali
                $contribTrim = round($inpsMinimali / 4, 2);
                $deadlines[] = ['date' => "$fiscalYear-02-16", 'label' => 'INPS fissi 4° trim. ' . $prevYear, 'type' => 'inps', 'amount' => $contribTrim, 'note' => 'Contributi fissi trimestrali', 'code' => 'AF'];
                $deadlines[] = ['date' => "$fiscalYear-05-16", 'label' => 'INPS fissi 1° trim. ' . $fiscalYear, 'type' => 'inps', 'amount' => $contribTrim, 'note' => 'Contributi fissi trimestrali', 'code' => 'AF'];
                $deadlines[] = ['date' => "$fiscalYear-06-30", 'label' => 'Saldo INPS eccedenza ' . $prevYear, 'type' => 'inps', 'amount' => $saldoInps, 'note' => 'Eccedenza sul reddito oltre il minimale', 'code' => 'AF'];
                $deadlines[] = ['date' => "$fiscalYear-08-20", 'label' => 'INPS fissi 2° trim. ' . $fiscalYear, 'type' => 'inps', 'amount' => $contribTrim, 'note' => 'Contributi fissi trimestrali', 'code' => 'AF'];
                $deadlines[] = ['date' => "$fiscalYear-11-16", 'label' => 'INPS fissi 3° trim. ' . $fiscalYear, 'type' => 'inps', 'amount' => $contribTrim, 'note' => 'Contributi fissi trimestrali', 'code' => 'AF'];
            }
            
            // IMPOSTA SOSTITUTIVA: Saldo + Acconti
            // Giugno: Saldo anno precedente + Acconto 1° anno corrente
            $deadlines[] = ['date' => "$fiscalYear-06-30", 'label' => 'Saldo imposta ' . $prevYear, 'type' => 'f24', 'amount' => $saldoImposta, 'note' => "Imposta €" . number_format($prevImpostaSostitutiva,2,',','.') . " - Acconti versati €" . number_format($accontiImpostaGiaVersati,2,',','.'), 'code' => '1790'];
            $deadlines[] = ['date' => "$fiscalYear-06-30", 'label' => 'Acconto imposta 1° ' . $fiscalYear . ' (40%)', 'type' => 'f24', 'amount' => $accontoImposta1, 'note' => "40% di €" . number_format($prevImpostaSostitutiva,2,',','.') . " — Anticipo anno corrente", 'code' => '1791'];
            
            // Novembre: Acconto 2° anno corrente
            $deadlines[] = ['date' => "$fiscalYear-11-30", 'label' => 'Acconto imposta 2° ' . $fiscalYear . ' (60%)', 'type' => 'f24', 'amount' => $accontoImposta2, 'note' => "60% di €" . number_format($prevImpostaSostitutiva,2,',','.') . " — Anticipo anno corrente", 'code' => '1792'];
            
            // Rimuovi scadenze con importo 0 e ordina per data
            $deadlines = array_filter($deadlines, function($dl) { return $dl['amount'] > 0.01; });
            usort($deadlines, function($a, $b) { return strcmp($a['date'], $b['date']); });
            
            // Aggiungi chiave univoca a ogni scadenza
            foreach ($deadlines as &$dl) {
                $dl['key'] = $dl['date'] . '_' . ($dl['code'] ?? 'X');
            }
            unset($dl);
            
            $totalF24 = 0;
            $totalPaid = 0;
            foreach ($deadlines as $dl) {
                $totalF24 += $dl['amount'];
                if (isset($paidPayments[$dl['key']])) $totalPaid += $dl['amount'];
            }
            $totalUnpaid = $totalF24 - $totalPaid;
            ?>
            <table style="width:100%">
                <thead><tr><th style="padding:10px 12px">Scadenza</th><th>Descrizione</th><th style="text-align:center">Codice</th><th style="text-align:right">Importo</th><th style="text-align:center;padding-right:12px">Stato</th></tr></thead>
                <tbody>
                <?php foreach ($deadlines as $dl): 
                    $isPast = $dl['date'] < $today;
                    $isNear = !$isPast && $dl['date'] <= date('Y-m-d', strtotime('+30 days'));
                    $isPaid = isset($paidPayments[$dl['key']]);
                    $paidInfo = $isPaid ? $paidPayments[$dl['key']] : null;
                    $color = $isPaid ? 'var(--success)' : ($isPast ? 'var(--gray)' : ($isNear ? 'var(--warning)' : 'var(--dark)'));
                    $rowOpacity = $isPaid ? 'opacity:.6' : ($isPast && !$isPaid ? '' : '');
                ?>
                <tr style="<?=$rowOpacity?><?=$isPaid?'background:rgba(52,211,153,.04)':($isNear && !$isPaid?'background:rgba(251,191,36,.04)':'')?>">
                    <td style="padding:10px 12px;white-space:nowrap">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?=$isPaid?'var(--success)':($dl['type']==='f24'?'var(--danger)':'#3182ce')?>;margin-right:6px"></span>
                        <strong style="color:<?=$color?>;<?=$isPaid?'text-decoration:line-through':''?>"><?=date('d/m', strtotime($dl['date']))?></strong>
                        <?php if($isPaid):?><span style="font-size:.7rem;color:var(--success)">✅</span>
                        <?php elseif($isNear):?><span style="font-size:.7rem;color:var(--warning)">⚠️</span><?php endif;?>
                    </td>
                    <td style="padding:10px 4px">
                        <div style="font-size:.85rem;font-weight:600;color:<?=$color?>;<?=$isPaid?'text-decoration:line-through':''?>"><?=$dl['label']?></div>
                        <div style="font-size:.72rem;color:var(--gray)"><?=$dl['note']?></div>
                        <?php if($isPaid && $paidInfo):?>
                        <div style="font-size:.7rem;color:var(--success);margin-top:2px">✅ Pagato il <?=date('d/m/Y', strtotime($paidInfo['paid_date']))?><?=$paidInfo['notes']?' — '.$paidInfo['notes']:''?></div>
                        <?php endif;?>
                    </td>
                    <td style="text-align:center"><code style="background:<?=$dl['type']==='f24'?'rgba(229,62,62,.1)':'rgba(49,130,206,.1)'?>;padding:2px 8px;border-radius:5px;font-size:.78rem;font-weight:700;color:<?=$dl['type']==='f24'?'var(--danger)':'#3182ce'?>"><?=$dl['code']??''?></code></td>
                    <td style="text-align:right;padding:10px 8px;font-weight:700;color:<?=$color?>;<?=$isPaid?'text-decoration:line-through':''?>">€<?=number_format($dl['amount'],2,',','.')?></td>
                    <td style="text-align:center;padding:10px 12px">
                        <button onclick="togglePayment('<?=$dl['key']?>',<?=$dl['amount']?>,'<?=htmlspecialchars($dl['label'])?>')" 
                            style="padding:6px 12px;border:1px solid <?=$isPaid?'var(--success)':'var(--border)'?>;border-radius:8px;background:<?=$isPaid?'rgba(52,211,153,.1)':'#fff'?>;color:<?=$isPaid?'var(--success)':'var(--gray)'?>;font-size:.78rem;font-weight:600;cursor:pointer;white-space:nowrap">
                            <?=$isPaid?'✅ Pagato':'💳 Paga'?>
                        </button>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--border)">
                        <td colspan="3" style="padding:12px;font-weight:800">TOTALE</td>
                        <td style="text-align:right;padding:12px;font-weight:800;font-size:1.1rem;color:var(--danger)">€<?=number_format($totalF24,2,',','.')?></td>
                        <td style="text-align:center;padding:12px;font-size:.8rem">
                            <?php if($totalPaid > 0):?>
                            <span style="color:var(--success);font-weight:600">✅ €<?=number_format($totalPaid,0,',','.')?></span>
                            <?php endif;?>
                            <?php if($totalUnpaid > 0):?>
                            <span style="color:var(--danger);font-weight:600">📌 €<?=number_format($totalUnpaid,0,',','.')?></span>
                            <?php endif;?>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <div style="padding:10px 12px;display:flex;gap:15px;font-size:.75rem;color:var(--gray)">
                <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--danger);margin-right:4px"></span>Imposta sostitutiva</span>
                <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#3182ce;margin-right:4px"></span>INPS</span>
            </div>
        </div>
    </div>
</div>

<!-- COME PAGARE -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>💳 Come Pagare le Tasse</h3></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            
            <!-- F24 IMPOSTA SOSTITUTIVA -->
            <div style="background:rgba(229,62,62,.05);border:1px solid rgba(229,62,62,.15);border-radius:12px;padding:18px">
                <h4 style="font-size:.95rem;margin-bottom:12px;color:var(--danger)">🏦 Imposta Sostitutiva (F24)</h4>
                <div style="font-size:.85rem;line-height:1.7">
                    <strong>Codici Tributo:</strong>
                    <div style="margin:8px 0;display:grid;grid-template-columns:auto 1fr;gap:4px 10px;font-size:.82rem">
                        <code style="background:#fee2e2;padding:2px 8px;border-radius:4px;font-weight:700">1790</code><span>Saldo imposta sostitutiva</span>
                        <code style="background:#fee2e2;padding:2px 8px;border-radius:4px;font-weight:700">1791</code><span>Acconto 1° rata (40%)</span>
                        <code style="background:#fee2e2;padding:2px 8px;border-radius:4px;font-weight:700">1792</code><span>Acconto 2° rata (60%)</span>
                    </div>
                    <div style="margin-top:10px;padding:10px;background:#fff;border-radius:8px;font-size:.8rem">
                        <strong>Anno di riferimento:</strong> anno d'imposta (es. <?=$fiscalYear-1?> per il saldo, <?=$fiscalYear?> per gli acconti)<br>
                        <strong>Sezione:</strong> Erario<br>
                        <strong>Rateizzazione:</strong> 0101 se in unica soluzione
                    </div>
                </div>
            </div>
            
            <!-- INPS -->
            <div style="background:rgba(49,130,206,.05);border:1px solid rgba(49,130,206,.15);border-radius:12px;padding:18px">
                <h4 style="font-size:.95rem;margin-bottom:12px;color:#3182ce">🏥 Contributi INPS</h4>
                <div style="font-size:.85rem;line-height:1.7">
                    <?php if($inpsMgmt === 'separata'):?>
                    <strong>Gestione Separata — Codici Tributo:</strong>
                    <div style="margin:8px 0;display:grid;grid-template-columns:auto 1fr;gap:4px 10px;font-size:.82rem">
                        <code style="background:#dbeafe;padding:2px 8px;border-radius:4px;font-weight:700">PXX</code><span>Acconto contributi (F24 sezione INPS)</span>
                        <code style="background:#dbeafe;padding:2px 8px;border-radius:4px;font-weight:700">P10</code><span>Saldo contributi</span>
                    </div>
                    <div style="margin-top:10px;padding:10px;background:#fff;border-radius:8px;font-size:.8rem">
                        <strong>Sezione:</strong> INPS<br>
                        <strong>Filiale/Sede:</strong> la tua sede INPS di competenza<br>
                        <strong>Scadenze:</strong> 2 acconti (giugno 40% + novembre 60%) + saldo giugno anno dopo
                    </div>
                    <?php elseif($inpsMgmt === 'commercianti'):?>
                    <strong>Gestione Commercianti:</strong>
                    <div style="margin-top:6px;padding:10px;background:#fff;border-radius:8px;font-size:.8rem">
                        Contributi fissi trimestrali (16 feb, 16 mag, 20 ago, 16 nov) + eventuale eccedenza a giugno.<br>
                        <strong>Codice:</strong> AF - contributi fissi artigiani/commercianti
                    </div>
                    <?php elseif($inpsMgmt === 'artigiani'):?>
                    <strong>Gestione Artigiani:</strong>
                    <div style="margin-top:6px;padding:10px;background:#fff;border-radius:8px;font-size:.8rem">
                        Contributi fissi trimestrali + eccedenza sul reddito eccedente il minimale.<br>
                        <strong>Codice:</strong> AF - contributi fissi artigiani
                    </div>
                    <?php endif;?>
                </div>
            </div>
        </div>
        
        <!-- DOVE PAGARE -->
        <div style="margin-top:20px;padding:18px;background:var(--bg);border-radius:12px">
            <h4 style="font-size:.95rem;margin-bottom:12px">📍 Dove e come pagare il modello F24</h4>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px">
                <div style="background:#fff;padding:15px;border-radius:10px;text-align:center">
                    <div style="font-size:2rem;margin-bottom:8px">🌐</div>
                    <strong style="font-size:.85rem">Agenzia delle Entrate</strong>
                    <p style="font-size:.78rem;color:var(--gray);margin-top:6px">Accedi a <strong>Fisconline / Entratel</strong> sul sito dell'Agenzia delle Entrate → Servizi → Pagamenti F24 → Compila e invia</p>
                    <a href="https://www.agenziaentrate.gov.it" target="_blank" style="display:inline-block;margin-top:8px;font-size:.78rem;color:var(--primary);font-weight:600">agenziaentrate.gov.it →</a>
                </div>
                <div style="background:#fff;padding:15px;border-radius:10px;text-align:center">
                    <div style="font-size:2rem;margin-bottom:8px">🏦</div>
                    <strong style="font-size:.85rem">Home Banking</strong>
                    <p style="font-size:.78rem;color:var(--gray);margin-top:6px">Dalla tua banca online → sezione <strong>Pagamenti → F24</strong> → compila i campi con codici tributo, importi e anno di riferimento</p>
                </div>
                <div style="background:#fff;padding:15px;border-radius:10px;text-align:center">
                    <div style="font-size:2rem;margin-bottom:8px">👨‍💼</div>
                    <strong style="font-size:.85rem">Commercialista</strong>
                    <p style="font-size:.78rem;color:var(--gray);margin-top:6px">Esporta i dati dalla pagina <strong>Export</strong> e inviali al commercialista che preparerà gli F24 per te</p>
                </div>
            </div>
        </div>
        
        <!-- MARCA DA BOLLO -->
        <?php if($yearBolli > 0):?>
        <div style="margin-top:15px;padding:15px;background:rgba(237,137,54,.06);border:1px solid rgba(237,137,54,.2);border-radius:12px">
            <h4 style="font-size:.9rem;margin-bottom:8px;color:var(--warning)">📋 Marca da Bollo Virtuale</h4>
            <p style="font-size:.83rem;color:var(--gray);line-height:1.6">
                Hai emesso <strong><?=$yearInvoices?> fatture</strong> con bollo virtuale per un totale di <strong>€<?=number_format($yearBolli,2,',','.')?></strong>.
                Il bollo virtuale va versato con F24 entro il <strong>30 aprile <?=$fiscalYear+1?></strong> con codice tributo <code style="background:#fefcbf;padding:2px 6px;border-radius:4px;font-weight:700">2501</code> (sezione Erario, anno <?=$fiscalYear?>).
            </p>
        </div>
        <?php endif;?>
    </div>
</div>

<!-- FATTURATO MENSILE -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>📊 Fatturato Mensile <?=$fiscalYear?></h3></div>
    <div class="card-body">
        <?php $maxMonth = max(1, max(array_column($monthlyData, 'rev') ?: [1])); ?>
        <div style="display:flex;align-items:flex-end;gap:8px;height:180px;padding:0 5px">
            <?php for($m=1;$m<=12;$m++):
                $md = $monthlyMap[$m] ?? ['rev'=>0,'cnt'=>0];
                $rev = floatval($md['rev']);
                $pct = $maxMonth > 0 ? round($rev / $maxMonth * 100) : 0;
                $isCurrent = ($m == intval(date('n')) && $fiscalYear == intval(date('Y')));
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
                <div style="font-size:.65rem;font-weight:700;color:<?=$rev>0?'var(--primary)':'var(--gray)'?>"><?=$rev>0?'€'.number_format($rev,0,',','.'):''?></div>
                <div style="width:100%;height:<?=max($pct,3)?>px;background:<?=$isCurrent?'var(--warning)':($rev>0?'var(--primary)':'#edf2f7')?>;border-radius:4px 4px 0 0;min-height:3px"></div>
                <div style="font-size:.7rem;color:<?=$isCurrent?'var(--warning)':'var(--gray)'?>;font-weight:<?=$isCurrent?'700':'400'?>"><?=substr($mesiNomi[$m],0,3)?></div>
            </div>
            <?php endfor;?>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:15px;padding-top:12px;border-top:1px solid var(--border);font-size:.85rem">
            <span>Media mensile: <strong>€<?=number_format($yearRevenue/max(1,intval(date('n'))),0,',','.')?></strong></span>
            <span>Da incassare: <strong style="color:var(--warning)">€<?=number_format(floatval($toCollect['amount']),0,',','.')?></strong> (<?=$toCollect['cnt']?> fatture)</span>
            <?php if($regime === 'forfettario'):?>
            <span>Limite forfettario: <strong style="color:<?=$yearRevenue>85000?'var(--danger)':'var(--success)'?>"><?=number_format($yearRevenue/85000*100,0)?>% di €85.000</strong></span>
            <?php endif;?>
        </div>
    </div>
</div>

<!-- RIEPILOGO CLIENTI -->
<div class="card">
    <div class="card-header"><h3>👥 Top Clienti <?=$fiscalYear?></h3></div>
    <div class="card-body" style="padding:0">
        <?php
        $stmtTopCl = $pdo->prepare("SELECT c.name, COUNT(i.id) as cnt, SUM(i.total) as tot 
            FROM acc_invoices i LEFT JOIN acc_clients c ON i.client_id = c.id 
            WHERE i.customer_id = ? AND i.type = 'active' AND YEAR(i.`date`) = ? AND i.status != 'cancelled'
            GROUP BY i.client_id ORDER BY tot DESC LIMIT 10");
        $stmtTopCl->execute([$customerId, $fiscalYear]);
        $topClients = $stmtTopCl->fetchAll();
        ?>
        <?php if(empty($topClients)):?>
        <div style="padding:30px;text-align:center;color:var(--gray)">Nessuna fattura per <?=$fiscalYear?></div>
        <?php else:?>
        <table style="width:100%">
            <thead><tr><th style="padding:10px 15px">#</th><th>Cliente</th><th style="text-align:center">Fatture</th><th style="text-align:right;padding-right:15px">Totale</th><th style="text-align:right;padding-right:15px">%</th></tr></thead>
            <tbody>
            <?php foreach($topClients as $i => $tc): $pct = $yearRevenue > 0 ? round(floatval($tc['tot'])/$yearRevenue*100) : 0; ?>
            <tr>
                <td style="padding:8px 15px;font-weight:700;color:var(--gray)"><?=$i+1?></td>
                <td style="padding:8px;font-weight:600"><?=htmlspecialchars($tc['name'] ?: 'Senza cliente')?></td>
                <td style="padding:8px;text-align:center"><?=$tc['cnt']?></td>
                <td style="padding:8px 15px;text-align:right;font-weight:700">€<?=number_format($tc['tot'],2,',','.')?></td>
                <td style="padding:8px 15px;text-align:right">
                    <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end">
                        <div style="width:60px;height:6px;background:#edf2f7;border-radius:3px;overflow:hidden"><div style="width:<?=$pct?>%;height:100%;background:var(--primary);border-radius:3px"></div></div>
                        <span style="font-size:.8rem;font-weight:600"><?=$pct?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php endif;?>
    </div>
</div>

<?php elseif($page === 'help'): ?>
<!-- ==================== GUIDA / AIUTO ==================== -->
<div style="padding:25px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div><h1 style="font-size:1.5rem;font-weight:700">❓ Centro Aiuto</h1><p style="color:var(--gray);font-size:.9rem">Guide per usare la contabilità</p></div>
        <button class="btn btn-primary btn-sm" onclick="startOnboarding()">🎓 Rivedi Tutorial</button>
    </div>
    
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;margin-bottom:25px">
        <div class="card" style="cursor:pointer" onclick="showGuide('company')"><div class="card-body pad" style="text-align:center;padding:25px"><div style="font-size:2.5rem;margin-bottom:10px">🏢</div><h4>Dati Azienda</h4><p style="color:var(--gray);font-size:.85rem;margin-top:5px">Configurare la tua azienda</p></div></div>
        <div class="card" style="cursor:pointer" onclick="showGuide('invoices')"><div class="card-body pad" style="text-align:center;padding:25px"><div style="font-size:2.5rem;margin-bottom:10px">🧾</div><h4>Fatture</h4><p style="color:var(--gray);font-size:.85rem;margin-top:5px">Emettere e gestire fatture</p></div></div>
        <div class="card" style="cursor:pointer" onclick="showGuide('quotes')"><div class="card-body pad" style="text-align:center;padding:25px"><div style="font-size:2.5rem;margin-bottom:10px">📋</div><h4>Preventivi</h4><p style="color:var(--gray);font-size:.85rem;margin-top:5px">Creare e inviare preventivi</p></div></div>
        <div class="card" style="cursor:pointer" onclick="showGuide('clients')"><div class="card-body pad" style="text-align:center;padding:25px"><div style="font-size:2.5rem;margin-bottom:10px">👥</div><h4>Clienti/Fornitori</h4><p style="color:var(--gray);font-size:.85rem;margin-top:5px">Anagrafica contatti</p></div></div>
        <div class="card" style="cursor:pointer" onclick="showGuide('regime')"><div class="card-body pad" style="text-align:center;padding:25px"><div style="font-size:2.5rem;margin-bottom:10px">🏛️</div><h4>Regime Fiscale</h4><p style="color:var(--gray);font-size:.85rem;margin-top:5px">IVA e tasse</p></div></div>
    </div>
    
    <div class="card" id="guideContent" style="display:none"><div class="card-header"><h3 id="guideTitle"></h3></div><div class="card-body pad" id="guideBody" style="line-height:1.8"></div></div>
    <div class="card" style="margin-top:15px"><div class="card-header"><h3>📞 Assistenza</h3></div><div class="card-body pad"><p>Hai bisogno di aiuto?</p><div style="display:flex;gap:15px;margin-top:15px;flex-wrap:wrap"><a href="mailto:<?php echo htmlspecialchars($supportEmail); ?>" class="btn btn-outline">📧 Email Supporto</a><?php if($supportWhatsapp): ?><a href="https://wa.me/<?php echo htmlspecialchars($supportWhatsapp); ?>" target="_blank" class="btn btn-outline">💬 WhatsApp</a><?php endif; ?></div></div></div>
</div>

<?php else: ?>
<div class="empty" style="padding:60px"><div class="empty-icon">🔒</div><h3>Funzione Pro</h3><a href="/checkout-saas.html?plan=accounting_pro" class="btn btn-primary" style="margin-top:15px">Passa a Pro</a></div>
<?php endif; ?>

</main>
</div>

<!-- MODALS -->
<div class="modal" id="invoiceModal">
<div class="modal-content">
<div class="modal-header"><h3>📄 Nuova Fattura</h3><button class="modal-close" onclick="closeModal('invoiceModal')">&times;</button></div>
<form method="POST" onsubmit="return prepareInvoiceSubmit()"><div class="modal-body">
<input type="hidden" name="action" value="save_invoice">
<input type="hidden" name="invoice_type" value="active">
<input type="hidden" name="items_json" id="itemsJson">
<div class="form-row">
    <div class="form-group"><label>Data *</label><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></div>
    <div class="form-group"><label>Scadenza</label><input type="date" name="due_date"></div>
</div>
<div class="form-group"><label>Cliente *</label>
<select name="client_id" required><option value="">-- Seleziona --</option>
<?php $stmt=$pdo->prepare("SELECT id,name FROM acc_clients WHERE customer_id=? AND type='client' ORDER BY name");$stmt->execute([$customerId]);while($c=$stmt->fetch())echo "<option value=\"{$c['id']}\">".htmlspecialchars($c['name'])."</option>"; ?>
</select></div>

<h4 style="margin-bottom:10px">Articoli / Servizi</h4>
<table class="invoice-items-table" id="invoiceItemsTable">
<thead><tr><th>Descrizione</th><th style="width:60px">Qtà</th><th style="width:80px">Prezzo</th><th style="width:60px">IVA%</th><th style="width:80px">Totale</th><th style="width:30px"></th></tr></thead>
<tbody id="invoiceItemsBody">
<tr class="item-row">
    <td><input type="text" class="item-desc" placeholder="Descrizione" required></td>
    <td><input type="number" class="item-qty qty-input" value="1" min="1" onchange="calcInvoiceTotals()"></td>
    <td><input type="number" class="item-price price-input" step="0.01" value="0" onchange="calcInvoiceTotals()"></td>
    <td><input type="number" class="item-vat vat-input" value="22" onchange="calcInvoiceTotals()"></td>
    <td class="item-total">€0,00</td>
    <td><button type="button" onclick="removeItemRow(this)" style="background:none;border:none;cursor:pointer">❌</button></td>
</tr>
</tbody>
</table>
<div class="add-item-btn" onclick="addItemRow()">➕ Aggiungi riga</div>

<div class="invoice-totals">
    <div class="row"><span>Imponibile:</span><span id="invSubtotal">€0,00</span></div>
    <div class="row"><span>IVA:</span><span id="invVat">€0,00</span></div>
    <div class="row total"><span>TOTALE:</span><span id="invTotal">€0,00</span></div>
</div>

<div class="form-row">
    <div class="form-group"><label>Stato</label><select name="status"><option value="draft">Bozza</option><option value="sent" selected>Emessa</option><option value="paid">Pagata</option></select></div>
    <div class="form-group"><label>Note</label><input type="text" name="notes" placeholder="Note opzionali"></div>
</div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('invoiceModal')">Annulla</button><button type="submit" class="btn btn-primary">💾 Salva Fattura</button></div></form>
</div></div>

<div class="modal" id="passiveModal">
<div class="modal-content">
<div class="modal-header"><h3>📥 Registra Fattura Passiva</h3><button class="modal-close" onclick="closeModal('passiveModal')">&times;</button></div>
<form method="POST" onsubmit="return preparePassiveSubmit()"><div class="modal-body">
<input type="hidden" name="action" value="save_invoice">
<input type="hidden" name="invoice_type" value="passive">
<input type="hidden" name="items_json" id="passiveItemsJson">
<div class="form-row"><div class="form-group"><label>Numero *</label><input type="text" name="number_manual" required placeholder="Numero fornitore"></div><div class="form-group"><label>Data *</label><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></div></div>
<div class="form-group"><label>Fornitore *</label>
<select name="client_id" required><option value="">-- Seleziona --</option>
<?php $stmt=$pdo->prepare("SELECT id,name FROM acc_clients WHERE customer_id=? AND type='supplier' ORDER BY name");$stmt->execute([$customerId]);while($s=$stmt->fetch())echo "<option value=\"{$s['id']}\">".htmlspecialchars($s['name'])."</option>"; ?>
</select></div>
<div class="form-row"><div class="form-group"><label>Imponibile €</label><input type="number" id="passiveSubtotal" step="0.01" onchange="calcPassiveTotals()"></div><div class="form-group"><label>IVA €</label><input type="number" id="passiveVatAmt" step="0.01" onchange="calcPassiveTotals()"></div></div>
<div style="text-align:right;font-size:1.2rem;font-weight:700;margin-bottom:15px">Totale: <span id="passiveTotal">€0,00</span></div>
<div class="form-row"><div class="form-group"><label>Scadenza</label><input type="date" name="due_date"></div><div class="form-group"><label>Stato</label><select name="status"><option value="sent">Da pagare</option><option value="paid">Pagata</option></select></div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('passiveModal')">Annulla</button><button type="submit" class="btn btn-primary">💾 Registra</button></div></form>
</div></div>

<?php if($isPro): ?>
<div class="modal" id="uploadXmlModal">
<div class="modal-content">
<div class="modal-header"><h3>📤 Importa Fattura XML</h3><button class="modal-close" onclick="closeModal('uploadXmlModal')">&times;</button></div>
<form method="POST" enctype="multipart/form-data"><div class="modal-body">
<input type="hidden" name="action" value="upload_xml">
<div class="form-group"><label>File XML FatturaPA</label><input type="file" name="xml_file" accept=".xml" required></div>
<p style="color:var(--gray);font-size:.85rem">Carica il file XML della fattura elettronica (emessa o ricevuta). Il sistema rileva automaticamente il tipo, estrae righe, cliente e crea la scadenza.</p>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('uploadXmlModal')">Annulla</button><button type="submit" class="btn btn-primary">📤 Importa</button></div></form>
</div></div>

<div class="modal" id="transModal">
<div class="modal-content">
<div class="modal-header"><h3>📝 Nuovo Movimento</h3><button class="modal-close" onclick="closeModal('transModal')">&times;</button></div>
<form method="POST"><div class="modal-body">
<input type="hidden" name="action" value="save_transaction">
<div class="form-row"><div class="form-group"><label>Data *</label><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></div><div class="form-group"><label>Tipo *</label><select name="trans_type"><option value="income">📈 Entrata</option><option value="expense">📉 Uscita</option></select></div></div>
<div class="form-row"><div class="form-group"><label>Categoria</label><select name="category"><option value="">--</option><option>Vendite</option><option>Servizi</option><option>Affitto</option><option>Utenze</option><option>Stipendi</option><option>Materiali</option><option>Altro</option></select></div><div class="form-group"><label>Metodo</label><select name="payment_method"><option value="">--</option><option>Bonifico</option><option>Contanti</option><option>Carta</option></select></div></div>
<div class="form-group"><label>Descrizione *</label><input type="text" name="description" required></div>
<div class="form-row"><div class="form-group"><label>Importo € *</label><input type="number" name="amount" step="0.01" required></div><div class="form-group"><label>Rif. Documento</label><input type="text" name="document_ref"></div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('transModal')">Annulla</button><button type="submit" class="btn btn-primary">💾 Salva</button></div></form>
</div></div>
<?php endif; ?>

<div class="modal" id="clientModal">
<div class="modal-content">
<div class="modal-header"><h3>👤 Nuovo Cliente</h3><button class="modal-close" onclick="closeModal('clientModal')">&times;</button></div>
<form method="POST"><div class="modal-body">
<input type="hidden" name="action" value="save_client">
<input type="hidden" name="type" value="client">
<div class="form-group"><label>Nome *</label><input type="text" name="name" required></div>
<div class="form-row"><div class="form-group"><label>P.IVA</label><input type="text" name="vat_number"></div><div class="form-group"><label>C.F.</label><input type="text" name="fiscal_code"></div></div>
<div class="form-group"><label>Indirizzo</label><input type="text" name="address"></div>
<div class="form-row"><div class="form-group"><label>Città</label><input type="text" name="city"></div><div class="form-group"><label>CAP</label><input type="text" name="zip"></div></div>
<div class="form-row"><div class="form-group"><label>Prov</label><input type="text" name="province" maxlength="2"></div><div class="form-group"><label>SDI</label><input type="text" name="sdi_code" maxlength="7"></div></div>
<div class="form-row"><div class="form-group"><label>Email</label><input type="email" name="email"></div><div class="form-group"><label>PEC</label><input type="email" name="pec"></div></div>
<div class="form-group"><label>Telefono</label><input type="text" name="phone"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('clientModal')">Annulla</button><button type="submit" class="btn btn-primary">💾 Salva</button></div></form>
</div></div>

<div class="modal" id="supplierModal">
<div class="modal-content">
<div class="modal-header"><h3>🏭 Nuovo Fornitore</h3><button class="modal-close" onclick="closeModal('supplierModal')">&times;</button></div>
<form method="POST"><div class="modal-body">
<input type="hidden" name="action" value="save_client">
<input type="hidden" name="type" value="supplier">
<div class="form-group"><label>Nome *</label><input type="text" name="name" required></div>
<div class="form-row"><div class="form-group"><label>P.IVA</label><input type="text" name="vat_number"></div><div class="form-group"><label>C.F.</label><input type="text" name="fiscal_code"></div></div>
<div class="form-group"><label>Indirizzo</label><input type="text" name="address"></div>
<div class="form-row"><div class="form-group"><label>Città</label><input type="text" name="city"></div><div class="form-group"><label>Email</label><input type="email" name="email"></div></div>
<div class="form-group"><label>Telefono</label><input type="text" name="phone"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('supplierModal')">Annulla</button><button type="submit" class="btn btn-primary">💾 Salva</button></div></form>
</div></div>

<!-- ==================== MODALI PREVENTIVI ==================== -->
<?php
$defaultVat = (float)($company['default_vat_rate'] ?? 22) ?: 22;
$clientiSelect = [];
try {
    $stmtCl = $pdo->prepare("SELECT id, name, email, phone FROM acc_clients WHERE customer_id=? AND type='client' ORDER BY name");
    $stmtCl->execute([$customerId]);
    $clientiSelect = $stmtCl->fetchAll();
} catch (Exception $e) {}
?>
<div class="modal" id="quoteModal">
<div class="modal-content" style="max-width:820px">
<div class="modal-header"><h3 id="quoteModalTitle">📋 Nuovo Preventivo</h3><button class="modal-close" onclick="closeModal('quoteModal')">&times;</button></div>
<form method="POST" onsubmit="return prepareQuoteSubmit()"><div class="modal-body">
<input type="hidden" name="action" value="save_quote">
<input type="hidden" name="quote_id" id="quoteId" value="">
<input type="hidden" name="items_json" id="quoteItemsJson">

<div class="form-group"><label>Cliente *</label>
<select name="client_id" id="quoteClient" required>
    <option value="">-- Seleziona cliente --</option>
    <?php foreach($clientiSelect as $c): ?>
    <option value="<?php echo $c['id']; ?>" data-email="<?php echo htmlspecialchars($c['email']); ?>" data-phone="<?php echo htmlspecialchars($c['phone']); ?>"><?php echo htmlspecialchars($c['name']); ?></option>
    <?php endforeach; ?>
</select>
<?php if(empty($clientiSelect)): ?><small style="color:var(--warning)">Nessun cliente in anagrafica: <a href="?page=clients">aggiungine uno</a> per creare un preventivo.</small><?php endif; ?>
</div>

<div class="form-row-3">
    <div class="form-group"><label>Data *</label><input type="date" name="date" id="quoteDate" value="<?php echo date('Y-m-d'); ?>" required></div>
    <div class="form-group"><label>Valido fino al</label><input type="date" name="valid_until" id="quoteValid" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></div>
    <div class="form-group"><label>Stato</label><select name="status" id="quoteStatus"><option value="draft">📝 Bozza</option><option value="sent">📨 Inviato</option><option value="accepted">✅ Accettato</option><option value="rejected">❌ Rifiutato</option></select></div>
</div>

<div class="form-group"><label>Oggetto</label><input type="text" name="subject" id="quoteSubject" placeholder="Es. Realizzazione sito web aziendale"></div>

<h4 style="margin-bottom:10px">Voci del preventivo</h4>
<table class="invoice-items-table" id="quoteItemsTable">
<thead><tr><th>Descrizione</th><th style="width:70px">Qtà</th><th style="width:90px">Prezzo €</th><th style="width:70px">IVA%</th><th style="width:90px">Totale</th><th style="width:30px"></th></tr></thead>
<tbody id="quoteItemsBody"></tbody>
</table>
<div class="add-item-btn" onclick="addQuoteRow()">➕ Aggiungi voce</div>

<div class="form-row">
    <div class="form-group"><label>Sconto % sull'imponibile</label><input type="number" name="discount_percent" id="quoteDiscount" step="0.01" min="0" max="100" value="0" oninput="calcQuoteTotals()"></div>
    <div class="form-group"><label>Modalità di pagamento</label><input type="text" name="payment_terms_text" id="quotePayment" value="Bonifico bancario 30 giorni data fattura"></div>
</div>

<div class="invoice-totals">
    <div class="row"><span>Imponibile:</span><span id="qSubtotal">€0,00</span></div>
    <div class="row" id="qDiscountRow" style="display:none;color:var(--success)"><span>Sconto:</span><span id="qDiscount">€0,00</span></div>
    <div class="row"><span>IVA:</span><span id="qVat">€0,00</span></div>
    <div class="row total"><span>TOTALE:</span><span id="qTotal">€0,00</span></div>
</div>

<div class="form-group"><label>Note per il cliente</label><textarea name="notes" id="quoteNotes" rows="2" placeholder="Es. Tempi di consegna, materiali inclusi..."></textarea></div>
<div class="form-group"><label>Condizioni</label><textarea name="terms" id="quoteTerms" rows="2" placeholder="Es. Il presente preventivo è vincolante per 30 giorni..."></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('quoteModal')">Annulla</button><button type="submit" class="btn btn-primary">💾 Salva Preventivo</button></div></form>
</div></div>

<div class="modal" id="quoteEmailModal">
<div class="modal-content" style="max-width:600px">
<div class="modal-header"><h3>📧 Invia preventivo via email</h3><button class="modal-close" onclick="closeModal('quoteEmailModal')">&times;</button></div>
<form method="POST"><div class="modal-body">
<input type="hidden" name="action" value="send_quote_email">
<input type="hidden" name="quote_id" id="emailQuoteId">
<p style="background:var(--light);padding:10px;border-radius:8px;font-size:.8rem;color:var(--gray);margin-bottom:15px">📎 Il PDF del preventivo viene allegato automaticamente al messaggio.</p>
<div class="form-group"><label>Destinatario *</label><input type="email" name="to_email" id="emailTo" required placeholder="cliente@email.it"></div>
<div class="form-group"><label>Oggetto</label><input type="text" name="subject_email" id="emailSubject"></div>
<div class="form-group"><label>Messaggio</label><textarea name="message" id="emailMessage" rows="8"></textarea></div>
<label style="display:flex;align-items:center;gap:8px;font-size:.85rem"><input type="checkbox" name="copy_to_me" value="1" style="width:auto"> Invia una copia anche a me (<?php echo htmlspecialchars($customerEmail); ?>)</label>
</div><div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('quoteEmailModal')">Annulla</button><button type="submit" class="btn btn-primary">📧 Invia ora</button></div></form>
</div></div>

<div class="modal" id="quoteWaModal">
<div class="modal-content" style="max-width:560px">
<div class="modal-header"><h3>💬 Invia preventivo su WhatsApp</h3><button class="modal-close" onclick="closeModal('quoteWaModal')">&times;</button></div>
<div class="modal-body">
<div class="form-group"><label>Numero WhatsApp (con prefisso internazionale)</label><input type="text" id="waPhone" placeholder="393331234567"></div>
<div class="form-group"><label>Messaggio</label><textarea id="waMessage" rows="8"></textarea></div>
<p style="color:var(--gray);font-size:.78rem">Si apre WhatsApp con il messaggio già pronto e il link al preventivo (che il cliente può scaricare in PDF). Il preventivo viene segnato come "Inviato".</p>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('quoteWaModal')">Annulla</button><button type="button" class="btn btn-success" onclick="sendQuoteWhatsapp()">💬 Apri WhatsApp</button></div>
</div></div>


<div class="modal" id="upgradeModal">
<div class="modal-content" style="max-width:380px;text-align:center">
<div class="modal-body" style="padding:35px">
<div style="font-size:3.5rem;margin-bottom:15px">🚀</div>
<h2 style="margin-bottom:10px">Funzione Pro</h2>
<p style="color:var(--gray);margin-bottom:20px">Disponibile nel piano Pro</p>
<a href="/checkout-saas.html?plan=accounting_pro" class="btn btn-primary" style="width:100%">Passa a Pro - €25/mese</a>
<button class="btn btn-outline" style="width:100%;margin-top:8px" onclick="closeModal('upgradeModal')">Dopo</button>
</div></div></div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function showUpgrade() { openModal('upgradeModal'); }

function addItemRow() {
    const tbody = document.getElementById('invoiceItemsBody');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td><input type="text" class="item-desc" placeholder="Descrizione" required></td>
        <td><input type="number" class="item-qty qty-input" value="1" min="1" onchange="calcInvoiceTotals()"></td>
        <td><input type="number" class="item-price price-input" step="0.01" value="0" onchange="calcInvoiceTotals()"></td>
        <td><input type="number" class="item-vat vat-input" value="22" onchange="calcInvoiceTotals()"></td>
        <td class="item-total">€0,00</td>
        <td><button type="button" onclick="removeItemRow(this)" style="background:none;border:none;cursor:pointer">❌</button></td>
    `;
    tbody.appendChild(row);
}

function removeItemRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calcInvoiceTotals();
    }
}

function calcInvoiceTotals() {
    let subtotal = 0, vatTotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const vat = parseFloat(row.querySelector('.item-vat').value) || 0;
        const itemTotal = qty * price;
        const itemVat = itemTotal * vat / 100;
        subtotal += itemTotal;
        vatTotal += itemVat;
        row.querySelector('.item-total').textContent = '€' + itemTotal.toFixed(2).replace('.',',');
    });
    document.getElementById('invSubtotal').textContent = '€' + subtotal.toFixed(2).replace('.',',');
    document.getElementById('invVat').textContent = '€' + vatTotal.toFixed(2).replace('.',',');
    document.getElementById('invTotal').textContent = '€' + (subtotal + vatTotal).toFixed(2).replace('.',',');
}

function prepareInvoiceSubmit() {
    const items = [];
    document.querySelectorAll('.item-row').forEach(row => {
        const desc = row.querySelector('.item-desc').value;
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const vat = parseFloat(row.querySelector('.item-vat').value) || 22;
        if (desc && qty > 0) {
            items.push({desc, qty, price, vat});
        }
    });
    if (items.length === 0) { alert('Aggiungi almeno un articolo!'); return false; }
    document.getElementById('itemsJson').value = JSON.stringify(items);
    return true;
}

function calcPassiveTotals() {
    const sub = parseFloat(document.getElementById('passiveSubtotal').value) || 0;
    const vat = parseFloat(document.getElementById('passiveVatAmt').value) || 0;
    document.getElementById('passiveTotal').textContent = '€' + (sub + vat).toFixed(2).replace('.',',');
}

function preparePassiveSubmit() {
    const sub = parseFloat(document.getElementById('passiveSubtotal').value) || 0;
    const vat = parseFloat(document.getElementById('passiveVatAmt').value) || 0;
    const items = [{desc: 'Fattura fornitore', qty: 1, price: sub, vat: vat > 0 ? (vat/sub*100) : 22}];
    document.getElementById('passiveItemsJson').value = JSON.stringify(items);
    return true;
}

/* ==================== PREVENTIVI ==================== */
var ACC_DEFAULT_VAT = <?php echo json_encode((float)($company['default_vat_rate'] ?? 22) ?: 22); ?>;

function quoteRowTemplate(desc, qty, price, vat) {
    var tr = document.createElement('tr');
    tr.className = 'quote-item-row';
    tr.innerHTML = '<td><input type="text" class="q-desc" placeholder="Descrizione voce" required></td>' +
        '<td><input type="number" class="q-qty qty-input" step="0.01" min="0" value="1" oninput="calcQuoteTotals()"></td>' +
        '<td><input type="number" class="q-price price-input" step="0.01" value="0" oninput="calcQuoteTotals()"></td>' +
        '<td><input type="number" class="q-vat vat-input" step="0.01" value="' + ACC_DEFAULT_VAT + '" oninput="calcQuoteTotals()"></td>' +
        '<td class="q-row-total">€0,00</td>' +
        '<td><button type="button" onclick="removeQuoteRow(this)" style="background:none;border:none;cursor:pointer">❌</button></td>';
    tr.querySelector('.q-desc').value = desc || '';
    if (qty !== undefined && qty !== null) tr.querySelector('.q-qty').value = qty;
    if (price !== undefined && price !== null) tr.querySelector('.q-price').value = price;
    if (vat !== undefined && vat !== null) tr.querySelector('.q-vat').value = vat;
    return tr;
}

function addQuoteRow(desc, qty, price, vat) {
    document.getElementById('quoteItemsBody').appendChild(quoteRowTemplate(desc, qty, price, vat));
    calcQuoteTotals();
}

function removeQuoteRow(btn) {
    if (document.querySelectorAll('.quote-item-row').length > 1) {
        btn.closest('tr').remove();
        calcQuoteTotals();
    }
}

function fmtEur(n) { return '€' + n.toFixed(2).replace('.', ','); }

function calcQuoteTotals() {
    var sconto = parseFloat((document.getElementById('quoteDiscount') || {}).value) || 0;
    if (sconto < 0) sconto = 0; if (sconto > 100) sconto = 100;
    var subtotal = 0, vatTot = 0;
    document.querySelectorAll('.quote-item-row').forEach(function (row) {
        var qty = parseFloat(row.querySelector('.q-qty').value) || 0;
        var price = parseFloat(row.querySelector('.q-price').value) || 0;
        var vat = parseFloat(row.querySelector('.q-vat').value) || 0;
        var rowTotal = qty * price;
        subtotal += rowTotal;
        vatTot += rowTotal * (1 - sconto / 100) * vat / 100;
        row.querySelector('.q-row-total').textContent = fmtEur(rowTotal);
    });
    var scontoVal = subtotal * sconto / 100;
    document.getElementById('qSubtotal').textContent = fmtEur(subtotal);
    document.getElementById('qDiscountRow').style.display = scontoVal > 0 ? 'flex' : 'none';
    document.getElementById('qDiscount').textContent = '- ' + fmtEur(scontoVal);
    document.getElementById('qVat').textContent = fmtEur(vatTot);
    document.getElementById('qTotal').textContent = fmtEur(subtotal - scontoVal + vatTot);
}

function prepareQuoteSubmit() {
    var items = [];
    document.querySelectorAll('.quote-item-row').forEach(function (row) {
        var desc = row.querySelector('.q-desc').value.trim();
        var qty = parseFloat(row.querySelector('.q-qty').value) || 0;
        var price = parseFloat(row.querySelector('.q-price').value) || 0;
        var vat = parseFloat(row.querySelector('.q-vat').value);
        if (isNaN(vat)) vat = ACC_DEFAULT_VAT;
        if (desc && qty > 0) items.push({ desc: desc, qty: qty, price: price, vat: vat });
    });
    if (items.length === 0) { alert('Aggiungi almeno una voce al preventivo!'); return false; }
    document.getElementById('quoteItemsJson').value = JSON.stringify(items);
    return true;
}

function openQuoteModal() {
    document.getElementById('quoteModalTitle').textContent = '📋 Nuovo Preventivo';
    document.getElementById('quoteId').value = '';
    document.getElementById('quoteItemsBody').innerHTML = '';
    addQuoteRow();
    var d = document.getElementById('quoteDiscount'); if (d) d.value = 0;
    calcQuoteTotals();
    openModal('quoteModal');
}

function openQuoteEdit() {
    if (typeof ACC_QUOTE_EDIT === 'undefined') return;
    var q = ACC_QUOTE_EDIT;
    document.getElementById('quoteModalTitle').textContent = '✏️ Modifica Preventivo';
    document.getElementById('quoteId').value = q.id;
    document.getElementById('quoteClient').value = q.client_id || '';
    document.getElementById('quoteDate').value = q.date || '';
    document.getElementById('quoteValid').value = q.valid_until || '';
    document.getElementById('quoteStatus').value = q.status || 'draft';
    document.getElementById('quoteSubject').value = q.subject || '';
    document.getElementById('quoteDiscount').value = q.discount_percent || 0;
    document.getElementById('quotePayment').value = q.payment_terms_text || '';
    document.getElementById('quoteNotes').value = q.notes || '';
    document.getElementById('quoteTerms').value = q.terms || '';
    var body = document.getElementById('quoteItemsBody');
    body.innerHTML = '';
    (q.items || []).forEach(function (it) { body.appendChild(quoteRowTemplate(it.desc, it.qty, it.price, it.vat)); });
    if (!body.children.length) addQuoteRow();
    calcQuoteTotals();
    openModal('quoteModal');
}

function openQuoteEmail(id) {
    var q = (typeof ACC_QUOTES !== 'undefined') ? ACC_QUOTES[id] : null;
    if (!q) return;
    document.getElementById('emailQuoteId').value = id;
    document.getElementById('emailTo').value = q.email || '';
    document.getElementById('emailSubject').value = q.subject || '';
    document.getElementById('emailMessage').value = q.msgMail || '';
    openModal('quoteEmailModal');
    if (!q.email) setTimeout(function () { document.getElementById('emailTo').focus(); }, 100);
}

var waQuoteId = null;
function openQuoteWhatsapp(id) {
    var q = (typeof ACC_QUOTES !== 'undefined') ? ACC_QUOTES[id] : null;
    if (!q) return;
    waQuoteId = id;
    document.getElementById('waPhone').value = q.phone || '';
    document.getElementById('waMessage').value = q.msgWa || '';
    openModal('quoteWaModal');
}

function sendQuoteWhatsapp() {
    var phone = (document.getElementById('waPhone').value || '').replace(/[^0-9]/g, '');
    var msg = document.getElementById('waMessage').value || '';
    if (!phone) { alert('Inserisci il numero WhatsApp del cliente (con prefisso, es. 39...)'); return; }
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
    markQuoteSent(waQuoteId, 'whatsapp');
    closeModal('quoteWaModal');
}

function markQuoteSent(id, channel) {
    var fd = new FormData();
    fd.append('action', 'mark_quote_sent');
    fd.append('quote_id', id);
    fd.append('channel', channel);
    fetch('', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function () { setTimeout(function () { location.reload(); }, 800); })
        .catch(function () {});
}

function copyQuoteLink() {
    var input = document.getElementById('quoteLink');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    if (navigator.clipboard) { navigator.clipboard.writeText(input.value).catch(function () {}); ok = true; }
    alert(ok ? 'Link copiato negli appunti!' : 'Copia manualmente il link.');
}


document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active')); });
</script>
<script src="/js/eva-widget.js?v=3" data-source="accounting"></script>
<script src="/js/eva-business.js?v=2" data-source="accounting"></script>

<script>
var onboardingSteps = [
    {icon:'👋', title:'Benvenuto nella Contabilità Pro!', text:'Questo tutorial ti guiderà attraverso le funzionalità principali del gestionale contabile.'},
    {icon:'🏢', title:'Dati Azienda', text:'Per prima cosa, configura i dati della tua azienda: ragione sociale, P.IVA, codice SDI, PEC. Questi dati appariranno automaticamente nelle fatture.'},
    {icon:'🏛️', title:'Regime Fiscale', text:'Seleziona il tuo regime fiscale (Ordinario, Forfettario, Semplificato) e configura l\'aliquota IVA predefinita e la marca da bollo.'},
    {icon:'👥', title:'Clienti e Fornitori', text:'Aggiungi la tua anagrafica clienti e fornitori con tutti i dati necessari per la fatturazione elettronica (P.IVA, SDI, PEC).'},
    {icon:'📋', title:'Preventivi', text:'Crea preventivi professionali, inviali al cliente via email o WhatsApp, scaricali in PDF e trasformali in fattura quando vengono accettati.'},
    {icon:'🧾', title:'Fatture', text:'Crea fatture attive e passive. Il sistema calcola automaticamente IVA e marca da bollo. Puoi esportare in PDF e XML.'},
    {icon:'✅', title:'Sei pronto!', text:'Inizia inserendo i dati della tua azienda e il regime fiscale. Puoi rivedere questa guida dalla sezione Aiuto.'}
];
var onboardingStep = 0;
function startOnboarding() { onboardingStep = 0; showOnboardingStep(); }
function showOnboardingStep() {
    var s = onboardingSteps[onboardingStep], isLast = onboardingStep === onboardingSteps.length - 1, isFirst = onboardingStep === 0;
    var dots = onboardingSteps.map(function(_,i){ return '<span style="width:8px;height:8px;border-radius:50%;background:'+(i===onboardingStep?'var(--primary)':'var(--border)')+';display:inline-block"></span>'; }).join('');
    var m = document.getElementById('onboardingModal');
    if (!m) { m = document.createElement('div'); m.id='onboardingModal'; m.className='modal'; m.style.zIndex='9999'; document.body.appendChild(m); }
    m.innerHTML = '<div class="modal-content" style="max-width:480px;text-align:center"><div style="padding:40px 30px 20px"><div style="font-size:4rem;margin-bottom:15px;animation:bounceIn .5s">'+s.icon+'</div><h2 style="margin-bottom:10px;font-size:1.3rem">'+s.title+'</h2><p style="color:var(--gray);line-height:1.6;font-size:.95rem">'+s.text+'</p></div><div style="padding:15px 30px 25px;display:flex;flex-direction:column;gap:12px;align-items:center"><div style="display:flex;gap:6px;margin-bottom:5px">'+dots+'</div><div style="display:flex;gap:10px;width:100%">'+(isFirst?'':'<button class="btn btn-outline" onclick="onboardingStep--;showOnboardingStep()" style="flex:1">← Indietro</button>')+(isLast?'<form method="POST" style="flex:1"><input type="hidden" name="action" value="complete_onboarding"><button class="btn btn-primary" style="width:100%">🚀 Inizia!</button></form>':'<button class="btn btn-primary" onclick="onboardingStep++;showOnboardingStep()" style="flex:1">Avanti →</button>')+'</div>'+(isFirst?'<button class="btn btn-outline" onclick="closeModal(\'onboardingModal\')" style="font-size:.8rem;color:var(--gray)">Salta tutorial</button>':'')+'</div></div>';
    m.classList.add('active');
}
var guides = {
    company: {title:'🏢 Guida Dati Azienda', body:'<p>Inserisci ragione sociale, indirizzo, P.IVA, codice fiscale, codice SDI e PEC. Questi dati vengono usati automaticamente nelle fatture.</p><p style="margin-top:10px"><strong>IBAN:</strong> Aggiungi il tuo IBAN per includerlo nelle fatture con pagamento bonifico.</p>'},
    invoices: {title:'🧾 Guida Fatture', body:'<p><strong>Nuova fattura:</strong> Clicca "Nuova Fattura", seleziona il cliente, aggiungi le voci con importo e aliquota IVA.</p><p style="margin-top:10px"><strong>Marca da bollo:</strong> Per fatture esenti IVA sopra €77,47 il sistema aggiunge automaticamente la marca da bollo di €2,00.</p><p><strong>Export:</strong> Scarica in PDF per stampa o XML per la fatturazione elettronica.</p>'},
    quotes: {title:'📋 Guida Preventivi', body:'<p><strong>Creare:</strong> vai su <em>Preventivi</em> e clicca "Nuovo Preventivo": scegli il cliente, aggiungi le voci con quantità, prezzo e IVA, imposta un eventuale sconto e la data di validità.</p><p style="margin-top:10px"><strong>Inviare:</strong> dal dettaglio del preventivo puoi inviarlo via <strong>email</strong> (il PDF viene allegato automaticamente) oppure su <strong>WhatsApp</strong> (si apre la chat con il messaggio e il link già pronti).</p><p style="margin-top:10px"><strong>PDF:</strong> il pulsante "Scarica PDF" genera il documento pronto da stampare o allegare.</p><p style="margin-top:10px"><strong>Link pubblico:</strong> ogni preventivo ha un link che il cliente può aprire per visualizzarlo, scaricarlo e accettarlo online.</p><p style="margin-top:10px"><strong>Converti in fattura:</strong> quando il cliente accetta, un clic crea la fattura con le stesse righe.</p>'},
    clients: {title:'👥 Guida Clienti/Fornitori', body:'<p>Aggiungi clienti e fornitori con P.IVA, codice fiscale, SDI e PEC per la fatturazione elettronica.</p><p style="margin-top:10px">I dati vengono compilati automaticamente quando selezioni un cliente durante la creazione di una fattura.</p>'},
    regime: {title:'🏛️ Guida Regime Fiscale', body:'<p><strong>Ordinario:</strong> IVA standard con liquidazione periodica. Per aziende con fatturato sopra i limiti del forfettario.</p><p><strong>Forfettario:</strong> Imposta sostitutiva 15% (o 5% primi 5 anni). Per professionisti e piccole attività.</p><p><strong>Semplificato:</strong> Contabilità semplificata per piccole imprese.</p>'}
};
function showGuide(key) {
    var g = guides[key];
    document.getElementById('guideTitle').textContent = g.title;
    document.getElementById('guideBody').innerHTML = g.body;
    document.getElementById('guideContent').style.display = 'block';
    document.getElementById('guideContent').scrollIntoView({behavior:'smooth'});
}
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
<?php if($showOnboarding): ?>
document.addEventListener('DOMContentLoaded', function() { setTimeout(startOnboarding, 500); });
<?php endif; ?>

// === Toggle pagamento scadenza fiscale ===
function togglePayment(key, amount, label) {
    var isPaid = event.target.textContent.includes('Pagato');
    
    if (isPaid) {
        if (!confirm('Vuoi segnare "' + label + '" come NON pagato?')) return;
    } else {
        var paidDate = prompt('Data pagamento (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
        if (!paidDate) return;
        var notes = prompt('Note (opzionale, es. numero F24):', '');
    }
    
    var fd = new FormData();
    fd.append('action', 'toggle_fiscal_payment');
    fd.append('fiscal_year', document.querySelector('[name=fiscal_year_val]')?.value || new Date().getFullYear());
    fd.append('deadline_key', key);
    fd.append('amount', amount);
    if (!isPaid) {
        fd.append('paid_date', paidDate);
        fd.append('notes', notes || '');
    }
    
    fetch('', {method:'POST', body:fd})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                location.reload();
            } else {
                alert('Errore: ' + (d.error || 'Sconosciuto'));
            }
        })
        .catch(function() { alert('Errore di rete'); });
}
</script>
<style>@keyframes bounceIn{0%{transform:scale(.5);opacity:0}60%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}</style>
</body>
</html>