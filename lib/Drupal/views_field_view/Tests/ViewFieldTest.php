<?php

/**
 * @file
 * Definition of Drupal\views_field_view\Tests\ViewFieldTest.
 */

namespace Drupal\views_field_view\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\views_field_view\Plugin\views\field\View as ViewField;

class ViewFieldTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views', 'node', 'views_field_view');

  /**
   * An array of created nodes.
   *
   * @var array
   */
  public $nodes = array();

  public static function getInfo() {
    return array(
      'name' => 'Views field view test',
      'description' => 'Tests the views field view.',
      'group' => 'Views field view'
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->nodes = array();
    for ($i = 0; $i <= 10; $i++) {
      $this->nodes[] = $this->drupalCreateNode();
    }
  }

  /**
   * Assert method which checks whether the first string is part of the second string.
   * @param $first
   *   The first value to check.
   * @param $second
   *   The second value to check.
   * @param string $message
   *   The message to display along with the assertion.
   * @param string $group
   *   The type of assertion - examples are "Browser", "PHP".
   * @return bool TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertContains($first, $second, $message = '', $group = 'Other') {
    return $this->assert(strpos($second, $first) !== FALSE, $message ? $message : t('Value @first is equal to value @second.', array('@first' => var_export($first, TRUE), '@second' => var_export($second, TRUE))), $group);
  }


  /**
   * @todo
   * Test normal view embedding.
   */
  function testNormalView() {
    // Get the child view and add it to the database, so it can be used later.
    $child_view = $this->viewChildNormal();
    $child_view->save();
    views_invalidate_cache();

    $parent_view = $this->viewParentNormal();
    $parent_view->preview();


    // Check that the child view has the same title as the parent one
    foreach ($parent_view->result as $index => $values) {
      $title = $parent_view->style_plugin->get_field($index, 'title');
      $child_view_field = $parent_view->style_plugin->get_field($index, 'view');
      $this->assertContains($title, $child_view_field);
    }

    // Sadly it's impossible to check the actual result of the child view, because the object is not saved.
  }

  /**
   * Test field handler methods in a unit test like way.
   */
  function testFieldHandlerMethods() {
    $field_handler = new ViewField();
    $this->assertTrue(is_object($field_handler));

    // Test the split_tokens() method.
    $result = $field_handler->splitTokens('[!uid],[%nid]');
    $expected = array('[!uid]', '[%nid]');
    $this->assertEqual($result, $expected, 'The token string has been split correctly (",").');

    $result = $field_handler->splitTokens('[!uid]/[%nid]');
    $this->assertEqual($result, $expected, 'The token string has been split correctly ("/").');

    $result = $field_handler->splitTokens('[uid]/[nid]');
    $expected = array('[uid]', '[nid]');
    $this->assertEqual($result, $expected, 'The token string has been split correctly ("/").');

    // Test the get_token_argument() method.
    $result = $field_handler->getTokenArgument('[!uid]');
    $expected = array('type' => '!', 'arg' => 'uid');
    $this->assertEqual($result, $expected, 'Correct token argument info processed ("!").');

    $result = $field_handler->getTokenArgument('[%uid]');
    $expected = array('type' => '%', 'arg' => 'uid');
    $this->assertEqual($result, $expected, 'Correct token argument info processed ("%").');

    $result = $field_handler->getTokenArgument('[uid]');
    $expected = array('type' => '', 'arg' => 'uid');
    $this->assertEqual($result, $expected, 'Correct token argument info processed.');

    // Test the token values from a view.
    $view = $this->viewChildNormal();
    $view->execute();
    $results = $view->result;

    // Add a value to args, just for the purpose of the !1 token to get a value
    // from but not affecting the query.
    $view->args = array(5);

    // Test all the results.
    foreach ($results as $values) {
      $map = array(
        '[!title]' => $values->node_title,
        '[title]' => $values->node_title,
        '!1' => 5,
        'static' => 'static',
      );
      // @todo Test the last_render % output.
      foreach ($map as $token => $value) {
        $processed_value = $field_handler->getTokenValue($token, $values, $view);
        $this->assertIdentical($value, $processed_value, format_string('Expected @token token output', array('@token' => $token)));
      }
    }
  }

  /**
   * @todo
   * Test aggregation feature.
   */

  /**
   * Contains a normal child view used for the normal view testcase.
   *
   * @see viewsFieldViewTestCase::testNormalView
   * @return view
   */
  function viewChildNormal() {
    $view = entity_create('view', array());
    $view->name = 'test_vfv_child_normal';
    $view->description = '';
    $view->tag = 'default';
    $view->base_table = 'node';
    $view->human_name = 'test_vfv_child_normal';
    $view->core = 8;
    $view->api_version = '3.0';
    $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

    /* Display: Master */
    $handler = $view->newDisplay('default', 'Master', 'default');
    $handler->display->display_options['access']['type'] = 'perm';
    $handler->display->display_options['cache']['type'] = 'none';
    $handler->display->display_options['query']['type'] = 'views_query';
    $handler->display->display_options['query']['options']['query_comment'] = FALSE;
    $handler->display->display_options['exposed_form']['type'] = 'basic';
    $handler->display->display_options['pager']['type'] = 'full';
    $handler->display->display_options['style_plugin'] = 'default';
    $handler->display->display_options['row_plugin'] = 'fields';
    /* Field: Content: Title */
    $handler->display->display_options['fields']['title']['id'] = 'title';
    $handler->display->display_options['fields']['title']['table'] = 'node';
    $handler->display->display_options['fields']['title']['field'] = 'title';
    $handler->display->display_options['fields']['title']['label'] = '';
    $handler->display->display_options['fields']['title']['alter']['alter_text'] = 0;
    $handler->display->display_options['fields']['title']['alter']['make_link'] = 0;
    $handler->display->display_options['fields']['title']['alter']['absolute'] = 0;
    $handler->display->display_options['fields']['title']['alter']['word_boundary'] = 0;
    $handler->display->display_options['fields']['title']['alter']['ellipsis'] = 0;
    $handler->display->display_options['fields']['title']['alter']['strip_tags'] = 0;
    $handler->display->display_options['fields']['title']['alter']['trim'] = 0;
    $handler->display->display_options['fields']['title']['alter']['html'] = 0;
    $handler->display->display_options['fields']['title']['hide_empty'] = 0;
    $handler->display->display_options['fields']['title']['empty_zero'] = 0;
    $handler->display->display_options['fields']['title']['link_to_node'] = 1;
    /* Contextual filter: Content: Nid */
    $handler->display->display_options['arguments']['nid']['id'] = 'nid';
    $handler->display->display_options['arguments']['nid']['table'] = 'node';
    $handler->display->display_options['arguments']['nid']['field'] = 'nid';
    $handler->display->display_options['arguments']['nid']['default_argument_type'] = 'fixed';
    $handler->display->display_options['arguments']['nid']['default_argument_skip_url'] = 0;
    $handler->display->display_options['arguments']['nid']['summary']['number_of_records'] = '0';
    $handler->display->display_options['arguments']['nid']['summary']['format'] = 'default_summary';
    $handler->display->display_options['arguments']['nid']['summary_options']['items_per_page'] = '25';
    $handler->display->display_options['arguments']['nid']['break_phrase'] = 1;
    $handler->display->display_options['arguments']['nid']['not'] = 0;

    return $view->getExecutable();
  }

  /**
   * Contains a normal parent view used for the normal view testcase.
   * @see viewsFieldViewTestCase::testNormalView
   * @return view
   */
  function viewParentNormal() {
    $view = entity_create('view', array());
    $view->name = 'test_vfv_parent_normal';
    $view->description = '';
    $view->tag = 'default';
    $view->base_table = 'node';
    $view->human_name = 'test_vfv_parent_normal';
    $view->core = 8;
    $view->api_version = '3.0';
    $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

    /* Display: Master */
    $handler = $view->newDisplay('default', 'Master', 'default');
    $handler->display->display_options['access']['type'] = 'perm';
    $handler->display->display_options['cache']['type'] = 'none';
    $handler->display->display_options['query']['type'] = 'views_query';
    $handler->display->display_options['query']['options']['query_comment'] = FALSE;
    $handler->display->display_options['exposed_form']['type'] = 'basic';
    $handler->display->display_options['pager']['type'] = 'full';
    $handler->display->display_options['style_plugin'] = 'default';
    $handler->display->display_options['row_plugin'] = 'fields';
    /* Field: Content: Title */
    $handler->display->display_options['fields']['title']['id'] = 'title';
    $handler->display->display_options['fields']['title']['table'] = 'node';
    $handler->display->display_options['fields']['title']['field'] = 'title';
    $handler->display->display_options['fields']['title']['label'] = '';
    $handler->display->display_options['fields']['title']['alter']['alter_text'] = 0;
    $handler->display->display_options['fields']['title']['alter']['make_link'] = 0;
    $handler->display->display_options['fields']['title']['alter']['absolute'] = 0;
    $handler->display->display_options['fields']['title']['alter']['word_boundary'] = 0;
    $handler->display->display_options['fields']['title']['alter']['ellipsis'] = 0;
    $handler->display->display_options['fields']['title']['alter']['strip_tags'] = 0;
    $handler->display->display_options['fields']['title']['alter']['trim'] = 0;
    $handler->display->display_options['fields']['title']['alter']['html'] = 0;
    $handler->display->display_options['fields']['title']['hide_empty'] = 0;
    $handler->display->display_options['fields']['title']['empty_zero'] = 0;
    $handler->display->display_options['fields']['title']['link_to_node'] = 1;
    /* Field: Content: Nid */
    $handler->display->display_options['fields']['nid']['id'] = 'nid';
    $handler->display->display_options['fields']['nid']['table'] = 'node';
    $handler->display->display_options['fields']['nid']['field'] = 'nid';
    $handler->display->display_options['fields']['nid']['alter']['alter_text'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['make_link'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['absolute'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['external'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['replace_spaces'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['trim_whitespace'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['nl2br'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['word_boundary'] = 1;
    $handler->display->display_options['fields']['nid']['alter']['ellipsis'] = 1;
    $handler->display->display_options['fields']['nid']['alter']['more_link'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['strip_tags'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['trim'] = 0;
    $handler->display->display_options['fields']['nid']['alter']['html'] = 0;
    $handler->display->display_options['fields']['nid']['element_label_colon'] = 1;
    $handler->display->display_options['fields']['nid']['element_default_classes'] = 1;
    $handler->display->display_options['fields']['nid']['hide_empty'] = 0;
    $handler->display->display_options['fields']['nid']['empty_zero'] = 0;
    $handler->display->display_options['fields']['nid']['hide_alter_empty'] = 1;
    $handler->display->display_options['fields']['nid']['link_to_node'] = 0;
    /* Field: Global: View */
    $handler->display->display_options['fields']['view']['id'] = 'view';
    $handler->display->display_options['fields']['view']['table'] = 'views';
    $handler->display->display_options['fields']['view']['field'] = 'view';
    $handler->display->display_options['fields']['view']['element_label_colon'] = 1;
    $handler->display->display_options['fields']['view']['element_default_classes'] = 1;
    $handler->display->display_options['fields']['view']['hide_empty'] = 0;
    $handler->display->display_options['fields']['view']['empty_zero'] = 0;
    $handler->display->display_options['fields']['view']['hide_alter_empty'] = 1;
    $handler->display->display_options['fields']['view']['view'] = 'test_vfv_child_normal';
    $handler->display->display_options['fields']['view']['arguments'] = '[nid]';
    $handler->display->display_options['fields']['view']['query_aggregation'] = 0;
    /* Filter criterion: Content: Published */
    $handler->display->display_options['filters']['status']['id'] = 'status';
    $handler->display->display_options['filters']['status']['table'] = 'node';
    $handler->display->display_options['filters']['status']['field'] = 'status';
    $handler->display->display_options['filters']['status']['value'] = 1;
    $handler->display->display_options['filters']['status']['group'] = 1;
    $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;

    return $view->getExecutable();
  }
}
