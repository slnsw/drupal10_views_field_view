<?php

/**
 * @file
 * Contains \Drupal\views_field_view\Plugin\views\field\View.
 */

namespace Drupal\views_field_view\Plugin\views\field;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ViewsField("view")
 */
class View extends FieldPluginBase {

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a View object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ImmutableConfig $config) {
    $this->config = $config;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('views_field_view.settings')
    );
  }

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
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $view_options = Views::getViewsAsOptions(TRUE, 'all', NULL, FALSE, TRUE);

    $form['views_field_view'] = array(
      '#type' => 'details',
      '#title' => $this->t("View settings"),
      '#open' => TRUE,
    );

    $form['view'] = array(
      '#type' => 'select',
      '#title' => $this->t('View'),
      '#description' => $this->t('Select a view to embed.'),
      '#default_value' => $this->options['view'],
      '#options' => $view_options,
      '#ajax' => array(
        'path' => views_ui_build_form_url($form_state),
      ),
      '#submit' => array(array($this, 'submitTemporaryForm')),
      '#executes_submit_callback' => TRUE,
      '#fieldset' => 'views_field_view',
    );

    // If there is no view set, use the first one for now.
    if (count($view_options) && empty($this->options['view'])) {
      $new_options = array_keys($view_options);
      $this->options['view'] = reset($new_options);
    }

    if ($this->options['view']) {
      $view = Views::getView($this->options['view']);

      $display_options = array();
      foreach ($view->storage->get('display') as $name => $display) {
        // Allow to embed a different display as the current one.
        if ($this->options['view'] != $this->view->storage->id() || ($this->view->current_display != $name)) {
          $display_options[$name] = $display['display_title'];
        }
      }

      $form['display'] = array(
        '#type' => 'select',
        '#title' => $this->t('Display'),
        '#description' => $this->t('Select a view display to use.'),
        '#default_value' => $this->options['display'],
        '#options' => $display_options,
        '#ajax' => array(
          'path' => views_ui_build_form_url($form_state),
        ),
        '#submit' => array(array($this, 'submitTemporaryForm')),
        '#executes_submit_callback' => TRUE,
        '#fieldset' => 'views_field_view',
      );

      // Provide a way to directly access the views edit link of the child view.
      // Don't show this link if the current view is the selected child view.
      if (!empty($this->options['view']) && !empty($this->options['display']) && ($this->view->storage->id() != $this->options['view'])) {
        // use t() here, and set HTML on #link options.
        $link_text = $this->t('Edit "%view (@display)" view', array('%view' => $view_options[$this->options['view']], '@display' => $this->options['display']));
        $form['view_edit'] = array(
          '#type' => 'container',
          '#fieldset' => 'views_field_view',
        );
        $form['view_edit']['view_edit_link'] = array(
          '#type' => 'link',
          '#title' => $link_text,
          '#url' => Url::fromRoute('entity.view.edit_display_form', array(
            'view' => $this->options['view'],
            'display_id' => $this->options['display'],
          ), array(
            'attributes' => array(
              'target' => '_blank',
              'class' => array('views-field-view-child-view-edit'),
            ),
            'html' => TRUE,
          )),
          '#attached' => array(
            'library' => array('views_field_view/drupal.views_field_view'),
          ),
          '#prefix' => '<span>[</span>',
          '#suffix' => '<span>]</span>',
        );
        $form['view_edit']['description'] = array(
          '#markup' => $this->t('Use this link to open the current child view\'s edit page in a new window.'),
          '#prefix' => '<div class="description">',
          '#suffix' => '</div>',
        );
      }

      $form['arguments'] = array(
        '#title' => $this->t('Contextual filters'),
        '#description' => $this->t('Use a comma (,) or forwardslash (/) separated list of each contextual filter which should be forwared to the view.
          See below list of available replacement tokens. Static values are also be passed to child views if they do not match a token format.
          You could pass static ID\'s or taxonomy terms in this way. E.g. 123 or "my taxonomy term".'),
        '#type' => 'textfield',
        '#default_value' => $this->options['arguments'],
        '#fieldset' => 'views_field_view',
      );
      $form['available_tokens'] = array(
        '#type' => 'details',
        '#title' => $this->t('Replacement patterns'),
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
  public function render(ResultRow $values) {
    $output = NULL;

    static $running = array();
    // Protect against the evil / recursion.
    // Set the variable for yourself, this is not for the normal "user".
    if (empty($running[$this->options['view']][$this->options['display']]) || $this->config->get('evil')) {
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

        // Get view and execute.
        $view = Views::getView($this->options['view']);

        // Only execute and render the view if the user has access.
        if ($view->access($this->options['display'])) {
          $view->setDisplay($this->options['display']);

          if ($view->display_handler->isPagerEnabled()) {
            // Check whether the pager IDs should be rewritten.
            $view->initQuery();
            // Find a proper start value for the ascening pager IDs.
            $start = 0;
            $pager = $view->display_handler->getOption('pager');
            if (isset($this->query->pager->options['id'])) {
              $start = (int) $this->query->pager->options['id'];
            }

            // Set the pager ID before initializing the pager, so
            // views_plugin_pager::set_current_page works as expected, which is
            // called from view::init_pager()
            $pager['options']['id'] = $start + 1 + $this->view->row_index;
            $view->display_handler->setOption('pager', $pager);
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
      $output = $this->t('Recursion, stop!');
    }

    if (!empty($output)) {
      // Add the rendered output back to the $values object
      // so it is available in $view->result objects.
      $values->{'views_field_view_' . $this->options['id']} = $output;
    }

    return $output;
  }

  /**
   * Gets field values from tokens.
   *
   * @param string $token
   *  The token string. E.g. explode(',', $this->options['args']);
   * @param \Drupal\views\ResultRow $values
   *  The values retrieved from a single row of a view's query result.
   * @param View $view
   *  The full view object to get token values from.
   *
   * @return array
   *  An array of raw argument values, returned in the same order as the token
   *  were passed in.
   */
  public function getTokenValue($token, ResultRow $values, $view) {
    $token_info = $this->getTokenArgument($token);
    $arg = $token_info['arg'];
    $token_type = $token_info['type'];

    // Collect all of the values that we intend to use as arguments of our
    // single query.
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
      $value = SafeMarkup::checkPlain(trim($token, '\'"'));
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
    $output = array();
    // Get a list of the available fields and arguments for token replacement.
    $options = array();

    foreach ($this->view->display_handler->getHandlers('field') as $field => $handler) {
      $options[$this->t('Fields')]["[!$field]"] = $handler->adminLabel() . ' (' . $this->t('raw') . ')';
      $options[$this->t('Fields')]["[%$field]"] = $handler->adminLabel() . ' (' . $this->t('rendered') . ')';
      // We only use fields up to (and including) this one.
      if ($field == $this->options['id']) {
        break;
      }
    }

    // This lets us prepare the key as we want it printed.
    $count = 0;

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$this->t('Arguments')]['%' . ++$count] = $this->t('@argument title', array('@argument' => $handler->adminLabel()));
      $options[$this->t('Arguments')]['!' . $count] = $this->t('@argument input', array('@argument' => $handler->adminLabel()));
    }

    // Add replacements for query string parameters.
    foreach ($_GET as $param => $val) {
      if (is_array($val)) {
        $val = implode(', ', $val);
      }
      $options[$this->t('Query string')]["[%$param]"] = strip_tags(Html::decodeEntities($val));
    }

    $this->documentSelfTokens($options[$this->t('Fields')]);

    // We have some options, so make a list.
    if (!empty($options)) {

      foreach (array_keys($options) as $type) {
        if (!empty($options[$type])) {
          $items = array();
          foreach ($options[$type] as $key => $value) {
            $items[] = $key . ' == ' . $value;
          }
          $output = array(
            '#theme' => 'item_list',
            '#items' => $items,
            '#type' => $type,
            '#prefix' => '<p>' . $this->t('The following tokens are available
              for this field. Note that due to rendering order, you cannot use
              fields that come after this field; if you need a field that is not
              listed here, re-arrange  your fields.') . '</p>',
            '#suffix' => '<p><em>' . $this->t('Using rendered (%) tokens can
              cause unexpected behaviour, as this will use the last output of
              the field. This could be re written output also. If no prefix is
              used in the token pattern, "!" will be used as a default.') .
              '</em></p>',
          );
        }
      }
    }
    else {
      $output = array(
        '#markup' => '<p>' . $this->t('You must add some additional fields to
          this display before using this field. These fields may be marked as
          <em>Exclude from display</em> if you prefer. Note that due to
          rendering order,you cannot use fields that come after this field; if
          you need a field not listed here, rearrange your fields.') . '</p>',
      );
    }

    return $output;
  }

}
