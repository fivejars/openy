<?php

namespace Drupal\openy_activity_finder\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Activity Finder backend annotation object.
 *
 * @Annotation
 */
class ActivityFinderBackend extends Plugin {

  /**
   * The backend plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the backend plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The backend description.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
