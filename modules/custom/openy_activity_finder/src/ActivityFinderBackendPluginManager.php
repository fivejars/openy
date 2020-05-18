<?php

namespace Drupal\openy_activity_finder;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages Activity Finder backend plugins.
 */
class ActivityFinderBackendPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ActivityFinder/backend',
      $namespaces,
      $module_handler,
      'Drupal\openy_activity_finder\ActivityFinderBackendInterface',
      'Drupal\openy_activity_finder\Annotation\ActivityFinderBackend'
    );

    $this->alterInfo('activity_finder_backend_info');
    $this->setCacheBackend($cache_backend, 'activity_finder_backend');
  }

}
