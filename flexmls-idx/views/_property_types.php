<?php if ($property_types_selected[0] != ''): ?>

  <fieldset class='flexmls_connect__search_field flexmls_connect__search_new_property_type
    flexmls_connect__search_new_field_group'>

    <legend class='flexmls_connect__search_new_label'>Property Type</legend>

    <?php
      if ($property_type_enabled == "on" and count($good_prop_types) > 0):
    ?>
    <?php
      foreach ($good_prop_types as $type):
        if(is_array($user_selected_property_types) && in_array($type, $user_selected_property_types))
          $checked = 'checked="checked"';
        else
          $checked = '';
    ?>
        <div class="flexmls_connect__checkbox_wrapper">
          <input type='checkbox' name='PropertyType[]' value='<?php echo $type; ?>'
            class='flexmls_connect__search_new_checkboxes' id="property-type-<?php echo $type; ?>-<?php echo $rand; ?>" <?php echo $checked; ?> >
          <label for="property-type-<?php echo $type; ?>-<?php echo $rand; ?>"><?php echo flexmlsConnect::nice_property_type_label($type); ?></label>
        </div>
    <?php
      endforeach;
    else:
    ?>
      <input type='hidden' name='PropertyType' value='<?php echo implode(",", $good_prop_types); ?>' />
    <?php endif; ?>


    <?php //  property sub type ?>

    <?php foreach ($property_sub_types as $property_code => $sub_types): ?>
      <fieldset id="flexmls_connect__search_new_subtypes_for_<?php echo $property_code; ?>"
        class="flexmls_connect__search_new_subtypes">
        <legend class='flexmls_connect__search_new_label'>Property Sub Types</legend>
        <?php foreach ($sub_types as $sub_type): ?>
            <?php
                /* WP-542: Unchecking criteria from IDX Search Widget In Search Results Doesn't Stay */
                // This creates a list of checkboxes for each sub type *for each property type*
                // This means that the hidden boxes maintain the `checked` attribute, even if the visible list is unchecked
                // This `if` statement only checks a box if the property type *and* sub-property type is checked
                if (
                    in_array($sub_type["Value"], $user_selected_property_sub_types)
                    and in_array($property_code, array_values($user_selected_property_types))
                ) {
                    $checked = 'checked="checked"';
                } else {
                    $checked = '';
                }
            ?>
          <div class="flexmls_connect__checkbox_wrapper">
            <input type='checkbox' name='PropertySubType[]' value='<?php echo $sub_type["Value"]; ?>' class='flexmls_connect__search_new_checkboxes' id="property-subtype-<?php echo $sub_type["Value"]; ?>-<?php echo $rand; ?>" <?php echo $checked; ?>>
            <label for="property-subtype-<?php echo $sub_type["Value"]; ?>-<?php echo $rand; ?>"><?php echo $sub_type["Name"]; ?></label>
          </div>
        <?php endforeach;  ?>
      </fieldset>
    <?php endforeach; ?>
  </fieldset>
<?php endif; ?>
