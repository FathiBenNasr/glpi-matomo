/*
 * Matomo Tag Manager loader for GLPI.
 *
 * Settings arrive as <meta> tags written by the plugin through GLPI's header
 * hooks (escaped by GLPI's template, never executed):
 *   glpi-matomo-container  container URL (https only)
 *   glpi-matomo-uid        optional user identity, pushed to the data layer
 *                          as "glpiUserId" for the User ID field in Matomo
 */
(function () {
    function meta(name) {
        var m = document.querySelector('meta[name="' + name + '"]');
        return m ? (m.getAttribute('content') || '') : '';
    }

    var url = meta('glpi-matomo-container');
    if (url.indexOf('https://') !== 0) return;

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
