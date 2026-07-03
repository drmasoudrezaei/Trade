( function ( $ ) {
	'use strict';

	$( function () {
		var $refreshAll = $( '#csp-refresh-all' );
		var $status     = $( '.csp-refresh-status' );

		function setStatus( text ) {
			$status.text( text );
		}

		$refreshAll.on( 'click', function () {
			$refreshAll.prop( 'disabled', true );
			setStatus( cspAdmin.i18nRefreshing || 'در حال به‌روزرسانی...' );

			$.post( cspAdmin.ajaxUrl, {
				action: 'csp_refresh_now',
				nonce: cspAdmin.nonce
			} ).done( function ( response ) {
				if ( response && response.success ) {
					setStatus( response.data.message );
					window.location.reload();
				} else {
					setStatus( ( response && response.data && response.data.message ) || 'خطا رخ داد.' );
				}
			} ).fail( function () {
				setStatus( 'خطا در ارتباط با سرور.' );
			} ).always( function () {
				$refreshAll.prop( 'disabled', false );
			} );
		} );

		$( document ).on( 'click', '.csp-refresh-one', function () {
			var $button = $( this );
			var coinId  = $button.data( 'coin-id' );

			$button.prop( 'disabled', true ).text( '...' );

			$.post( cspAdmin.ajaxUrl, {
				action: 'csp_refresh_now',
				nonce: cspAdmin.nonce,
				coin_id: coinId
			} ).done( function ( response ) {
				if ( response && response.success ) {
					window.location.reload();
				} else {
					$button.text( 'خطا' );
				}
			} ).fail( function () {
				$button.text( 'خطا' );
			} );
		} );

		// Lightweight AJAX user search for the subscriptions screen.
		var $userSearch  = $( '#csp-user-search' );
		var $userId      = $( '#csp-user-id' );
		var $userResults = $( '#csp-user-results' );
		var searchTimer  = null;

		$userSearch.on( 'input', function () {
			var term = $userSearch.val();
			$userId.val( '' );

			window.clearTimeout( searchTimer );
			if ( term.length < 2 ) {
				$userResults.hide().empty();
				return;
			}

			searchTimer = window.setTimeout( function () {
				$.post( cspAdmin.ajaxUrl, {
					action: 'csp_search_users',
					nonce: cspAdmin.nonce,
					term: term
				} ).done( function ( response ) {
					if ( ! response || ! response.success ) {
						return;
					}
					$userResults.empty();
					response.data.users.forEach( function ( user ) {
						var $row = $( '<div></div>' ).text( user.text ).attr( 'data-id', user.id );
						$userResults.append( $row );
					} );
					$userResults.toggle( response.data.users.length > 0 );
				} );
			}, 300 );
		} );

		$( document ).on( 'click', '#csp-user-results div', function () {
			$userId.val( $( this ).attr( 'data-id' ) );
			$userSearch.val( $( this ).text() );
			$userResults.hide();
		} );
	} );
} )( jQuery );
