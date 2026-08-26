<?php
/**
 * Property type + sub-type markup (IDX Search v2).
 *
 * Expects: $property_types_selected, $property_type_enabled, $good_prop_types,
 * $user_selected_property_types, $user_selected_property_sub_types, $property_sub_types,
 * $property_type_ui, $show_property_subtypes, $rand
 */
if ( $property_types_selected[0] == '' ) {
	return;
}

$show_subtypes = ( isset( $show_property_subtypes ) && 'on' === $show_property_subtypes );
$use_tabs      = ( isset( $property_type_ui ) && 'tabs' === $property_type_ui );
$rand_suffix   = isset( $rand ) ? (int) $rand : 0;

/**
 * Render subtype tabs for one property type (tabs layout only).
 */
$render_subtype_tabs = function( $type, $is_visible ) use (
	$property_sub_types,
	$user_selected_property_sub_types,
	$user_selected_property_types,
	$rand_suffix
) {
	if ( empty( $property_sub_types[ $type ] ) ) {
		return;
	}

	$active_subtype_index = null;
	if (
		is_array( $user_selected_property_sub_types )
		&& ! empty( $user_selected_property_sub_types )
		&& is_array( $user_selected_property_types )
		&& in_array( $type, array_values( $user_selected_property_types ), true )
	) {
		foreach ( $property_sub_types[ $type ] as $si => $sub_type ) {
			if ( in_array( $sub_type['Value'], $user_selected_property_sub_types, true ) ) {
				$active_subtype_index = $si;
				break;
			}
		}
	}

	$type_slug          = preg_replace( '/[^A-Za-z0-9_-]+/', '-', $type );
	$subtype_tablist_id = 'flexmls_property_subtype_tablist_' . $rand_suffix . '_' . $type_slug;
	?>
	<div id="<?php echo esc_attr( $subtype_tablist_id ); ?>"
		class="flexmls_connect__search_property_subtype--tabs<?php echo $is_visible ? ' is-active' : ''; ?>"
		data-property-type="<?php echo esc_attr( $type ); ?>">
		<div class="flexmls_connect__search_property_type_tablist flexmls_connect__search_property_subtype_tablist"
			role="tablist"
			aria-label="<?php echo esc_attr( sprintf( __( 'Property sub type for %s', 'flexmls-idx' ), flexmlsConnect::nice_property_type_label( $type ) ) ); ?>">
			<?php foreach ( $property_sub_types[ $type ] as $si => $sub_type ) : ?>
				<?php
				$subtype_slug       = preg_replace( '/[^A-Za-z0-9_-]+/', '-', $sub_type['Value'] );
				$subtype_panel_id   = 'flexmls_property_subtype_panel_' . $rand_suffix . '_' . $type_slug . '_' . $subtype_slug;
				$subtype_tab_id     = 'flexmls_property_subtype_tab_' . $rand_suffix . '_' . $type_slug . '_' . $subtype_slug;
				$subtype_is_active  = ( null !== $active_subtype_index && (int) $si === (int) $active_subtype_index );
				?>
			<button type="button"
				class="flexmls_property_type_tab flexmls_property_subtype_tab<?php echo $subtype_is_active ? ' is-active' : ''; ?>"
				id="<?php echo esc_attr( $subtype_tab_id ); ?>"
				role="tab"
				aria-selected="<?php echo $subtype_is_active ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $subtype_panel_id ); ?>"
				data-target="#<?php echo esc_attr( $subtype_panel_id ); ?>">
				<?php echo esc_html( $sub_type['Name'] ); ?>
			</button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $property_sub_types[ $type ] as $si => $sub_type ) : ?>
			<?php
			$subtype_slug      = preg_replace( '/[^A-Za-z0-9_-]+/', '-', $sub_type['Value'] );
			$subtype_panel_id  = 'flexmls_property_subtype_panel_' . $rand_suffix . '_' . $type_slug . '_' . $subtype_slug;
			$subtype_tab_id    = 'flexmls_property_subtype_tab_' . $rand_suffix . '_' . $type_slug . '_' . $subtype_slug;
			$subtype_is_active = ( null !== $active_subtype_index && (int) $si === (int) $active_subtype_index );
			$sub_checked       = $subtype_is_active ? 'checked="checked"' : '';
			?>
		<div id="<?php echo esc_attr( $subtype_panel_id ); ?>"
			class="flexmls_property_subtype_panel<?php echo $subtype_is_active ? ' is-active' : ''; ?>"
			role="tabpanel"
			aria-labelledby="<?php echo esc_attr( $subtype_tab_id ); ?>"
			<?php echo $subtype_is_active ? '' : ' hidden'; ?>>
			<input type="checkbox"
				name="PropertySubType[]"
				value="<?php echo esc_attr( $sub_type['Value'] ); ?>"
				class="flexmls_connect__search_new_checkboxes flexmls_property_subtype_tab_hidden_cb"
				<?php echo $sub_checked; ?>
				aria-label="<?php echo esc_attr( sprintf( __( 'Include %s in search', 'flexmls-idx' ), $sub_type['Name'] ) ); ?>">
		</div>
		<?php endforeach; ?>
	</div>
	<?php
};
?>
<?php if ( $use_tabs && 'on' === $property_type_enabled && count( $good_prop_types ) > 0 ) : ?>
	<?php
	$has_user_property_type_selection = is_array( $user_selected_property_types ) && ! empty( $user_selected_property_types );
	$active_property_type_index       = null;
	if ( $has_user_property_type_selection ) {
		foreach ( $good_prop_types as $ti => $type ) {
			if ( in_array( $type, $user_selected_property_types, true ) ) {
				$active_property_type_index = $ti;
				break;
			}
		}
	}
	$active_property_type = ( null !== $active_property_type_index ) ? $good_prop_types[ $active_property_type_index ] : null;
	$has_subtype_tabs     = $show_subtypes && ! empty( array_filter( array_map( function( $type ) use ( $property_sub_types ) {
		return ! empty( $property_sub_types[ $type ] );
	}, $good_prop_types ) ) );
	?>
<div class="flexmls_connect__search_field flexmls_connect__search_property_type flexmls_connect__search_new_property_type flexmls_connect__search_property_type--tabs flexmls_connect__search_new_field_group">
	<label class="flexmls_connect__search_new_label flexmls_connect__search_property_type_heading"><?php echo esc_html__( 'Property Type', 'flexmls-idx' ); ?></label>
	<div class="flexmls_connect__search_property_type_tablist" role="tablist" aria-label="<?php echo esc_attr__( 'Property type', 'flexmls-idx' ); ?>">
		<?php
		foreach ( $good_prop_types as $ti => $type ) :
			$panel_id = 'flexmls_property_type_panel_' . $rand_suffix . '_' . preg_replace( '/[^A-Za-z0-9_-]+/', '-', $type );
			$tab_id   = 'flexmls_property_type_tab_' . $rand_suffix . '_' . preg_replace( '/[^A-Za-z0-9_-]+/', '-', $type );
			$is_active = ( null !== $active_property_type_index && (int) $ti === (int) $active_property_type_index );
			?>
		<button type="button" class="flexmls_property_type_tab<?php echo $is_active ? ' is-active' : ''; ?>"
			id="<?php echo esc_attr( $tab_id ); ?>"
			role="tab"
			aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
			aria-controls="<?php echo esc_attr( $panel_id ); ?>"
			data-property-type="<?php echo esc_attr( $type ); ?>"
			data-target="#<?php echo esc_attr( $panel_id ); ?>">
			<?php echo esc_html( flexmlsConnect::nice_property_type_label( $type ) ); ?>
		</button>
		<?php endforeach; ?>
	</div>

	<?php if ( $has_subtype_tabs ) : ?>
	<div class="flexmls_connect__search_property_subtype_row<?php echo ( $active_property_type && ! empty( $property_sub_types[ $active_property_type ] ) ) ? ' is-visible' : ''; ?>">
		<label class="flexmls_connect__search_new_label flexmls_connect__search_property_subtype_heading"><?php echo esc_html__( 'Property Sub-Types', 'flexmls-idx' ); ?></label>
		<div class="flexmls_connect__search_property_subtype_blocks">
			<?php
			foreach ( $good_prop_types as $ti => $type ) {
				$render_subtype_tabs( $type, $active_property_type && $type === $active_property_type );
			}
			?>
		</div>
	</div>
	<?php endif; ?>

	<div class="flexmls_connect__search_property_type_panels-sr">
		<?php foreach ( $good_prop_types as $ti => $type ) : ?>
			<?php
			$panel_id = 'flexmls_property_type_panel_' . $rand_suffix . '_' . preg_replace( '/[^A-Za-z0-9_-]+/', '-', $type );
			$tab_id   = 'flexmls_property_type_tab_' . $rand_suffix . '_' . preg_replace( '/[^A-Za-z0-9_-]+/', '-', $type );
			if ( is_array( $user_selected_property_types ) && in_array( $type, $user_selected_property_types, true ) ) {
				$checked = 'checked="checked"';
			} else {
				$checked = '';
			}
			?>
		<div id="<?php echo esc_attr( $panel_id ); ?>"
			class="flexmls_property_type_panel"
			role="tabpanel"
			aria-labelledby="<?php echo esc_attr( $tab_id ); ?>">
			<input
				id="property_type_value_<?php echo esc_attr( $type ); ?>_<?php echo esc_attr( (string) $rand_suffix ); ?>"
				type="checkbox"
				name="PropertyType[]"
				value="<?php echo esc_attr( $type ); ?>"
				class="flexmls_connect__search_new_checkboxes flexmls_property_type_tab_hidden_cb"
				<?php echo $checked; ?>
				aria-label="<?php echo esc_attr( sprintf( __( 'Include %s in search', 'flexmls-idx' ), flexmlsConnect::nice_property_type_label( $type ) ) ); ?>"
			>
		</div>
		<?php endforeach; ?>
	</div>
</div>
<?php else : ?>
<div class="flexmls_connect__search_field flexmls_connect__search_property_type flexmls_connect__search_new_property_type flexmls_connect__search_new_field_group">
	<?php if ( 'on' === $property_type_enabled && count( $good_prop_types ) > 0 ) : ?>
		<label class="flexmls_connect__search_new_label"><?php echo esc_html__( 'Property Type', 'flexmls-idx' ); ?></label>
		<?php
		foreach ( $good_prop_types as $type ) :
			if ( is_array( $user_selected_property_types ) && in_array( $type, $user_selected_property_types, true ) ) {
				$checked = 'checked="checked"';
			} else {
				$checked = '';
			}
			?>
		<input id="property_type_value_<?php echo esc_attr( $type ); ?>" type="checkbox" name="PropertyType[]" value="<?php echo esc_attr( $type ); ?>"
			class="flexmls_connect__search_new_checkboxes" <?php echo $checked; ?> >
		<label for="property_type_value_<?php echo esc_attr( $type ); ?>"><?php echo esc_html( flexmlsConnect::nice_property_type_label( $type ) ); ?></label>
		<br>
		<?php endforeach; ?>
	<?php else : ?>
		<input type="hidden" name="PropertyType" value="<?php echo esc_attr( implode( ',', $good_prop_types ) ); ?>" />
	<?php endif; ?>

	<?php if ( $show_subtypes ) : ?>
		<?php foreach ( $property_sub_types as $property_code => $sub_types ) : ?>
			<div id="flexmls_connect__search_new_subtypes_for_<?php echo esc_attr( $property_code ); ?>"
				class="flexmls_connect__search_new_subtypes">
				<label class="flexmls_connect__search_new_label"><?php echo esc_html__( 'Property Sub Types', 'flexmls-idx' ); ?></label>
				<?php foreach ( $sub_types as $sub_type ) : ?>
					<?php
					if (
						is_array( $user_selected_property_sub_types )
						&& in_array( $sub_type['Value'], $user_selected_property_sub_types, true )
						&& is_array( $user_selected_property_types )
						&& in_array( $property_code, array_values( $user_selected_property_types ), true )
					) {
						$checked = 'checked="checked"';
					} else {
						$checked = '';
					}
					?>
				<input type="checkbox" name="PropertySubType[]" value="<?php echo esc_attr( $sub_type['Value'] ); ?>" class="flexmls_connect__search_new_checkboxes" <?php echo $checked; ?> >
				<?php echo esc_html( $sub_type['Name'] ); ?><br>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
<?php endif; ?>
