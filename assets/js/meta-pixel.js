(() => {
  const config = window.SmileBabyPixelConfig;
  if (!config || !/^\d+$/.test(String(config.id || ''))) return;

  const pixelId = String(config.id);
  let initialized = false;
  let pageTracked = false;

  const hasConsent = () => {
    try { return localStorage.getItem('sb_cookie_consent') === 'all'; }
    catch { return false; }
  };

  const initialize = () => {
    if (!hasConsent()) return false;
    if (initialized) {
      window.fbq('consent', 'grant');
      return true;
    }

    const shouldLoadScript = !window.fbq;
    if (shouldLoadScript) {
      const fbq = function () {
        if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments);
        else fbq.queue.push(arguments);
      };
      fbq.queue = [];
      fbq.loaded = true;
      fbq.version = '2.0';
      window.fbq = fbq;
      window._fbq = fbq;
    }

    window.fbq('consent', 'grant');
    window.fbq('set', 'autoConfig', false, pixelId);
    window.fbq('init', pixelId);
    if (shouldLoadScript) {
      const script = document.createElement('script');
      script.async = true;
      script.src = 'https://connect.facebook.net/en_US/fbevents.js';
      document.head.appendChild(script);
    }
    initialized = true;
    return true;
  };

  const track = (name, data = {}, options = {}) => {
    if (!initialize() || !name) return false;
    if (name === 'Search') {
      const query = String(data.search_string || '').trim().toLocaleLowerCase('ro-RO');
      if (!query) return false;
      try {
        const last = JSON.parse(sessionStorage.getItem('smilebaby_meta_search_last') || '{}');
        if (last.query === query && Date.now() - Number(last.time) < 5000) return false;
      } catch { /* Search tracking still works when session storage is unavailable. */ }
    }
    const onceKey = typeof options.once_key === 'string' ? options.once_key : '';
    if (onceKey) {
      try { if (localStorage.getItem(onceKey)) return false; }
      catch { /* A private browser may block storage; keep tracking functional. */ }
    }

    const eventOptions = options.event_id ? { eventID: String(options.event_id) } : undefined;
    window.fbq('trackSingle', pixelId, name, data, eventOptions);
    if (name === 'Search') {
      try { sessionStorage.setItem('smilebaby_meta_search_last', JSON.stringify({ query: String(data.search_string).trim().toLocaleLowerCase('ro-RO'), time: Date.now() })); }
      catch { /* Search event was queued successfully. */ }
    }
    if (onceKey) {
      try { localStorage.setItem(onceKey, '1'); }
      catch { /* Pixel still works without local deduplication. */ }
    }
    return true;
  };

  const trackPage = () => {
    if (pageTracked || !initialize()) return;
    pageTracked = true;
    track('PageView');
    (Array.isArray(config.events) ? config.events : []).forEach(event => {
      if (event && event.name) track(event.name, event.data || {}, event);
    });
  };

  window.SmileBabyPixel = { track, hasConsent };
  trackPage();

  window.addEventListener('smilebaby:consent', () => {
    if (hasConsent()) {
      if (pageTracked) initialize();
      else trackPage();
    }
    else if (initialized) window.fbq('consent', 'revoke');
  });
  window.addEventListener('storage', event => {
    if (event.key !== 'sb_cookie_consent') return;
    if (hasConsent()) {
      if (pageTracked) initialize();
      else trackPage();
    }
    else if (initialized) window.fbq('consent', 'revoke');
  });
})();
