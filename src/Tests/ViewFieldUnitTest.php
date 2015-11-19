<?php

/**
 * @file
 * Contains \Drupal\views_field_view\Tests\ViewFieldUnitTest.
 */

namespace Drupal\views_field_view\Tests;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\views\Tests\ViewKernelTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use Drupal\views_field_view\Plugin\views\field\View as ViewField;

/**
 * Tests the views field view handler methods.
 *
 * @group views_field_view
 */
class ViewFieldUnitTest extends ViewKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['views_field_view', 'views_field_view_test_config', 'user'];

  /**
   * Views to enable.
   *
   * @var array
   */
  public static $testViews = ['views_field_view_test_parent_normal', 'views_field_view_test_child_normal'];

  protected function setUp() {
    parent::setUp();

    ViewTestData::createTestViews(get_class($this), ['views_field_view_test_config']);
  }

  /**
   * Test normal view embedding.
   */
  public function testNormalView() {
    $parent_view = Views::getView('views_field_view_test_parent_normal');
    $parent_view->preview();

    // Check that the child view has the same title as the parent one
    foreach ($parent_view->result as $index => $values) {
      $name = $parent_view->style_plugin->getField($index, 'name');
      $child_view_field = $parent_view->style_plugin->getField($index, 'view');
      $this->assertContains((string) $name, (string) $child_view_field);
    }

    // Sadly it's impossible to check the actual result of the child view, because the object is not saved.
  }

  /**
   * Test field handler methods in a unit test like way.
   */
  public function testFieldHandlerMethods() {
    $item = [
      'table' => 'views',
      'field' => 'view',
    ];
    /** @var ViewField $field_handler */
    $field_handler = $this->container->get('plugin.manager.views.field')->getHandler($item);

    $this->assertTrue($field_handler instanceof ViewField);

    // Test the split_tokens() method.
    $result = $field_handler->splitTokens('[!uid],[%nid]');
    $expected = ['[!uid]', '[%nid]'];
    $this->assertEqual($result, $expected, 'The token string has been split correctly (",").');

    $result = $field_handler->splitTokens('[!uid]/[%nid]');
    $this->assertEqual($result, $expected, 'The token string has been split correctly ("/").');

    $result = $field_handler->splitTokens('[uid]/[nid]');
    $expected = ['[uid]', '[nid]'];
    $this->assertEqual($result, $expected, 'The token string has been split correctly ("/").');

    // Test the get_token_argument() method.
    $result = $field_handler->getTokenArgument('[!uid]');
    $expected = ['type' => '!', 'arg' => 'uid'];
    $this->assertEqual($result, $expected, 'Correct token argument info processed ("!").');

    $result = $field_handler->getTokenArgument('[%uid]');
    $expected = ['type' => '%', 'arg' => 'uid'];
    $this->assertEqual($result, $expected, 'Correct token argument info processed ("%").');

    $result = $field_handler->getTokenArgument('[uid]');
    $expected = ['type' => '', 'arg' => 'uid'];
    $this->assertEqual($result, $expected, 'Correct token argument info processed.');
  }

  /**
   * Test token replacements.
   */
  public function testTokenReplacement() {
    // Test the token values from a view.
    $view = Views::getView('views_field_view_test_parent_normal');
    $this->executeView($view);

    // Add a value to args, just for the purpose of the !1 token to get a value
    // from but not affecting the query.
    $view->args = [5];

    // Test all the results.
    foreach ($view->result as $values) {
      $map = [
        '[!name]' => $values->views_test_data_name,
        '[name]' => $values->views_test_data_name,
        '!1' => 5,
        'static' => 'static',
      ];
      // @todo Test the last_render % output.
      foreach ($map as $token => $value) {
        $processed_value = $view->field['view']->getTokenValue($token, $values, $view);
        $this->assertIdentical($value, $processed_value, (new FormattableMarkup('Expected @token token output', ['@token' => $token]))->__toString());
      }
    }
  }

  /**
   * Assert method which checks whether the first string is part of the second string.
   *
   * @param $first
   *   The first value to check.
   * @param $second
   *   The second value to check.
   * @param string $message
   *   The message to display along with the assertion.
   * @param string $group
   *   The type of assertion - examples are "Browser", "PHP".
   *
   * @return bool TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertContains($first, $second, $message = '', $group = 'Other') {
    return $this->assert(strpos($second, $first) !== FALSE, $message ? $message : t('Value @second contains value @first.', ['@first' => var_export($first, TRUE), '@second' => var_export($second, TRUE)]), $group);
  }

}
