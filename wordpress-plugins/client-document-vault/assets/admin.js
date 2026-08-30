/* global CDV_Data, jQuery */
( function ( $ ) {
	'use strict';

	$( function () {
		var $form = $( '#cdv-upload-form' );

		if ( ! $form.length ) {
			return;
		}

		$form.on( 'submit', function ( event ) {
			event.preventDefault();

			var $status    = $form.find( '.cdv-upload-status' );
			var $fileInput = $form.find( '#cdv_file' )[ 0 ];
			var $submit    = $form.find( 'button[type="submit"]' );

			if ( ! $fileInput.files.length ) {
				$status
					.removeClass( 'cdv-status-success' )
					.addClass( 'cdv-status-error' )
					.text( CDV_Data.i18n.select_file );
				return;
			}

			if ( $fileInput.files[ 0 ].size > CDV_Data.max_filesize ) {
				$status
					.removeClass( 'cdv-status-success' )
					.addClass( 'cdv-status-error' )
					.text( CDV_Data.i18n.too_large );
				return;
			}

			var formData = new FormData( $form[ 0 ] );
			formData.append( 'action', 'cdv_upload_document' );
			formData.append( 'nonce', CDV_Data.upload_nonce );

			$submit.prop( 'disabled', true );
			$status
				.removeClass( 'cdv-status-error cdv-status-success' )
				.text( CDV_Data.i18n.uploading );

			$.ajax( {
				url: CDV_Data.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						$status.addClass( 'cdv-status-success' ).text( response.data.message );

						var $tbody = $( '#cdv-documents-table tbody' );
						$tbody.find( 'td[colspan]' ).closest( 'tr' ).remove();
						var $newRow = $( response.data.row );
						$newRow.addClass( 'cdv-row-new' );
						$tbody.prepend( $newRow );

						$form[ 0 ].reset();
					} else {
						var message = ( response && response.data && response.data.message ) || CDV_Data.i18n.error;
						$status.addClass( 'cdv-status-error' ).text( message );
					}
				} )
				.fail( function () {
					$status.addClass( 'cdv-status-error' ).text( CDV_Data.i18n.error );
				} )
				.always( function () {
					$submit.prop( 'disabled', false );
				} );
		} );
	} );
} )( jQuery );
