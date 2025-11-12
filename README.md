# 🚀 MessageFlow - Sito Web E-commerce Completo

Sistema completo per vendere MessageFlow Pro con integrazione Stripe, SEO ottimizzato e gestione licenze.

---

## 📦 Contenuti del Progetto

```
messageflow-website/
├── index.html              # Homepage con SEO e prezzi
├── demo.html               # Demo limitata (max 5 contatti)
├── privacy.html            # Privacy Policy GDPR compliant
├── terms.html              # Termini di Servizio
├── server.js               # Backend Node.js + Stripe
├── package.json            # Dipendenze npm
├── .env.example            # Template variabili ambiente
└── templates/
    └── messageflow-pro-template.html  # Template versione Pro
```

---

## 🎯 Funzionalità

### ✅ Sito Web
- ✨ Landing page professionale con SEO ottimizzato
- 💳 Integrazione completa con Stripe Checkout
- 🎁 Demo gratuita funzionante (limitata a 5 contatti)
- 📊 Sistema di prezzi: Pro (€97) e Business (€247)
- 📱 Design responsive e mobile-friendly
- 🔒 Pagine legali (Privacy Policy, Terms of Service)

### ✅ Sistema di Pagamento
- 💰 Stripe Checkout integrato
- 🔐 Webhook per conferme automatiche
- 🎫 Generazione automatica chiavi di licenza
- 📧 Email automatica con licenza e download
- 💾 Sistema di download sicuro post-acquisto

### ✅ SEO & Marketing
- 🎯 Meta tags ottimizzati per Google
- 📈 Schema.org structured data
- 🔍 Sitemap ready
- 📊 Google Analytics ready
- 💬 Open Graph per social media

---

## 🛠️ Setup Rapido

### 1️⃣ Prerequisiti
- Node.js 16+ installato
- Account Stripe (gratuito per test)
- Account email per invio licenze

### 2️⃣ Installazione

```bash
# 1. Installa le dipendenze
npm install

# 2. Copia e configura le variabili ambiente
cp .env.example .env
nano .env  # Modifica con i tuoi dati

# 3. Avvia il server
npm start

# Server in esecuzione su http://localhost:3000
```

### 3️⃣ Configurazione Stripe

#### Crea Account Stripe
1. Vai su https://dashboard.stripe.com/register
2. Completa la registrazione
3. Attiva l'account (verifica email e dati)

#### Crea i Prodotti
1. Dashboard Stripe → **Prodotti** → **Aggiungi prodotto**
2. **Prodotto 1: MessageFlow Pro**
   - Nome: `MessageFlow Pro`
   - Prezzo: `€97.00` (una tantum)
   - Copia il **Price ID** (esempio: `price_xxx`)
3. **Prodotto 2: MessageFlow Business**
   - Nome: `MessageFlow Business`
   - Prezzo: `€247.00` (una tantum)
   - Copia il **Price ID**

#### Ottieni le Chiavi API
1. Dashboard Stripe → **Developers** → **API keys**
2. Copia:
   - **Publishable key** (inizia con `pk_`)
   - **Secret key** (inizia con `sk_`)

#### Configura Webhook
1. Dashboard Stripe → **Developers** → **Webhooks**
2. Clicca **Add endpoint**
3. URL: `https://tuo-dominio.com/webhook`
4. Eventi da ascoltare: `checkout.session.completed`
5. Copia il **Webhook signing secret** (inizia con `whsec_`)

### 4️⃣ Configura .env

Apri il file `.env` e inserisci:

```env
# Stripe Keys
STRIPE_SECRET_KEY=sk_test_YOUR_SECRET_KEY
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# Price IDs
STRIPE_PRICE_ID_PRO=price_YOUR_PRO_PRICE_ID
STRIPE_PRICE_ID_BUSINESS=price_YOUR_BUSINESS_PRICE_ID

# Domain
DOMAIN=https://tuo-dominio.com

# Email (Gmail esempio)
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USER=tuaemail@gmail.com
EMAIL_PASSWORD=tua-app-password
```

---

## 🚀 Deploy in Produzione

### Opzione 1: Heroku (Consigliato)

```bash
# 1. Installa Heroku CLI
# https://devcenter.heroku.com/articles/heroku-cli

# 2. Login
heroku login

# 3. Crea app
heroku create messageflow-pro

# 4. Imposta variabili ambiente
heroku config:set STRIPE_SECRET_KEY=sk_live_xxx
heroku config:set STRIPE_PUBLISHABLE_KEY=pk_live_xxx
heroku config:set STRIPE_WEBHOOK_SECRET=whsec_xxx
heroku config:set STRIPE_PRICE_ID_PRO=price_xxx
heroku config:set STRIPE_PRICE_ID_BUSINESS=price_xxx
heroku config:set DOMAIN=https://messageflow-pro.herokuapp.com

# 5. Deploy
git push heroku main

# 6. Apri il sito
heroku open
```

### Opzione 2: Vercel

```bash
# 1. Installa Vercel CLI
npm i -g vercel

# 2. Deploy
vercel

# 3. Aggiungi variabili ambiente su vercel.com
# Dashboard → Settings → Environment Variables
```

### Opzione 3: VPS (DigitalOcean, AWS, etc)

```bash
# 1. Connettiti via SSH
ssh user@your-server-ip

# 2. Clona repository
git clone https://github.com/tuoaccount/messageflow-website.git
cd messageflow-website

# 3. Installa dipendenze
npm install

# 4. Configura PM2 per auto-restart
npm install -g pm2
pm2 start server.js --name messageflow
pm2 startup
pm2 save

# 5. Configura Nginx come reverse proxy
sudo nano /etc/nginx/sites-available/messageflow

# Contenuto Nginx:
server {
    listen 80;
    server_name tuo-dominio.com;
    
    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}

# 6. Attiva sito e riavvia Nginx
sudo ln -s /etc/nginx/sites-available/messageflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx

# 7. Installa SSL con Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tuo-dominio.com
```

---

## 📧 Configurazione Email

### Gmail (più semplice)

```env
EMAIL_HOST=smtp.gmail.com
EMAIL_PORT=587
EMAIL_USER=tuaemail@gmail.com
EMAIL_PASSWORD=app-password-generata
```

**Genera App Password:**
1. Account Google → Sicurezza
2. Verifica in due passaggi (attivala se non lo è)
3. Password per le app → Genera
4. Usa la password generata nel .env

### SendGrid (professionale)

```env
EMAIL_HOST=smtp.sendgrid.net
EMAIL_PORT=587
EMAIL_USER=apikey
EMAIL_PASSWORD=tua-sendgrid-api-key
```

---

## 🧪 Test in Locale

### Test Pagamenti con Stripe Test Mode

Carte di test Stripe:
- ✅ **Successo**: `4242 4242 4242 4242`
- ❌ **Rifiutata**: `4000 0000 0000 0002`
- 🔐 **3D Secure**: `4000 0025 0000 3155`

Dati da usare:
- CVC: qualsiasi 3 cifre
- Data: qualsiasi data futura
- ZIP: qualsiasi codice

### Test Webhook in Locale

```bash
# 1. Installa Stripe CLI
# https://stripe.com/docs/stripe-cli

# 2. Login
stripe login

# 3. Forward webhook a localhost
stripe listen --forward-to localhost:3000/webhook

# 4. Copia il webhook secret mostrato e aggiungilo a .env
```

---

## 📊 Monitoraggio & Analytics

### Google Analytics Setup

1. Crea property su https://analytics.google.com
2. Ottieni ID (tipo: `G-XXXXXXXXXX`)
3. Aggiungi in `index.html` prima di `</head>`:

```html
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

### Hotjar (Heat Maps)

```html
<!-- Hotjar Tracking Code -->
<script>
    (function(h,o,t,j,a,r){
        h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
        h._hjSettings={hjid:YOUR_HOTJAR_ID,hjsv:6};
        a=o.getElementsByTagName('head')[0];
        r=o.createElement('script');r.async=1;
        r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
        a.appendChild(r);
    })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
</script>
```

---

## 🎨 Personalizzazione

### Cambiare Colori

Nel file `index.html`, modifica le variabili CSS:

```css
:root {
    --primary: #667eea;     /* Colore principale */
    --secondary: #764ba2;   /* Colore secondario */
    --success: #48bb78;     /* Verde successo */
    --danger: #f56565;      /* Rosso errore */
}
```

### Cambiare Logo

Sostituisci l'emoji nella navbar:

```html
<div class="logo">
    <span>⚡</span>  <!-- Cambia questo -->
    MessageFlow
</div>
```

### Aggiungere Nuovo Piano

1. Crea prodotto su Stripe
2. Aggiungi in `server.js`:

```javascript
const PRODUCTS = {
    // ... piani esistenti
    enterprise: {
        name: 'MessageFlow Enterprise',
        price: 49700, // €497
        priceId: 'price_xxx',
        features: [...]
    }
};
```

3. Aggiungi card in `index.html` nella sezione pricing

---

## 🔒 Sicurezza

### Checklist Produzione

- [ ] Usa HTTPS (Let's Encrypt)
- [ ] Cambia tutte le chiavi da test a live su Stripe
- [ ] Abilita rate limiting (usa express-rate-limit)
- [ ] Valida tutti gli input utente
- [ ] Usa helmet.js per header sicuri
- [ ] Mantieni Node.js aggiornato
- [ ] Backup regolari del database licenze
- [ ] Monitora i log per attività sospette

### Aggiungi Rate Limiting

```bash
npm install express-rate-limit
```

```javascript
const rateLimit = require('express-rate-limit');

const limiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 minuti
    max: 100 // max 100 richieste
});

app.use('/create-checkout-session', limiter);
```

---

## 📈 Marketing & SEO

### Sitemap.xml

Crea `sitemap.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://tuo-dominio.com/</loc>
    <lastmod>2025-11-12</lastmod>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://tuo-dominio.com/demo.html</loc>
    <priority>0.8</priority>
  </url>
</urlset>
```

### robots.txt

Crea `robots.txt`:

```
User-agent: *
Allow: /
Disallow: /admin/

Sitemap: https://tuo-dominio.com/sitemap.xml
```

---

## 💡 Tips & Best Practices

1. **Test Prima di Lanciare**
   - Prova tutti i flussi di pagamento
   - Verifica email di conferma
   - Testa su mobile

2. **Backup Regolari**
   - Database licenze
   - Configurazioni
   - File template

3. **Monitoraggio**
   - Imposta alerting per errori
   - Monitora transazioni Stripe
   - Controlla uptime server

4. **Customer Support**
   - Rispondi velocemente alle email
   - FAQ ben documentate
   - Video tutorial

---

## 🆘 Troubleshooting

### Il webhook Stripe non funziona
- Verifica che l'URL sia raggiungibile pubblicamente
- Controlla che il secret sia corretto
- Guarda i log in Stripe Dashboard

### Email non inviate
- Verifica credenziali SMTP
- Controlla firewall/porte bloccate
- Prova con altro provider email

### Errore "popup bloccato" nella demo
- Istruisci utenti a consentire popup
- Aggiungi istruzioni visibili nella UI

---

## 📞 Supporto

- 📧 Email: support@messageflow.pro
- 💬 Issues: GitHub Issues
- 📚 Docs: (crea una wiki)

---

## 📄 Licenza

Copyright © 2025 Mercury Marketing. Tutti i diritti riservati.

---

## 🎉 Credits

Sviluppato con ❤️ da **Paolo - Mercury Marketing**

- Stripe per i pagamenti
- Node.js & Express
- WhatsApp Web API

---

**🚀 Buon lancio con MessageFlow!**