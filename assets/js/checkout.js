(function ($) {
	'use strict';

	function loadConfirmPlugin() {
		if ($.fn && typeof $.fn.confirm === 'function') {
			return $.Deferred().resolve().promise();
		}
		var deferred = $.Deferred();
		$('head').append(
			$('<link rel="stylesheet" type="text/css" />').attr(
				'href',
				'https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css'
			)
		);
		$.getScript('https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js')
			.done(function () { deferred.resolve(); })
			.fail(function () { deferred.reject(); });
		return deferred.promise();
	}

	function deleteCard(id, cardLabel) {
		var nonce = $('#thawani_payment_card').attr('data-nonce');

		$.confirm({
			title: window.thawani._delete,
			content: window.thawani._delete_confirm.replace('%s', cardLabel),
			buttons: {
				confirm: {
					text: window.thawani._confirm,
					action: function () {
						$.ajax({
							type: 'POST',
							dataType: 'json',
							url: window.thawani.ajaxurl,
							data: {
								action: 'thawaniDeleteCard',
								nonce: nonce,
								id: id,
								card: cardLabel
							},
							beforeSend: function () {
								$('.woocommerce-checkout-payment').block({
									message: null,
									overlayCSS: { background: '#fff', opacity: 0.6 }
								});
							},
							success: function (response) {
								if (response && response.success) {
									$('input[name=thawani_payment_option][value=' + id + ']')
										.closest('li')
										.hide();
									$('input[name=thawani_payment_option][value="-1"]').prop('checked', true);
								}
							},
							complete: function () {
								$('.woocommerce-checkout-payment').unblock();
							}
						});
					}
				},
				cancel: {
					text: window.thawani._cancel,
					action: function () {}
				}
			}
		});
	}

	$(function () {
		loadConfirmPlugin();
	});

	$(document).on('click', '.thawani-payment-delete', function (e) {
		e.preventDefault();
		var $btn = $(this);
		loadConfirmPlugin().done(function () {
			deleteCard($btn.attr('data-id'), $btn.attr('data-card'));
		});
	});
})(jQuery);
