<?php
/*****************************************************************

  Page template for IDX Search Widget, Version 2

******************************************************************/
?>

<?php echo $before_widget; ?>

<?php if ( class_exists( 'flexmlsConnectPageCore' ) ) { flexmlsConnectPageCore::render_primary_color_styles_once(); } ?>

<div class="flexmls_connect__search flexmls-v2-widget <?php echo esc_attr( $wrapper_class ); ?>
  flexmls_connect__search_v2_<?php echo $orientation; ?> flexmls_connect__search_v2_instance_<?php echo esc_attr( (string) $rand ); ?>"
  style="
    color: <?php echo $field_text_color; ?>;
    max-width: <?php echo esc_attr( $width ); ?>;
    <?php echo $border_radius; ?>
    background-color: <?php echo esc_attr( $background_color ); ?>;
    <?php if ( ! empty( $property_type_tab_background_color ) ) : ?>
    --flexmls-property-type-tab-active-bg: <?php echo esc_attr( $property_type_tab_background_color ); ?>;
    --flexmls-property-type-tab-active-color: <?php echo esc_attr( $property_type_tab_text_color ); ?>;
    <?php endif; ?>
  ">

  <?php if ($destination == "remote") { ?>
    <form action='<?php echo $_SERVER['REQUEST_URI'] ?>' method='post' <?php echo $this_target ?> role="search" aria-label="Property search form">
  <?php } else { ?>
    <form action="<?php echo flexmlsConnect::make_nice_tag_url('search'); ?>" method='get'
      <?php echo $this_target; ?> role="search" aria-label="Property search form">
  <?php } ?>

    <?php // title (above property type tabs when tabs are shown at top) ?>
    <div class='flexmls_connect__search_v2_title' style="color: <?php echo $title_text_color; ?>;">
      <?php echo $title; ?>
    </div>

    <?php if ( ! empty( $pt_render_top_horizontal ) ) : ?>
    <div class="flexmls_connect__search_v2_property_types_top">
      <?php require __DIR__ . '/_property_types.php'; ?>
    </div>
    <?php endif; ?>

    <?php
      if ( ! empty( $pt_render_in_vertical_slot ) ) {
        require __DIR__ . '/_property_types.php';
      }
    ?>
    <?php if($default_view == "map"){   ?>
    <input type="hidden" name="view" value="map" />
    <?php } ?>

    <div class="flexmls_connect__search_v2_main">

    <?php // Location Search ?>

    <?php if ($location_search == "on") { ?>
      <div class='flexmls_connect__search_field location'>
        <label for="location-search-v2-<?php echo $rand; ?>">Location</label>
        <select class="flexmlsLocationSearch" id="location-search-v2-<?php echo $rand; ?>" data-portal-slug="<?= $portal_slug ?>" multiple="true" aria-describedby="location-help-v2-<?php echo $rand; ?>" data-select2-accessible="true">
          <?php
            foreach ($location_fields as $field => $value) {
              $option_value = $field . '_' . stripslashes($value);
              $displayName = $value . " ($field)";

              echo '<option value="' . $option_value . '" selected="selected">' . stripslashes($displayName) . '</option>';
            }
          ?>
        </select>
        <div id="location-help-v2-<?php echo $rand; ?>" class="sr-only">Select one or more locations to search for properties</div>
      </div>
    <?php
        $search_fields[] = "Location";
      }
    ?>
  <div class="flexmls_connect__filters_wrapper">
      <?php if ($std_fields_selected[0] != '') { ?>

        <div class='flexmls_connect__search_v2_min_max flexmls_connect__search_v2_field_group'>

          <?php

            foreach ($std_fields_selected as $fi) {

              fmcSearch::create_min_max_row($fi);

            }
          ?>
        </div>
      <?php } ?>

  <div class="flexmls_connect__righthand_filters_wrapper">
      <?php
        if ( ! empty( $pt_render_in_righthand ) ) {
          require __DIR__ . '/_property_types.php';
        }
      ?>

      <?php if ($destination == "local" and $user_sorting == "on") { ?>

        <div class='flexmls_connect__search_field flexmls_connect__search_v2_sort_by
          flexmls_connect__search_v2_field_group'>
          <label for="sort-by-v2-<?php echo $rand; ?>">Sort By</label>
          <select name='OrderBy' id="sort-by-v2-<?php echo $rand; ?>" size='1'>
            <option value='-ListPrice'>List price (High to Low)</option>
            <option value='ListPrice'>List price (Low to High)</option>
            <option value='-BedsTotal'># Bedrooms</option>
            <option value='-BathsTotal'># Bathrooms</option>
            <option value='-YearBuilt'>Year Built</option>
            <option value='-BuildingAreaTotal'>Square Footage</option>
            <option value='-ModificationTimestamp'>Recently Updated</option>
          </select>
        </div>
      <?php } ?>

  <?php if($allow_sold_searching == "on" || $allow_pending_searching == "on") : ?>
        <div class='flexmls_connect__search_field flexmls_connect__search_v2_field_group'>
          <label class='flexmls_connect__search_v2_label'>Listing Status</label>
  <div class='flexmls_sold_pending_search_wrapper'>
          <input id="flexmls_status_active" type='checkbox' name='StandardStatus[]' value='Active'
            class='flexmls_connect__search_v2_checkboxes' checked="checked" > <label for="flexmls_status_active">Active</label>
        <?php if($allow_sold_searching == "on"){?>
          <input id="flexmls_status_closed" type='checkbox' name='StandardStatus[]' value='Closed'
            class='flexmls_connect__search_v2_checkboxes'> <label for="flexmls_status_closed">Sold</label>
        <?php } ?>
      <?php if($allow_pending_searching == "on"){?>
          <input id="flexmls_status_pending" type='checkbox' name='StandardStatus[]' value='Pending'
            class='flexmls_connect__search_v2_checkboxes'> <label for="flexmls_status_pending">Pending</label>
      <?php } ?>
  </div>
        </div>
      <?php endif; ?>
  </div>
  </div>

    <?php echo $submit_return; ?>

    </div><!-- .flexmls_connect__search_v2_main -->

  </form>
</div>

<?php echo $after_widget; ?>
