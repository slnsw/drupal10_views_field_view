<?php
/**
 * @file
 * Views field view field handler class.
 * 
 */

class views_field_view_handler_field_view extends views_handler_field {
  /**
   * If query aggregation is used, all of the arguments for the child view.
   *
   * This is a multidimensional array containing field_aliases for the argument's fields
   * and containing a linear array of all of the results to be used as arguments in various fields.
   */
  public $child_arguments = array();

  /**
   * If query aggregation is used, this attribute contains an array of the results
   * of the aggregated child views.
   */
  public $child_view_results = array();

  /**
   * If query aggregation is enabled, one instance of the child view to be reused.
   *
   * Note, it should never contain arguments or results because they will be
   * injected into it for rendering.
   */
  public $child_view = FALSE;

  function option_definition() {
    $options = parent::option_definition();
    $options['view'] = array('default' => '');
    $options['display'] = array('default' => 'default');
    $options['arguments'] = array('default' => '');
    $options['query_aggregation'] = array('default' => FALSE);

    return $options;
  }

  function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);

    $options = drupal_map_assoc(array_keys(views_get_enabled_views()));

    $form['view'] = array(
      '#type' => 'select',
      '#title' => t('View'),
      '#default_value' => $this->options['view'],
      '#options' => $options,
      '#ajax' => array(
        'path' => views_ui_build_form_url($form_state),
      ),
      '#submit' => array('views_ui_config_item_form_submit_temporary'),
      '#executes_submit_callback' => TRUE,
    );

    if ($this->options['view']) {
      $view = views_get_view($this->options['view']);

      $options = array();
      foreach ($view->display as $name => $display) {
        // Allow to embed a different display as the current one.
        if ($this->options['view'] != $this->view->name || ($this->view->current_display != $name)) {
          $options[$name] = $display->display_title;
        }
      }

      $form['display'] = array(
        '#title' => t('Display'),
        '#type' => 'select',
        '#default_value' => $this->options['display'],
        '#options' => $options,
      );
      $form['arguments'] = array(
        '#title' => t('Arguments'),
        '#description' => t('Use a comma-seperated list of each argument which should be forwared to the view'),
        '#type' => 'textfield',
        '#default_value' => $this->options['arguments'],
      );
      $form['available_tokens'] = array(
        '#type' => 'fieldset',
        '#title' => t('Replacement patterns'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#value' => $this->get_token_info(),
      );

      $form['query_aggregation'] = array(
        '#title' => t('Aggregate queries'),
        '#description' => t('Views Field View usually runs a separate query for each instance of this field on each row and that can mean a lot of queries. 
          This option attempts to aggregate these queries into one query per instance of this field (regardless of how many rows are displayed). 
          <strong>Currently child views must be configured to "display all values" if no argument is present and query aggregation is enabled.</strong>. 
          This may only work on simple views, please test thoroughly.'),
        '#type' => 'checkbox',
        '#default_value' => $this->options['query_aggregation'],
      );
      // Ensure we're working with a SQL view.
      $views_data = views_fetch_data($view->base_table);
      if ($views_data['table']['base']['query class'] == 'views_query') {
        $form['query_aggregation']['#disabled'] = TRUE;
      }
    }
    $form['alter']['#access'] = FALSE;
  }

  function query() {
    $this->ensure_my_table();
    $this->add_additional_fields();
  }

  /**
   * Run before any fields are rendered.
   *
   * This gives the handlers some time to set up before any handler has
   * been rendered.
   *
   * @param $values
   *   An array of all objects returned from the query.
   */
  function pre_render(&$values) {
    // Only act if we are attempting to aggregate all of the field
    // instances into a single query.
    if ($this->options['view'] && $this->options['query_aggregation']) {
      // Note: Unlike render, pre_render will be run exactly once per
      // views_field_view field (not once for each row).
      $child_view_name = $this->options['view'];
      $child_view_display = $this->options['display'];

      // Add each argument token configured for this view_field.
      foreach (explode(',', $this->options['arguments']) as $token) {
        // Remove the brackets around the token
        $argument = substr($token, 1, -1);
        // Collect all of the values that we intend to use as arguments of our single query.
        // TODO: Get this to be handled by get_token_values() method too.
        if (isset($this->view->field[$argument]->field_alias)) {
          $field_alias = $this->view->field[$argument]->field_alias;
          foreach ($values as $value) {
            if (isset($value->$field_alias)) {
              $this->child_arguments[$field_alias]['argument_name'] = $argument;
              $this->child_arguments[$field_alias]['values'][] = $value->$field_alias;
            }
          }
        }
      }
      // If we don't have child arguments we should not try to do any of our magic.
      if (count($this->child_arguments)) {
        // Cache the child_view in this object to minize our calls to views_get_view.
        $this->child_view = views_get_view($child_view_name);
        $child_view = $this->child_view;
        // Set the appropriate display.
        $child_view->access($child_view_display);
        // Find the arguments on the child view that we're going to need if the arguments have been overridden.
        foreach ($child_view->display['default']->display_options['arguments'] as $argument_name => $argument_value) {
          if (isset($child_view->display[$child_view_display]->display_options['arguments'][$argument_name])) {
            $configured_arguments[$argument_name] = $child_view->display[$child_view_display]->display_options['arguments'][$argument_name];
          }
          else {
            $configured_arguments[$argument_name] = $child_view->display['default']->display_options['arguments'][$argument_name];
          }
        }
        $argument_ids = array();
        foreach ($this->child_arguments as $child_argument_name => $child_argument) {
          // Work with the arguments on the child view in the order they are
          // specified in our views_field_view field settings.
          $configured_argument = array_shift($configured_arguments);
          // To be able to later split up our results among the appropriate rows,
          // we need to add whatever argument fields we're using to the query.
          $argument_ids[$child_argument_name] = $child_view->add_item($child_view_display, 'field', $configured_argument['table'], $configured_argument['field'], array('exclude' => TRUE));
          // Initialize the query object so that we have it to alter.
          // The child view may have been limited but our result set here should not be.
          if (isset($child_view->pager['items_per_page'])) {
            $child_view->pager['items_per_page'] = 0;
          }
          $child_view->build();
          // Add the WHERE IN clause to this query.
          $child_view->query->add_where(0, $configured_argument['table'] . '.' . $configured_argument['field'], $child_argument['values']);
        }
        $child_view->build_info['query'] = $child_view->query->query();
        $child_view->build_info['count_query'] = $child_view->query->query(TRUE);
        $child_view->build_info['query_args'] = $child_view->query->get_where_args();
        // Execute the query to retrieve the results.
        $child_view->execute();
        // Now that the query has run, we need to get the field alias for each argument field
        // so that it can be identified later.
        foreach ($argument_ids as $child_argument_name => $argument_id) {
          $this->child_arguments[$child_argument_name]['child_view_field_alias'] = $child_view->field[$argument_id]->field_alias;
        }
        $results = $child_view->result;

        // Finally: Cache the results so that they're easily accessible for the render function.
        // Loop through the results from the main view so that we can cache the results relevant to each row.
        foreach ($values as $value) {
          // Add an element to the child_view_results array for each of the rows keyed by this view's base_field.
          $this->child_view_results[$value->{$this->view->base_field}] = array();
          $child_view_result_row =& $this->child_view_results[$value->{$this->view->base_field}];
          // Loop through the actual result set looking for matches to these arguments.
          foreach ($results as $result) {
            // Assume that we have a matching item until we know that we don't.
            $matching_item = TRUE;
            // Check each argument that we care about to ensure that it matches.
            foreach ($this->child_arguments as $child_argument_field_alias => $child_argument) {
              // If one of our arguments does not match the argument of this field, do not add it to this row.
              if (isset($value->$child_argument_field_alias) && $value->$child_argument_field_alias != $result->{$child_argument['child_view_field_alias']}) {
                $matching_item = FALSE;
              }
            }
            if ($matching_item) {
              $child_view_result_row[] = $result;
            }
          }
          // Make a best effort attempt at paging.
          if (isset($this->child_view->pager['items_per_page'])) {
            $item_limit = $this->child_view->pager['items_per_page'];
            // If the item limit exists but is set to zero, do not split up the results.
            if ($item_limit != 0) {
              $results = array_chunk($results, $item_limit);
              $offset = (isset($this->child_view->pager['offset']) ? $this->child_view->pager['offset'] : 0);
              $results = $results[$offset];
            }
          }
          unset($child_view_result_row);
        }
        // We have essentially built and executed the child view member of this view.
        // Set it accordingly so that it is not rebuilt during the rendering of each row below.
        $this->child_view->built = TRUE;
        $this->child_view->executed = TRUE;
      }
    }
  }

  function render($values) {
    static $running = array();
    // Protect against the evil / recursion.
    // Set the variable for yourself, this is not for the normal "user".
    if (empty($running[$this->options['view']][$this->options['display']]) || variable_get('views_field_view_evil', FALSE)) {
      if ($this->options['view'] && !$this->options['query_aggregation']) {
        $running[$this->options['view']][$this->options['display']] = TRUE;
        $args = array();
        // Only perform this loop if there are actually arguments present.
        if (!empty($this->options['arguments'])) {
          // Create array of tokens.
          $tokens = explode(',', $this->options['arguments']);
          $args = $this->get_token_values($tokens, $values, $this->view);
        }
        // get view etc… and execute.
        $view = views_get_view($this->options['view']);
        $view->set_display($this->options['display']);
        $view->pre_execute($args);
        $view->execute();

        // If there are no results and hide_empty is set.
        if (empty($view->result) && $this->options['hide_empty']) {
          $output = '';
        }
        // Else just call render on the view object.
        else {
          $output = $view->render();
        }
        //$output = call_user_func_array('views_embed_view', $args);
        $running[$this->options['view']][$this->options['display']] = FALSE;
      }
      // Verify we have a child view (if there were no arguments specified we
      // won't have one), and that query aggregation was enabled.
      elseif ($this->child_view && $this->options['view'] && $this->options['query_aggregation']) {
        $running[$this->options['view']][$this->options['display']] = TRUE;
        $child_view = $this->child_view;
        $results =  $this->child_view_results[$values->{$this->view->base_field}];
        // Inject the appropriate result set before rendering the view.
        $child_view->result = $results;
        if (isset($child_view->style_plugin->rendered_fields)) {
          unset($child_view->style_plugin->rendered_fields);
        }
        $output = $child_view->render();
        $running[$this->options['view']][$this->options['display']] = FALSE;
      }
    }
    else {
      $output = t("Recursion, stop!");
    }
    return $output;
  }
  
  /**
   * Get raw token values from tokens.
   * 
   * @param $tokens
   *  array of token strings. E.g. explode(',', $this->options['args']);
   * 
   * @param $values
   *  object of row field values, as passed into pre_render or render methods.
   * 
   * @param $view
   *  Full view object to get token values from.
   * 
   * @return
   *  array of raw argument values, returned in the same order as the token were passed in.
   * 
   */
  function get_token_values($tokens, $values, $view) {
    $args = array();
    // Add each argument token configured for this view_field.
    foreach ($tokens as $token) {
      // Trim whitespace and remove the brackets around the token.
      $argument = substr(trim($token), 1, -1);
      // Collect all of the values that we intend to use as arguments of our single query.
      if (isset($view->field[$argument]->field_alias)) {
        $field_alias = $view->field[$argument]->field_alias;
        if (isset($values->{$field_alias})) {
          $args[] = $values->{$field_alias};
        }
      }
    }
    return $args;
  }
  
  /**
   * Get available field tokens, code/logic stolen from views_handler_field.inc.
   */
  public function get_token_info() {
    // Get a list of the available fields and arguments for token replacement.
    $options = array();
    foreach ($this->view->display_handler->get_handlers('field') as $field => $handler) {
      $options[t('Fields')]["[$field]"] = $handler->ui_name();
      // We only use fields up to (and including) this one.
      if ($field == $this->options['id']) {
        break;
      }
    }
    $count = 0; // This lets us prepare the key as we want it printed.
    foreach ($this->view->display_handler->get_handlers('argument') as $arg => $handler) {
      $options[t('Arguments')]['%' . ++$count] = t('@argument title', array('@argument' => $handler->ui_name()));
      $options[t('Arguments')]['!' . $count] = t('@argument input', array('@argument' => $handler->ui_name()));
    }

    $this->document_self_tokens($options[t('Fields')]);

    // Default text.
    $output = '<p>' . t('You must add some additional fields to this display before using this field. 
      These fields may be marked as <em>Exclude from display</em> if you prefer. Note that due to rendering order, 
      you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>';
    // We have some options, so make a list.
    if (!empty($options)) {
      $output = '<p>' . t('The following tokens are available for this field. Note that due to rendering order, 
        you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>';
      foreach (array_keys($options) as $type) {
        if (!empty($options[$type])) {
          $items = array();
          foreach ($options[$type] as $key => $value) {
            $items[] = $key . ' == ' . $value;
          }
          $output .= theme('item_list',
            array(
              'items' => $items,
              'type' => $type
            ));
        }
      }
    }
    return $output;
  }

}
