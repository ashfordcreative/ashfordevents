/* Ashford Events — admin color picker init */
jQuery( function ( $ ) {
	function initPickers( scope ) {
		$( '.ash-color-field', scope || document ).each( function () {
			if ( ! $( this ).hasClass( 'wp-color-picker' ) ) {
				$( this ).wpColorPicker();
			}
		} );
	}
	initPickers();

	// Re-init after the "Add Category" AJAX form resets.
	$( document ).ajaxComplete( function () {
		initPickers();
	} );
} );
