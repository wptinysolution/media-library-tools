<?php
/**
 * Main ActionHooks class.
 *
 * @package TinySolutions\WM
 */

namespace TinySolutions\mlt\Controllers\Hooks;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}
use TinySolutions\mlt\Helpers\Fns;
use TinySolutions\mlt\Traits\SingletonTrait;


/**
 * Main ActionHooks class.
 */
class ActionHooks {

	/**
	 * Singleton
	 */
	use SingletonTrait;

	/**
	 * Init Hooks.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'manage_media_custom_column', [ $this, 'display_column_value' ], 10, 2 );
		add_action( 'add_attachment', [ $this, 'add_image_info_to' ] );
		// Hook the function to a cron job.
		add_action( 'in_admin_header', [ $this, 'remove_all_notices' ], 99 );
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_attachment_field' ], 10, 2 );
	}

	/**
	 * Shows parent info in the media modal / grid view (upload.php?item=ID).
	 */
	public function add_attachment_field( $form_fields, $post ) {
		$html                            = $this->get_parent_html( $post );
		$form_fields['tsmlt_parent_post'] = [
			'label' => __( 'Uploaded To', 'media-library-tools' ),
			'input' => 'html',
			'html'  => $html,
		];
		return $form_fields;
	}

	/**
	 * Shared HTML for both metabox and attachment field.
	 *
	 * @param $post
	 *
	 * @return string
	 */
	private function get_parent_html( $post ) {
		if ( ! $post->post_parent ) {
			return '<p style="margin:0;padding:8px 10px;background:#f9f9f9;border-left:3px solid #dcdcde;color:#a7aaad;font-size:12px;">Not attached to any post.</p>';
		}

		$parent = get_post( $post->post_parent );

		if ( ! $parent ) {
			return '<p style="margin:0;padding:8px 10px;background:#f9f9f9;border-left:3px solid #dcdcde;color:#a7aaad;font-size:12px;">Parent post not found.</p>';
		}

		$edit_link    = get_edit_post_link( $parent->ID );
		$post_type    = get_post_type_object( $parent->post_type );
		$type_label   = $post_type ? $post_type->labels->singular_name : $parent->post_type;
		$status       = get_post_status_object( $parent->post_status );
		$status_label = $status ? $status->label : $parent->post_status;
		$dot_color    = $parent->post_status === 'publish' ? '#00a32a' : '#dba617';
		ob_start();
		?>
		<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:10px 12px;">
			<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
				<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $dot_color ); ?>;flex-shrink:0;"></span>
				<span style="font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">
				<?php echo esc_html( $type_label ); ?> &middot; <?php echo esc_html( $status_label ); ?>
			</span>
			</div>
			<a href="<?php echo esc_url( $edit_link ); ?>"
			   style="display:block;font-size:13px;font-weight:600;color:#2271b1;text-decoration:none;line-height:1.4;word-break:break-word;"
			   onmouseover="this.style.textDecoration='underline'"
			   onmouseout="this.style.textDecoration='none'">
				<?php echo esc_html( $parent->post_title ); ?>
			</a>
			<div style="margin-top:6px;font-size:11px;color:#8c8f94;">
				ID: <?php echo absint( $parent->ID ); ?>
				<?php if ( 'publish' === $parent->post_status ) : ?>
				&nbsp;&middot;&nbsp;
				<a href="<?php echo esc_url( get_permalink( $parent->ID ) ); ?>"
				   target="_blank"
				   style="color:#8c8f94;text-decoration:none;"
				   onmouseover="this.style.color='#2271b1'"
				   onmouseout="this.style.color='#8c8f94'">
					<?php esc_html_e( 'View', 'media-library-tools' ); ?> &nearr;
				</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
	/**
	 * @return void
	 */
	public function remove_all_notices() {
		$screen = get_current_screen();
		if ( in_array( $screen->base, [ 'media_page_media-library-tools', 'media_page_tsmlt-get-pro', 'media_page_tsmlt-pricing-pro' ], true ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}
	/***
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function add_image_info_to( $attachment_ID ) {
		$options     = Fns::get_options();
		$image_title = get_the_title( $attachment_ID );
		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended
		$post_id = absint( $_REQUEST['post_id'] ?? 0 );
		if ( ! $post_id || empty( $options['alt_text_by_post_title'] ) ) {
			if ( ! empty( $options['default_alt_text'] ) && 'image_name_to_alt' === $options['default_alt_text'] ) {
				update_post_meta( $attachment_ID, '_wp_attachment_image_alt', $image_title );
			} elseif ( ! empty( $options['media_default_alt'] ) && 'custom_text_to_alt' === $options['default_alt_text'] ) {
				update_post_meta( $attachment_ID, '_wp_attachment_image_alt', $options['media_default_alt'] );
			}
		}

		$image_meta = [];
		if ( ! empty( $options['default_caption_text'] ) && 'image_name_to_caption' === $options['default_caption_text'] ) {
			$image_meta['post_excerpt'] = $image_title;
		} elseif ( ! empty( $options['media_default_caption'] ) && 'custom_text_to_caption' === $options['default_caption_text'] ) {
			$image_meta['post_excerpt'] = $options['media_default_caption'];
		}

		if ( ! empty( $options['default_desc_text'] ) && 'image_name_to_desc' === $options['default_desc_text'] ) {
			$image_meta['post_content'] = $image_title;
		} elseif ( ! empty( $options['media_default_desc'] ) && 'custom_text_to_desc' === $options['default_desc_text'] ) {
			$image_meta['post_content'] = $options['media_default_desc'];
		}

		$image_meta = apply_filters( 'tsmlt/before/add/image/info', $image_meta, $options, $attachment_ID, $post_id );

		if ( ! empty( $image_meta ) ) {
			$image_meta['ID'] = $attachment_ID;
			wp_update_post( $image_meta );
		}
	}

	/**
	 * @param $column
	 * @param $post_id
	 *
	 * @return void
	 */
	public function display_column_value( $column, $post_id ) {
		$image = Fns::wp_get_attachment( $post_id );
		switch ( $column ) {
			case 'alt':
				echo esc_html( wp_strip_all_tags( $image['alt'] ) );
				break;
			case 'caption':
				echo esc_html( $image['caption'] );
				break;
			case 'description':
				echo esc_html( $image['description'] );
				break;
			case 'category':
				$taxonomy_object = get_taxonomy( Fns::CATEGORY );

				if ( $terms = get_the_terms( $post_id, Fns::CATEGORY ) ) {
					$out = [];
					foreach ( $terms as $t ) {
						$posts_in_term_qv              = [];
						$posts_in_term_qv['post_type'] = get_post_type( $post_id );

						if ( $taxonomy_object->query_var ) {
							$posts_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
						} else {
							$posts_in_term_qv['taxonomy'] = Fns::CATEGORY;
							$posts_in_term_qv['term']     = $t->slug;
						}

						$out[] = sprintf(
							'<a href="%s">%s</a>',
							esc_url( add_query_arg( $posts_in_term_qv, 'upload.php' ) ),
							esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, Fns::CATEGORY, 'display' ) )
						);
					}

					/* translators: used between list items, there is a space after the comma */
					echo esc_html( join( ', ', $out ) );
				}
				break;
			default:
				break;
		}
	}
}
