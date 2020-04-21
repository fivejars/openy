<?php

namespace Drupal\openy_node_blog;

use Drupal\Core\Entity\EntityInterface;
use Drupal\openy_node_alert\Service\AlertBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides an alert builder for landing pages.
 */
class BlogAlertBuilder implements AlertBuilderInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs the LandingPageAlertBuilder.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function build(EntityInterface $node) {
    if ($node->bundle() != 'blog') {
      return;
    }
    if ($node->field_blog_location->isEmpty()) {
      return;
    }
    $location_ids = array_column($node->field_blog_location->getValue(), 'target_id');
    if (empty($location_ids)) {
      return;
    }
    $alerts_entities = $this->nodeStorage->loadByProperties([
      'type' => 'alert',
      'field_alert_location' => $location_ids,
      'status' => 1,
    ]);
    return array_keys($alerts_entities);
  }

}
