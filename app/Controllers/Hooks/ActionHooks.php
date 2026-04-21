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
use TinySolutions\mlt\Modules\ExifData\ExifDataReader;
use TinySolutions\mlt\Modules\ExifData\ExifAutoProcessor;
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
		add_action( 'add_attachment', [ $this, 'process_exif_on_upload' ], 15 ); // After add_image_info_to.
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

		// Add EXIF Data panel.
		$exif_html                       = ExifDataReader::instance()->render_modal_html( $post->ID );
		$form_fields['tsmlt_exif_data']  = [
			'label' => __( 'EXIF Data', 'media-library-tools' ),
			'input' => 'html',
			'html'  => $exif_html,
		];

		// Add EXIF removal button for JPEG images.
		$form_fields['tsmlt_strip_exif'] = [
			'label' => __( 'Remove EXIF', 'media-library-tools' ),
			'input' => 'html',
			'html'  => $this->get_strip_exif_button_html( $post->ID ),
		];

		// Add EXIF editor for JPEG images (Pro feature).
		$form_fields['tsmlt_exif_editor'] = [
			'label' => __( 'Edit EXIF', 'media-library-tools' ),
			'input' => 'html',
			'html'  => $this->get_exif_editor_html( $post->ID ),
		];

		return $form_fields;
	}

	/**
	 * Generate HTML for the "Remove EXIF Data" button.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string HTML for the button.
	 */
	private function get_strip_exif_button_html( int $attachment_id ): string {
		$mime_type = get_post_mime_type( $attachment_id );

		// Only show button for JPEG images.
		if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/jpg' ], true ) ) {
			return sprintf(
				'<p style="margin:0;padding:8px 10px;background:#f9f9f9;border-left:3px solid #dcdcde;color:#a7aaad;font-size:12px;">%s</p>',
				esc_html__( 'EXIF removal is only available for JPEG images.', 'media-library-tools' )
			);
		}

		ob_start();
		?>
		<div style="padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;">
			<button
				type="button"
				class="button button-secondary"
				id="tsmlt-strip-exif-btn-<?php echo absint( $attachment_id ); ?>"
				data-attachment-id="<?php echo absint( $attachment_id ); ?>"
				style="display:inline-block;margin-bottom:8px;">
				<?php esc_html_e( 'Remove EXIF Data', 'media-library-tools' ); ?>
			</button>
			<p style="margin:8px 0 0 0;font-size:11px;color:#8c8f94;line-height:1.4;">
				<?php esc_html_e( 'This will remove all metadata (camera info, GPS, dates, etc.) from the image while preserving visual quality.', 'media-library-tools' ); ?>
			</p>
			<div id="tsmlt-strip-exif-msg-<?php echo absint( $attachment_id ); ?>" style="margin-top:8px;"></div>
		</div>

		<script type="text/javascript">
		(function() {
			const btn = document.getElementById('tsmlt-strip-exif-btn-<?php echo absint( $attachment_id ); ?>');
			const msgDiv = document.getElementById('tsmlt-strip-exif-msg-<?php echo absint( $attachment_id ); ?>');

			if (btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();

					const attachmentId = this.dataset.attachmentId;
					const nonce = this.dataset.nonce;

					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to remove EXIF metadata? This action cannot be undone.', 'media-library-tools' ) ); ?>')) {
						return;
					}

					btn.disabled = true;
					btn.textContent = '<?php echo esc_js( __( 'Removing...', 'media-library-tools' ) ); ?>';
					msgDiv.innerHTML = '';

					const formData = new FormData();
					formData.append('action', 'tsmlt_strip_exif_single');
					formData.append('nonce', tsmltParams.tsmlt_wpnonce);
					formData.append('params', JSON.stringify({
						attachment_id: parseInt(attachmentId)
					}));

					fetch(ajaxurl, {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						btn.disabled = false;
						btn.textContent = '<?php echo esc_js( __( 'Remove EXIF Data', 'media-library-tools' ) ); ?>';

						const msgStyle = data.success
							? 'padding:8px;background:#d4edda;border:1px solid #c3e6cb;border-radius:3px;color:#155724;'
							: 'padding:8px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:3px;color:#721c24;';

						const msgEl = document.createElement('div');
						msgEl.style.cssText = msgStyle;
						msgEl.textContent = data.data?.message || data.data || '';
						msgDiv.innerHTML = '';
						msgDiv.appendChild(msgEl);

						// Refresh the attachment if successful.
						if (data.success) {
							setTimeout(() => {
								location.reload();
							}, 2000);
						}
					})
					.catch(error => {
						btn.disabled = false;
						btn.textContent = '<?php echo esc_js( __( 'Remove EXIF Data', 'media-library-tools' ) ); ?>';
						const errEl = document.createElement('div');
						errEl.style.cssText = 'padding:8px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:3px;color:#721c24;';
						errEl.textContent = 'Error: ' + (error.message || 'Unknown error');
						msgDiv.innerHTML = '';
						msgDiv.appendChild(errEl);
					});
				});
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate HTML for the EXIF editor form.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string HTML for the editor.
	 */
	private function get_exif_editor_html( int $attachment_id ): string {
		$mime_type = get_post_mime_type( $attachment_id );

		// Only show editor for JPEG images.
		if ( ! in_array( $mime_type, [ 'image/jpeg', 'image/jpg' ], true ) ) {
			return sprintf(
				'<p style="margin:0;padding:8px 10px;background:#f9f9f9;border-left:3px solid #dcdcde;color:#a7aaad;font-size:12px;">%s</p>',
				esc_html__( 'EXIF editing is only available for JPEG images.', 'media-library-tools' )
			);
		}

		// Check if Pro is active; if not, show upgrade prompt.
		if ( ! function_exists( 'tsmlt' ) || ! tsmlt()->has_pro() ) {
			return sprintf(
				'<p style="margin:0;padding:8px 10px;background:#f0f6fc;border-left:3px solid #0073aa;color:#0073aa;font-size:12px;"><strong>%s</strong> %s</p>',
				esc_html__( 'Pro Feature', 'media-library-tools' ),
				esc_html__( 'EXIF editing is available in the Pro version.', 'media-library-tools' )
			);
		}

		ob_start();
		?>
		<div id="tsmlt-exif-editor-<?php echo absint( $attachment_id ); ?>" style="padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;">
			<!-- Toggle button -->
			<button type="button" class="button button-secondary" id="tsmlt-exif-edit-btn-<?php echo absint( $attachment_id ); ?>" style="display:inline-block;margin-bottom:8px;">
				<?php esc_html_e( 'Edit EXIF Data', 'media-library-tools' ); ?>
			</button>

			<!-- Edit form (hidden by default) -->
			<div id="tsmlt-exif-form-<?php echo absint( $attachment_id ); ?>" style="display:none;margin-top:10px;">

				<!-- Before / After tabs -->
				<div style="display:flex;gap:4px;margin-bottom:8px;border-bottom:1px solid #dcdcde;">
					<button type="button" class="tsmlt-tab-btn active" data-tab="before" style="padding:6px 12px;border:none;background:transparent;cursor:pointer;border-bottom:2px solid #0073aa;color:#0073aa;font-weight:500;">
						<?php esc_html_e( 'Before', 'media-library-tools' ); ?>
					</button>
					<button type="button" class="tsmlt-tab-btn" data-tab="after" style="padding:6px 12px;border:none;background:transparent;cursor:pointer;border-bottom:2px solid transparent;color:#646970;">
						<?php esc_html_e( 'After', 'media-library-tools' ); ?>
					</button>
				</div>

				<!-- Before panel -->
				<div class="tsmlt-tab-panel" data-panel="before" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;padding:10px;background:#fff;border:1px solid #dcdcde;border-radius:3px;">
					<!-- Populated via AJAX on form open -->
				</div>

				<!-- After panel (mirrors form inputs) -->
				<div class="tsmlt-tab-panel" data-panel="after" style="display:none;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;padding:10px;background:#fff;border:1px solid #dcdcde;border-radius:3px;">
					<!-- Live-updated as user types -->
				</div>

				<!-- Camera Section -->
				<div style="margin-bottom:12px;padding:10px;background:#fff;border:1px solid #dcdcde;border-radius:3px;">
					<h4 style="margin:0 0 8px 0;font-size:12px;color:#646970;text-transform:uppercase;">
						<?php esc_html_e( 'Camera', 'media-library-tools' ); ?>
					</h4>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
						<div>
							<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
								<?php esc_html_e( 'Make', 'media-library-tools' ); ?>
							</label>
							<input type="text" id="tsmlt-make-<?php echo absint( $attachment_id ); ?>" maxlength="64" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
						</div>
						<div>
							<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
								<?php esc_html_e( 'Model', 'media-library-tools' ); ?>
							</label>
							<input type="text" id="tsmlt-model-<?php echo absint( $attachment_id ); ?>" maxlength="64" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
						</div>
					</div>
				</div>

				<!-- Exposure Section -->
				<div style="margin-bottom:12px;padding:10px;background:#fff;border:1px solid #dcdcde;border-radius:3px;">
					<h4 style="margin:0 0 8px 0;font-size:12px;color:#646970;text-transform:uppercase;">
						<?php esc_html_e( 'Exposure', 'media-library-tools' ); ?>
					</h4>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
						<div>
							<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
								<?php esc_html_e( 'ISO', 'media-library-tools' ); ?>
							</label>
							<input type="number" id="tsmlt-iso-<?php echo absint( $attachment_id ); ?>" min="1" max="102400" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
						</div>
						<div>
							<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
								<?php esc_html_e( 'Aperture (f/)', 'media-library-tools' ); ?>
							</label>
							<input type="text" id="tsmlt-aperture-<?php echo absint( $attachment_id ); ?>" placeholder="1.8" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
						</div>
					</div>
					<div>
						<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
							<?php esc_html_e( 'Shutter Speed', 'media-library-tools' ); ?>
						</label>
						<input type="text" id="tsmlt-shutter-<?php echo absint( $attachment_id ); ?>" placeholder="1/250" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
					</div>
				</div>

				<!-- GPS Section -->
				<div style="margin-bottom:12px;padding:10px;background:#fff;border:1px solid #dcdcde;border-radius:3px;">
					<h4 style="margin:0 0 8px 0;font-size:12px;color:#646970;text-transform:uppercase;">
						<?php esc_html_e( 'Location', 'media-library-tools' ); ?>
					</h4>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
						<div>
							<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
								<?php esc_html_e( 'Latitude (-90 to 90)', 'media-library-tools' ); ?>
							</label>
							<input type="text" id="tsmlt-lat-<?php echo absint( $attachment_id ); ?>" placeholder="37.7749" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
						</div>
						<div>
							<label style="display:block;font-size:12px;font-weight:500;margin-bottom:4px;">
								<?php esc_html_e( 'Longitude (-180 to 180)', 'media-library-tools' ); ?>
							</label>
							<input type="text" id="tsmlt-lng-<?php echo absint( $attachment_id ); ?>" placeholder="-122.4194" style="width:100%;padding:6px;border:1px solid #dcdcde;border-radius:3px;font-size:12px;" />
						</div>
					</div>
				</div>

				<!-- Validation errors -->
				<div id="tsmlt-exif-errors-<?php echo absint( $attachment_id ); ?>" style="color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:3px;padding:8px;display:none;margin-bottom:12px;font-size:12px;"></div>

				<!-- Action buttons -->
				<div style="display:flex;gap:8px;margin-bottom:12px;">
					<button type="button" id="tsmlt-exif-cancel-<?php echo absint( $attachment_id ); ?>" class="button" style="flex:1;">
						<?php esc_html_e( 'Cancel', 'media-library-tools' ); ?>
					</button>
					<button type="button" id="tsmlt-exif-save-<?php echo absint( $attachment_id ); ?>" class="button button-primary" style="flex:1;">
						<?php esc_html_e( 'Save EXIF', 'media-library-tools' ); ?>
					</button>
				</div>

				<!-- Result message -->
				<div id="tsmlt-exif-result-<?php echo absint( $attachment_id ); ?>" style="margin-bottom:8px;"></div>
			</div>
		</div>

		<script type="text/javascript">
		(function() {
			const id = <?php echo absint( $attachment_id ); ?>;
			const btnEdit = document.getElementById('tsmlt-exif-edit-btn-' + id);
			const form = document.getElementById('tsmlt-exif-form-' + id);
			const btnCancel = document.getElementById('tsmlt-exif-cancel-' + id);
			const btnSave = document.getElementById('tsmlt-exif-save-' + id);
			const errDiv = document.getElementById('tsmlt-exif-errors-' + id);
			const resDiv = document.getElementById('tsmlt-exif-result-' + id);
			const beforePanel = form.querySelector('[data-panel="before"]');
			const afterPanel = form.querySelector('[data-panel="after"]');
			const tabBtns = form.querySelectorAll('.tsmlt-tab-btn');

			let currentData = null;

			// Toggle form open/close
			btnEdit.addEventListener('click', function(e) {
				e.preventDefault();
				if (form.style.display === 'none') {
					form.style.display = 'block';
					if (!currentData) {
						loadExifData();
					}
				} else {
					form.style.display = 'none';
				}
			});

			// Tab switching
			tabBtns.forEach(btn => {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					const tab = this.dataset.tab;
					tabBtns.forEach(b => {
						b.classList.toggle('active', b === this);
						b.style.borderBottom = b === this ? '2px solid #0073aa' : '2px solid transparent';
						b.style.color = b === this ? '#0073aa' : '#646970';
					});
					form.querySelectorAll('.tsmlt-tab-panel').forEach(p => {
						p.style.display = p.dataset.panel === tab ? 'grid' : 'none';
					});
					if (tab === 'after') {
						updateAfterPanel();
					}
				});
			});

			// Load EXIF data via AJAX
			function loadExifData() {
				const formData = new FormData();
				formData.append('action', 'tsmlt_exif_get_editable');
				formData.append('nonce', tsmltParams.tsmlt_wpnonce);
				formData.append('params', JSON.stringify({ attachment_id: id }));

				fetch(ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(r => r.json())
				.then(data => {
					if (data.success) {
						currentData = data.data;
						populateForm(data.data);
						renderBeforePanel(data.data);
					} else {
						errDiv.textContent = data.data?.message || 'Failed to load EXIF data';
						errDiv.style.display = 'block';
					}
				})
				.catch(err => {
					errDiv.textContent = 'Error: ' + err.message;
					errDiv.style.display = 'block';
				});
			}

			// Populate form inputs from EXIF data
			function populateForm(data) {
				document.getElementById('tsmlt-make-' + id).value = data.make || '';
				document.getElementById('tsmlt-model-' + id).value = data.model || '';
				document.getElementById('tsmlt-iso-' + id).value = data.iso || '';
				document.getElementById('tsmlt-aperture-' + id).value = data.aperture || '';
				document.getElementById('tsmlt-shutter-' + id).value = data.shutter_speed || '';
				document.getElementById('tsmlt-lat-' + id).value = data.gps_lat || '';
				document.getElementById('tsmlt-lng-' + id).value = data.gps_lng || '';

				// Add live-update listeners
				['make', 'model', 'iso', 'aperture', 'shutter', 'lat', 'lng'].forEach(field => {
					const el = document.getElementById('tsmlt-' + field + '-' + id);
					if (el) {
						el.addEventListener('input', updateAfterPanel);
					}
				});
			}

			// Render Before panel (read-only)
			function renderBeforePanel(data) {
				const fields = [
					['Make', data.make],
					['Model', data.model],
					['ISO', data.iso],
					['Aperture', data.aperture ? 'f/' + data.aperture : ''],
					['Shutter Speed', data.shutter_speed],
					['Latitude', data.gps_lat],
					['Longitude', data.gps_lng],
				];
				let html = '';
				fields.forEach(([label, value]) => {
					if (value) {
						html += `<div><small style="color:#646970;font-weight:500;">${esc(label)}:</small><div style="font-size:13px;color:#000;">${esc(value)}</div></div>`;
					}
				});
				beforePanel.innerHTML = html || '<p style="color:#a7aaad;font-size:12px;">No EXIF data found</p>';
			}

			// Update After panel (live from form)
			function updateAfterPanel() {
				const fields = [
					['Make', document.getElementById('tsmlt-make-' + id).value],
					['Model', document.getElementById('tsmlt-model-' + id).value],
					['ISO', document.getElementById('tsmlt-iso-' + id).value],
					['Aperture', (v => v ? 'f/' + v : '')( document.getElementById('tsmlt-aperture-' + id).value)],
					['Shutter Speed', document.getElementById('tsmlt-shutter-' + id).value],
					['Latitude', document.getElementById('tsmlt-lat-' + id).value],
					['Longitude', document.getElementById('tsmlt-lng-' + id).value],
				];
				let html = '';
				fields.forEach(([label, value]) => {
					if (value) {
						html += `<div><small style="color:#646970;font-weight:500;">${esc(label)}:</small><div style="font-size:13px;color:#000;">${esc(value)}</div></div>`;
					}
				});
				afterPanel.innerHTML = html || '<p style="color:#a7aaad;font-size:12px;">No changes</p>';
			}

			// Validate fields
			function validate() {
				const errors = [];
				const date = document.getElementById('tsmlt-date-' + id).value;
				if (date && !/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}$/.test(date))
					errors.push('Date must be YYYY:MM:DD HH:MM:SS');
				const iso = document.getElementById('tsmlt-iso-' + id).value;
				if (iso && (isNaN(iso) || parseInt(iso) < 1 || parseInt(iso) > 102400))
					errors.push('ISO must be 1–102400');
				const aperture = document.getElementById('tsmlt-aperture-' + id).value;
				if (aperture && (isNaN(aperture) || parseFloat(aperture) <= 0))
					errors.push('Aperture must be positive');
				const shutter = document.getElementById('tsmlt-shutter-' + id).value;
				if (shutter && !/^(1\/\d+|\d*\.?\d+)$/.test(shutter))
					errors.push('Shutter speed must be like 1/250 or 0.5');
				const lat = document.getElementById('tsmlt-lat-' + id).value;
				if (lat && (isNaN(lat) || parseFloat(lat) < -90 || parseFloat(lat) > 90))
					errors.push('Latitude must be -90 to 90');
				const lng = document.getElementById('tsmlt-lng-' + id).value;
				if (lng && (isNaN(lng) || parseFloat(lng) < -180 || parseFloat(lng) > 180))
					errors.push('Longitude must be -180 to 180');
				return errors;
			}

			// Save EXIF
			btnSave.addEventListener('click', function(e) {
				e.preventDefault();
				errDiv.style.display = 'none';
				resDiv.innerHTML = '';

				const errors = validate();
				if (errors.length > 0) {
					errDiv.textContent = errors.join(' | ');
					errDiv.style.display = 'block';
					return;
				}

				btnSave.disabled = true;
				btnSave.textContent = '<?php echo esc_js( __( 'Saving...', 'media-library-tools' ) ); ?>';

				const fields = {
					make: document.getElementById('tsmlt-make-' + id).value,
					model: document.getElementById('tsmlt-model-' + id).value,
					iso: document.getElementById('tsmlt-iso-' + id).value,
					aperture: document.getElementById('tsmlt-aperture-' + id).value,
					shutter_speed: document.getElementById('tsmlt-shutter-' + id).value,
					gps_lat: document.getElementById('tsmlt-lat-' + id).value,
					gps_lng: document.getElementById('tsmlt-lng-' + id).value,
				};

				const formData = new FormData();
				formData.append('action', 'tsmlt_exif_save');
				formData.append('nonce', tsmltParams.tsmlt_wpnonce);
				formData.append('params', JSON.stringify({ attachment_id: id, fields: fields }));

				fetch(ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(r => r.json())
				.then(data => {
					btnSave.disabled = false;
					btnSave.textContent = '<?php echo esc_js( __( 'Save EXIF', 'media-library-tools' ) ); ?>';

					if (data.success) {
						const msg = document.createElement('div');
						msg.style.cssText = 'padding:8px;background:#d4edda;border:1px solid #c3e6cb;border-radius:3px;color:#155724;font-size:12px;';
						msg.textContent = data.data?.message || 'EXIF saved successfully';
						resDiv.innerHTML = '';
						resDiv.appendChild(msg);
						setTimeout(() => {
							location.reload();
						}, 2000);
					} else {
						const msg = document.createElement('div');
						msg.style.cssText = 'padding:8px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:3px;color:#721c24;font-size:12px;';
						msg.textContent = data.data?.message || 'Failed to save EXIF';
						resDiv.innerHTML = '';
						resDiv.appendChild(msg);
					}
				})
				.catch(err => {
					btnSave.disabled = false;
					btnSave.textContent = '<?php echo esc_js( __( 'Save EXIF', 'media-library-tools' ) ); ?>';
					const msg = document.createElement('div');
					msg.style.cssText = 'padding:8px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:3px;color:#721c24;font-size:12px;';
					msg.textContent = 'Error: ' + err.message;
					resDiv.innerHTML = '';
					resDiv.appendChild(msg);
				});
			});

			// Cancel
			btnCancel.addEventListener('click', function(e) {
				e.preventDefault();
				form.style.display = 'none';
				errDiv.style.display = 'none';
				resDiv.innerHTML = '';
			});

			// Helper: escape HTML
			function esc(str) {
				const div = document.createElement('div');
				div.textContent = str;
				return div.innerHTML;
			}
		})();
		</script>
		<?php
		return ob_get_clean();
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
			case 'tsmlt_exif_camera':
				echo esc_html( ExifDataReader::instance()->get_camera_display( $post_id ) );
				break;
				case 'tsmlt_exif_dimensions':
				echo esc_html( ExifDataReader::instance()->get_dimensions_display( $post_id ) );
				break;
			default:
				break;
		}
	}

	/**
	 * Process EXIF on attachment upload based on settings.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function process_exif_on_upload( int $attachment_id ): void {
		ExifAutoProcessor::instance()->process_on_upload( $attachment_id );
	}
}
