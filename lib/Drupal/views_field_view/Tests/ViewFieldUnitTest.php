<?php

/**
 * @file
 * Contains \Drupal\views_field_view\Tests\ViewFieldUnitTest.
 */

namespace Drupal\views_field_view\Tests;

use Drupal\views\Tests\ViewUnitTestBase;
use Drupal\views_field_view\Plugin\views\field\View as ViewField;

class ViewFieldUnitTest extends ViewUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views_field_view');

  public static function getInfo() {
    return array(
      'name' => 'Views field view unit tests',
      'description' => 'Tests the views field view handler methods.',
      'group' => 'Views field view'
    );
  }

  /**
   * Test field handler methods in a unit test like way.
   */
  public function testFieldHandlerMethods() {
    $field_handler = $this->container->get('plugin.manager.views.field')->createInstance('views_field_view_field', array());

    $this->assertTrue($field_handler instanceof ViewField);

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
  }

}
