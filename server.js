// server.js - Backend SICURO per MessageFlow con Stripe
require('dotenv').config();
const express = require('express');
const stripe = require('stripe')(process.env.STRIPE_SECRET_KEY);
const cors = require('cors');
const crypto = require('crypto');
const fs = require('fs').promises;
const path = require('path');
const rateLimit = require('express-rate-limit');
const helmet = require('helmet');
const validator = require('validator');

const app = express();
const PORT = process.env.PORT || 3000;

// ==================== SICUREZZA ====================

// 1. Helmet - Protezione header HTTP
app.use(helmet({
    contentSecurityPolicy: {
        directives: {
            defaultSrc: ["'self'"],
            scriptSrc: ["'self'", "'unsafe-inline'", "https://js.stripe.com"],
            frameSrc: ["'self'", "https://js.stripe.com"],
            connectSrc: ["'self'", "https://api.stripe.com"],
            styleSrc: ["'self'", "'unsafe-inline'"]
        }
    },
    hsts: {
        maxAge: 31536000,
        includeSubDomains: true,
        preload: true
    }
}));

// 2. CORS - Configurazione sicura
const corsOptions = {
    origin: process.env.NODE_ENV === 'production' 
        ? process.env.DOMAIN 
        : ['http://localhost:3000', 'http://127.0.0.1:3000'],
    credentials: true,
    optionsSuccessStatus: 200
};
app.use(cors(corsOptions));
app.set('trust proxy', 1);  // ← AGGIUNGI QUESTA

// 3. Rate Limiting - Protezione contro attacchi DDoS
const createAccountLimiter = rateLimit({
    windowMs: 60 * 60 * 1000, // 1 ora
    max: 5, // Max 5 tentativi
    message: { error: 'Troppi tentativi di creazione account. Riprova tra un\'ora.' }
});

const checkoutLimiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 minuti
    max: 10, // Max 10 checkout
    message: { error: 'Troppi tentativi di checkout. Riprova tra 15 minuti.' }
});

const generalLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 100, // Max 100 richieste ogni 15 minuti
    message: { error: 'Troppe richieste. Riprova più tardi.' }
});

app.use('/create-checkout-session', checkoutLimiter);
app.use(generalLimiter);

// 4. Body Parser con limite di dimensione
app.use(express.json({ limit: '10kb' }));
app.use(express.urlencoded({ extended: true, limit: '10kb' }));

// 5. Serve file statici dalla cartella public
app.use(express.static(path.join(__dirname, 'public'), {
    maxAge: process.env.NODE_ENV === 'production' ? '1d' : 0,
    etag: true
}));

// ==================== VALIDAZIONE INPUT ====================

function sanitizeInput(input) {
    if (typeof input !== 'string') return '';
    return validator.escape(validator.trim(input));
}

function validateEmail(email) {
    return validator.isEmail(email);
}

function validatePlan(plan) {
    return ['pro', 'business'].includes(plan);
}

// ==================== DATABASE LICENZE ====================

// In produzione, usa un database reale (MongoDB, PostgreSQL, ecc.)
const licenses = new Map();

// Funzione per salvare licenze in modo persistente
async function saveLicenses() {
    try {
        const licensesArray = Array.from(licenses.entries());
        await fs.writeFile(
            path.join(__dirname, 'data', 'licenses.json'),
            JSON.stringify(licensesArray, null, 2)
        );
    } catch (error) {
        console.error('Errore salvataggio licenze:', error);
    }
}

// Funzione per caricare licenze
async function loadLicenses() {
    try {
        const data = await fs.readFile(path.join(__dirname, 'data', 'licenses.json'), 'utf-8');
        const licensesArray = JSON.parse(data);
        licensesArray.forEach(([key, value]) => licenses.set(key, value));
        console.log(`✅ Caricate ${licenses.size} licenze`);
    } catch (error) {
        console.log('📂 Nessuna licenza esistente da caricare');
    }
}

// ==================== CONFIGURAZIONE PRODOTTI ====================

const PRODUCTS = {
    pro: {
        name: 'MessageFlow Pro',
        price: 9700, // €97.00 in centesimi
        priceId: process.env.STRIPE_PRICE_ID_PRO,
        features: ['unlimited_contacts', 'no_watermark', 'lifetime_updates', 'email_support'],
        licenses: 1
    },
    business: {
        name: 'MessageFlow Business',
        price: 24700, // €247.00 in centesimi
        priceId: process.env.STRIPE_PRICE_ID_BUSINESS,
        features: ['unlimited_contacts', 'no_watermark', 'lifetime_updates', 'phone_support', 'white_label', '5_licenses'],
        licenses: 5
    }
};

// ==================== FUNZIONI LICENZA ====================

function generateLicenseKey() {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const segments = [];
    
    for (let i = 0; i < 4; i++) {
        let segment = '';
        for (let j = 0; j < 5; j++) {
            segment += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        segments.push(segment);
    }
    
    return segments.join('-');
}

function hashLicenseKey(key) {
    return crypto.createHash('sha256').update(key).digest('hex');
}

async function generateProFile(licenseKey, plan, email) {
    try {
        const template = await fs.readFile(
            path.join(__dirname, 'templates', 'messageflow-pro-template.html'),
            'utf-8'
        );

        const customized = template
            .replace(/\{\{LICENSE_KEY\}\}/g, licenseKey)
            .replace(/\{\{LICENSE_EMAIL\}\}/g, email)
            .replace(/\{\{PLAN\}\}/g, plan.toUpperCase())
            .replace(/\{\{MAX_CONTACTS\}\}/g, 'Infinity')
            .replace(/\{\{GENERATION_DATE\}\}/g, new Date().toISOString());

        return customized;
    } catch (error) {
        console.error('Errore generazione file Pro:', error);
        throw error;
    }
}

// ==================== ENDPOINTS SICURI ====================

// Health check
app.get('/health', (req, res) => {
    res.json({ 
        status: 'OK', 
        timestamp: new Date().toISOString(),
        environment: process.env.NODE_ENV || 'development'
    });
});

// Serve la homepage
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Crea sessione checkout Stripe (PROTETTO)
app.post('/create-checkout-session', async (req, res) => {
    try {
        const { plan, email } = req.body;

        // Validazione input
        if (!validatePlan(plan)) {
            return res.status(400).json({ error: 'Piano non valido' });
        }

        if (email && !validateEmail(email)) {
            return res.status(400).json({ error: 'Email non valida' });
        }

        const product = PRODUCTS[plan];

        if (!product || !product.priceId) {
            return res.status(400).json({ error: 'Configurazione prodotto non valida' });
        }

        // Crea sessione Stripe
        const session = await stripe.checkout.sessions.create({
            payment_method_types: ['card'],
            line_items: [
                {
                    price: product.priceId,
                    quantity: 1,
                },
            ],
            mode: 'payment',
            success_url: `${process.env.DOMAIN}/success?session_id={CHECKOUT_SESSION_ID}`,
            cancel_url: `${process.env.DOMAIN}/cancel`,
            metadata: {
                plan: plan,
                timestamp: Date.now().toString()
            },
            customer_email: email || undefined,
            // Sicurezza extra
            payment_intent_data: {
                metadata: {
                    plan: plan,
                    integration: 'messageflow'
                }
            }
        });

        // Log sicuro (senza dati sensibili)
        console.log(`✅ Sessione checkout creata: ${session.id} - Piano: ${plan}`);

        res.json({ id: session.id });
    } catch (error) {
        console.error('❌ Errore creazione sessione:', error.message);
        res.status(500).json({ error: 'Errore durante la creazione della sessione di pagamento' });
    }
});

// Webhook Stripe (SICURO CON VERIFICA FIRMA)
app.post('/webhook', express.raw({ type: 'application/json' }), async (req, res) => {
    const sig = req.headers['stripe-signature'];
    let event;

    try {
        // Verifica firma webhook (CRUCIALE PER SICUREZZA)
        event = stripe.webhooks.constructEvent(
            req.body,
            sig,
            process.env.STRIPE_WEBHOOK_SECRET
        );
    } catch (err) {
        console.error('❌ Errore verifica webhook:', err.message);
        return res.status(400).send(`Webhook Error: ${err.message}`);
    }

    // Gestisci evento checkout completato
    if (event.type === 'checkout.session.completed') {
        const session = event.data.object;
        
        try {
            const plan = session.metadata.plan;
            const email = session.customer_email;

            if (!validatePlan(plan)) {
                throw new Error('Piano non valido nel webhook');
            }

            if (!validateEmail(email)) {
                throw new Error('Email non valida nel webhook');
            }

            // Genera licenza
            const licenseKey = generateLicenseKey();
            const hashedKey = hashLicenseKey(licenseKey);
            
            // Salva licenza
            const licenseData = {
                key: licenseKey,
                hashedKey: hashedKey,
                plan: plan,
                email: email,
                purchaseDate: new Date().toISOString(),
                active: true,
                stripeSessionId: session.id,
                stripeCustomerId: session.customer,
                amount: session.amount_total
            };

            licenses.set(licenseKey, licenseData);
            await saveLicenses();

            // Genera file personalizzato
            const customizedFile = await generateProFile(licenseKey, plan, email);

            // TODO: Invia email con licenza e link download
            // Usa un servizio sicuro come SendGrid, Mailgun, o AWS SES
            console.log(`✅ Licenza generata: ${licenseKey} per ${email}`);
            console.log(`📧 Email da inviare a: ${email}`);

            // In produzione, integra servizio email qui
            // await sendLicenseEmail(email, licenseKey, plan);

        } catch (error) {
            console.error('❌ Errore processamento webhook:', error);
            // Non restituire errore a Stripe per evitare retry infiniti
        }
    }

    res.json({ received: true });
});

// Verifica licenza (PROTETTO)
app.post('/verify-license', async (req, res) => {
    try {
        let { licenseKey } = req.body;

        if (!licenseKey || typeof licenseKey !== 'string') {
            return res.json({ valid: false, error: 'Licenza non fornita' });
        }

        // Sanitizza input
        licenseKey = sanitizeInput(licenseKey);

        const license = licenses.get(licenseKey);

        if (!license) {
            return res.json({ valid: false, error: 'Licenza non trovata' });
        }

        if (!license.active) {
            return res.json({ valid: false, error: 'Licenza non attiva' });
        }

        // Verifica hash
        const providedHash = hashLicenseKey(licenseKey);
        if (providedHash !== license.hashedKey) {
            return res.json({ valid: false, error: 'Licenza corrotta' });
        }

        res.json({
            valid: true,
            plan: license.plan,
            features: PRODUCTS[license.plan].features,
            purchaseDate: license.purchaseDate
        });
    } catch (error) {
        console.error('❌ Errore verifica licenza:', error);
        res.status(500).json({ valid: false, error: 'Errore server' });
    }
});

// Download file Pro (PROTETTO)
app.get('/download/:licenseKey', async (req, res) => {
    try {
        let { licenseKey } = req.params;
        licenseKey = sanitizeInput(licenseKey);

        const license = licenses.get(licenseKey);

        if (!license || !license.active) {
            return res.status(404).send('<h1>❌ Licenza non valida</h1><p>Contatta support@messageflow.pro</p>');
        }

        // Genera file personalizzato
        const customizedFile = await generateProFile(licenseKey, license.plan, license.email);

        // Invia file
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        res.setHeader('Content-Disposition', `attachment; filename="messageflow-pro-${licenseKey}.html"`);
        res.setHeader('X-Content-Type-Options', 'nosniff');
        res.send(customizedFile);

        console.log(`✅ Download effettuato: ${licenseKey}`);
    } catch (error) {
        console.error('❌ Errore download:', error);
        res.status(500).send('<h1>❌ Errore</h1><p>Si è verificato un errore. Riprova più tardi.</p>');
    }
});

// Pagina successo (SICURA)
app.get('/success', async (req, res) => {
    const sessionId = sanitizeInput(req.query.session_id || '');
    
    try {
        const session = await stripe.checkout.sessions.retrieve(sessionId);
        
        // Trova licenza
        let userLicense = null;
        for (const [key, value] of licenses.entries()) {
            if (value.stripeSessionId === sessionId) {
                userLicense = value;
                break;
            }
        }

        const htmlContent = `
            <!DOCTYPE html>
            <html lang="it">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Acquisto Completato - MessageFlow</title>
                <meta http-equiv="X-Content-Type-Options" content="nosniff">
                <meta http-equiv="X-Frame-Options" content="DENY">
                <style>
                    body {
                        font-family: 'Inter', sans-serif;
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 20px;
                        margin: 0;
                    }
                    .success-card {
                        background: white;
                        padding: 50px;
                        border-radius: 20px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        max-width: 600px;
                        text-align: center;
                    }
                    .success-icon {
                        font-size: 5rem;
                        margin-bottom: 20px;
                    }
                    h1 {
                        color: #2d3748;
                        margin-bottom: 15px;
                    }
                    .license-box {
                        background: #f7fafc;
                        padding: 20px;
                        border-radius: 12px;
                        margin: 30px 0;
                        border: 2px dashed #667eea;
                    }
                    .license-key {
                        font-size: 1.5rem;
                        font-weight: 700;
                        color: #667eea;
                        letter-spacing: 2px;
                        margin: 10px 0;
                        word-break: break-all;
                    }
                    .download-btn {
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        color: white;
                        padding: 18px 40px;
                        border-radius: 30px;
                        text-decoration: none;
                        display: inline-block;
                        font-weight: 700;
                        margin-top: 20px;
                        transition: transform 0.3s;
                    }
                    .download-btn:hover {
                        transform: translateY(-2px);
                    }
                    .warning {
                        background: #fff3cd;
                        color: #856404;
                        padding: 15px;
                        border-radius: 10px;
                        margin-top: 20px;
                        font-size: 0.9rem;
                    }
                </style>
            </head>
            <body>
                <div class="success-card">
                    <div class="success-icon">🎉</div>
                    <h1>Pagamento Completato!</h1>
                    <p>Grazie per aver acquistato MessageFlow Pro!</p>
                    
                    ${userLicense ? `
                        <div class="license-box">
                            <strong>La tua Chiave di Licenza:</strong>
                            <div class="license-key">${userLicense.key}</div>
                            <small style="color: #718096;">⚠️ Conserva questa chiave al sicuro</small>
                        </div>
                        
                        <a href="/download/${userLicense.key}" class="download-btn">
                            📥 Scarica MessageFlow Pro
                        </a>
                        
                        <div class="warning">
                            🔒 <strong>Sicurezza:</strong> Questo link è unico e personale. Non condividerlo con nessuno.
                        </div>
                        
                        <p style="margin-top: 30px; color: #4a5568;">
                            📧 Riceverai anche un'email con la licenza e il link download.
                        </p>
                    ` : `
                        <p style="margin-top: 20px; color: #4a5568;">
                            📧 Riceverai un'email con la tua licenza e il link download a breve.
                        </p>
                    `}
                </div>
            </body>
            </html>
        `;

        res.send(htmlContent);
    } catch (error) {
        console.error('❌ Errore pagina successo:', error);
        res.status(500).send('<h1>❌ Errore</h1><p>Si è verificato un errore. Contatta support@messageflow.pro</p>');
    }
});

// Pagina cancellazione
app.get('/cancel', (req, res) => {
    res.send(`
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <title>Pagamento Annullato</title>
            <style>
                body {
                    font-family: 'Inter', sans-serif;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .cancel-card {
                    background: white;
                    padding: 50px;
                    border-radius: 20px;
                    max-width: 500px;
                    text-align: center;
                }
                a {
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 700;
                }
            </style>
        </head>
        <body>
            <div class="cancel-card">
                <div style="font-size: 4rem; margin-bottom: 20px;">😕</div>
                <h1>Pagamento Annullato</h1>
                <p>Nessun addebito è stato effettuato.</p>
                <p style="margin-top: 30px;">
                    <a href="/">← Torna alla home</a>
                </p>
            </div>
        </body>
        </html>
    `);
});

// Gestione errori 404
app.use((req, res) => {
    res.status(404).send('<h1>404 - Pagina Non Trovata</h1>');
});

// Gestione errori generali
app.use((err, req, res, next) => {
    console.error('❌ Errore server:', err);
    res.status(500).send('<h1>500 - Errore Server</h1><p>Si è verificato un errore. Riprova più tardi.</p>');
});

// ==================== AVVIO SERVER ====================

async function startServer() {
    try {
        // Crea directory data se non esiste
        await fs.mkdir(path.join(__dirname, 'data'), { recursive: true });
        
        // Carica licenze esistenti
        await loadLicenses();

        // Verifica variabili ambiente critiche
        const requiredEnvVars = [
            'STRIPE_SECRET_KEY',
            'STRIPE_WEBHOOK_SECRET',
            'DOMAIN'
        ];

        const missingVars = requiredEnvVars.filter(varName => !process.env[varName]);
        
        if (missingVars.length > 0) {
            console.error('❌ ERRORE: Variabili ambiente mancanti:', missingVars.join(', '));
            console.error('⚠️  Configura il file .env prima di avviare il server!');
            process.exit(1);
        }

        // Avvia server
        app.listen(PORT, () => {
            console.log('\n╔═══════════════════════════════════════════════════════════╗');
            console.log('║                                                           ║');
            console.log('║         🚀 MessageFlow Server SICURO Avviato! 🚀         ║');
            console.log('║                                                           ║');
            console.log('╚═══════════════════════════════════════════════════════════╝\n');
            console.log(`✅ Server in ascolto sulla porta: ${PORT}`);
            console.log(`🌐 URL: ${process.env.DOMAIN || 'http://localhost:' + PORT}`);
            console.log(`📧 Webhook: ${process.env.DOMAIN || 'http://localhost:' + PORT}/webhook`);
            console.log(`🔒 Ambiente: ${process.env.NODE_ENV || 'development'}`);
            console.log(`📦 Licenze caricate: ${licenses.size}`);
            console.log('\n💡 Pronto per ricevere pagamenti sicuri!\n');
        });

    } catch (error) {
        console.error('❌ Errore avvio server:', error);
        process.exit(1);
    }
}

// Gestione graceful shutdown
process.on('SIGTERM', async () => {
    console.log('\n⏳ Ricevuto SIGTERM. Chiusura graceful...');
    await saveLicenses();
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('\n⏳ Ricevuto SIGINT. Chiusura graceful...');
    await saveLicenses();
    process.exit(0);
});

// Avvia il server
startServer();

module.exports = app;
