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

	/* ----------------------------------------------------------------
	 * Assistente di redazione (Gemini)
	 * ---------------------------------------------------------------- */

	/**
	 * ID del post in modifica.
	 *
	 * @return {string} ID.
	 */
	function postId() {
		var field = document.getElementById( 'post_ID' );
		if ( field && field.value ) {
			return field.value;
		}
		return ( window.GLP && window.GLP.postId ) || '';
	}

	/**
	 * Raccoglie le risposte attualmente nel modulo, anche non salvate.
	 *
	 * @param {FormData} data Contenitore.
	 */
	function collectAnswers( data ) {
		var inputs = document.querySelectorAll( '[name^="glp["]' );
		Array.prototype.forEach.call( inputs, function ( input ) {
			if ( ( input.type === 'checkbox' || input.type === 'radio' ) && ! input.checked ) {
				return;
			}
			data.append( input.name, input.value );
		} );
	}

	/**
	 * Mostra un messaggio accanto al pulsante.
	 *
	 * @param {HTMLElement} button Pulsante.
	 * @param {string}      text   Testo.
	 * @param {boolean}     isError Se è un errore.
	 */
	function say( button, text, isError ) {
		var box = button.parentNode.querySelector( '.glp-ai__msg' );
		if ( ! box ) {
			return;
		}
		box.textContent = text;
		box.className = 'glp-ai__msg' + ( isError ? ' is-error' : '' );
	}

	/**
	 * Chiamata AJAX al plugin.
	 *
	 * @param {FormData} data Dati.
	 * @return {Promise} Risposta JSON.
	 */
	function post( data ) {
		return fetch( window.GLP.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
			.then( function ( response ) {
				return response.json().catch( function () {
					throw new Error( 'HTTP ' + response.status );
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || 'Errore' );
				}
				return json.data;
			} );
	}

	/**
	 * Il campo di destinazione contiene già qualcosa?
	 *
	 * @param {string} target Chiave campo.
	 * @param {string} format Formato.
	 * @return {boolean} Vero se pieno.
	 */
	function targetHasContent( target, format ) {
		if ( format === 'faq' || format === 'processo' ) {
			var repeater = document.querySelector( '.glp-repeater[data-field="' + target + '"]' );
			if ( ! repeater ) {
				return false;
			}
			var filled = false;
			Array.prototype.forEach.call( repeater.querySelectorAll( 'textarea' ), function ( cell ) {
				if ( cell.value.trim() ) {
					filled = true;
				}
			} );
			return filled;
		}
		var field = document.querySelector( '[name="glp[' + target + ']"]' );
		return !! ( field && field.value.trim() );
	}

	/**
	 * Scrive il risultato nel campo di destinazione.
	 *
	 * @param {Object} result Risposta del server.
	 */
	function applyResult( result ) {
		var target = result.target;
		var format = result.format;

		if ( format === 'text' ) {
			var field = document.querySelector( '[name="glp[' + target + ']"]' );
			if ( field ) {
				field.value = result.value;
				field.classList.add( 'glp-field--ai' );
			}
			return;
		}

		if ( format === 'list' ) {
			var list = document.querySelector( '[name="glp[' + target + ']"]' );
			if ( list ) {
				list.value = result.value.join( '\n' );
				list.classList.add( 'glp-field--ai' );
			}
			return;
		}

		// FAQ e processo: campi ripetibili.
		var repeater = document.querySelector( '.glp-repeater[data-field="' + target + '"]' );
		if ( ! repeater ) {
			return;
		}
		var rows = repeater.querySelector( '.glp-repeater__rows' );
		rows.innerHTML = '';
		result.value.forEach( function ( item ) {
			var row = addRow( repeater );
			if ( ! row ) {
				return;
			}
			Object.keys( item ).forEach( function ( key ) {
				var cell = row.querySelector( '[name*="[' + key + ']"]' );
				if ( cell ) {
					cell.value = item[ key ];
					cell.classList.add( 'glp-field--ai' );
				}
			} );
		} );
		reindex( repeater );
	}

	document.addEventListener( 'click', function ( event ) {
		var generate = event.target.closest( '.glp-ai__btn' );
		if ( generate ) {
			event.preventDefault();
			runGenerate( generate );
			return;
		}

		var models = event.target.closest( '.glp-ai-models' );
		if ( models ) {
			event.preventDefault();
			loadModels( models );
			return;
		}

		var accept = event.target.closest( '.glp-ai__accept' );
		if ( accept ) {
			event.preventDefault();
			acceptField( accept );
		}
	} );

	/**
	 * Genera il testo di un campo.
	 *
	 * @param {HTMLElement} button Pulsante premuto.
	 */
	function runGenerate( button ) {
		var target = button.getAttribute( 'data-target' );
		var format = button.getAttribute( 'data-format' );
		var texts = ( window.GLP && window.GLP.i18n ) || {};

		if ( targetHasContent( target, format ) && ! window.confirm( texts.overwrite ) ) {
			return;
		}

		var label = button.textContent;
		button.disabled = true;
		button.textContent = texts.working || '…';
		say( button, '' );

		var data = new FormData();
		data.append( 'action', 'glp_ai_generate' );
		data.append( 'nonce', window.GLP.aiNonce );
		data.append( 'post_id', postId() );
		data.append( 'task', button.getAttribute( 'data-task' ) );
		collectAnswers( data );

		post( data )
			.then( function ( result ) {
				applyResult( result );
				say( button, result.notice, false );
			} )
			.catch( function ( error ) {
				say( button, ( texts.error || 'Errore' ) + ': ' + error.message, true );
			} )
			.then( function () {
				button.disabled = false;
				button.textContent = label;
			} );
	}

	/**
	 * Verifica la chiave e propone i modelli disponibili.
	 *
	 * @param {HTMLElement} button Pulsante premuto.
	 */
	function loadModels( button ) {
		var texts = ( window.GLP && window.GLP.i18n ) || {};
		var input = document.getElementById( 'glp-gemini-model' );
		var keyField = document.getElementById( 'glp-gemini-key' );

		button.disabled = true;
		say( button, texts.checking || '…' );

		var data = new FormData();
		data.append( 'action', 'glp_ai_models' );
		data.append( 'nonce', window.GLP.aiNonce );
		if ( keyField ) {
			data.append( 'key', keyField.value );
		}

		post( data )
			.then( function ( result ) {
				var select = document.getElementById( 'glp-model-choices' );
				if ( ! select ) {
					select = document.createElement( 'select' );
					select.id = 'glp-model-choices';
					select.style.marginLeft = '.5rem';
					button.parentNode.insertBefore( select, button.nextSibling );
					select.addEventListener( 'change', function () {
						if ( input ) {
							input.value = select.value;
						}
					} );
				}
				select.innerHTML = '';
				result.models.forEach( function ( model ) {
					var option = document.createElement( 'option' );
					option.value = model.id;
					option.textContent = model.label;
					if ( input && input.value === model.id ) {
						option.selected = true;
					}
					select.appendChild( option );
				} );
				say( button, result.models.length + ' modelli disponibili', false );
			} )
			.catch( function ( error ) {
				say( button, ( texts.error || 'Errore' ) + ': ' + error.message, true );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	/**
	 * Segna come riletto un campo generato.
	 *
	 * @param {HTMLElement} button Pulsante premuto.
	 */
	function acceptField( button ) {
		var row = button.closest( 'li' );
		var data = new FormData();
		data.append( 'action', 'glp_ai_accept' );
		data.append( 'nonce', window.GLP.aiNonce );
		data.append( 'post_id', postId() );
		data.append( 'field', button.getAttribute( 'data-field' ) );

		button.disabled = true;
		post( data )
			.then( function () {
				if ( row && row.parentNode ) {
					row.parentNode.removeChild( row );
				}
			} )
			.catch( function () {
				button.disabled = false;
			} );
	}
} )();
