/* AIM-AirBnB client data layer (server-backed).
   Public pages: bootstrap from /api/bootstrap, guests act through token-protected endpoints.
   Admin: session login, full data from /api/admin/data. Price and availability are always re-checked on the server. */
(function () {
  var P = "aim:", BLOCKING = ["awaiting_payment", "payment_submitted", "confirmed"];
  var ADMIN = location.pathname.indexOf("/admin/") > -1, API = ADMIN ? "../api/" : "api/", csrf = "";
  var C = { settings: null, properties: [], offers: [], reviews: [], busy: {}, seo: false, bookings: [], enquiries: [], activity: [], guestNotes: {}, users: [], admin: null, offline: false };
  function rd(k, d) { try { var v = localStorage.getItem(P + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } }
  function wr(k, v) { try { localStorage.setItem(P + k, JSON.stringify(v)); return true; } catch (e) { return false; } }
  function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 7); }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function iso(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function today() { return iso(new Date()); }
  function addDays(s, n) { var d = new Date(s + "T00:00:00Z"); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }
  function nights(a, b) { return Math.round((new Date(b + "T00:00:00Z") - new Date(a + "T00:00:00Z")) / 864e5); }
  function dow(s) { return new Date(s + "T00:00:00Z").getUTCDay(); }

  var PAL = [["#cfe3ee", "#dfeccf", "#3f7d5e", "#efe3cd", "#8c4f3a", "#f6d98b"], ["#f3dcc4", "#cfe1c6", "#2f6b52", "#f4efe6", "#5b6b7a", "#f2b872"], ["#d7e6f0", "#e8efd6", "#4b8a6b", "#e5dccb", "#6f4a3a", "#fff1b8"], ["#e2ddd0", "#c9dcc5", "#245a46", "#f0ebe0", "#3d4a52", "#f5c97c"], ["#c9dfe8", "#d6e6c4", "#356f55", "#eadfca", "#99593f", "#ffe6a0"]];
  function art(seed, kind) {
    var c = PAL[seed % PAL.length], g = "";
    var tree = function (x, y, s) { return '<rect x="' + (x - 4 * s) + '" y="' + (y - 40 * s) + '" width="' + 8 * s + '" height="' + 40 * s + '" fill="#6b4a33"/><circle cx="' + x + '" cy="' + (y - 62 * s) + '" r="' + 38 * s + '" fill="' + c[2] + '"/><circle cx="' + (x + 22 * s) + '" cy="' + (y - 44 * s) + '" r="' + 26 * s + '" fill="' + c[2] + '" opacity=".85"/>'; };
    var win = function (x, y, w, h) { return '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="3" fill="#a9c9d6"/><path d="M' + (x + w / 2) + ' ' + y + 'v' + h + 'M' + x + ' ' + (y + h / 2) + 'h' + w + '" stroke="#fff" stroke-width="2"/>'; };
    if (kind === "apartment") {
      g += '<rect x="280" y="90" width="260" height="360" fill="' + c[3] + '"/><rect x="270" y="80" width="280" height="16" fill="' + c[4] + '"/>';
      for (var r = 0; r < 5; r++) for (var q = 0; q < 3; q++) g += win(300 + q * 82, 112 + r * 62, 52, 40);
      g += '<rect x="378" y="390" width="64" height="60" fill="' + c[4] + '"/><rect x="150" y="300" width="110" height="150" fill="' + c[3] + '" opacity=".8"/><rect x="560" y="260" width="120" height="190" fill="' + c[3] + '" opacity=".8"/>' + tree(110, 470, 1.1) + tree(720, 470, 1);
    } else if (kind === "townhouse") {
      [c[3], "#e9d3b8", c[1]].forEach(function (col, i) { var x = 150 + i * 170; g += '<rect x="' + x + '" y="190" width="170" height="260" fill="' + col + '"/><polygon points="' + (x - 6) + ",190 " + (x + 85) + ",120 " + (x + 176) + ',190" fill="' + c[4] + '"/>' + win(x + 24, 230, 44, 52) + win(x + 102, 230, 44, 52) + win(x + 24, 330, 44, 52) + '<rect x="' + (x + 100) + '" y="360" width="48" height="90" fill="' + c[4] + '" opacity=".9"/>'; });
      g += tree(90, 470, 1) + tree(710, 470, 1.1);
    } else if (kind === "modern") {
      g += '<rect x="170" y="230" width="300" height="220" fill="' + c[3] + '"/><rect x="420" y="170" width="220" height="280" fill="#d8d3c6"/><rect x="160" y="218" width="320" height="16" fill="' + c[4] + '"/><rect x="410" y="158" width="240" height="16" fill="' + c[4] + '"/><rect x="190" y="260" width="200" height="120" fill="#a9c9d6"/><path d="M290 260v120" stroke="#fff" stroke-width="3"/><rect x="450" y="200" width="160" height="90" fill="#a9c9d6"/><rect x="450" y="330" width="60" height="120" fill="' + c[4] + '"/>' + tree(100, 470, 1) + tree(700, 470, 1.2);
    } else {
      g += '<rect x="560" y="120" width="30" height="70" fill="' + c[4] + '"/><rect x="190" y="230" width="420" height="220" fill="' + c[3] + '"/><polygon points="160,236 400,100 640,236" fill="' + c[4] + '"/>' + win(230, 280, 70, 70) + win(500, 280, 70, 70) + '<rect x="364" y="320" width="72" height="130" rx="4" fill="' + c[2] + '"/><circle cx="424" cy="390" r="4" fill="#f6d98b"/>' + win(364, 250, 72, 48).replace("#a9c9d6", "#cfe3ee") + tree(110, 480, 1.1) + tree(700, 480, 1) + '<ellipse cx="300" cy="470" rx="60" ry="14" fill="' + c[2] + '" opacity=".6"/>';
    }
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 560"><defs><linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + c[0] + '"/><stop offset="1" stop-color="#f7f5ee"/></linearGradient></defs><rect width="800" height="560" fill="url(#s)"/><circle cx="' + (620 + seed * 17 % 120) + '" cy="90" r="42" fill="' + c[5] + '"/><path d="M0 400 Q200 350 400 392 T800 380 V560 H0z" fill="' + c[1] + '"/><rect y="450" width="800" height="110" fill="' + c[2] + '" opacity=".35"/>' + g + '</svg>';
    return "data:image/svg+xml;utf8," + encodeURIComponent(svg);
  }

  var AM_LIST = ["Wi-Fi", "Free parking", "Kitchen", "Air conditioning", "TV", "Hot water", "Secure compound", "Generator backup", "Washing machine", "Garden", "Swimming pool", "Breakfast available", "Workspace", "Balcony", "Airport pickup"];
  var DEF = {
    brand: "AIM-AirBnB", tagline: "Thoughtfully selected stays in Kampala, Uganda.", phone: "", email: "", whatsapp: "", address: "Kampala, Uganda",
    currency: "UGX", locale: "en-UG", depositPct: 30, holdHours: 24, serviceFeePct: 0, maxGuests: 20,
    momo: { mtn: "", airtel: "", name: "" },
    hero: { eyebrow: "Find your place to call home", title: "Find a stay that feels like yours.", text: "Discover thoughtfully selected homes in desirable neighbourhoods, with the information and guidance you need to book with confidence." },
    about: "AIM-AirBnB curates short-stay homes in Kampala and around Uganda. Every listing shows the details guests actually need: price, rules, check-in times and a clear booking status.\n\nWe confirm every booking personally, so you always know where you stand.",
    faq: [["How do I book a stay?", "Pick dates on the property page. Some homes are instant book; others are request-to-book and the host replies within a day. After that you pay with Mobile Money and the team confirms your payment."], ["When is my booking confirmed?", "Only when the status shows Confirmed. A request or an unpaid hold does not guarantee your dates."], ["How do I pay?", "Pay the amount due by MTN or Airtel Mobile Money, then enter the transaction ID on the payment page. The team verifies it and confirms."], ["Can I cancel?", "Yes. Refunds follow the property's cancellation policy (Flexible, Moderate or Strict). See the Cancellation page."], ["What if I don't pay in time?", "Instant-book holds are released after the hold period shown on your payment page and the dates open up again."]],
    policies: { privacy: "We collect the details you enter (name, phone, email, trip dates) only to handle your booking and enquiries. We do not sell personal data.\n\nTo request a copy or deletion of your data, contact us using the details on the Contact page.", terms: "By sending a booking you agree to the house rules shown on the property page and to the cancellation policy of that property.\n\nA booking is confirmed only when its status reads Confirmed. Prices are in the currency shown and may include a cleaning fee.", cancellation: "" },
    destinations: [{ name: "Kololo", blurb: "Quiet, leafy and close to restaurants and embassies." }, { name: "Bugolobi", blurb: "A calm residential area with easy access to the city and lakeside." }, { name: "Muyenga", blurb: "Hilltop homes with long views, popular with families." }, { name: "Ntinda", blurb: "Everyday convenience with shops, cafés and good transport links." }, { name: "Naguru", blurb: "Modern developments minutes from the centre." }, { name: "Entebbe", blurb: "Lake Victoria shoreline and a short ride from the airport." }],
    theme: { light: "", dark: "" }, notifyEmail: "", demo: false
  };
  var POL = {
    flexible: ["Flexible", "Full refund up to 24 hours before check-in. After that, the first night is not refunded."],
    moderate: ["Moderate", "Full refund up to 5 days before check-in. 50% refund up to 24 hours before. After that, no refund."],
    strict: ["Strict", "50% refund up to 7 days before check-in. After that, no refund."]
  };


  /* ---------- network ---------- */
  function api(method, path, body) {
    var h = { Accept: "application/json" }, fd = typeof FormData !== "undefined" && body instanceof FormData;
    if (body && !fd) h["Content-Type"] = "application/json"; if (csrf) h["X-CSRF"] = csrf;
    return fetch(API + path, { method: method, headers: h, credentials: "same-origin", body: body ? (fd ? body : JSON.stringify(body)) : undefined }).then(function (r) {
      return r.json().catch(function () { return { error: "Server error (" + r.status + ")" }; }).then(function (j) { if (!r.ok && !j.error) j.error = "Request failed (" + r.status + ")"; j._status = r.status; return j; });
    }, function () { return { error: "Cannot reach the server. Check your connection and try again.", _status: 0 }; });
  }
  function settings() { var s = C.settings || {}, o = {}; for (var k in DEF) o[k] = s[k] == null ? DEF[k] : s[k]; return o; }
  function money(n) { var s = settings(); try { return new Intl.NumberFormat(s.locale, { style: "currency", currency: s.currency, maximumFractionDigits: 0 }).format(n || 0); } catch (e) { return s.currency + " " + Math.round(n || 0).toLocaleString("en-US"); } }
  function fmt(s) { if (!s) return "–"; try { return new Date(s.length > 10 ? s.replace(" ", "T") : s + "T00:00:00").toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" }); } catch (e) { return s; } }
  function fmtT(s) { try { return new Date(String(s).replace(" ", "T")).toLocaleString("en-GB", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }); } catch (e) { return s; } }
  function props(all) { return all ? C.properties : C.properties.filter(function (p) { return p.status === "published"; }); }
  function prop(k) { return C.properties.filter(function (p) { return p.id === k || p.slug === k; })[0]; }
  function bookings() { return C.bookings; }
  function booking(ref) { var raw = String(ref || "").trim(), up = raw.toUpperCase(); return C.bookings.filter(function (b) { return b.ref === up || b.id === raw; })[0]; }
  function conflicts(pid, a, b, ignoreId) {
    var p = prop(pid), out = [];
    ((p && p.blocked) || []).forEach(function (x) { if (a <= x.to && b > x.from) out.push({ kind: "blocked", from: x.from, to: x.to }); });
    if (ADMIN) C.bookings.forEach(function (x) { if (x.propertyId === pid && x.id !== ignoreId && BLOCKING.indexOf(x.status) > -1 && x.in < b && x.out > a) out.push({ kind: "booking", ref: x.ref, from: x.in, to: x.out }); });
    else (C.busy[pid] || []).forEach(function (x) { if (x[0] < b && x[1] > a) out.push({ kind: "booking", from: x[0], to: x[1] }); });
    return out;
  }
  function isNightTaken(pid, day) { return conflicts(pid, day, addDays(day, 1)).length > 0; }
  function nightPrice(p, d) {
    for (var i = 0; i < (p.seasons || []).length; i++) { var s = p.seasons[i]; if (s.from && s.to && d >= s.from && d <= s.to && s.price) return +s.price; }
    var w = dow(d); if (p.weekendPrice && (w === 5 || w === 6)) return +p.weekendPrice;
    return +p.price || 0;
  }
  function findPromo(code, p, n) {
    code = String(code || "").trim().toUpperCase(); if (!code) return null;
    var t = today(), o = C.offers.filter(function (x) { return x.active && String(x.code).toUpperCase() === code; })[0];
    if (!o) return { error: "That code is not valid." };
    if (o.validFrom && t < o.validFrom || o.validTo && t > o.validTo) return { error: "That code has expired." };
    if (o.propertyId && p && o.propertyId !== p.id) return { error: "That code does not apply to this stay." };
    if (o.minNights && n < o.minNights) return { error: "That code needs at least " + o.minNights + " nights." };
    return o;
  }
  /* display-only quote; the server recomputes the authoritative price on booking */
  function quote(p, a, b, code, quiet) {
    var n = nights(a, b), s = settings(), q = { nights: n, nightly: [], sub: 0, weekly: 0, promo: null, promoAmt: 0, cleaning: +p.cleaning || 0, fee: 0, total: 0, deposit: 0, error: "" };
    if (!(n > 0)) { q.error = "Choose a check-out after check-in."; return q; }
    for (var i = 0; i < n; i++) { var pr = nightPrice(p, addDays(a, i)); q.nightly.push(pr); q.sub += pr; }
    if (p.weeklyPct && n >= 7) q.weekly = Math.round(q.sub * p.weeklyPct / 100);
    var pm = findPromo(code, p, n);
    if (pm && pm.error) { if (!quiet) q.promoError = pm.error; } else if (pm) { q.promo = pm.code; q.promoAmt = pm.type === "fixed" ? Math.min(+pm.value, q.sub - q.weekly) : Math.round((q.sub - q.weekly) * pm.value / 100); }
    var base = q.sub - q.weekly - q.promoAmt + q.cleaning; q.fee = Math.round(base * (+s.serviceFeePct || 0) / 100); q.total = base + q.fee;
    q.deposit = Math.min(q.total, Math.ceil(q.total * (+s.depositPct || 100) / 100 / 1000) * 1000);
    return q;
  }
  function validate(p, a, b, g, ignoreId) {
    var n = nights(a, b);
    if (!a || !b || !(n > 0)) return "Choose a check-out after check-in.";
    if (a < today()) return "Check-in cannot be in the past.";
    if (p.minStay && n < p.minStay) return "Minimum stay is " + p.minStay + " night" + (p.minStay > 1 ? "s" : "") + ".";
    if (p.maxStay && n > p.maxStay) return "Maximum stay is " + p.maxStay + " nights.";
    if (g > p.guests) return "This home sleeps up to " + p.guests + " guests.";
    if (conflicts(p.id, a, b, ignoreId).length) return "Those dates are not available. Try different dates.";
    return "";
  }
  function refundFor(b, byHost) {
    var paid = (b.payment && b.payment.amountPaid) || 0; if (!paid) return 0; if (byHost) return paid;
    var days = nights(today(), b.in), pol = b.cancellation || "moderate";
    if (pol === "flexible") return days >= 1 ? paid : Math.max(0, paid - Math.round((b.pricing.sub / b.nights) || 0));
    if (pol === "moderate") return days >= 5 ? paid : days >= 1 ? Math.round(paid / 2) : 0;
    return days >= 7 ? Math.round(paid / 2) : 0;
  }
  function guests() {
    var m = {}, notes = C.guestNotes || {};
    C.bookings.forEach(function (b) { var k = (b.guest.email || b.guest.phone || b.guest.name).toLowerCase(); var g = m[k] = m[k] || { key: k, name: b.guest.name, email: b.guest.email, phone: b.guest.phone, trips: 0, spent: 0, last: "", note: notes[k] || "" }; g.trips++; if (["confirmed", "completed"].indexOf(b.status) > -1) g.spent += b.payment.amountPaid || 0; if (b.createdAt > g.last) g.last = b.createdAt; });
    return Object.keys(m).map(function (k) { return m[k]; }).sort(function (a, b) { return b.last > a.last ? 1 : -1; });
  }
  function rating(pid) { var l = C.reviews.filter(function (r) { return r.status === "approved" && (!pid || r.propertyId === pid); }); return { n: l.length, avg: l.length ? Math.round(l.reduce(function (a, r) { return a + r.rating; }, 0) / l.length * 10) / 10 : 0 }; }
  function shade(hex, a) { var n = parseInt(hex.slice(1), 16), f = function (v) { return Math.max(0, Math.min(255, v + a)); }; return "#" + [n >> 16, (n >> 8) & 255, n & 255].map(function (v) { return ("0" + f(v).toString(16)).slice(-2); }).join(""); }
  function applyTheme() {
    var s = settings(), css = "", ok = function (v) { return /^#[0-9a-f]{6}$/i.test(v || ""); };
    if (ok(s.theme.light)) css += ':root{--action:' + s.theme.light + ';--action-h:' + shade(s.theme.light, -30) + ';--link:' + s.theme.light + '}';
    if (ok(s.theme.dark)) css += '[data-theme="dark"]{--action:' + s.theme.dark + ';--action-h:' + shade(s.theme.dark, 40) + ';--link:' + s.theme.dark + '}';
    var st = document.getElementById("aim-theme-css"); if (!st) { st = document.createElement("style"); st.id = "aim-theme-css"; document.head.appendChild(st); } st.textContent = css;
  }

  /* ---------- guest actions (token-protected) ---------- */
  function tokens() { return rd("tok", {}); }
  function setTok(ref, t) { var m = tokens(); m[ref] = t; wr("tok", m); }
  function create(d) { return api("POST", "bookings", d).then(function (j) { if (j.booking && j.token) setTok(j.booking.ref, j.token); return j; }); }
  function lookup(ref, who) { return api("POST", "bookings/lookup", { ref: ref, who: who }).then(function (j) { if (j.booking && j.token) setTok(j.booking.ref, j.token); return j; }); }
  function guestBooking(ref, t) { ref = String(ref || "").toUpperCase(); t = t || tokens()[ref] || ""; if (!ref || !t) return Promise.resolve({ error: "auth", _status: 401 }); return api("GET", "bookings/" + encodeURIComponent(ref) + "?t=" + encodeURIComponent(t)).then(function (j) { if (j.booking) { setTok(j.booking.ref, t); j.token = t; } return j; }); }
  function guestPay(ref, t, d) { d.t = t; return api("POST", "bookings/" + encodeURIComponent(ref) + "/payment", d); }
  function guestCancel(ref, t, reason) { return api("POST", "bookings/" + encodeURIComponent(ref) + "/cancel", { t: t, reason: reason }); }
  function addReview(d) { return api("POST", "reviews", d); }
  function addEnquiry(d) { return api("POST", "enquiries", d); }

  /* ---------- admin ---------- */
  function login(u, p) { return api("POST", "admin/login", { username: u, password: p }).then(function (j) { if (j.csrf) { csrf = j.csrf; C.admin = j.user; } return j; }); }
  function me() { return api("GET", "admin/me").then(function (j) { if (j.csrf) { csrf = j.csrf; C.admin = j.user; } return j; }); }
  function logout() { return api("POST", "admin/logout", {}).then(function () { csrf = ""; C.admin = null; }); }
  function adminLoad() { return api("GET", "admin/data").then(function (j) { if (!j.error) { ["properties", "bookings", "reviews", "offers", "enquiries", "activity", "guestNotes", "users", "settings"].forEach(function (k) { if (j[k] != null) C[k] = j[k]; }); } return j; }); }
  function swap(b) { var i = C.bookings.findIndex(function (x) { return x.id === b.id; }); if (i < 0) C.bookings.unshift(b); else C.bookings[i] = b; }
  function act(id, action, x) { return api("POST", "admin/bookings/" + encodeURIComponent(id) + "/act", { action: action, payload: x || {} }).then(function (j) { if (j.booking) swap(j.booking); return j; }); }
  function adminCreate(d) { return api("POST", "admin/bookings", d).then(function (j) { if (j.booking) swap(j.booking); return j; }); }
  function save(k, v) {
    var body, old = C[k];
    if (Array.isArray(v)) { var ids = v.map(function (x) { return x.id; }); body = { items: v, removed: (old || []).map(function (x) { return x.id; }).filter(function (i) { return ids.indexOf(i) < 0; }) }; } else body = { items: v };
    C[k] = v; api("POST", "admin/save/" + k, body).then(function (j) { if (j.error) { window.UI && UI.toast(j.error); adminLoad().then(function () { window.dispatchEvent(new Event("aim:reload")); }); } }); return true;
  }
  function saveSettings(patch) { var s = C.settings || {}; for (var k in patch) s[k] = patch[k]; C.settings = s; return api("POST", "admin/settings", { patch: patch }).then(function (j) { if (j.error && window.UI) UI.toast(j.error); return j; }); }
  function upload(dataUrl) { return fetch(dataUrl).then(function (r) { return r.blob(); }).then(function (b) { var fd = new FormData(); fd.append("file", b, "photo.jpg"); return api("POST", "admin/upload", fd); }); }

  var ready = api("GET", "bootstrap").then(function (j) {
    if (j.error) { C.offline = true; return; }
    ["settings", "properties", "offers", "reviews", "busy", "seo"].forEach(function (k) { if (j[k] != null) C[k] = j[k]; });
    if (Array.isArray(C.busy)) C.busy = {};
  });
  window.AIM = { ready: ready, state: C, api: api, rd: rd, wr: wr, uid: uid, iso: iso, today: today, addDays: addDays, nights: nights, dow: dow, art: art, AM_LIST: AM_LIST, POL: POL, DEF: DEF, settings: settings, saveSettings: saveSettings, log: function () {}, sweep: function () {}, money: money, fmt: fmt, fmtT: fmtT,
    props: props, prop: prop, saveProps: function (l) { return save("properties", l); }, bookings: bookings, booking: booking, conflicts: conflicts, isNightTaken: isNightTaken, nightPrice: nightPrice, quote: quote, findPromo: findPromo, validate: validate, refundFor: refundFor, guests: guests, rating: rating, applyTheme: applyTheme, BLOCKING: BLOCKING,
    list: function (k) { return C[k] || []; }, save: save, notes: function () { return C.guestNotes || {}; }, users: function () { return C.users || []; },
    create: create, lookup: lookup, guestBooking: guestBooking, guestPay: guestPay, guestCancel: guestCancel, addReview: addReview, addEnquiry: addEnquiry, tokens: tokens,
    login: login, me: me, logout: logout, adminLoad: adminLoad, act: act, adminCreate: adminCreate, upload: upload, admin: function () { return C.admin; },
    stayUrl: function (slug) { return C.seo ? "stays/" + slug : "property.html?p=" + slug; },
    wa: function (m, to) { var n = String(to || settings().whatsapp || "").replace(/\D/g, ""); if (n.length === 10 && n[0] === "0") n = "256" + n.slice(1); return n ? "https://wa.me/" + n + "?text=" + encodeURIComponent(m) : ""; } };
})();
