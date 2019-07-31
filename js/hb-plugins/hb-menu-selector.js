/**
 * @file
 * Location finder extension with Home Branch logic.
 */

(function ($, Drupal, drupalSettings) {

  "use strict";

  /**
   * Add plugin, that related to Location Finder.
   */
  Drupal.homeBranch.plugins.push({
    name: 'hb-menu-selector',
    attach: (context) => {
      // Attach plugin instance to header menu item.
      // @see openy_home_branch/js/hb-plugin-base.js
      $('.hb-menu-selector', context).hbPlugin({
        selector: '.hb-menu-selector',
        event: 'click',
        element: null,
        menuSelector: drupalSettings.home_branch.hb_menu_selector.menuSelector,
        init: function () {
          if (!this.element) {
            return;
          }
          let selected = Drupal.homeBranch.getValue('id');
          let locations = Drupal.homeBranch.getLocations();
          if (selected) {
            this.element.text(locations[selected]);
          }
          else {
            this.element.text('My home branch');
          }
        },
        onChange: function (event, el) {
          // Show HB locations modal.
          Drupal.homeBranch.showModal();
        },
        addMarkup: function (context) {
          let menu = $(this.menuSelector);
          menu.prepend('<li><a class="hb-menu-selector" href="#">My home branch</a></li>');
          // Save created element in plugin.
          this.element = $(this.selector, menu);
        },
      });
    },
  });

})(jQuery, Drupal, drupalSettings);
