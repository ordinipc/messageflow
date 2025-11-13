// ============================================
// AGGIORNAMENTO server.js PER CODICI SCONTO
// ============================================

// TROVA LA FUNZIONE /create-checkout-session (circa riga 165)
// SOSTITUISCI CON QUESTA VERSIONE AGGIORNATA:

app.post('/create-checkout-session', async (req, res) => {
    try {
        const { plan, email, discountCode } = req.body;

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

        // Configurazione base sessione
        const sessionConfig = {
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
            payment_intent_data: {
                metadata: {
                    plan: plan,
                    integration: 'messageflow'
                }
            },
            // ABILITA CODICI PROMO DI STRIPE
            allow_promotion_codes: true
        };

        // Se c'è un codice sconto personalizzato, cerca il coupon su Stripe
        if (discountCode) {
            try {
                // Cerca il coupon su Stripe
                const coupons = await stripe.coupons.list({ limit: 100 });
                const coupon = coupons.data.find(c => c.id.toUpperCase() === discountCode.toUpperCase());
                
                if (coupon) {
                    // Applica il coupon
                    sessionConfig.discounts = [{
                        coupon: coupon.id
                    }];
                    console.log(`✅ Sconto applicato: ${coupon.id}`);
                }
            } catch (error) {
                console.log(`⚠️ Coupon non trovato: ${discountCode}`);
                // Continua comunque senza sconto
            }
        }

        // Crea sessione Stripe
        const session = await stripe.checkout.sessions.create(sessionConfig);

        // Log sicuro (senza dati sensibili)
        console.log(`✅ Sessione checkout creata: ${session.id} - Piano: ${plan}${discountCode ? ' - Sconto: ' + discountCode : ''}`);

        res.json({ id: session.id });
    } catch (error) {
        console.error('❌ Errore creazione sessione:', error.message);
        res.status(500).json({ error: 'Errore durante la creazione della sessione di pagamento' });
    }
});

// ============================================
// CODICI SCONTO: 3 MODALITÀ
// ============================================

/**
 * MODALITÀ 1: Coupon Stripe (Consigliata)
 * - Crei coupon su Stripe Dashboard
 * - Aggiungi: allow_promotion_codes: true
 * - Cliente inserisce codice in Stripe Checkout
 * 
 * MODALITÀ 2: Coupon Stripe + Campo Custom
 * - Come sopra + campo sconto nel tuo sito
 * - Applichi il coupon via API (come nel codice sopra)
 * 
 * MODALITÀ 3: Sconti Dinamici
 * - Calcoli prezzo scontato nel backend
 * - Crei price on-the-fly con Stripe
 */

// ============================================
// ESEMPI COUPON DA CREARE SU STRIPE
// ============================================

/**
 * Dashboard Stripe → Products → Coupons → Create
 * 
 * Coupon 1: LANCIO2025
 * - ID: LANCIO2025
 * - Type: Percentage
 * - Value: 20%
 * - Duration: Once
 * - Max: 50 utilizzi
 * - Expires: 31/12/2025
 * 
 * Coupon 2: PROMO50
 * - ID: PROMO50
 * - Type: Amount
 * - Value: €50
 * - Duration: Once
 * 
 * Coupon 3: VIP
 * - ID: VIP
 * - Type: Percentage
 * - Value: 30%
 * - Duration: Forever (per clienti VIP)
 */

// ============================================
// STATISTICHE USO CODICI
// ============================================

// Stripe Dashboard → Products → Coupons → [Nome Coupon]
// Puoi vedere:
// - Quante volte è stato usato
// - Revenue generato
// - Clienti che l'hanno usato

// ============================================
// FINE AGGIORNAMENTO
// ============================================
