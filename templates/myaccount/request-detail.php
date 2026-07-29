<?php
/**
 * "My requests" — single request detail (data + customer-visible history).
 *
 * Override: copy to yourtheme/px-wc-requests/myaccount/request-detail.php
 *
 * @var \Pixeler\Requests\Request $request
 * @var WP_Comment[] $notes
 *
 * @package Pixeler\Requests
 */

defined( 'ABSPATH' ) || exit;

use Pixeler\Requests\MyAccount;
use Pixeler\Requests\RequestNotes;

$list_url = wc_get_endpoint_url( MyAccount::endpoint(), '', wc_get_page_permalink( 'myaccount' ) );
?>

<p>
	<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to my requests', 'px-wc-requests' ); ?></a>
</p>

<h2>
	<?php
	printf(
		/* translators: 1: type label, 2: request id */
		esc_html__( '%1$s no. %2$d', 'px-wc-requests' ),
		esc_html( $request->get_type_label() ),
		(int) $request->get_id()
	);
	?>
</h2>

<?php pxer_render_request_summary( $request ); ?>

<h3 style="margin-top:24px"><?php esc_html_e( 'History & updates', 'px-wc-requests' ); ?></h3>

<?php if ( empty( $notes ) ) : ?>
	<p><?php esc_html_e( 'No updates yet.', 'px-wc-requests' ); ?></p>
<?php else : ?>
	<ul class="pxer-timeline">
		<?php foreach ( $notes as $note ) :
			$is_status = RequestNotes::is_status_log( (int) $note->comment_ID );
			?>
			<li class="pxer-timeline-item <?php echo $is_status ? 'is-status' : 'is-note'; ?>">
				<div class="pxer-timeline-meta">
					<?php echo esc_html( get_comment_date( wc_date_format() . ' ' . wc_time_format(), $note->comment_ID ) ); ?>
					<?php if ( ! $is_status ) : ?>
						<span class="pxer-timeline-badge"><?php esc_html_e( 'Note', 'px-wc-requests' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="pxer-timeline-content"><?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?></div>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<style>
	.pxer-timeline { list-style: none; margin: 0; padding: 0; }
	.pxer-timeline-item { padding: 10px 0; border-bottom: 1px solid #eee; }
	.pxer-timeline-meta { font-size: .85em; color: #777; margin-bottom: 4px; }
	.pxer-timeline-badge { display: inline-block; margin-left: 6px; padding: 0 6px; background: #674399; color: #fff; border-radius: 3px; font-size: .8em; }
	.pxer-timeline-item.is-status .pxer-timeline-content { color: #333; }
</style>
