/**
 * Move the navigation's shop selector beside the mobile language selector.
 * Classic and Hummingbird themes use different mobile language placeholder IDs.
 */
(function () {
  'use strict';

  function init() {
    var languageSelector = document.getElementById('_mobile_ps_languageselector')
      || document.getElementById('_mobile_language_selector');
    if (!languageSelector) {
      return;
    }

    document.querySelectorAll('[data-multistoreswitcher-responsive]').forEach(function (switcher) {
      var desktopPosition = document.createComment('multistoreswitcher desktop position');
      switcher.parentNode.insertBefore(desktopPosition, switcher);

      var mobileContainer = document.createElement('div');
      mobileContainer.className = 'multistoreswitcher-mobile col-auto';
      languageSelector.parentNode.insertBefore(mobileContainer, languageSelector);

      var select = switcher.querySelector('.js-multistoreswitcher-select');
      var button = switcher.querySelector('button');
      var menu = switcher.querySelector('.dropdown-menu');
      select.addEventListener('change', function () {
        window.location.assign(select.value);
      });

      var mobile = window.matchMedia('(max-width: 767px)');
      function updatePosition() {
        // A native select stays usable inside the themes' scrolling mobile menus.
        select.classList.toggle('d-none', !mobile.matches);
        button.classList.toggle('d-none', mobile.matches);
        menu.classList.toggle('d-none', mobile.matches);
        menu.classList.remove('show');
        button.classList.remove('show');
        button.setAttribute('aria-expanded', 'false');
        if (mobile.matches) {
          mobileContainer.appendChild(switcher);
        } else {
          desktopPosition.parentNode.insertBefore(switcher, desktopPosition.nextSibling);
        }
      }

      updatePosition();
      if (mobile.addEventListener) {
        mobile.addEventListener('change', updatePosition);
      } else {
        mobile.addListener(updatePosition);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
