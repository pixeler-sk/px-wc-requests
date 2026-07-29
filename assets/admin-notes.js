(function ($) {
	'use strict';

	function request(action, data) {
		return $.post(
			pxer_notes.ajax_url,
			$.extend( { action: action, nonce: pxer_notes.nonce, post_id: pxer_notes.post_id }, data )
		);
	}

	$(document).on('click', '#pxer-add-note-btn', function (e) {
		e.preventDefault();

		var $btn  = $(this),
			note  = $('#pxer-note-content').val(),
			type  = $('#pxer-note-type').val();

		if (!note || !note.trim()) {
			return;
		}

		$btn.prop('disabled', true);

		request('pxer_add_request_note', { note: note, note_type: type })
			.done(function (response) {
				if (response && response.success) {
					$('.pxer-notes .pxer-note-empty').remove();
					$('.pxer-notes').prepend(response.data.html);
					$('#pxer-note-content').val('');
				}
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$(document).on('click', '.pxer-delete-note', function (e) {
		e.preventDefault();

		if (!window.confirm(pxer_notes.i18n.confirm_delete)) {
			return;
		}

		var $li = $(this).closest('.pxer-note'),
			id  = $li.data('note-id');

		request('pxer_delete_request_note', { note_id: id }).done(function (response) {
			if (response && response.success) {
				$li.remove();
			}
		});
	});

})(jQuery);
