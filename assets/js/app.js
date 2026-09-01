/* TubigKo — frontend behaviour.
 * All data now comes from real <form> POSTs to PHP; this file only
 * handles pure client-side UI (nav toggle, search/filter, modals,
 * tabs, table export/print) plus the pre-checkout shopping cart,
 * which is intentionally kept in the browser until the customer
 * submits it on the Payment step (the server then recalculates and
 * persists everything).
 */
(function () {
  "use strict";

  var CART_KEY = "tubigko:cart";

  // ---------------------------------------------------------------
  // Mobile sidebar toggle
  // ---------------------------------------------------------------
  var menuToggle = document.getElementById("menuToggle");
  var sidebar = document.getElementById("sidebar");
  if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", function () {
      sidebar.classList.toggle("is-open");
    });
    document.addEventListener("click", function (e) {
      if (sidebar.classList.contains("is-open") && !sidebar.contains(e.target) && e.target !== menuToggle) {
        sidebar.classList.remove("is-open");
      }
    });
  }

  // ---------------------------------------------------------------
  // Modals: openModal(id) / closeModal(id)
  // ---------------------------------------------------------------
  window.openModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.hidden = false;
  };
  window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.hidden = true;
  };
  document.addEventListener("click", function (e) {
    if (e.target.classList && e.target.classList.contains("modal")) {
      e.target.hidden = true;
    }
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal:not([hidden])").forEach(function (m) { m.hidden = true; });
    }
  });

  // "View" buttons that carry a JSON blob in data-detail render into
  // the shared #detailModal (used on admin customers.php / gallons.php).
  document.querySelectorAll("[data-detail]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var data;
      try { data = JSON.parse(btn.getAttribute("data-detail")); } catch (err) { return; }
      var title = document.getElementById("detailTitle");
      var body = document.getElementById("detailBody");
      if (!body) return;
      if (title && data.__title) title.textContent = data.__title;
      var rows = "";
      Object.keys(data).forEach(function (key) {
        if (key === "__title") return;
        rows += '<div class="cart-line"><span>' + escapeHtml(key) + '</span><strong>' + escapeHtml(String(data[key] ?? "")) + '</strong></div>';
      });
      body.innerHTML = rows || '<p class="empty">No details available.</p>';
      openModal("detailModal");
    });
  });

  // ---------------------------------------------------------------
  // Tabs: [data-tabs] container, [data-tab] buttons, [data-tabpanel]+[data-panel]
  // ---------------------------------------------------------------
  document.querySelectorAll("[data-tabs]").forEach(function (group) {
    var groupName = group.getAttribute("data-tabs");
    var buttons = group.querySelectorAll("[data-tab]");
    var panels = document.querySelectorAll('[data-tabpanel="' + groupName + '"]');
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var target = btn.getAttribute("data-tab");
        buttons.forEach(function (b) { b.classList.toggle("is-active", b === btn); });
        panels.forEach(function (p) { p.hidden = p.getAttribute("data-panel") !== target; });
      });
    });
  });

  // ---------------------------------------------------------------
  // Table search + filter
  // data-search="tableId" on an <input>, data-filter="tableId" on a <select>
  // Rows opt into filtering via data-filter-value="...".
  // A row with [data-empty] is shown when nothing matches.
  // ---------------------------------------------------------------
  function wireTable(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var searchInput = document.querySelector('[data-search="' + tableId + '"]');
    var filterSelect = document.querySelector('[data-filter="' + tableId + '"]');
    var emptyRow = table.querySelector("[data-empty]");

    function apply() {
      var term = searchInput ? searchInput.value.trim().toLowerCase() : "";
      var filterVal = filterSelect ? filterSelect.value : "";
      var visible = 0;
      table.querySelectorAll("tbody tr:not([data-empty])").forEach(function (row) {
        var text = row.textContent.toLowerCase();
        var matchesText = !term || text.indexOf(term) !== -1;
        var matchesFilter = !filterVal || row.getAttribute("data-filter-value") === filterVal;
        var show = matchesText && matchesFilter;
        row.hidden = !show;
        if (show) visible++;
      });
      if (emptyRow) emptyRow.hidden = visible !== 0;
    }

    if (searchInput) searchInput.addEventListener("input", apply);
    if (filterSelect) filterSelect.addEventListener("change", apply);
  }
  document.querySelectorAll("[data-search]").forEach(function (el) { wireTable(el.getAttribute("data-search")); });
  document.querySelectorAll("[data-filter]").forEach(function (el) { wireTable(el.getAttribute("data-filter")); });

  // ---------------------------------------------------------------
  // Export visible table rows to CSV + print
  // ---------------------------------------------------------------
  window.exportTable = function (tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var rows = [];
    table.querySelectorAll("tr").forEach(function (tr) {
      if (tr.hidden) return;
      var cols = [];
      tr.querySelectorAll("th, td").forEach(function (cell) {
        if (cell.classList.contains("no-print")) return;
        cols.push('"' + cell.textContent.replace(/\s+/g, " ").trim().replace(/"/g, '""') + '"');
      });
      if (cols.length) rows.push(cols.join(","));
    });
    var csv = rows.join("\r\n");
    var blob = new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8;" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = (filename || "export") + ".csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  window.printPage = function () { window.print(); };

  // ---------------------------------------------------------------
  // Shopping cart (client-side, pre-checkout only)
  // ---------------------------------------------------------------
  function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch (e) { return []; }
  }
  function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    renderCart();
  }
  function addToCart(item) {
    var cart = getCart();
    var existing = cart.find(function (c) { return String(c.id) === String(item.id); });
    if (existing) {
      existing.qty += 1;
    } else {
      cart.push({ id: item.id, name: item.name, price: Number(item.price), qty: 1 });
    }
    saveCart(cart);
  }
  function changeQty(id, delta) {
    var cart = getCart();
    var line = cart.find(function (c) { return String(c.id) === String(id); });
    if (!line) return;
    line.qty += delta;
    if (line.qty <= 0) cart = cart.filter(function (c) { return String(c.id) !== String(id); });
    saveCart(cart);
  }
  function removeFromCart(id) {
    saveCart(getCart().filter(function (c) { return String(c.id) !== String(id); }));
  }

  function renderCart() {
    var lines = document.getElementById("cartLines");
    var totalEl = document.getElementById("cartTotal");
    var countEl = document.getElementById("cartCount");
    if (!lines) return;
    var cart = getCart();

    if (!cart.length) {
      lines.innerHTML = '<p class="empty">No gallons selected yet.</p>';
    } else {
      lines.innerHTML = cart.map(function (item) {
        return (
          '<div class="cart-line">' +
            '<span>' + escapeHtml(item.name) + ' <br><small class="muted">' + peso(item.price) + ' each</small></span>' +
            '<span style="display:flex;align-items:center;gap:.4rem">' +
              '<button type="button" class="icon-btn" data-qty-minus="' + item.id + '" aria-label="Decrease">&minus;</button>' +
              '<strong>' + item.qty + '</strong>' +
              '<button type="button" class="icon-btn" data-qty-plus="' + item.id + '" aria-label="Increase">+</button>' +
              '<button type="button" class="icon-btn" data-remove="' + item.id + '" aria-label="Remove">&times;</button>' +
            '</span>' +
          '</div>'
        );
      }).join("");
    }

    var total = cart.reduce(function (sum, c) { return sum + c.price * c.qty; }, 0);
    if (totalEl) totalEl.textContent = peso(total);
    if (countEl) countEl.textContent = String(cart.reduce(function (n, c) { return n + c.qty; }, 0));

    lines.querySelectorAll("[data-qty-plus]").forEach(function (b) {
      b.addEventListener("click", function () { changeQty(b.getAttribute("data-qty-plus"), 1); });
    });
    lines.querySelectorAll("[data-qty-minus]").forEach(function (b) {
      b.addEventListener("click", function () { changeQty(b.getAttribute("data-qty-minus"), -1); });
    });
    lines.querySelectorAll("[data-remove]").forEach(function (b) {
      b.addEventListener("click", function () { removeFromCart(b.getAttribute("data-remove")); });
    });
  }

  document.querySelectorAll("[data-add-gallon]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var item;
      try { item = JSON.parse(btn.getAttribute("data-add-gallon")); } catch (e) { return; }
      addToCart(item);
      var original = btn.textContent;
      btn.textContent = "Added!";
      setTimeout(function () { btn.textContent = original; }, 900);
    });
  });

  // Checkout form (customer/payment.php): inject the cart as JSON right
  // before the normal POST submit. The server recalculates every total.
  var checkoutForm = document.getElementById("checkoutForm");
  if (checkoutForm) {
    checkoutForm.addEventListener("submit", function (e) {
      var cart = getCart();
      if (!cart.length) {
        e.preventDefault();
        alert("Your cart is empty. Please select at least one gallon first.");
        window.location.href = "gallons.php";
        return;
      }
      document.getElementById("cartJsonField").value = JSON.stringify(
        cart.map(function (c) { return { id: c.id, qty: c.qty }; })
      );
      // Cart is cleared after delivery.php confirms the order was saved
      // (see ?clear_cart=1 redirect target), not here, in case of error.
    });
  }

  if (document.getElementById("cartLines")) renderCart();

  // ---------------------------------------------------------------
  // Near-real-time notifications
  // ---------------------------------------------------------------
  (function () {
    var appBody = document.body;
    var feedUrl = appBody && appBody.getAttribute("data-notification-feed");
    if (!feedUrl) return;

    var onNotificationPage = appBody.getAttribute("data-notification-page") === "1";
    var lastLatestId = null;
    var requestInFlight = false;

    function setConnectionState(state, label) {
      document.querySelectorAll("[data-notification-status]").forEach(function (status) {
        status.classList.remove("realtime-status--checking", "realtime-status--connected", "realtime-status--offline");
        status.classList.add("realtime-status--" + state);
        var text = status.querySelector("[data-notification-status-text]");
        if (text) text.textContent = label;
        status.title = state === "connected"
          ? "Notification updates are connected"
          : state === "offline"
            ? "Notification updates are unavailable; retrying automatically"
            : "Checking for new notifications";
      });
    }

    function updateNotificationBadges(unreadCount) {
      var count = Math.max(0, Number(unreadCount) || 0);
      document.querySelectorAll("[data-notification-count]").forEach(function (badge) {
        badge.textContent = count > 9 ? "9+" : String(count);
        badge.hidden = count === 0;
        // The sidebar badge has an inline display rule for its initial state.
        badge.style.display = count === 0 ? "none" : "";
      });
    }

    function refreshNotifications() {
      if (requestInFlight || document.visibilityState !== "visible") return;
      requestInFlight = true;
      setConnectionState("checking", "Checking updates...");

      fetch(feedUrl, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" }
      })
        .then(function (response) {
          if (!response.ok) throw new Error("Notification feed unavailable");
          return response.json();
        })
        .then(function (data) {
          if (!data || data.ok !== true) throw new Error("Invalid notification feed");
          setConnectionState("connected", "Live updates on");
          updateNotificationBadges(data.unread_count);

          if (onNotificationPage && lastLatestId !== null && Number(data.latest_id) !== lastLatestId) {
            window.location.reload();
            return;
          }
          lastLatestId = Number(data.latest_id) || 0;
        })
        .catch(function () {
          setConnectionState("offline", "Offline - retrying");
          // A temporary network error should not interrupt the rest of the UI.
        })
        .finally(function () {
          requestInFlight = false;
        });
    }

    refreshNotifications();
    window.setInterval(refreshNotifications, 10000);
    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "visible") refreshNotifications();
    });
  })();

  // ---------------------------------------------------------------
  // Small helpers
  // ---------------------------------------------------------------
  function peso(n) {
    return "\u20B1" + Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
})();
