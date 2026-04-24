(() => {
  const POLL_INTERVAL = 5000;
  const API_URL = '/CampusConnect/api/notifications.php';

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  async function requestJson(url, options) {
    const response = await fetch(url, options);
    if (!response.ok) {
      throw new Error(`Request failed with status ${response.status}`);
    }
    return response.json();
  }

  function initNotificationUi(root) {
    const notificationTrigger = root.querySelector('[data-notification-trigger]');
    const notificationMenu = root.querySelector('[data-notification-menu]');
    const notificationList = root.querySelector('[data-notification-list]');
    const notificationCount = root.querySelector('[data-notification-count]');
    const profileTrigger = root.querySelector('[data-profile-trigger]');
    const profileMenu = root.querySelector('[data-profile-menu]');
    const markAllButton = root.querySelector('[data-notification-mark-all]');

    if (!notificationTrigger || !notificationMenu || !notificationList) {
      return;
    }

    function setMenuState(menu, open) {
      if (!menu) return;
      menu.classList.toggle('open', open);
      menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function closeMenus() {
      setMenuState(notificationMenu, false);
      setMenuState(profileMenu, false);
    }

    function updateBadge(count) {
      if (!notificationCount) return;
      const unread = Number(count || 0);
      notificationCount.textContent = String(Math.min(unread, 99));
      notificationCount.style.display = unread > 0 ? 'grid' : 'none';
    }

    function resolveNotificationLink(message) {
      const text = String(message || '').toLowerCase();
      if (text.includes('interview')) return '/CampusConnect/company/index.php#interviewInsights';
      if (text.includes('application status')) return '/CampusConnect/student/applications.php';
      if (text.includes('application submitted')) return '/CampusConnect/student/applications.php';
      if (text.includes('new application')) return '/CampusConnect/company/index.php#pipeline';
      if (text.includes('job submitted')) return '/CampusConnect/admin/dashboard.php';
      if (text.includes('job') && text.includes('approved')) return '/CampusConnect/company/index.php';
      if (text.includes('job') && text.includes('rejected')) return '/CampusConnect/company/index.php';
      if (text.includes('job') && text.includes('removed')) return '/CampusConnect/company/index.php';
      if (text.includes('account has been approved')) return '/CampusConnect/auth/login.php';
      if (text.includes('account has been rejected')) return '/CampusConnect/auth/login.php';
      return '/CampusConnect/';
    }

    async function markAsRead(id) {
      await requestJson(API_URL, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) })
      });
    }

    async function markAllAsRead() {
      await requestJson(API_URL, { method: 'POST' });
    }

    function attachNotificationActions() {
      notificationList.querySelectorAll('[data-notification-id]').forEach((item) => {
        item.addEventListener('click', async () => {
          const id = Number(item.getAttribute('data-notification-id') || 0);
          const href = item.getAttribute('data-notification-link') || '';
          if (!id) return;

          try {
            await markAsRead(id);
            if (href) {
              window.location.href = href;
              return;
            }
            await fetchNotifications();
          } catch (_) {
            notificationList.insertAdjacentHTML(
              'afterbegin',
              '<div class="notify-error">Could not update that notification.</div>'
            );
          }
        });
      });
    }

    function renderNotifications(items, count) {
      updateBadge(count);

      if (!Array.isArray(items) || items.length === 0) {
        notificationList.innerHTML = '<div class="notify-empty">No notifications right now.</div>';
      } else {
        notificationList.innerHTML = items.map((notification) => {
          const isRead = Boolean(notification.is_read);
          const link = resolveNotificationLink(notification.message || '');
          return `
            <button
              type="button"
              class="notify-item${isRead ? '' : ' unread'}"
              data-notification-id="${Number(notification.id || 0)}"
              data-notification-link="${escapeHtml(link)}"
              title="Open related page"
            >
              <span class="notify-message">${escapeHtml(notification.message || '')}</span>
            </button>
          `;
        }).join('');
      }

      attachNotificationActions();
    }

    async function fetchNotifications() {
      try {
        const data = await requestJson(API_URL, { cache: 'no-store' });
        renderNotifications(data.notifications || [], data.count || 0);
      } catch (_) {
        notificationList.innerHTML = '<div class="notify-error">Could not load notifications.</div>';
      }
    }

    notificationTrigger.addEventListener('click', (event) => {
      event.preventDefault();
      const willOpen = !notificationMenu.classList.contains('open');
      closeMenus();
      setMenuState(notificationMenu, willOpen);
    });

    if (profileTrigger && profileMenu) {
      profileTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        const willOpen = !profileMenu.classList.contains('open');
        closeMenus();
        setMenuState(profileMenu, willOpen);
      });
    }

    if (markAllButton) {
      markAllButton.addEventListener('click', async () => {
        try {
          await markAllAsRead();
          await fetchNotifications();
        } catch (_) {
          notificationList.insertAdjacentHTML(
            'afterbegin',
            '<div class="notify-error">Could not mark notifications as read.</div>'
          );
        }
      });
    }

    document.addEventListener('click', (event) => {
      const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
      const clickedInside = path.includes(root);
      if (!clickedInside) {
        closeMenus();
      }
    });

    fetchNotifications();
    window.setInterval(fetchNotifications, POLL_INTERVAL);
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-notification-root]').forEach(initNotificationUi);
  });
})();
