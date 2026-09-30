/*
 * BlueFairy Microgreen Tracker — the Microgreens screen.
 *
 * A trash link asks first. The question is on the link itself
 * (data-bfmt-confirm), so it is translated and escaped where it is printed.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( 'a[data-bfmt-confirm]' ) : null;

		if ( link && ! window.confirm( link.getAttribute( 'data-bfmt-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
}() );
