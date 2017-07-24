<form method="post" action="admin.php?page=it-exchange-addons&add-on-settings=2checkout">

	<?php settings_fields('exchange_2checkout_license'); ?>

	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row" valign="top">
					<?php _e('License Key'); ?>
				</th>
				<td>
					<input id="exchange_2checkout_license_key" name="exchange_2checkout_license_key" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
					<label class="description" for="exchange_2checkout_license_key"><?php _e('Enter your license key'); ?></label>
				</td>
			</tr>
			<?php if( false !== $license ) { ?>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php _e('Activate License'); ?>
					</th>
					<td>
						<?php if( $status !== false && $status == 'valid' ) { ?>
							<span style="color:green;"><?php _e('active'); ?></span>
							<?php wp_nonce_field( 'exchange_2checkout_nonce', 'exchange_2checkout_nonce' ); ?>
							<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
						<?php } else {
							wp_nonce_field( 'exchange_2checkout_nonce', 'exchange_2checkout_nonce' ); ?>
							<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e('Activate License'); ?>"/>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php submit_button(); ?>

</form>
