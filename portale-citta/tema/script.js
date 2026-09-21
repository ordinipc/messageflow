/* =========================================================
   PORTALE CITTÀ — front-end

   Comparsa progressiva, apertura delle FAQ e, se gli effetti
   sono attivi, alone che segue il cursore sui riquadri e
   numeri che salgono fino al valore.

   Tutto è facoltativo: senza JavaScript la pagina resta
   completa e leggibile.
========================================================= */

( function () {
	'use strict';

	var ridotto = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * Mostra subito tutto, senza animazioni.
	 */
	function mostraTutto() {
		var elementi = document.querySelectorAll( '.glp-reveal' );
		Array.prototype.forEach.call( elementi, function ( el ) {
			el.classList.add( 'is-visible' );
		} );
	}

	/**
	 * Comparsa dell'intestazione: sfalsata, come sul resto del sito.
	 */
	function rivelaHero() {
		var hero = document.querySelector( '.glp-hero' );
		if ( ! hero ) {
			return;
		}

		var elementi = hero.querySelectorAll( '.glp-reveal' );

		Array.prototype.forEach.call( elementi, function ( el, indice ) {
			el.style.transitionDelay = ( 100 + indice * 90 ) + 'ms';
		} );

		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				Array.prototype.forEach.call( elementi, function ( el ) {
					el.classList.add( 'is-visible' );
				} );
			} );
		} );
	}

	/**
	 * Comparsa delle sezioni quando entrano nello schermo.
	 */
	function rivelaSezioni() {
		var sezioni = document.querySelectorAll( '.glp-sections .glp-reveal' );
		if ( ! sezioni.length ) {
			return;
		}

		// Senza IntersectionObserver si mostra tutto subito.
		if ( ! ( 'IntersectionObserver' in window ) ) {
			Array.prototype.forEach.call( sezioni, function ( el ) {
				el.classList.add( 'is-visible' );
			} );
			return;
		}

		var osservatore = new IntersectionObserver(
			function ( voci ) {
				voci.forEach( function ( voce ) {
					if ( ! voce.isIntersecting ) {
						return;
					}
					voce.target.classList.add( 'is-visible' );
					osservatore.unobserve( voce.target );
				} );
			},
			{ rootMargin: '0px 0px -12% 0px', threshold: 0.05 }
		);

		Array.prototype.forEach.call( sezioni, function ( el ) {
			osservatore.observe( el );
		} );
	}

	/**
	 * Una sola FAQ aperta alla volta.
	 */
	function faqSingola() {
		document.addEventListener( 'click', function ( evento ) {
			var domanda = evento.target.closest( '.glp-faq__q' );
			if ( ! domanda ) {
				return;
			}

			var corrente = domanda.parentElement;
			var gruppo = corrente.closest( '.glp-faq' );
			if ( ! gruppo ) {
				return;
			}

			Array.prototype.forEach.call( gruppo.querySelectorAll( 'details[open]' ), function ( voce ) {
				if ( voce !== corrente ) {
					voce.removeAttribute( 'open' );
				}
			} );
		} );
	}

	/**
	 * Alone che segue il cursore dentro i riquadri.
	 * Aggiorna due variabili CSS; il disegno lo fa il foglio di stile.
	 */
	function aloneCursore() {
		if ( ! window.matchMedia || ! window.matchMedia( '(hover: hover)' ).matches ) {
			return;
		}

		var riquadri = document.querySelectorAll( '.glp-box' );
		if ( ! riquadri.length ) {
			return;
		}

		Array.prototype.forEach.call( riquadri, function ( box ) {
			var attesa = false;

			box.addEventListener( 'pointermove', function ( ev ) {
				if ( attesa ) {
					return;
				}
				attesa = true;

				// Un aggiornamento per fotogramma: il resto è sprecato.
				window.requestAnimationFrame( function () {
					var r = box.getBoundingClientRect();
					box.style.setProperty( '--glp-x', ( ev.clientX - r.left ) + 'px' );
					box.style.setProperty( '--glp-y', ( ev.clientY - r.top ) + 'px' );
					attesa = false;
				} );
			} );

			box.addEventListener( 'pointerleave', function () {
				box.style.removeProperty( '--glp-x' );
				box.style.removeProperty( '--glp-y' );
			} );
		} );
	}

	/**
	 * I numeri salgono da zero al valore, una volta sola,
	 * quando il riquadro entra nello schermo.
	 */
	function numeriCheSalgono() {
		var numeri = document.querySelectorAll( '[data-conta-fino]' );
		if ( ! numeri.length || ! window.IntersectionObserver ) {
			return;
		}

		function sali( el ) {
			var fine = parseInt( el.getAttribute( 'data-conta-fino' ), 10 );
			if ( isNaN( fine ) || fine <= 0 ) {
				return;
			}

			var durata = 900;
			var avvio = null;

			function passo( ora ) {
				if ( null === avvio ) {
					avvio = ora;
				}
				var quota = Math.min( ( ora - avvio ) / durata, 1 );
				// Frenata dolce verso il valore finale.
				var dolce = 1 - Math.pow( 1 - quota, 3 );
				el.textContent = String( Math.round( fine * dolce ) );

				if ( quota < 1 ) {
					window.requestAnimationFrame( passo );
				} else {
					el.textContent = String( fine );
				}
			}

			window.requestAnimationFrame( passo );
		}

		var osservatore = new IntersectionObserver( function ( voci ) {
			voci.forEach( function ( v ) {
				if ( ! v.isIntersecting ) {
					return;
				}
				osservatore.unobserve( v.target );
				sali( v.target );
			} );
		}, { threshold: 0.6 } );

		Array.prototype.forEach.call( numeri, function ( el ) {
			osservatore.observe( el );
		} );
	}

	/**
	 * Menu dell'intestazione.
	 *
	 * Prima misura: se le voci non stanno in riga accanto a logo e
	 * telefono, l'intestazione passa alla forma compatta e il menu si
	 * chiude dietro il pulsante a tre righe. Vale a qualsiasi larghezza,
	 * non solo sul telefono: con otto servizi anche un desktop è stretto.
	 *
	 * Finché questo codice non gira il menu resta visibile, quindi chi
	 * non ha JavaScript trova comunque tutte le voci.
	 */
	function menuIntestazione() {
		var barra = document.querySelector( '.glp-topbar' );
		var bottone = document.querySelector( '.glp-topbar__toggle' );
		var pannello = document.getElementById( 'glp-menu' );
		var elenco = pannello ? pannello.querySelector( '.glp-topbar__list' ) : null;

		if ( ! barra || ! bottone || ! pannello || ! elenco ) {
			return;
		}

		function apri() {
			pannello.classList.add( 'is-aperto' );
			bottone.setAttribute( 'aria-expanded', 'true' );
			bottone.setAttribute( 'aria-label', 'Chiudi il menu' );
		}

		function chiudi() {
			pannello.classList.remove( 'is-aperto' );
			bottone.setAttribute( 'aria-expanded', 'false' );
			bottone.setAttribute( 'aria-label', 'Apri il menu' );
		}

		function aperto() {
			return 'true' === bottone.getAttribute( 'aria-expanded' );
		}

		/**
		 * Il menu ci sta in riga? Si misura sempre in forma distesa,
		 * altrimenti si misurerebbe il pannello già chiuso.
		 */
		function adatta() {
			// Si misura sempre in forma distesa, altrimenti si misurerebbe
			// il pannello già chiuso o la seconda riga già aperta.
			barra.classList.remove( 'glp-topbar--compatto', 'glp-topbar--due-righe' );
			chiudi();

			// Due pixel di margine: gli arrotondamenti non devono far scattare la misura.
			if ( elenco.scrollWidth <= pannello.clientWidth + 2 ) {
				return;
			}

			// Non ci sta in riga. Su schermo largo il menu va a capo dentro
			// l'intestazione: i collegamenti restano tutti visibili, che è
			// meglio sia per chi legge sia per chi indicizza. Solo quando lo
			// schermo è davvero stretto conviene chiuderlo dietro il pulsante.
			if ( window.innerWidth >= 900 ) {
				barra.classList.add( 'glp-topbar--due-righe' );
			} else {
				barra.classList.add( 'glp-topbar--compatto' );
			}
		}

		bottone.addEventListener( 'click', function ( ev ) {
			ev.stopPropagation();
			if ( aperto() ) {
				chiudi();
			} else {
				apri();
			}
		} );

		// Un clic fuori chiude.
		document.addEventListener( 'click', function ( ev ) {
			if ( aperto() && ! pannello.contains( ev.target ) && ! bottone.contains( ev.target ) ) {
				chiudi();
			}
		} );

		// Esc chiude e riporta il fuoco al pulsante.
		document.addEventListener( 'keydown', function ( ev ) {
			if ( 'Escape' === ev.key && aperto() ) {
				chiudi();
				bottone.focus();
			}
		} );

		// Alla prima apertura e a ogni cambio di larghezza.
		var attesa = null;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( attesa );
			attesa = window.setTimeout( adatta, 160 );
		} );

		adatta();

		// I caratteri caricati dopo cambiano la larghezza delle voci.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( adatta );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		menuIntestazione();
		faqSingola();

		// Nell'editor Elementor il contenuto deve essere sempre visibile.
		if ( ridotto || document.body.classList.contains( 'elementor-editor-active' ) ) {
			mostraTutto();
			return;
		}

		rivelaHero();
		rivelaSezioni();

		if ( document.body.classList.contains( 'glp-effetti' ) ) {
			aloneCursore();
			numeriCheSalgono();
		}
	} );
} )();
