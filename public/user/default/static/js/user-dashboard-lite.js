(function () {
  var sidebar = document.getElementById('dashboardSidebar');
  var toggle = document.querySelector('[data-sidebar-toggle]');
  var notice = document.getElementById('dashboardNotice');
  var noticeClose = document.querySelector('[data-close-notice]');

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    document.body.classList.remove('sidebar-open');
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      document.body.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
    });

    document.addEventListener('click', function (event) {
      if (!sidebar.classList.contains('open')) return;
      if (sidebar.contains(event.target) || toggle.contains(event.target)) return;
      closeSidebar();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeSidebar();
      }
    });
  }

  if (notice && noticeClose) {
    noticeClose.addEventListener('click', function () {
      notice.remove();
    });
  }
})();
