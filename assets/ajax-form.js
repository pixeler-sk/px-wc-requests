(function ($) {
	'use strict';

	var T = (window.pxer_ajax && window.pxer_ajax.i18n) || {};

	function t(key, fallback) {
		return T[key] || fallback;
	}

	function fmt(str, a, b) {
		return String(str).replace('%1$d', a).replace('%2$d', b).replace('%d', a);
	}

	// --- Inline validation ---------------------------------------------------
	// Forms are `novalidate`: the browser bubble is replaced by a message under
	// the field (aria-invalid + aria-describedby) and a summary with role=alert.
	// The server validates everything again - this is only the fast path.

	var uid = 0;

	function ensureId(el) {
		if (!el.id) {
			uid += 1;
			el.id = 'pxer-auto-' + uid;
		}
		return el.id;
	}

	function isGroup(el) {
		return $(el).is('.pxer-order-items, .pxer-field-radio');
	}

	// First focusable control of a field (the group itself is not focusable).
	function focusTarget(el) {
		if (!isGroup(el)) {
			return el;
		}
		var $c = $(el).find('input:not(:disabled)');
		return ($c.filter(':checked')[0] || $c[0] || el);
	}

	function labelOf(el) {
		var $el = $(el), text = '';
		if (isGroup(el)) {
			var lb = $el.attr('aria-labelledby');
			text = lb ? $('#' + lb).text() : '';
		} else if ($el.attr('aria-label')) {
			text = $el.attr('aria-label');
		} else if (el.id) {
			var $label = $('label[for="' + el.id + '"]').first().clone();
			$label.find('.screen-reader-text').each(function () {
				$(this).replaceWith(' ' + $(this).text());
			});
			text = $label.text();
		}
		return $.trim(String(text).replace(/\*/g, '').replace(/\s+/g, ' '));
	}

	function describedBy(el, add, token) {
		var list = ($(el).attr('aria-describedby') || '').split(/\s+/).filter(function (x) {
			return x && x !== token;
		});
		if (add) {
			list.push(token);
		}
		if (list.length) {
			$(el).attr('aria-describedby', list.join(' '));
		} else {
			$(el).removeAttr('aria-describedby');
		}
	}

	function clearError(el) {
		var errId = ensureId(el) + '-error';
		$(document.getElementById(errId)).remove();
		describedBy(el, false, errId);
		$(el).removeAttr('aria-invalid').removeClass('not-valid');
	}

	function setError(el, msg) {
		clearError(el);
		var errId = ensureId(el) + '-error',
			$msg = $('<span class="pxer-field-error"></span>').attr('id', errId).text(msg);

		if (isGroup(el)) {
			$(el).append($msg);
		} else {
			var $box = $(el).closest('.pxer-field, td');
			($box.length ? $box : $(el).parent()).append($msg);
		}
		describedBy(el, true, errId);
		$(el).attr('aria-invalid', 'true').addClass('not-valid');
	}

	function ibanValid(raw) {
		var iban = String(raw).replace(/\s+/g, '').toUpperCase();
		if (!/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(iban)) {
			return false;
		}
		var moved = iban.slice(4) + iban.slice(0, 4), rest = 0, i, code, digits;
		for (i = 0; i < moved.length; i++) {
			code = moved.charCodeAt(i);
			digits = code >= 65 ? String(code - 55) : moved.charAt(i);
			rest = parseInt(String(rest) + digits, 10) % 97;
		}
		return rest === 1;
	}

	function controlError(el) {
		var $el = $(el),
			type = (el.type || '').toLowerCase(),
			val = $.trim($el.val() || ''),
			required = $el.prop('required') || $el.is('[data-pxer-required]');

		if (type === 'checkbox') {
			return required && !el.checked ? t('checkbox', 'Please check this box to continue.') : '';
		}
		if (type === 'file') {
			return required && !el.files.length ? t('required', 'Please fill in this field.') : '';
		}
		if (required && val === '') {
			return t('required', 'Please fill in this field.');
		}
		if (val === '') {
			return '';
		}
		if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
			return t('email', 'Enter a valid e-mail address.');
		}
		if ($el.data('pxer-type') === 'iban' && !ibanValid(val)) {
			return t('iban', 'Please enter a valid IBAN.');
		}
		if (type === 'number') {
			var min = parseInt($el.attr('min'), 10), max = parseInt($el.attr('max'), 10);
			if (!/^\d+$/.test(val)) {
				return t('number', 'Enter a whole number.');
			}
			var n = parseInt(val, 10);
			if ((!isNaN(min) && n < min) || (!isNaN(max) && n > max)) {
				return fmt(t('range', 'Enter a number from %1$d to %2$d.'), isNaN(min) ? 1 : min, isNaN(max) ? n : max);
			}
		}
		return '';
	}

	function validateForm($form) {
		var errors = [];

		$form.find('input, select, textarea').each(function () {
			var el = this, $el = $(el), type = (el.type || '').toLowerCase();

			if (el.disabled || type === 'hidden' || type === 'submit' || type === 'button' || type === 'radio') {
				return;
			}
			if ($el.closest('[aria-hidden="true"]').length || el.name === 'pxer_homepage') {
				return; // honeypot
			}
			if ($el.is('.pxer-item-check')) {
				return; // validated as a group below
			}
			if ($el.closest('.pxer-item-reason').length && !$el.closest('.pxer-item-reason').is(':visible')) {
				return; // reason of an unselected item
			}
			if ($el.closest('.pxer-item-qty').length && !$el.closest('tr').find('.pxer-item-check').is(':checked')) {
				return; // quantity of an unselected item
			}
			if (!$el.is(':visible') && type !== 'checkbox' && type !== 'file') {
				return; // hidden by a theme or a conditional block
			}
			var msg = controlError(el);
			if (msg) {
				errors.push({ el: el, msg: msg });
			}
		});

		$form.find('.pxer-order-items[data-pxer-required]').each(function () {
			if (!$(this).find('.pxer-item-check:checked, .pxer-item-radio:checked').length) {
				errors.push({ el: this, msg: t('items', 'Select at least one item.') });
			}
		});

		$form.find('.pxer-field-radio[aria-required="true"]').each(function () {
			var $r = $(this).find('input[type="radio"]:not(:disabled)');
			if ($r.length && !$r.filter(':checked').length) {
				errors.push({ el: this, msg: t('choose', 'Please choose one of the options.') });
			}
		});

		// Document order, so the summary and the focus follow the form.
		errors.sort(function (a, b) {
			if (a.el === b.el) {
				return 0;
			}
			return (a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
		});

		return errors;
	}

	// The response box is the live region (role=alert in the template); a role
	// on the list itself would hide its items from assistive technology.
	function showSummary($form, errors) {
		var $box = $form.find('.ajax-response').first().attr('role', 'alert'),
			$list = $('<ul class="woocommerce-error pxer-error-summary"></ul>');

		$list.append($('<li></li>').text(fmt(t('summary', 'Please correct the highlighted fields (%d):'), errors.length)));
		$.each(errors, function (i, e) {
			var label = labelOf(e.el),
				$a = $('<a></a>').attr('href', '#' + ensureId(focusTarget(e.el))).text((label ? label + ': ' : '') + e.msg);
			$list.append($('<li></li>').append($a));
		});
		$box.empty().append($list);
	}

	function clearAll($form) {
		$form.find('[aria-invalid="true"], .not-valid').each(function () {
			clearError(this);
		});
		$form.find('.pxer-field-error').remove();
	}

	function failClientSide($form, errors) {
		$.each(errors, function (i, e) {
			setError(e.el, e.msg);
		});
		showSummary($form, errors);
		focusTarget(errors[0].el).focus();
	}

	// Summary links: move the focus to the control, not just scroll.
	$(document).on('click', '.pxer-error-summary a[href^="#"]', function (e) {
		var target = document.getElementById(this.getAttribute('href').slice(1));
		if (target) {
			e.preventDefault();
			target.focus();
		}
	});

	// Fixing a field clears its message.
	$(document).on('input change', '.pxer-form [aria-invalid="true"], .pxer-search-form [aria-invalid="true"]', function () {
		if (!isGroup(this) && !controlError(this)) {
			clearError(this);
		}
	});
	$(document).on('change', '.pxer-order-items input, .pxer-field-radio input', function () {
		var group = $(this).closest('.pxer-order-items, .pxer-field-radio')[0];
		if (group && $(group).attr('aria-invalid') === 'true') {
			clearError(group);
		}
	});

	// Order search (plain GET form): validate, then let it submit.
	$(document).on('submit', '.pxer-search-form', function (e) {
		var $form = $(this);
		clearAll($form);
		$form.find('.ajax-response').empty();
		var errors = validateForm($form);
		if (errors.length) {
			e.preventDefault();
			failClientSide($form, errors);
		}
	});

	// --- AJAX submit -------------------------------------------------------
	$(document).on('submit', '.ajax-form', function (e) {
		e.preventDefault();

		var form = $(this),
			url = form.attr('action') || (window.pxer_ajax ? pxer_ajax.ajax_url : ''),
			method = form.attr('method') || 'POST',
			redirect = form.find('input[name="redirect"]').val() || false,
			responseBox = form.find('.ajax-response');

		clearAll(form);
		responseBox.html('');

		var errors = validateForm(form);
		if (errors.length) {
			failClientSide(form, errors);
			return;
		}

		var formData = new FormData(form[0]);

		$.ajax({
			type: method,
			url: url,
			data: formData,
			dataType: 'json',
			contentType: false,
			processData: false,
			beforeSend: function () {
				form.addClass('ajax-processing').attr('aria-busy', 'true');
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
					responseBox.attr('role', 'status').html(msg).children().removeAttr('role');
				} else {
					responseBox.attr('role', 'alert').html(msg).children().removeAttr('role');

					var field = null;
					if (payload.field) {
						field = form.find('.pxer-order-items#pxer-' + payload.field)[0] ||
							form.find('[name="' + payload.field + '"]').not(':disabled')[0] || null;
					}
					if (field) {
						setError(field, payload.text || $('<div>').html(msg).text());
						focusTarget(field).focus();
					} else {
						responseBox.trigger('focus');
					}
					// A Turnstile / reCAPTCHA token is valid once - let the widget renew it.
					$(document).trigger('pxer:failed', [form]);
				}
			},
			error: function () {
				var $err = $('<ul class="woocommerce-error"><li></li></ul>');
				$err.find('li').text(t('network', 'The form could not be sent. Check your connection and try again.'));
				responseBox.attr('role', 'alert').empty().append($err).trigger('focus');
				$(document).trigger('pxer:failed', [form]);
			},
			complete: function () {
				form.removeClass('ajax-processing').removeAttr('aria-busy');
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
		if (!$(this).is(':checked')) {
			$(this).closest('tr').find('.pxer-item-qty input').each(function () {
				clearError(this);
			});
		}
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
