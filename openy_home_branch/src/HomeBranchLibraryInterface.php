<?php

namespace Drupal\openy_home_branch;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the common interface for all HomeBranchLibrary classes.
 *
 * @see \Drupal\openy_home_branch\HomeBranchLibraryManager
 * @see \Drupal\openy_home_branch\Annotation\HomeBranchLibrary
 * @see plugin_api
 */
interface HomeBranchLibraryInterface extends PluginInspectionInterface {

  /**
   * Get HomeBranchLibrary plugin id.
   */
  public function getId();

  /**
   * Get HomeBranchLibrary plugin title.
   */
  public function getTitle();

  /**
   * Get HomeBranchLibrary plugin entity machine name.
   */
  public function getEntityName();

  /**
   * Get HomeBranchLibrary plugin rules for attaching to entity.
   *
   * @var array $variables
   *  An array of elements to display in view mode.
   *  See template_preprocess_{entity_type}.
   *
   * @return bool
   *   TRUE if allowed.
   */
  public function isAllowedForAttaching($variables);

  /**
   * Get Library name for attaching to entity.
   */
  public function getLibrary();

}
