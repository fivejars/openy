<?php

namespace Drupal\openy_activity_finder\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\openy_activity_finder\ActivityFinderBackendPluginBase;
use Drupal\openy_activity_finder\ActivityFinderBackendPluginManager;
use Drupal\openy_activity_finder\Entity\ProgramSearchLog;
use Drupal\openy_activity_finder\Entity\ProgramSearchCheckLog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * {@inheritdoc}
 */
class ActivityFinderController extends ControllerBase {

  // Cache queries for 5 minutes.
  const CACHE_LIFETIME = 300;

  /**
   * @var \Drupal\openy_activity_finder\ActivityFinderBackendInterface
   */
  protected $backend;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Creates a new ActivityFinderController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\openy_activity_finder\ActivityFinderBackendPluginManager $af_backend_plugin_manager
   *   Activity Finder backend plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cacheBackend, TimeInterface $time, ActivityFinderBackendPluginManager $af_backend_plugin_manager) {
    $this->cacheBackend = $cacheBackend;
    $this->time = $time;

    $plugin_id = $config_factory->get('openy_activity_finder.settings')->get('backend');
    $this->backend = $af_backend_plugin_manager->createInstance($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache.default'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.activity_finder.backend')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getData(Request $request) {
    $ip = $request->getClientIp();
    $user_agent = $request->headers->get('User-Agent', '');
    $hash_ip_agent = substr($user_agent, 0, 50) . '   ' . $ip;
    $record = [
      'hash_ip_agent' => $hash_ip_agent,
      'location' => $request->get('locations'),
      'keyword' => $request->get('keywords'),
      'category' => $request->get('categories'),
      'page' => $request->get('page'),
      'day' => $request->get('days'),
      'age' => $request->get('ages'),
      'sort' => $request->get('sort'),
    ];
    $record['hash'] = md5(json_encode($record));

    $record_cache_key = $record;
    unset($record_cache_key['hash']);
    unset($record_cache_key['hash_ip_agent']);
    $cid = md5(json_encode($record_cache_key));

    $log = ProgramSearchLog::create($record);
    $log->save();

    $parameters = $request->query->all();

    foreach ($parameters as &$value) {
      $value = urldecode($value);
    }

    $data = NULL;
    if ($cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = $this->backend->runProgramSearch($parameters, $log->id());

      /* @var $expanderSectionsConfig \Drupal\Core\Config\Config */
      $expanderSectionsConfig = $this->config('openy_activity_finder.settings');
      $data['expanderSectionsConfig'] = $expanderSectionsConfig->getRawData();

      // Allow other modules to alter the search results.
      $this->moduleHandler()->alter('activity_finder_program_search_results', $data);

      // Cache for 5 minutes.
      $expire = $this->time->getRequestTime() + self::CACHE_LIFETIME;
      $this->cacheBackend->set($cid, $data, $expire, [ActivityFinderBackendPluginBase::ACTIVITY_FINDER_CACHE_TAG]);
    }

    return new JsonResponse($data);
  }

  /**
   * Redirect to register.
   */
  public function redirectToRegister(Request $request, $log) {
    $details = $request->get('details');
    $url = $request->get('url');

    if (!empty($details) && !empty($log)) {
      $details_log = ProgramSearchCheckLog::create([
        'details' => $details,
        'log_id' => $log,
        'type' => ProgramSearchCheckLog::TYPE_REGISTER,
      ]);
      $details_log->save();
    }

    if (empty($url)) {
      throw new NotFoundHttpException();
    }

    return new TrustedRedirectResponse($url, 301);
  }

  /**
   * Callback to retrieve programs full information.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function ajaxProgramsMoreInfo(Request $request) {
    $parameters = $request->query->all();
    $cid = md5(json_encode($parameters));
    $data = NULL;
    if ($cache = $this->cacheBackend->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = $this->backend->getProgramsMoreInfo($request);

      // Allow other modules to alter the search results.
      $this->moduleHandler()->alter('activity_finder_program_more_info', $data);

      // Cache for 5 minutes.
      $expire = $this->time->getRequestTime() + self::CACHE_LIFETIME;
      $this->cacheBackend->set($cid, $data, $expire, [ActivityFinderBackendPluginBase::ACTIVITY_FINDER_CACHE_TAG]);
    }

    return new JsonResponse($data);
  }

}
