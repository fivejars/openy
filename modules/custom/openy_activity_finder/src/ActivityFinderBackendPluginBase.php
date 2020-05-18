<?php

namespace Drupal\openy_activity_finder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base implementation for Activity Finder backend plugins.
 */
abstract class ActivityFinderBackendPluginBase extends PluginBase implements ActivityFinderBackendInterface {

  // Cache ID for locations info.
  const ACTIVITY_FINDER_CACHE_TAG = 'openy_activity_finder:default';

  /**
   * Activity Finder configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Site's default timezone.
   *
   * @var string
   */
  protected $timezone;

  /**
   * Constructs Activity Finder Backend PluginBase plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory->get('openy_activity_finder.settings');
    $this->timezone = new \DateTimeZone($config_factory->get('system.date')->get('timezone')['default']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * Run Programs search.
   *
   * @param $parameters
   *   GET parameters for the search.
   * @param $log_id
   *   Id of the Search Log needed for tracking Register / Details actions.
   */
  abstract public function runProgramSearch($parameters, $log_id);

  /**
   * Get list of all locations for filters.
   */
  abstract public function getLocations();


  /**
   * Get ages from configuration.
   */
  public function getAges() {
    $ages = [];

    $ages_config = $this->config->get('ages');
    foreach (explode("\n", $ages_config) as $row) {
      $row = trim($row);
      list($months, $label) = explode(',', $row);
      $ages[] = [
        'label' => $label,
        'value' => $months,
      ];
    }

    return $ages;
  }

  public function getCategoriesType() {
    return 'multiple';
  }

}
