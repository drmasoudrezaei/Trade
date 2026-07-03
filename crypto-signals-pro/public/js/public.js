( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var dashboard = document.querySelector( '.csp-dashboard' );
		if ( ! dashboard ) {
			return;
		}

		var filterInput  = dashboard.querySelector( '.csp-filter-input' );
		var filterSelect = dashboard.querySelector( '.csp-filter-signal' );
		var cards        = dashboard.querySelectorAll( '.csp-card' );

		function applyFilters() {
			var term   = filterInput ? filterInput.value.trim().toLowerCase() : '';
			var signal = filterSelect ? filterSelect.value : '';

			cards.forEach( function ( card ) {
				var symbol = card.getAttribute( 'data-symbol' ) || '';
				var matchesTerm   = ! term || symbol.indexOf( term ) !== -1;
				var matchesSignal = ! signal || card.getAttribute( 'data-signal' ) === signal;
				card.style.display = ( matchesTerm && matchesSignal ) ? '' : 'none';
			} );
		}

		if ( filterInput ) {
			filterInput.addEventListener( 'input', applyFilters );
		}
		if ( filterSelect ) {
			filterSelect.addEventListener( 'change', applyFilters );
		}
	} );
} )();
