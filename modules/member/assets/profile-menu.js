(function () {
  'use strict';

  function closeMemberProfileMenus(exceptMenu) {
    Array.prototype.slice.call(document.querySelectorAll('.member-profile-menu[open]')).forEach(function (menu) {
      if (menu !== exceptMenu) {
        menu.removeAttribute('open');
      }
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    var currentMenu = target && typeof target.closest === 'function'
      ? target.closest('.member-profile-menu')
      : null;
    closeMemberProfileMenus(currentMenu);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeMemberProfileMenus(null);
    }
  });
})();
