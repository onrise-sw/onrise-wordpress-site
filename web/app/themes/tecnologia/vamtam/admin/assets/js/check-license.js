/*
 VamTam Check License
 */

/*global jQuery*/

(function( $ ) {
	'use strict';

	$('body').on('click', '#vamtam-check-license', function(e) {
		e.preventDefault();

		var self = $(this);

		if ( self.hasClass('disabled' ) ) return false;

		var result = $('#vamtam-check-license-result').html('').css( 'display', 'block' );
		var $validMsg = $('#vamtam-license-result-wrap > .valid');
		var $invalidMsg = $('#vamtam-license-result-wrap > .invalid');
		var isUnregister = self.hasClass('unregister');
		var $licenseInput = $('#vamtam-envato-license-key');
		if ( isUnregister ) {
			if (confirm('You are about to unregister your purchase code. Are you sure you want to continue?')) {
				$licenseInput.attr('value', '');
			} else {
				return;
			}
		}
		$licenseInput.prop('disabled', true);

		$validMsg.hide();
		$invalidMsg.hide();

		self.css('display', 'inline-block').addClass('disabled');

		var spinner = $('#vamtam-check-license ~ span.spinner');
		if ( spinner.length > 0 ) {
			spinner.show();
		} else {
			if ( isUnregister ) {
				$('#vamtam-check-license').after('<span class="spinner is-active" style="display:inline-block;" />');
				spinner = $('#vamtam-check-license ~ span.spinner');
			} else {
				$licenseInput.after('<span class="spinner is-active" style="display:inline-block;" />');
				spinner = $('#vamtam-envato-license-key ~ span.spinner');
			}
		}

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'vamtam-check-license',
				'license-key': $licenseInput.val(),
				nonce: self.attr('data-nonce'),
				unregister: isUnregister ? true : false,
			},
			success: function(data) {
				if ( data.includes( 'Valid Purchase Key' ) ) {
					window.location = window.location.href;
				} else if ( data.includes( 'Incorrect Purchase Key' ) ) {
					if ( isUnregister ) {
						window.location = window.location.href;
					} else {
						$invalidMsg.css('display', 'flex');
						self.removeClass('disabled');
						$licenseInput.prop('disabled', false);
						spinner.hide();
					}
				} else if ( data.includes( 'Unregistered Key' ) ) {
					window.location = window.location.href;
				} else {
					result.append( $('<p />').addClass('vamtam-check-license-response').append(data) );
					spinner.hide();
					self.removeClass('disabled');
					$licenseInput.prop('disabled', false);
				}
			}
		});
	});
})( jQuery );
