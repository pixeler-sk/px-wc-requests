(function ($) {
	'use strict';

	// --- AJAX submit -------------------------------------------------------
	$(document).on('submit', '.ajax-form', function (e) {
		e.preventDefault();

		var form = $(this),
			url = form.attr('action') || (window.pxer_ajax ? pxer_ajax.ajax_url : ''),
			method = form.attr('method') || 'POST',
			redirect = form.find('input[name="redirect"]').val() || false,
			responseBox = form.find('.ajax-response'),
			formData = new FormData(form[0]);

		$.ajax({
			type: method,
			url: url,
			data: formData,
			dataType: 'json',
			contentType: false,
			processData: false,
			beforeSend: function () {
				form.addClass('ajax-processing');
				responseBox.html('');
				form.find('.not-valid').removeClass('not-valid');
				form.find('button[type="submit"]').prop('disabled', true);
			},
			success: function (response) {
				var payload = response.data || {};
				var msg = payload.message || '';

				if (payload.error_code === '') {
					if (redirect) {
						window.location.replace(redirect);
						return;
					}
					responseBox.html(msg);
				} else {
					if (payload.error_code) {
						form.find('[name="' + payload.error_code + '"]').addClass('not-valid');
					}
					responseBox.html(msg);
				}
			},
			error: function () {
				responseBox.html('<div class="woocommerce-error">Chyba pri odosielaní. Skúste znova.</div>');
			},
			complete: function () {
				form.removeClass('ajax-processing');
				form.find('button[type="submit"]').prop('disabled', false);
			}
		});
	});

	// --- Order item reason toggling ---------------------------------------
	$(document).on('change', '.pxer-order-items[data-mode="single"] .pxer-item-radio', function () {
		var wrap = $(this).closest('.pxer-order-items');
		wrap.find('.pxer-item-reason').hide();
		wrap.find('.pxer-item-reason[data-item="' + $(this).val() + '"]').show();
	});

	$(document).on('change', '.pxer-order-items[data-mode="multiple"] .pxer-item-check', function () {
		var id = $(this).closest('.pxer-item-row').next('.pxer-item-reason').data('item');
		var row = $(this).closest('.pxer-order-items').find('.pxer-item-reason[data-item="' + id + '"]');
		row.toggle($(this).is(':checked'));
	});

	// --- Conditional fields (show_if) -------------------------------------
	// A field wrapped in .pxer-conditional[data-pxer-show-if][data-pxer-show-value]
	// is shown only when the controlling field's current value is one of the
	// comma-separated allowed values. Hidden blocks get their inputs disabled so
	// stale/irrelevant values are not submitted.
	function pxerFieldValue($form, name) {
		var $inputs = $form.find('[name="' + name + '"]');
		if (!$inputs.length) {
			return null;
		}
		var type = ($inputs[0].type || '').toLowerCase();
		if (type === 'radio') {
			return $inputs.filter(':checked').val() || '';
		}
		if (type === 'checkbox') {
			return $inputs.filter(':checked').length ? ($inputs.val() || 'yes') : '';
		}
		return $inputs.val();
	}

	function pxerApplyConditionals($form) {
		$form.find('.pxer-conditional').each(function () {
			var $c = $(this),
				name = String($c.data('pxer-show-if') || ''),
				allowed = String($c.data('pxer-show-value')).split(','),
				value = pxerFieldValue($form, name),
				show = value !== null && allowed.indexOf(String(value)) !== -1;

			$c.toggle(show);
			$c.find('input, select, textarea').prop('disabled', !show);
		});
	}

	$(document).on('change', '.pxer-form input, .pxer-form select, .pxer-form textarea', function () {
		pxerApplyConditionals($(this).closest('form'));
	});

	$(function () {
		$('.pxer-form').each(function () {
			pxerApplyConditionals($(this));
		});
	});

})(jQuery);
