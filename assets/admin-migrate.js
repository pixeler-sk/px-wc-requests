(function ($) {
	'use strict';

	var running = false;

	function runBatch(offset, dryRun, status) {
		return $.post(pxer_migrate.ajax_url, {
			action:  'pxer_migrate_batch',
			nonce:   pxer_migrate.nonce,
			offset:  offset,
			dry_run: dryRun ? 1 : 0,
			status:  status
		});
	}

	function start(dryRun) {
		if (running) {
			return;
		}
		running = true;

		var status = $('#pxer-migrate-status').val(),
			$log   = $('#pxer-migrate-log').empty(),
			$bar   = $('#pxer-migrate-bar'),
			totals = { imported: 0, skipped: 0, errors: 0 };

		function step(offset) {
			runBatch(offset, dryRun, status)
				.done(function (response) {
					if (!response || !response.success) {
						$log.append('<div style="color:#a00">' + pxer_migrate.i18n.failed + '</div>');
						running = false;
						return;
					}
					var d = response.data;
					totals.imported += d.imported;
					totals.skipped  += d.skipped;
					totals.errors   += d.errors_count;
					$bar.text(d.progress + '%');

					(d.errors || []).forEach(function (msg) {
						$log.append('<div style="color:#a00">' + msg + '</div>');
					});

					if (d.done) {
						var head = dryRun ? pxer_migrate.i18n.dry_done : pxer_migrate.i18n.import_done;
						$log.append('<div><strong>' + head + ': ' +
							totals.imported + ' ' + pxer_migrate.i18n.imported + ', ' +
							totals.skipped + ' ' + pxer_migrate.i18n.skipped + ', ' +
							totals.errors + ' ' + pxer_migrate.i18n.errors + '</strong></div>');
						running = false;
					} else {
						step(d.next_offset);
					}
				})
				.fail(function () {
					$log.append('<div style="color:#a00">' + pxer_migrate.i18n.failed + '</div>');
					running = false;
				});
		}

		step(0);
	}

	$('#pxer-migrate-dry').on('click', function (e) { e.preventDefault(); start(true); });
	$('#pxer-migrate-run').on('click', function (e) { e.preventDefault(); start(false); });

})(jQuery);
