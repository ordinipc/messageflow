/**
 * Comportamenti minimi del front-end: una sola FAQ aperta alla volta.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var summary = event.target.closest( '.glp-faq__q' );
		if ( ! summary ) {
			return;
		}
		var current = summary.parentElement;
		var faq = current.closest( '.glp-faq' );
		if ( ! faq ) {
			return;
		}
		Array.prototype.forEach.call( faq.querySelectorAll( 'details[open]' ), function ( item ) {
			if ( item !== current ) {
				item.removeAttribute( 'open' );
			}
		} );
	} );
} )();
