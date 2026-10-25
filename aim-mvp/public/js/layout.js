/* Shared UI helpers: icons, theme, header/footer, toast, calendar. Used by public site and admin. */
(function () {
  var IC = {
    pin: '<path d="M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>', home: '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>', cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>', user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>', users: '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3.5-5.5 7-5.5s7 2 7 5.5"/><path d="M16 4.5a3.5 3.5 0 010 7M18 14.8c2.4.6 4 2.3 4 5.2"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>', arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>', arrowUp: '<path d="M7 17L17 7M8 7h9v9"/>', left: '<path d="M15 6l-6 6 6 6"/>', right: '<path d="M9 6l6 6-6 6"/>', down: '<path d="M6 9l6 6 6-6"/>',
    heart: '<path d="M12 20s-8-5-8-11a4.5 4.5 0 018-2.7A4.5 4.5 0 0120 9c0 6-8 11-8 11z"/>', menu: '<path d="M4 7h16M4 12h16M4 17h16"/>', x: '<path d="M6 6l12 12M18 6L6 18"/>', check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>', info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
    bed: '<path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 00-3-3h-7v5"/><circle cx="7" cy="11" r="1.6"/>', bath: '<path d="M4 12h16v3a4 4 0 01-4 4H8a4 4 0 01-4-4v-3zM6 12V6a2 2 0 013.5-1.3M7 19l-1 2M17 19l1 2"/>', star: '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6L19 19M5 19l1.4-1.4M17.6 6.4L19 5"/>', moon: '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"/>', phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>', mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>', key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>', wifi: '<path d="M2 9a15 15 0 0120 0M5 12.5a10 10 0 0114 0M8.5 16a5 5 0 017 0"/><circle cx="12" cy="19" r=".8"/>', car: '<path d="M5 16l1.5-5.5A2 2 0 018.4 9h7.2a2 2 0 011.9 1.5L19 16M3 16h18v3H3zM7 19v1M17 19v1"/>', tag: '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>', cash: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>', chart: '<path d="M4 20V4M4 20h16M8 16v-5M13 16V8M18 16v-8"/>', cog: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9L7 7M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>', msg: '<path d="M4 5h16v11H9l-5 4z"/>', edit: '<path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/>', trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>', plus: '<path d="M12 5v14M5 12h14"/>', download: '<path d="M12 4v11M7 11l5 5 5-5M4 20h16"/>', logout: '<path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/>', list: '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>', print: '<path d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/>', eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>', clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
  };
  function I(n, cls) { return '<svg class="i ' + (cls || "") + '" viewBox="0 0 24 24" aria-hidden="true">' + (IC[n] || "") + "</svg>"; }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function qs(k) { return new URLSearchParams(location.search).get(k) || ""; }
  function toast(m) { var t = document.getElementById("toast"); if (!t) { t = document.createElement("div"); t.id = "toast"; t.className = "toast"; t.setAttribute("role", "status"); document.body.appendChild(t); } t.textContent = m; t.classList.add("show"); clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove("show"); }, 2800); }
  var ST = { pending: ["Awaiting host approval", "warn"], awaiting_payment: ["Awaiting payment", "warn"], payment_submitted: ["Payment being verified", "info"], confirmed: ["Confirmed", "ok"], declined: ["Declined", "bad"], cancelled: ["Cancelled", "bad"], completed: ["Completed", "ok"], expired: ["Expired", "bad"] };
  function chip(s) { var x = ST[s] || [s, ""]; return '<span class="chip chip--' + x[1] + '">' + x[0] + "</span>"; }
  function stars(n) { var h = ""; for (var i = 0; i < Math.round(n); i++) h += I("star"); return '<span class="stars" role="img" aria-label="' + n + ' out of 5">' + h + "</span>"; }

  /* theme */
  function theme() { var t = localStorage.getItem("aim:theme") || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"); document.documentElement.setAttribute("data-theme", t); return t; }
  theme();
  function toggleTheme() { var t = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark"; localStorage.setItem("aim:theme", t); document.documentElement.setAttribute("data-theme", t); var b = document.getElementById("themeBtn"); if (b) b.innerHTML = I(t === "dark" ? "sun" : "moon"); }

  /* header/footer */
  function shell(active) {
    var s = AIM.settings(), th = document.documentElement.getAttribute("data-theme");
    var nav = [["properties.html", "Properties", "properties"], ["how-it-works.html", "Our Process", "how"], ["destinations.html", "Neighborhoods", "destinations"], ["about.html", "About Us", "about"]];
    var link = function (n) { return '<a href="' + n[0] + '"' + (active === n[2] ? ' aria-current="page"' : "") + ">" + n[1] + "</a>"; };
    var brand = esc(s.brand).replace(/^(\S+?)(-)(.*)$/, '<b>$1</b>$2$3');
    var h = document.getElementById("app-header");
    if (h) h.outerHTML = '<a class="skip" href="#main">Skip to content</a>' + (s.demo && AIM.props().some(function (p) { return p.demo; }) ? '<div class="demo-bar">Sample listings shown for demonstration. Replace them in Admin.</div>' : "") + '<header class="header"><div class="container header__in"><a class="brand" href="index.html" aria-label="' + esc(s.brand) + ' home">' + brand + '</a><nav class="nav" aria-label="Primary">' + nav.map(link).join("") + '</nav><div class="header__act"><a class="icon-btn" href="saved.html" aria-label="Saved stays" title="Saved stays">' + I("heart") + '</a><button class="icon-btn" id="themeBtn" type="button" aria-label="Toggle light and dark theme">' + I(th === "dark" ? "sun" : "moon") + '</button><a class="btn btn--primary btn--sm header__cta" href="contact.html">Connect Us</a><button class="icon-btn menu-btn" id="menuBtn" type="button" aria-expanded="false" aria-controls="mnav" aria-label="Open menu">' + I("menu") + '</button></div></div><div class="mnav" id="mnav">' + nav.concat([["offers.html", "Offers"], ["booking-lookup.html", "Find my booking"], ["faq.html", "FAQ"], ["host.html", "List your property"]]).map(function (n) { return '<a href="' + n[0] + '">' + n[1] + "</a>"; }).join("") + '<a class="btn btn--primary" href="contact.html">Connect Us</a></div></header>';
    var f = document.getElementById("app-footer");
    if (f) f.outerHTML = '<footer class="footer"><div class="container"><div class="footer__grid"><div><a class="brand" href="index.html">' + esc(s.brand) + '</a><p style="margin-top:12px;max-width:34ch">' + esc(s.tagline) + '</p></div><div><h4>Explore</h4><ul><li><a href="properties.html">Properties</a></li><li><a href="offers.html">Offers</a></li><li><a href="destinations.html">Neighborhoods</a></li><li><a href="saved.html">Saved stays</a></li></ul></div><div><h4>Company</h4><ul><li><a href="about.html">About Us</a></li><li><a href="how-it-works.html">Our Process</a></li><li><a href="reviews.html">Guest reviews</a></li><li><a href="host.html">List your property</a></li></ul></div><div><h4>Support</h4><ul><li><a href="booking-lookup.html">Find my booking</a></li><li><a href="faq.html">FAQ</a></li><li><a href="contact.html">Contact</a></li>' + (s.email ? "<li>" + esc(s.email) + "</li>" : "") + (s.phone ? "<li>" + esc(s.phone) + "</li>" : "") + '<li>' + esc(s.address) + '</li></ul></div></div><div class="footer__bottom"><span>© ' + new Date().getFullYear() + " " + esc(s.brand) + '. All rights reserved.</span><span><a href="privacy.html">Privacy</a> · <a href="terms.html">Terms</a> · <a href="cancellation-policy.html">Cancellation</a> · <a href="admin/index.html">Admin</a></span></div></div></footer>';
    var mb = document.getElementById("menuBtn"), mn = document.getElementById("mnav");
    if (mb) mb.onclick = function () { var o = mn.classList.toggle("open"); mb.setAttribute("aria-expanded", o); mb.innerHTML = I(o ? "x" : "menu"); };
  }
  document.addEventListener("click", function (e) { if (e.target.closest && e.target.closest("#themeBtn")) toggleTheme(); });

  /* date-range calendar. o: {start,end,onChange(a,b), min} */
  function calendar(el, o) {
    var today = AIM.today(), a = o.start || "", b = o.end || "", base = new Date((a || today) + "T00:00:00"), y = base.getFullYear(), m = base.getMonth(), pid = o.propertyId;
    function taken(d) { return pid && AIM.isNightTaken(pid, d); }
    function rangeFree(s, e) { for (var d = s; d < e; d = AIM.addDays(d, 1)) if (taken(d)) return false; return true; }
    function draw() {
      var first = new Date(y, m, 1), days = new Date(y, m + 1, 0).getDate(), lead = (first.getDay() + 6) % 7, h = '<div class="cal__head"><button type="button" class="icon-btn" data-nav="-1" aria-label="Previous month">' + I("left") + "</button><span>" + first.toLocaleDateString("en-GB", { month: "long", year: "numeric" }) + '</span><button type="button" class="icon-btn" data-nav="1" aria-label="Next month">' + I("right") + '</button></div><div class="cal__grid">';
      "MTWTFSS".split("").forEach(function (d) { h += '<div class="cal__dow">' + d + "</div>"; });
      for (var i = 0; i < lead; i++) h += "<span></span>";
      for (var n = 1; n <= days; n++) {
        var d = y + "-" + ("0" + (m + 1)).slice(-2) + "-" + ("0" + n).slice(-2), past = d < (o.min || today), dis = past;
        if (!dis) dis = (a && !b && d > a) ? !rangeFree(a, d) : taken(d) && !(a && !b && d > a);
        var cls = "cal__d" + (past ? " past" : "") + (d === a || d === b ? " edge" : a && b && d > a && d < b ? " in" : "");
        h += '<button type="button" class="' + cls + '" data-d="' + d + '"' + (dis ? " disabled" : "") + ' aria-label="' + AIM.fmt(d) + (dis && !past ? ", unavailable" : "") + '">' + n + "</button>";
      }
      el.innerHTML = '<div class="cal">' + h + '</div></div><div class="legend"><span><i style="background:var(--action)"></i>Selected</span><span><i style="background:var(--border)"></i>Unavailable</span></div>';
    }
    el.onclick = function (e) {
      var n = e.target.closest("[data-nav]"), d = e.target.closest("[data-d]");
      if (n) { m += +n.dataset.nav; if (m > 11) { m = 0; y++; } if (m < 0) { m = 11; y--; } draw(); }
      if (d && !d.disabled) { var v = d.dataset.d; if (!a || b || v <= a) { a = v; b = ""; } else b = v; o.onChange && o.onChange(a, b); draw(); }
    };
    el.set = function (s, e2) { a = s || ""; b = e2 || ""; if (a) { var bb = new Date(a + "T00:00:00"); y = bb.getFullYear(); m = bb.getMonth(); } draw(); };
    draw();
  }
  window.UI = { I: I, esc: esc, qs: qs, toast: toast, chip: chip, stars: stars, shell: shell, calendar: calendar, toggleTheme: toggleTheme, ST: ST };
  if (window.AIM) { AIM.applyTheme(); AIM.ready.then(AIM.applyTheme); }
})();
