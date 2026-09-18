/**
 * Campi ripetibili e precaricamento delle FAQ.
 */
( function () {
	'use strict';

	/**
	 * Rinumera i campi di un ripetitore dopo aggiunte o rimozioni.
	 *
	 * @param {HTMLElement} repeater Contenitore.
	 */
	function reindex( repeater ) {
		var base = repeater.getAttribute( 'data-name' );
		var rows = repeater.querySelectorAll( '.glp-repeater__rows > .glp-repeater__row' );

		Array.prototype.forEach.call( rows, function ( row, index ) {
			Array.prototype.forEach.call( row.querySelectorAll( '[name]' ), function ( input ) {
				var name = input.getAttribute( 'name' );
				var sub = name.substring( name.lastIndexOf( '[' ) );
				input.setAttribute( 'name', base + '[' + index + ']' + sub );
			} );
		} );
	}

	/**
	 * Aggiunge una riga vuota.
	 *
	 * @param {HTMLElement} repeater Contenitore.
	 * @return {HTMLElement} La riga creata.
	 */
	function addRow( repeater ) {
		var tpl = repeater.querySelector( '.glp-repeater__tpl' );
		var rows = repeater.querySelector( '.glp-repeater__rows' );
		if ( ! tpl || ! rows ) {
			return null;
		}
		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = tpl.innerHTML.replace( /__i__/g, String( rows.children.length ) );
		var row = wrapper.firstElementChild;
		rows.appendChild( row );
		reindex( repeater );
		return row;
	}

	document.addEventListener( 'click', function ( event ) {
		var add = event.target.closest( '.glp-repeater__add' );
		if ( add ) {
			event.preventDefault();
			var repeater = add.closest( '.glp-repeater' );
			var row = addRow( repeater );
			if ( row ) {
				var first = row.querySelector( 'input, textarea' );
				if ( first ) {
					first.focus();
				}
			}
			return;
		}

		var remove = event.target.closest( '.glp-repeater__remove' );
		if ( remove ) {
			event.preventDefault();
			var target = remove.closest( '.glp-repeater' );
			var current = remove.closest( '.glp-repeater__row' );
			var message = ( window.GLP && window.GLP.confirmRemove ) || 'Eliminare questa riga?';
			if ( ! window.confirm( message ) ) {
				return;
			}
			current.parentNode.removeChild( current );
			if ( ! target.querySelector( '.glp-repeater__rows > .glp-repeater__row' ) ) {
				addRow( target );
			}
			reindex( target );
			return;
		}

		var suggest = event.target.closest( '.glp-faq-suggest' );
		if ( suggest ) {
			event.preventDefault();
			fillFaqSuggestions( suggest );
		}
	} );

	/**
	 * Precarica le domande frequenti tipiche nel ripetitore FAQ.
	 *
	 * @param {HTMLElement} button Pulsante premuto.
	 */
	function fillFaqSuggestions( button ) {
		var box = button.closest( '.postbox' ) || document;
		var repeater = box.querySelector( '.glp-repeater[data-field="faq"]' );
		var questions = ( window.GLP && window.GLP.faqSuggestions ) || [];
		if ( ! repeater || ! questions.length ) {
			return;
		}

		// Domande già presenti: non vengono duplicate.
		var existing = [];
		Array.prototype.forEach.call( repeater.querySelectorAll( 'input[name*="[domanda]"]' ), function ( input ) {
			if ( input.value.trim() ) {
				existing.push( input.value.trim().toLowerCase() );
			}
		} );

		questions.forEach( function ( question ) {
			if ( existing.indexOf( question.toLowerCase() ) !== -1 ) {
				return;
			}
			// Riusa una riga vuota se disponibile, altrimenti ne crea una.
			var empty = null;
			Array.prototype.forEach.call( repeater.querySelectorAll( '.glp-repeater__row' ), function ( row ) {
				var field = row.querySelector( 'input[name*="[domanda]"]' );
				if ( ! empty && field && ! field.value.trim() ) {
					empty = row;
				}
			} );
			var row = empty || addRow( repeater );
			if ( ! row ) {
				return;
			}
			var input = row.querySelector( 'input[name*="[domanda]"]' );
			if ( input ) {
				input.value = question;
			}
		} );

		reindex( repeater );
	}
} )();
