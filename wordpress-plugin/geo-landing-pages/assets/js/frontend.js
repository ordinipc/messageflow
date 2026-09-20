/* =========================================================
   GEO LANDING PAGES — front-end
   Comparsa progressiva e apertura delle FAQ.
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

	document.addEventListener( 'DOMContentLoaded', function () {
		faqSingola();

		// Nell'editor Elementor il contenuto deve essere sempre visibile.
		if ( ridotto || document.body.classList.contains( 'elementor-editor-active' ) ) {
			mostraTutto();
			return;
		}

		rivelaHero();
		rivelaSezioni();
	} );
} )();
