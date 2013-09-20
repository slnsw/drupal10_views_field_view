<?php

/**
 * @file
 * Contains \Drupal\views_field_view\Plugin\views\field\View.
 */

namespace Drupal\views_field_view\Plugin\views\field;

use Drupal\Component\Annotation\PluginID;
use Drupal\Core\Annotation\Translation;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Component\Utility\String;

/**
 * @PluginID("views_field_view_field")
 */
class View extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function useStringGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['view'] = array('default' => '');
    $options['display'] = array('default' => 'default');
    $options['arguments'] = array('default' => '');

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $view_options = views_get_views_as_options(TRUE);

    $form['views_field_view'] = array(
      '#type' => 'fieldset',
      '#title' => t("View settings"),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    $form['view'] = array(
      '#type' => 'select',
      '#title' => t('View'),
      '#description' => t('Select a view to embed.'),
      '#default_value' => $this->options['view'],
      '#options' => $view_options,
      '#ajax' => array(
        'path' => views_ui_build_form_path($form_state),
      ),
      '#submit' => array('views_ui_config_item_form_submit_temporary'),
      '#executes_submit_callback' => TRUE,
      '#fieldset' => 'views_field_view',
    );

    // If there is no view set, use the first one for now.
    if (count($view_options) && empty($this->options['view'])) {
      $new_options = array_keys($view_options);
      $this->options['view'] = reset($new_options);
    }

    if ($this->options['view']) {
      $view = views_get_view($this->options['view']);

      $display_options = array();
      foreach ($view->storage->get('display') as $name => $display) {
        // Allow to embed a different display as the current one.
        if ($this->options['view'] != $this->view->storage->id() || ($this->view->current_display != $name)) {
          $display_options[$name] = $display['display_title'];
        }
      }

      $form['display'] = array(
        '#type' => 'select',
        '#title' => t('Display'),
        '#description' => t('Select a view display to use.'),
        '#default_value' => $this->options['display'],
        '#options' => $display_options,
        '#ajax' => array(
          'path' => views_ui_build_form_path($form_state),
        ),
        '#submit' => array('views_ui_config_item_form_submit_temporary'),
        '#executes_submit_callback' => TRUE,
        '#fieldset' => 'views_field_view',
      );

      // Provide a way to directly access the views edit link of the child view.
      // Don't show this link if the current view is the selected child view.
      if (!empty($this->options['view']) && !empty($this->options['display']) && ($this->view->storage->id() != $this->options['view'])) {
        // use t() here, and set HTML on #link options.
        $link_text = t('Edit "%view (@display)" view', array('%view' => $view_options[$this->options['view']], '@display' => $this->options['display']));
        $form['view_edit'] = array(
          '#type' => 'container',
          '#fieldset' => 'views_field_view',
        );
        $form['view_edit']['view_edit_link'] = array(
          '#theme' => 'link',
          '#text' => $link_text,
          '#path' => 'admin/structure/views/view/' . $this->options['view'] . '/edit/' . $this->options['display'],
          '#options' => array(
            'attributes' => array(
              'target' => '_blank',
              'class' => array('views-field-view-child-view-edit'),
            ),
            'html' => TRUE,
          ),
          '#attached' => array(
            'css' => array(
              drupal_get_path('module', 'views_field_view') . '/views_field_view.css',
            ),
          ),
          '#prefix' => '<span>[</span>',
          '#suffix' => '<span>]</span>',
        );
        $form['view_edit']['description'] = array(
          '#markup' => t('Use this link to open the current child view\'s edit page in a new window.'),
          '#prefix' => '<div class="description">',
          '#suffix' => '</div>',
        );
      }

      $form['arguments'] = array(
        '#title' => t('Contextual filters'),
        '#description' => t('Use a comma (,) or forwardslash (/) separated list of each contextual filter which should be forwared to the view.
          See below list of available replacement tokens. Static values are also be passed to child views if they do not match a token format.
          You could pass static ID\'s or taxonomy terms in this way. E.g. 123 or "my taxonomy term".'),
        '#type' => 'textfield',
        '#default_value' => $this->options['arguments'],
        '#fieldset' => 'views_field_view',
      );
      $form['available_tokens'] = array(
        '#type' => 'fieldset',
        '#title' => t('Replacement patterns'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#value' => $this->getTokenInfo(),
        '#fieldset' => 'views_field_view',
      );
    }

    $form['alter']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render($values) {
    $output = NULL;

    static $running = array();
    // Protect against the evil / recursion.
    // Set the variable for yourself, this is not for the normal "user".
    if (empty($running[$this->options['view']][$this->options['display']]) || variable_get('views_field_view_evil', FALSE)) {
      if (!empty($this->options['view'])) {
        $running[$this->options['view']][$this->options['display']] = TRUE;
        $args = array();

        // Only perform this loop if there are actually arguments present.
        if (!empty($this->options['arguments'])) {
          // Create array of tokens.
          foreach ($this->splitTokens($this->options['arguments']) as $token) {
            $args[] = $this->getTokenValue($token, $values, $this->view);
          }
        }

        // get view etc… and execute.
        $view = views_get_view($this->options['view']);

        // Only execute and render the view if the user has access.
        if ($view->access($this->options['display'])) {
          $view->setDisplay($this->options['display']);

          if ($view->displayHandler->isPagerEnabled()) {
            // Check whether the pager IDs should be rewritten.
            $view->initQuery();
            // Find a proper start value for the ascening pager IDs.
            $start = 0;
            $pager = $view->displayHandler->getOption('pager');
            if (isset($this->query->pager->options['id'])) {
              $start = (int) $this->query->pager->options['id'];
            }

            // Set the pager ID before initializing the pager, so
            // views_plugin_pager::set_current_page works as expected, which is
            // called from view::init_pager()
            $pager['options']['id'] = $start + 1 + $this->view->row_index;
            $view->displayHandler->setOption('pager', $pager);
            $view->initPager();
          }

          $view->preExecute($args);
          $view->execute();

          // If there are no results and hide_empty is set.
          if (empty($view->result) && $this->options['hide_empty']) {
            $output = '';
          }
          // Else just call render on the view object.
          else {
            $output = $view->render();
          }
        }

        $running[$this->options['view']][$this->options['display']] = FALSE;
      }
    }
    else {
      $output = t('Recursion, stop!');
    }

    if (!empty($output)) {
      // Add the rendered output back to the $values object
      // so it is available in $view->result objects.
      $values->{'views_field_view_' . $this->options['id']} = $output;
    }

    return $output;
  }

  /**
   * Get field values from tokens.
   *
   * @param string $token
   *  token string. E.g. explode(',', $this->options['args']);
   * @param View $view
   *  Full view object to get token values from.
   *
   * @return array
   *  An array of raw argument values, returned in the same order as the token
   *  were passed in.
   */
  public function getTokenValue($token, $values, $view) {
    $token_info = $this->getTokenArgument($token);
    $arg = $token_info['arg'];
    $token_type = $token_info['type'];

    // Collect all of the values that we intend to use as arguments of our single query.
    if (isset($view->field[$arg])) {
      switch ($token_type) {
        case '%':
          $value = $view->field[$arg]->last_render;
        break;
        case '!':
        default:
          $value = $view->field[$arg]->getValue($values);
        break;
      }
    }
    elseif (isset($view->args[$arg - 1])) {
      switch ($token_type) {
        case '%':
          // Get an array of argument keys. So we can use the index as an
          // identifier.
          $keys = array_keys($view->argument);
          $value = $view->argument[$keys[$arg - 1]]->getTitle();
        break;
        case '!':
        default:
          $value = $view->args[$arg - 1];
        break;
      }
    }
    else {
      $value = String::checkPlain(trim($token, '\'"'));
    }

    return $value;
  }

  /**
   * Return the argument type and raw argument from a token.
   * E.g. [!test_token] will return "array('type' => '!', 'arg' => test_token)".
   *
   * @param string $token
   *  A single token string.
   *
   * @return array
   *  An array containing type and arg (As described above).
   */
  public function getTokenArgument($token) {
    // Trim whitespace and remove the brackets around the token.
    $argument = trim(trim($token), '[]');
    $diff = ltrim($argument, '!..%');
    $token_type = '';

    if ($argument != $diff) {
      $token_type = $argument[0];
      // Make the new argument the diff (without token type character).
      $argument = $diff;
    }

    return array(
      'type' => $token_type,
      'arg' => $argument,
    );
  }

  /**
   * Returns array of tokens/values to be used in child views.
   * String containing tokens is split on either "," or "/" characters.
   *
   * @param string $token_string
   *   The string of tokens to split.
   *
   * @return array
   *   An array of split token strings.
   */
  public function splitTokens($token_string) {
    return preg_split('/,|\//', $token_string);
  }

  /**
   * Get available field tokens, code/logic stolen from views_handler_field.inc.
   *
   * @return string
   *   A full HTML string, containing a list of available tokens.
   */
  public function getTokenInfo() {
    // Get a list of the available fields and arguments for token replacement.
    $options = array();

    foreach ($this->view->display_handler->getHandlers('field') as $field => $handler) {
      $options[t('Fields')]["[!$field]"] = $handler->adminLabel() . ' (' . t('raw') . ')';
      $options[t('Fields')]["[%$field]"] = $handler->adminLabel() . ' (' . t('rendered') . ')';
      // We only use fields up to (and including) this one.
      if ($field == $this->options['id']) {
        break;
      }
    }

    // This lets us prepare the key as we want it printed.
    $count = 0;

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[t('Arguments')]['%' . ++$count] = t('@argument title', array('@argument' => $handler->adminLabel()));
      $options[t('Arguments')]['!' . $count] = t('@argument input', array('@argument' => $handler->adminLabel()));
    }

    // Add replacements for query string parameters.
    foreach ($_GET as $param => $val) {
      if (is_array($val)) {
        $val = implode(', ', $val);
      }
      $options[t('Query string')]["[%$param]"] = strip_tags(decode_entities($val));
    }

    $this->documentSelfTokens($options[t('Fields')]);

    // Default text.
    $output = '<p>' . t('You must add some additional fields to this display before using this field.
      These fields may be marked as <em>Exclude from display</em> if you prefer. Note that due to rendering order,
      you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>';

    // We have some options, so make a list.
    if (!empty($options)) {
      $output = '<p>' . t('The following tokens are available for this field. Note that due to rendering order,
        you cannot use fields that come after this field; if you need a field that is not listed here, re-arrange your fields.') . '</p>';

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

    $output .= '<p><em>' . t('Using rendered (%) tokens can cause unexpected behaviour, as this will use the last output of the field.
      This could be re written output also. If no prefix is used in the token pattern, "!" will be used as a default.') . '</em></p>';

    return $output;
  }

}
