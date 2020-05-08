<?php

namespace Drupal\openy_activity_finder\Plugin\ActivityFinderBackend;

use DateTimeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\openy_activity_finder\ActivityFinderBackendPluginBase;
use Drupal\openy_activity_finder\Annotation\ActivityFinderBackend;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Activity Finder Solr backend plugin.
 *
 * @ActivityFinderBackend(
 *   id = "openy_activity_finder.solr_backend",
 *   label = @Translation("Activity Finder Solr backend"),
 *   description = @Translation("Store data in the Solr server")
 * )
 */
class OpenyActivityFinderSolrBackend extends ActivityFinderBackendPluginBase {

  // 1 day for cache.
  const CACHE_TTL = 86400;

  // Number of results to retrieve per page.
  const TOTAL_RESULTS_PER_PAGE = 25;

  /**
   * Cache default.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Time manager needed for calculating expire for caches.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->setCacheBackend($container->get('cache.default'));
    $instance->setDatabase($container->get('database'));
    $instance->setEntityQuery($container->get('entity.query'));
    $instance->setEntityTypeManager($container->get('entity_type.manager'));
    $instance->setDateFormatter($container->get('date.formatter'));
    $instance->setTimeService($container->get('datetime.time'));
    $instance->setLogger($container->get('logger.channel.default'));
    $instance->setModuleHandler($container->get('module_handler'));

    return $instance;
  }

  /**
   * Sets the Cache backend to use for this plugin.
   *
   * @param CacheBackendInterface $cache_backend
   *   The Cache default to use for this plugin.
   *
   * @return $this
   */
  public function setCacheBackend(CacheBackendInterface $cache_backend) {
    $this->cache = $cache_backend;
    return $this;
  }

  /**
   * Returns the module handler to use for this plugin.
   *
   * @return CacheBackendInterface
   *   The module handler.
   */
  public function getCacheBackend() {
    return $this->cache;
  }

  /**
   * Sets the the database connection to use for this plugin.
   *
   * @param Connection $database
   *   The database connection to use for this plugin.
   *
   * @return $this
   */
  public function setDatabase(Connection $database) {
    $this->database = $database;
    return $this;
  }

  /**
   * Returns the database connection to use for this plugin.
   *
   * @return Connection
   *   The module handler.
   */
  public function getDatabase() {
    return $this->database;
  }

  /**
   * Sets the module handler to use for this plugin.
   *
   * @param QueryFactory $entity_query
   *   The module handler to use for this plugin.
   *
   * @return $this
   */
  public function setEntityQuery(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
    return $this;
  }

  /**
   * Returns the module handler to use for this plugin.
   *
   * @return QueryFactory
   *   The module handler.
   */
  public function getEntityQuery() {
    return $this->entityQuery;
  }

  /**
   * Retrieves the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Retrieves the date formatter.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter.
   */
  public function getDateFormatter() {
    return $this->dateFormatter;
  }

  /**
   * Sets the date formatter.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The new date formatter.
   *
   * @return $this
   */
  public function setDateFormatter(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
    return $this;
  }

  /**
   * Retrieves the time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The time service.
   */
  public function getTimeService() {
    return $this->time;
  }

  /**
   * Sets the time service.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The new time service.
   *
   * @return $this
   */
  public function setTimeService(TimeInterface $time_service) {
    $this->time = $time_service;
    return $this;
  }

  /**
   * Retrieves the logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  public function getLogger() {
    return $this->loggerChannel;
  }

  /**
   * Sets the logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The new logger.
   *
   * @return $this
   */
  public function setLogger(LoggerInterface $logger) {
    $this->loggerChannel = $logger;
    return $this;
  }

  /**
   * Returns the module handler to use for this plugin.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler ?: \Drupal::moduleHandler();
  }

  /**
   * Sets the module handler to use for this plugin.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to use for this plugin.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function runProgramSearch($parameters, $log_id) {
    // Make a request to Search API.
    $results = $this->doSearchRequest($parameters);

    // Get results count.
    $data['count'] = $results->getResultCount();

    // Get facets and enrich, sort data, add static filters.
    $data['facets'] = $this->getFacets($results);

    // Set pager as current page number.
    $data['pager'] = isset($parameters['page']) && $data['count'] > self::TOTAL_RESULTS_PER_PAGE ? $parameters['page'] : 0;

    // Get pager structure.
    $data['pager_info'] = $this->getPages($data['count']);

    // Process results.
    $data['table'] = $this->processResults($results, $log_id);

    $locations = $this->getLocations();
    foreach ($locations as $key => $group) {
      $locations[$key]['count'] = 0;
      foreach ($group['value'] as $location) {
        foreach ($data['facets']['locations'] as $fl) {
          if ($fl['id'] == $location['value']) {
            $locations[$key]['count'] += $fl['count'];
          }
        }
      }
    }
    $data['groupedLocations'] = $locations;

    $data['sort'] = isset($parameters['sort']) ? $parameters['sort'] : 'title__ASC';

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function doSearchRequest($parameters) {
    $index_id = $this->config->get('index') ? $this->config->get('index') : 'default';
    $index = Index::load($index_id);
    $query = $index->query();
    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')->createInstance('direct');
    $query->getParseMode()->setConjunction('OR');
    $query->setParseMode($parse_mode);
    $keys = !empty($parameters['keywords']) ? $parameters['keywords'] : '';
    $query->keys($keys);
    $query->setFulltextFields(['title']);
    $query->addCondition('status', 1);

    if (!empty($parameters['ages'])) {
      $ages = explode(',', rawurldecode($parameters['ages']));
      $query->addCondition('af_ages_min_max', $ages, 'IN');
    }

    if (!empty($parameters['days'])) {
      $days_ids = explode(',', rawurldecode($parameters['days']));
      // Convert ids to search value.
      $days_info = $this->getDaysOfWeek();
      foreach ($days_info as $i) {
        if (in_array($i['value'], $days_ids)) {
          $days[] = $i['search_value'];
        }
      }
      $query->addCondition('field_session_time_days', $days, 'IN');
    }

    if (!empty($parameters['times'])) {
      $times = explode(',', rawurldecode($parameters['times']));
      $query->addCondition('af_parts_of_day', $times, 'IN');
    }

    if (!empty($parameters['program_types'])) {
      $program_types = explode(',', rawurldecode($parameters['program_types']));

    }

    $category_program_info = $this->getCategoryProgramInfo();
    if (!empty($parameters['categories'])) {
      $categories_nids = explode(',', rawurldecode($parameters['categories']));
      // Map nids to titles.
      foreach ($categories_nids as $nid) {
        $categories[] = !empty($category_program_info[$nid]['title']) ? $category_program_info[$nid]['title'] : '';

        // Subcategories with the same title could belong to different categories.
        // Additional category filter is essential.
        if (!empty($category_program_info[$nid]['program']['title'])) {
          $program_types[] = $category_program_info[$nid]['program']['title'];
        }

      }

      if ($categories) {
        $query->addCondition('field_activity_category', $categories, 'IN');
      }

    }
    // Ignore sessions which don't have referenced activity.
    else {
      $query->addCondition('field_activity_category', NULL, '<>');
    }

    if (!empty($program_types)) {
      $query->addCondition('field_category_program', $program_types, 'IN');
    }

    // Ensure to exclude categories.
    $exclude_nids = [];
    if (!empty($parameters['exclude'])) {
      $exclude_nids = explode(',', $parameters['exclude']);
    }
    $exclude_nids_config = explode(',', $this->config->get('exclude'));
    $exclude_nids = array_merge($exclude_nids, $exclude_nids_config);
    $exclude_categories = [];
    foreach ($exclude_nids as $nid) {
      if (empty($category_program_info[$nid]['title'])) {
        continue;
      }
      $exclude_categories[] = $category_program_info[$nid]['title'];
    }
    if ($exclude_categories) {
      $query->addCondition('field_activity_category', $exclude_categories, 'NOT IN');
    }

    if (!empty($parameters['locations'])) {
      $locations_nids = explode(',', rawurldecode($parameters['locations']));
      // Map nids to titles.
      $locations_info = $this->getLocationsInfo();
      foreach ($locations_info as $key => $item) {
        if (in_array($item['nid'], $locations_nids)) {
          $locations[] = $key;
        }
      }
      $query->addCondition('field_session_location', $locations, 'IN');
    }

    $query->range(0, self::TOTAL_RESULTS_PER_PAGE);
    // Use pager if parameter has been provided.
    if (isset($parameters['page'])) {
      $offset = self::TOTAL_RESULTS_PER_PAGE * $parameters['page'] - self::TOTAL_RESULTS_PER_PAGE;
      $query->range($offset, self::TOTAL_RESULTS_PER_PAGE);
    }
    // Set up default sort as relevance and expose if manual sort has been provided.
    //$query->sort('search_api_relevance', 'DESC');
    if (empty($parameters['sort'])) {
      $query->sort('title', 'ASC');
    }
    else {
      $sort = explode('__', $parameters['sort']);
      $sort_by = $sort[0];
      $sort_mode = $sort[1];
      $query->sort($sort_by, $sort_mode);
    }

    $server = $index->getServerInstance();
    if ($server->supportsFeature('search_api_facets')) {
      $filters = $this->getFilters();
      $query->setOption('search_api_facets', $filters);
    }
    else {
      $this->getLogger()->info(t('Search server doesn\'t support facets (filters). '));
    }
    $query->addTag('af_search');
    $results = $query->execute();

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function processResults($results, $log_id) {
    $data = [];
    $locations_info = $this->getLocationsInfo();
    /** @var \Drupal\search_api\Item\Item $result_item */
    foreach ($results->getResultItems() as $result_item) {
      try {
        $entity = $result_item->getOriginalObject()->getValue();
        if (!$entity) {
          $this->getLogger()->error('Failed to load original object ' . $this->itemId);
          continue;
        }
      }
      catch (SearchApiException $e) {
        // If we couldn't load the object, just log an error and fail
        // silently to set the values.
        $this->getLogger()->error($e);
        continue;
      }
      $fields = $result_item->getFields();
      $dates = $entity->field_session_time->referencedEntities();
      $schedule_items = [];
      foreach ($dates as $date) {
        $_period = $date->field_session_time_date->getValue()[0];
        $_from = DrupalDateTime::createFromTimestamp(strtotime($_period['value'] . 'Z'), $this->timezone);
        $_to = DrupalDateTime::createFromTimestamp(strtotime($_period['end_value'] . 'Z'), $this->timezone);
        $days = [];
        foreach ($date->field_session_time_days->getValue() as $time_days) {
          $days[] = substr(ucfirst($time_days['value']), 0, 3);
        }
        $schedule_items[] = [
          'days' => implode(', ', $days),
          'time' => $_from->format('g:ia') . '-' . $_to->format('g:ia'),
        ];
        $full_dates = $_from->format('M d') . '-' . $_to->format('M d');
        // It is necessary to calculate not the number of full weeks,
        // but the number of sessions that takes place in the specified period.
        // I.e. we calculate the amount of the last day of the week
        // with the session (for example, Friday) in the period.
        $weeks = $this->countDaysByName(end($days), $_from->getPhpDateTime(), $_to->getPhpDateTime());
      }

      $availability_status = 'closed';
      if (!$entity->field_session_online->isEmpty()) {
        $availability_status = $entity->field_session_online->value ? 'open' : 'closed';
      }

      $availability_note = '';
      if ($availability_status == 'closed') {
        $availability_note = t('Registration closed')->__toString();
      }

      $class = $entity->field_session_class->entity;
      $activity = $class->field_class_activity->entity;
      $sub_category = $activity->field_activity_category->entity;
      $learn_more = '';
      if ($sub_category && $sub_category->hasField('field_learn_more')) {
        $link = $sub_category->field_learn_more->getValue();
        if (!empty($link[0]['uri'])) {
          $learn_more_view = $sub_category->field_learn_more->view();
          $learn_more = render($learn_more_view)->__toString();
        }
      }

      $price = [];
      if (!empty($entity->field_session_mbr_price->value)) {
        $price[] = '$' . $entity->field_session_mbr_price->value . '(member)';
      }
      if (!empty($entity->field_session_nmbr_price->value)) {
        $price[] = '$' . $entity->field_session_nmbr_price->value . '(non-member)';
      }

      $activity_type = '';
      if (!empty($entity->field_activity_type->value)) {
        $activity_type = $entity->field_activity_type->value;
      }

      $atc_info = [];
      if ($activity_type == 'group') {
        // Create "Add to calendar" info for "group" activity types.
        // Example of calendar format 2018-08-21 14:15:00.
        $atc_info['time_start_calendar'] = DrupalDateTime::createFromTimestamp(strtotime($dates[0]->field_session_time_date->getValue()[0]['value'] . 'Z'), $this->timezone)->format('Y-m-d H:i:s');
        $atc_info['time_end_calendar'] = DrupalDateTime::createFromTimestamp(strtotime($dates[0]->field_session_time_date->getValue()[0]['end_value'] . 'Z'), $this->timezone)->format('Y-m-d H:i:s');
        $atc_info['timezone'] = drupal_get_user_timezone();
      }

      $item_data = [
        'nid' => $entity->id(),
        'availability_note' => $availability_note,
        'availability_status' => $availability_status,
        'activity_type' => $activity_type,
        'dates' => isset($full_dates) ? $full_dates : '',
        'weeks' => isset($weeks) ? $weeks : '',
        'schedule' => $schedule_items,
        'days' => isset($schedule_items[0]['days']) ? $schedule_items[0]['days'] : '',
        'times' => isset($schedule_items[0]['time']) ? $schedule_items[0]['time'] : '',
        'location' => $fields['field_session_location']->getValues()[0],
        'location_id' => $locations_info[$fields['field_session_location']->getValues()[0]]['nid'],
        'location_info' => $locations_info[$fields['field_session_location']->getValues()[0]],
        'log_id' => $log_id,
        'name' => $fields['title']->getValues()[0]->getText(),
        'price' => implode(', ', $price),
        'link' => Url::fromRoute('openy_activity_finder.register_redirect', [
            'log' => $log_id,
          ],
          ['query' => [
            'url' => $entity->field_session_reg_link->uri,
          ],
        ])->toString(),
        'description' => html_entity_decode(strip_tags(text_summary($entity->field_session_description->value, $entity->field_session_description->format, 600))),
        'ages' => $this->convertData([$entity->field_session_min_age->value, isset($entity->field_session_max_age->value) ? $entity->field_session_max_age->value : '0']),
        'gender' => !empty($entity->field_session_gender->value) ? $entity->field_session_gender->value : '',
        // We keep empty variables in order to have the same structure with other backends (e.g. Daxko) for avoiding unexpected errors.
        'program_id' => $sub_category->id(),
        'offering_id' => '',
        'info' => [],
        'location_name' => '',
        'location_address' => '',
        'location_phone' => '',
        'spots_available' => !empty($entity->field_availability->value) ? $entity->field_availability->value : '',
        'status' => $availability_status,
        'note' => $availability_note,
        'learn_more' => !empty($learn_more) ? $learn_more : '',
        'more_results' => '',
        'more_results_type' => 'program',
        'program_name' => $fields['title']->getValues()[0]->getText(),
        'atc_info' => $atc_info,
      ];

      // Allow other modules to alter the process results.
      $this->getModuleHandler()
        ->alter('activity_finder_program_process_results', $item_data, $entity);

      $data[] = $item_data;
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getFacets($results) {
    $facets = $results->getExtraData('search_api_facets', []);
    $locationsInfo = $this->getLocationsInfo();
    $category_program_info = $this->getCategoryProgramInfo();

    // Add static Age filter.
    $facets['static_age_filter'] = $this->getAges();

    $facets_m = $facets;
    foreach ($facets as $f => $facet) {
      foreach ($facet as $i => $item) {
        if (!empty($item['filter'])) {
          // Remove double quotes.
          $facets_m[$f][$i]['filter'] = str_replace('"', '', $item['filter']);
        }
        if ($f == 'locations') {
          if (!empty($item['filter'])) {
            $facets_m[$f][$i]['id'] = $locationsInfo[str_replace('"', '', $item['filter'])]['nid'];
          }
        }
        if ($f == 'field_activity_category') {
          foreach ($category_program_info as $nid => $info) {
            if ('"' . $info['title'] . '"' == $item['filter']) {
              $facets_m[$f][$i]['id'] = $nid;
            }
          }
        }
        // Pass counters to static ages filter.
        if ($f == 'static_age_filter') {
          foreach ($facets['af_ages_min_max'] as $info) {
            if ('"' . $item['value'] . '"' == $info['filter']) {
              $facets_m[$f][$i]['count'] = $info['count'];
            }
          }
        }
      }
    }
    return $facets_m;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    $filters = [
      'field_session_min_age' => [
        'field' => 'field_session_min_age',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'field_session_max_age' => [
        'field' => 'field_session_max_age',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'field_category_program' => [
        'field' => 'field_category_program',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'field_activity_category' => [
        'field' => 'field_activity_category',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'locations' => [
        'field' => 'field_session_location',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'days_of_week' => [
        'field' => 'field_session_time_days',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'af_parts_of_day' => [
        'field' => 'af_parts_of_day',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
      'af_ages_min_max' => [
        'field' => 'af_ages_min_max',
        'limit' => 0,
        'operator' => 'AND',
        'min_count' => 0,
        'missing' => TRUE,
      ],
    ];
    return $filters;
  }

  /**
   * Get referencing chain for Session -> Program info.
   */
  public function getCategoryProgramInfo() {
    $data = [];
    $cid = 'openy_activity_finder:activity_program_info';
    if ($cache = $this->getCacheBackend()->get($cid)) {
      $data = $cache->data;
    }
    else {
      $nids = $this->getEntityQuery()
        ->get('node')
        ->condition('type', 'program_subcategory')
        ->execute();
      $nids_chunked = array_chunk($nids, 20, TRUE);
      foreach ($nids_chunked as $chunked) {
        $program_subcategories = $this->getEntityTypeManager()->getStorage('node')->loadMultiple($chunked);
        if (!empty($program_subcategories)) {
          foreach ($program_subcategories as $program_subcategory_node) {
            if ($program_node = $program_subcategory_node->field_category_program->entity) {
              $data[$program_subcategory_node->id()] = [
                'title' => $program_subcategory_node->title->value,
                'program' => [
                  'nid' => $program_node->id(),
                  'title' => $program_node->title->value,
                ],
              ];
            }
          }
        }
      }

      $expire = $this->getTimeService()->getRequestTime() + self::CACHE_TTL;
      $this->getCacheBackend()->set($cid, $data, $expire, [self::ACTIVITY_FINDER_CACHE_TAG]);
    }

    return $data;
  }

  /**
   * Get Locations Info.
   */
  public function getLocationsInfo() {
    $data = [];
    $cid = 'openy_activity_finder:locations_info';
    if ($cache = $this->getCacheBackend()->get($cid)) {
      $data = $cache->data;
    }
    else {
      $nids = $this->getEntityQuery()
        ->get('node')
        ->condition('type', ['branch', 'camp', 'facility'], 'IN')
        ->condition('status', 1)
        ->sort('title', 'ASC')
        ->execute();
      $nids_chunked = array_chunk($nids, 20, TRUE);
      foreach ($nids_chunked as $chunked) {
        $locations = $this->getEntityTypeManager()->getStorage('node')->loadMultiple($chunked);
        if (!empty($locations)) {
          foreach ($locations as $location) {
            $address = [];
            if (!empty($location->field_location_address->address_line1))  {
              array_push($address, $location->field_location_address->address_line1);
            }
            if (!empty($location->field_location_address->locality))  {
              array_push($address, $location->field_location_address->locality);
            }
            if (!empty($location->field_location_address->administrative_area))  {
              array_push($address, $location->field_location_address->administrative_area);
            }
            if (!empty($location->field_location_address->postal_code))  {
              array_push($address, $location->field_location_address->postal_code);
            }
            $address = implode(', ', $address);
            $days = [];
            if ($location->hasField('field_branch_hours')) {
              foreach ($location->field_branch_hours as $multi_hours) {
                $sub_hours = $multi_hours->getValue();
                $days = [
                  [
                    0 => "Mon - Fri:",
                    1 => $sub_hours['hours_mon']
                  ],
                  [
                    0 => "Sat - Sun:",
                    1 => $sub_hours['hours_sat']
                  ]
                ];
              }
            }
            $data[$location->title->value] =[
              'type' => $location->bundle(),
              'address' => $address,
              'days' => $days,
              'email' => $location->field_location_email->value,
              'nid' => $location->id(),
              'phone' => $location->field_location_phone->value,
              'title' => $location->title->value
            ];
          }
        }
      }
      $expire = $this->getTimeService()->getRequestTime() + self::CACHE_TTL;
      $this->getCacheBackend()->set($cid, $data, $expire, [self::ACTIVITY_FINDER_CACHE_TAG]);
    }

    return $data;
  }

  public function getCategoriesTopLevel() {
    $categories = [];
    $programInfo = $this->getCategoryProgramInfo();
    $exclude_nids = explode(',', $this->config->get('exclude'));

    foreach ($programInfo as $key => $item) {
      if (in_array($key, $exclude_nids)) {
        continue;
      }
      $categories[$item['program']['nid']] = $item['program']['title'];
    }
    return array_values($categories);
  }

  public function getCategories() {
    $categories = [];
    $programInfo = $this->getCategoryProgramInfo();
    $exclude_nids = explode(',', $this->config->get('exclude'));

    foreach ($programInfo as $key => $item) {
      if (in_array($key, $exclude_nids)) {
        continue;
      }
      $categories[$item['program']['nid']]['value'][] = [
        'value' => $key,
        'label' => $item['title']
      ];
      $categories[$item['program']['nid']]['label'] = $item['program']['title'];
    }
    return array_values($categories);
  }

  /**
   * Get the days of week.
   */
  public function getDaysOfWeek() {
    return [
      [
        'label' => 'Mon',
        'search_value' => 'monday',
        'value' => '1',
      ],
      [
        'label' => 'Tue',
        'search_value' => 'tuesday',
        'value' => '2',
      ],
      [
        'label' => 'Wed',
        'search_value' => 'wednesday',
        'value' => '3',
      ],
      [
        'label' => 'Thu',
        'search_value' => 'thursday',
        'value' => '4',
      ],
      [
        'label' => 'Fri',
        'search_value' => 'friday',
        'value' => '5',
      ],
      [
        'label' => 'Sat',
        'search_value' => 'saturday',
        'value' => '6',
      ],
      [
        'label' => 'Sun',
        'search_value' => 'sunday',
        'value' => '7',
      ],
    ];
  }

  /**
   * @inheritdoc
   */
  public function getLocations() {
    $locations = [];
    $locationsInfo = $this->getLocationsInfo();
    foreach ($locationsInfo as $key => $item) {
      $locations[$item['type']]['value'][] = [
        'value' => $item['nid'],
        'label' => $key
      ];
      $locations[$item['type']]['label'] = ucfirst($item['type']);
    }
    return array_values($locations);
  }

  /**
   * @inheritdoc
   */
  public function getProgramsMoreInfo($request) {
    // Idea is that when we use Solr backend we have all the data
    // available in runProgramSearch() call so this call is not needed
    // meanwhile you can alter search results to set availability_status
    // to be empty so getProgramsMoreInfo call will be triggered and you
    // can alter its behavior. For example if you like to check availability
    // with live call to your CRM.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPages($count) {
    $pages = [];
    // Calculate number of pages.
    $pages_count = $count / $this::TOTAL_RESULTS_PER_PAGE;
    $pages_count = ceil($pages_count);
    $pages['total_pages'] = $pages_count;
    $range = range(1, $pages_count);
    // Make array starts from 1 for better usage.
    array_unshift($range, '');
    unset($range[0]);
    $pages['pages'] = $range;

    return $pages;
  }

  public function getSortOptions() {
    return [
      'title__ASC' => t('Sort by Title (A-Z)'),
      'title__DESC' => t('Sort by Title (Z-A)'),
      'field_session_location__ASC' => t('Sort by Location (A-Z)'),
      'field_session_location__DESC' => t('Sort by Location (Z-A)'),
      'field_session_class__ASC' => t('Sort by Activity (A-Z)'),
      'field_session_class__DESC' => t('Sort by Activity (Z-A)'),
    ];
  }

  /**
   * Date months to years transformation.
   *
   * @param array $ages
   *   Array with min and max age values,
   *
   * @return string
   *   String with month or year.
   */
  public function convertData($ages = []) {
    $ages_y = [];
    for ($i = 0; $i < count($ages); $i++) {
      if ($ages[$i] > 18) {
        if ($ages[$i] % 12) {
          $ages_y[$i] = number_format($ages[$i] / 12, 1, '.', '');
        }
        else {
          $ages_y[$i] = number_format($ages[$i] / 12, 0, '.', '');;
        }
        if (isset($ages[$i + 1]) && $ages[$i + 1] == 0) {
          $ages_y[$i] .= t('+ years');
        }
        if (isset($ages[$i + 1]) && $ages[$i + 1] > 18 || !isset($ages[$i + 1])) {
          if ($i % 2 || (!$ages[$i + 1]) && !($i % 2)) {
            $ages_y[$i] .= t(' years');
          }
        }
      }
      else {
        if ($ages[$i] <= 18 && $ages[$i] != 0) {
          $plus = '';
          if (isset($ages[$i + 1]) && $ages[$i + 1] == 0) {
            $plus = ' + ';
          }
          $ages_y[$i] = $ages[$i] . \Drupal::translation()->formatPlural($ages_y[$i], ' month', ' months' . $plus);
        }
        else {
          if ($ages[$i] == 0 && $ages[$i + 1]) {
            $ages_y[$i] = $ages[$i];
          }
        }
      }
    }
    return implode(' - ', $ages_y);
  }

  /**
   * Helper function to calculate weekday quantity in period.
   *
   * @param String $dayName eg 'Mon', 'Tue' etc
   * @param DateTimeInterface $start
   * @param DateTimeInterface $end
   *
   * @return int
   * @throws \Exception
   */
  public function countDaysByName($dayName, DateTimeInterface $start, DateTimeInterface $end) {
    $count = 0;
    $interval = new \DateInterval('P1D');
    $period = new \DatePeriod($start, $interval, $end);

    foreach($period as $day){
      if($day->format('D') === ucfirst(substr($dayName, 0, 3))){
        $count ++;
      }
    }
    return $count;
  }

}
