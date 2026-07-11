<script>
  window.SUPPORT_API_BASE_URL = @json('/api/v1');
  window.SUPPORT_API_PUBLIC_BASE_URL = @json(rtrim((string) config('services.support_api.public_url'), '/'));

  window.supportApiToken = function () {
    return localStorage.getItem('auth_token')
      || localStorage.getItem('access_token')
      || sessionStorage.getItem('auth_token')
      || sessionStorage.getItem('access_token')
      || '';
  };

  window.supportApiUrl = function (path) {
    if (/^https?:\/\//i.test(path)) return path;
    var base = String(window.SUPPORT_API_BASE_URL || '/api/v1').replace(/\/$/, '');
    return base + '/' + String(path || '').replace(/^\//, '');
  };

  window.supportBackendUrl = function (path) {
    if (/^https?:\/\//i.test(path)) return path;
    var p = String(path || '');
    var configuredBase = String(window.SUPPORT_API_PUBLIC_BASE_URL || 'http://localhost:8600/api/v1').trim();
    var base = /^https?:\/\//i.test(configuredBase) ? configuredBase : 'http://localhost:8600/api/v1';
    var origin = /^https?:\/\//i.test(base) ? base.replace(/\/api\/v\d+$/i, '') : 'http://localhost:8600';
    if (p.indexOf('/api/v1') === 0 || p.indexOf('api/v1') === 0) {
      return (origin.replace(/\/$/, '') + (p.charAt(0) === '/' ? p : '/' + p));
    }
    return String(base).replace(/\/$/, '') + '/' + p.replace(/^\//, '');
  };

  window.supportBackendUrlCandidates = function (path) {
    var seen = {};
    var out = [];
    var raw = String(path || '');
    var p = /^https?:\/\//i.test(raw) ? raw : String(raw).replace(/^\//, '');
    var configuredPublic = String(window.SUPPORT_API_PUBLIC_BASE_URL || 'http://localhost:8600/api/v1').trim();
    var configuredHost = String(window.SUPPORT_API_BASE_URL || '/api/v1').trim();
    var bases = [
      configuredPublic,
      'http://localhost:8600/api/v1',
      'http://127.0.0.1:8600/api/v1',
      'http://host.docker.internal:8600/api/v1'
    ];

    if (/^https?:\/\//i.test(raw)) {
      return [raw];
    }

    bases.forEach(function (base) {
      var full = String(base).replace(/\/$/, '') + '/' + p.replace(/^\//, '');
      if (!seen[full]) {
        seen[full] = true;
        out.push(full);
      }
    });

    if (configuredHost && configuredHost !== '/api/v1') {
      var hostBase = configuredHost.replace(/\/$/, '');
      var alt = hostBase + '/' + p.replace(/^\//, '');
      if (!seen[alt]) {
        seen[alt] = true;
        out.unshift(alt);
      }
    }

    return out;
  };

  window.supportBackendFetch = async function (path, opts) {
    opts = opts || {};
    var isVoiceRoute = /(^|\/)voice(\/|$)|voice-agents\//.test(String(path || ''));
    var shouldFallbackToDirect = function (err) {
      if (!err) return false;
      if (!Object.prototype.hasOwnProperty.call(err, 'httpStatus')) return true;
      return [502, 503, 504].indexOf(Number(err.httpStatus)) !== -1;
    };

    if (isVoiceRoute) {
      var token = window.supportApiToken();
      var headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
      if (token && !headers.Authorization) headers.Authorization = 'Bearer ' + token;
      if (opts.body && !(opts.body instanceof FormData) && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
      }

      var res = await fetch(window.supportBackendUrl(path), Object.assign({}, opts, { headers: headers }));
      if (!res.ok) {
        var data = await res.json().catch(function () { return {}; });
        var detail = data.detail || data.message || data.error || res.statusText || ('HTTP ' + res.status);
        if (Array.isArray(detail)) detail = detail.join(', ');
        throw new Error(typeof detail === 'string' ? detail : JSON.stringify(detail));
      }
      if (res.status === 204) return null;
      return res.json();
    }

    try {
      return await window.supportApiFetch(path, opts);
    } catch (proxyError) {
      if (!shouldFallbackToDirect(proxyError)) {
        throw proxyError;
      }

      var headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
      if (opts.body && !(opts.body instanceof FormData) && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
      }

      var directErrors = [];
      var directUrls = window.supportBackendUrlCandidates(path);
      for (var i = 0; i < directUrls.length; i++) {
        try {
          var res = await fetch(directUrls[i], Object.assign({}, opts, { headers: headers }));
          if (!res.ok) {
            var data = await res.json().catch(function () { return {}; });
            var detail = data.detail || data.message || data.error || res.statusText || ('HTTP ' + res.status);
            if (Array.isArray(detail)) detail = detail.join(', ');
            throw new Error(typeof detail === 'string' ? detail : JSON.stringify(detail));
          }
          if (res.status === 204) return null;
          return res.json();
        } catch (directError) {
          directErrors.push(directError);
        }
      }

      var proxyMsg = proxyError && proxyError.message ? String(proxyError.message) : '';
      var directMsg = directErrors.length && directErrors[directErrors.length - 1] && directErrors[directErrors.length - 1].message
        ? String(directErrors[directErrors.length - 1].message)
        : '';
        var msg = 'Impossible de joindre le service support.';
        if (/voice-agents\/support-call-token/.test(String(path || ''))) {
          msg = "Impossible de demarrer l'appel. Verifiez que le service d'appel est actif.";
        } else if (/\/voice\/transcribe/.test(String(path || ''))) {
          msg = "Impossible de lancer la transcription vocale. Verifiez que le service audio est actif.";
        } else if (/visual-ai\/screenshare\/assist/.test(String(path || ''))) {
          msg = "Impossible de lancer l'analyse d'ecran. Verifiez que le service IA est actif.";
        }

        if (proxyMsg) msg += ' (' + proxyMsg + ')';
        if (directMsg && directMsg !== proxyMsg) msg += ' - ' + directMsg;
        throw new Error(msg);
      
    }
  };

  window.supportApiFetch = async function (path, opts) {
    opts = opts || {};
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
    var token = window.supportApiToken();

    if (token && !headers.Authorization) headers.Authorization = 'Bearer ' + token;
    if (csrf && !headers['X-CSRF-TOKEN']) headers['X-CSRF-TOKEN'] = csrf;
    if (!(opts.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    var res = await fetch(window.supportApiUrl(path), Object.assign({}, opts, { headers: headers }));
    if (!res.ok) {
      var data = await res.json().catch(function () { return {}; });
      var detail = data.detail || data.message || data.error || res.statusText || ('HTTP ' + res.status);
      if (Array.isArray(detail)) detail = detail.join(', ');
      var err = new Error(typeof detail === 'string' ? detail : JSON.stringify(detail));
      err.httpStatus = res.status;
      err.responseBody = data;
      throw err;
    }
    if (res.status === 204) return null;
    return res.json();
  };

  (function () {
    var nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      if (!String(url).startsWith('/api/v1')) {
        return nativeFetch(input, init);
      }

      var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      var token = window.supportApiToken();
      var nextInit = Object.assign({}, init || {});
      var headers = Object.assign({}, nextInit.headers || {});

      if (csrf && !headers['X-CSRF-TOKEN']) headers['X-CSRF-TOKEN'] = csrf;
      if (token && !headers.Authorization) headers.Authorization = 'Bearer ' + token;
      if (!headers.Accept) headers.Accept = 'application/json';
      nextInit.headers = headers;

      return nativeFetch(input, nextInit);
    };
  })();
</script>
