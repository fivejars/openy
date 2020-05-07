<?php

namespace Drupal\openy_activity_finder\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Config;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\openy_activity_finder\ActivityFinderBackendPluginBase;
use Drupal\openy_activity_finder\ActivityFinderBackendPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PEF Programs' block.
 *
 * @Block(
 *   id = "activity_finder_search_block",
 *   admin_label = @Translation("Activity Finder Search Block"),
 *   category = @Translation("Paragraph Blocks")
 * )
 */
class ActivityFinderSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The 'openy_activity_finder.settings' config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The Activity Finder backend plugin.
   *
   * @var \Drupal\openy_activity_finder\ActivityFinderBackendInterface
   */
  protected $backend;

  /**
   * ActivityFinderSearchBlock constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\Config $config
   *   The 'openy_activity_finder.settings' config.
   * @param \Drupal\openy_activity_finder\ActivityFinderBackendPluginManager $af_backend_plugin_manager
   *   Activity Finder backend plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Config $config,
    ActivityFinderBackendPluginManager $af_backend_plugin_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config;

    $backend_plugin_id = $this->config->get('backend');
    $this->backend = $af_backend_plugin_manager->createInstance($backend_plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('openy_activity_finder.settings'),
      $container->get('plugin.manager.activity_finder.backend')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'openy_activity_finder_program_search_page',
      '#locations' => $this->backend->getLocations(),
      '#categories' => $this->backend->getCategories(),
      '#categories_type' => $this->backend->getCategoriesType(),
      '#ages' => $this->backend->getAges(),
      '#days' => $this->backend->getDaysOfWeek(),
      '#expanderSectionsConfig' => $this->config->getRawData(),
      '#is_search_box_disabled' => $this->config->get('disable_search_box'),
      '#is_spots_available_disabled' => $this->config->get('disable_spots_available'),
      '#sort_options' => $this->backend->getSortOptions(),
      '#attached' => [
        'drupalSettings' => [
          'activityFinder' => [
            'is_search_box_disabled' => $this->config->get('disable_search_box'),
            'is_spots_available_disabled' => $this->config->get('disable_spots_available'),
          ],
        ],
      ],
      '#cache' => [
        'tags' => $this->getCacheTags(),
        'contexts' => $this->getCacheContexts(),
        'max-age' => $this->getCacheMaxAge(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [ActivityFinderBackendPluginBase::ACTIVITY_FINDER_CACHE_TAG]);
  }

}
