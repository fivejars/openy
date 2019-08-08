<?php

namespace Drupal\openy_home_branch;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * Defines the base plugin for HomeBranchLibrary classes.
 *
 * @see \Drupal\openy_home_branch\HomeBranchLibraryInterface
 * @see \Drupal\openy_home_branch\Annotation\HomeBranchLibrary
 * @see plugin_api
 */
class HomeBranchLibraryManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/HomeBranchLibrary',
      $namespaces,
      $module_handler,
      'Drupal\openy_home_branch\HomeBranchLibraryInterface',
      'Drupal\openy_home_branch\Annotation\HomeBranchLibrary'
    );

    $this->alterInfo('home_branch_library_info');
    $this->setCacheBackend($cache_backend, 'home_branch_library');
    $this->factory = new ContainerFactory($this->getDiscovery());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsByEntityType($entity) {
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if ($definition['entity'] != $entity) {
        unset($definitions[$id]);
      }
    }
    return $definitions;
  }

  /**
   * Helper function for attaching Drupal Settings required bu plugin.
   *
   * @param array $attached
   *   Attached array from preprocess variables - $variables['#attached'].
   * @param string $plugin_id
   *   Home Branch plugin ID, used as key/identifier in HB settings list.
   * @param array $settings
   *   Plugin settings that used on front-end.
   */
  public static function attachHbLibrarySettings(array &$attached, $plugin_id, array $settings) {
    $parents = ['#attached', 'drupalSettings', 'home_branch', $plugin_id];
    NestedArray::setValue($attached, $parents, $settings, TRUE);
  }

}
