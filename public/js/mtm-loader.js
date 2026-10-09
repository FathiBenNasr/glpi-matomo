/*
 * Matomo Tag Manager loader for GLPI.
 *
 * Settings arrive as <meta> tags written by the plugin through GLPI's header
 * hooks (escaped by GLPI's template, never executed):
 *   glpi-matomo-container  container URL (https only)
 *   glpi-matomo-uid        optional user identity, pushed to the data layer
 *                          as "glpiUserId" for the User ID field in Matomo
 *
 * The server already withholds the tags on the pages below; the loader checks
 * again (defence in depth). Both patterns are copied verbatim from
 * src/Config.php (SECRET_PARAM_PATTERN, PRIVILEGED_PATH_PATTERN); a PHP test
 * fails if they drift apart.
 */
(function () {
    var SECRET_PARAM = new RegExp('^([^=]*(token|passw|secret)[^=]*|code|state)$', 'i');
    var PRIVILEGED_PATH = new RegExp('/(front|ajax)/(config|setup|profile|user|preference|group|entity|auth|apiclient|oauthclient|plugin|marketplace|crontask|mailcollector|notification|rule|webhook)[A-Za-z0-9_.-]*[.]php|/plugins/[^/]+/front/config', 'i');
    var CONTAINER = /^(?!.*\/\.)https:\/\/[^\/?#@\s"'<>\\]+(\/[A-Za-z0-9._~-]+)*\/container_[A-Za-z0-9_]+\.js$/;

    function meta(name) {
        var m = document.querySelector('meta[name="' + name + '"]');
        return m ? (m.getAttribute('content') || '') : '';
    }

    // WHY (M-13): the tracker records the page URL; never when it carries a secret.
    function carriesSecret(part) {
        var pairs = String(part || '').replace(/^[?#]/, '').split(/[&;]/);
        for (var i = 0; i < pairs.length; i++) {
            var eq = pairs[i].indexOf('=');
            if (eq < 0) continue;
            var name = pairs[i].slice(0, eq).replace(/\+/g, ' ');
            try { name = decodeURIComponent(name); } catch (e) { return true; }
            if (SECRET_PARAM.test(name)) return true;
        }
        return false;
    }

    // The tracker also reports document.referrer (urlref): check its query and fragment.
    function urlCarriesSecret(u) {
        u = String(u || '');
        var h = u.indexOf('#');
        var frag = h < 0 ? '' : u.slice(h + 1);
        var base = h < 0 ? u : u.slice(0, h);
        var q = base.indexOf('?');
        return carriesSecret(q < 0 ? '' : base.slice(q + 1)) || carriesSecret(frag);
    }

    // Same normalisation as Config::isPrivilegedPath(): decode, merge "//" and "/./".
    function isPrivilegedPath(p) {
        try { p = decodeURIComponent(String(p || '')); } catch (e) { return true; }
        var before;
        do { before = p; p = p.replace(/\/{2,}/g, '/').replace(/\/\.\//g, '/'); } while (p !== before);
        return PRIVILEGED_PATH.test(p);
    }

    var loc = window.location || {};
    if (carriesSecret(loc.search) || carriesSecret(loc.hash)) return;
    if (urlCarriesSecret((window.document || {}).referrer)) return;
    // WHY (M-12): mutable third-party code never runs in administration pages.
    if (isPrivilegedPath(loc.pathname)) return;

    var url = meta('glpi-matomo-container');
    if (!CONTAINER.test(url)) return;

    var w = window;
    w._mtm = w._mtm || [];

    var uid = meta('glpi-matomo-uid');
    if (uid) {
        w._mtm.push({ glpiUserId: uid });
    }
    w._mtm.push({ 'mtm.startTime': new Date().getTime(), event: 'mtm.Start' });

    var d = document;
    var g = d.createElement('script');
    var s = d.getElementsByTagName('script')[0];
    g.async = true;
    g.src = url;
    s.parentNode.insertBefore(g, s);
})();
