<?php

namespace Drupal\openy_activity_finder;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Activity Finder backend plugins.
 */
interface ActivityFinderBackendInterface extends PluginInspectionInterface {

  /**
   * Run Programs search.
   *
   * @param $parameters
   *   GET parameters for the search.
   * @param $log_id
   *   Id of the Search Log needed for tracking Register / Details actions.
   */
  public function runProgramSearch($parameters, $log_id);

  /**
   * Get list of all locations for filters.
   */
  public function getLocations();

  /**
   * Get list of all sort options.
   */
  public function getSortOptions();

  /**
   * Get more info for programs.
   *
   * @param $request
   *   A request object.
   *
   * @return mixed
   */
  public function getProgramsMoreInfo($request);
}
