<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_webtools_page_feedback\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the configuration form of the Page Feedback Form webtools widget.
 */
class PageFeedbackFormTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'language',
    'block',
    'oe_webtools_page_feedback',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('oe_webtools_page_feedback_form');
    $this->drupalPlaceBlock('page_title_block');
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    ConfigurableLanguage::createFromLangcode('pt')->save();
  }

  /**
   * Tests Page Feedback Form configuration and block rendering.
   */
  public function testPageFeedbackForm(): void {
    // Create a node and a user for configuring the Page Feedback Form.
    $this->drupalCreateNode(['type' => 'page', 'title' => 'Page node']);
    $user = $this->createUser([
      'administer webtools page feedback form',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/config/system/oe_webtools_page_feedback');
    // Assert default values.
    $this->assertSession()->pageTextContains('Webtools Page Feedback Form settings');
    $this->assertSession()->checkboxNotChecked('Enabled');
    $this->assertSession()->pageTextContains('Check this box if you would like to enable the Page feedback form on this site.');
    $this->assertSession()->fieldValueEquals('Form ID', '');
    $this->assertFalse($this->assertSession()->elementExists('css', 'input#edit-feedback-form-id')->hasAttribute('required'));
    $this->assertSession()->pageTextContains('Provide your webtools form ID.');
    // Configure the form.
    $page = $this->getSession()->getPage();
    $page->checkField('Enabled');
    $this->assertSession()->elementAttributeContains('css', 'input#edit-feedback-form-id', 'required', 'required');
    $page->fillField('Form ID', '1234');
    $page->pressButton('Save configuration');
    // Assert values are correctly saved.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->checkboxChecked('Enabled');
    $this->assertSession()->fieldValueEquals('Form ID', '1234');
    $page_feedback_config = $this->config('oe_webtools_page_feedback.settings');
    $this->assertEquals(TRUE, $page_feedback_config->get('enabled'));
    $this->assertEquals('1234', $page_feedback_config->get('feedback_form_id'));

    // Assert the block is rendered only on node pages following the interface
    // language.
    $this->drupalGet('/node/1');
    $this->assertSession()->pageTextContains('Page node');
    $this->assertSession()->responseContains('<script type="application/json" data-process="true">{"service":"dff","id":1234,"lang":"en"}</script>');
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains('<script type="application/json" data-process="true">{"service":"dff","id":1234,"lang":"en"}</script>');
    $this->drupalGet('/pt/node/1');
    $this->assertSession()->responseContains('<script type="application/json" data-process="true">{"service":"dff","id":1234,"lang":"pt"}</script>');

    // Disable the block and assert the block is not rendered and the cache was
    // properly invalidated.
    $this->drupalLogin($user);
    $this->drupalGet('/admin/config/system/oe_webtools_page_feedback');
    $page->uncheckField('Enabled');
    $this->assertFalse($this->assertSession()->elementExists('css', 'input#edit-feedback-form-id')->hasAttribute('required'));
    $page->pressButton('Save configuration');
    $this->drupalLogout();
    $this->drupalGet('/node/1');
    $this->assertSession()->pageTextContains('Page node');
    $this->assertSession()->responseNotContains('<script type="application/json" data-process="true">{"service":"dff","id":1234,"lang":"en"}</script>');
  }

}
