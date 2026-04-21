/**
 * WP Easy Redirect — Admin JS
 *
 * Handles adding/removing redirect rule rows dynamically.
 */
/* global werRedirectsKey, jQuery */
( function ( $ ) {
	'use strict';

	var WER = {
		/**
		 * Initialise events.
		 */
		init: function () {
			$( '#wer-add-row' ).on( 'click', WER.addRow );
			$( '#wer-redirects-table' ).on( 'click', '.wer-remove-row', WER.removeRow );
		},

		/**
		 * Add a new blank redirect row.
		 */
		addRow: function () {
			var idx      = $( '#wer-redirects-body tr' ).length;
			var types    = [ '301', '302', '307', '308' ];
			var options  = '';
			var prefix   = werRedirectsKey.value + '[' + idx + ']';
			var numTypes = types.length;
			var i;

			for ( i = 0; i < numTypes; i++ ) {
				options += '<option value="' + types[ i ] + '">' + types[ i ] + '</option>';
			}

			var row = $(
				'<tr>' +
					'<td><input type="text" class="regular-text" name="' + prefix + '[from]" value="" placeholder="/old-page" /></td>' +
					'<td><input type="url" class="regular-text" name="' + prefix + '[to]" value="" placeholder="https://new-site.example.com/page" /></td>' +
					'<td><select name="' + prefix + '[type]">' + options + '</select></td>' +
					'<td><button type="button" class="button wer-remove-row" title="Remove">✕</button></td>' +
				'</tr>'
			);

			$( '#wer-redirects-body' ).append(
				row
			);

			row.find( 'input:first' ).focus();
		},

		/**
		 * Remove a redirect row.
		 */
		removeRow: function () {
			$( this ).closest( 'tr' ).fadeOut(
				200,
				function () {
					$( this ).remove();
				}
			);
		}
	};

	$( WER.init );
}( jQuery ) );
