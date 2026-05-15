(function () {
    var url = window.MATOMO_CONTAINER_URL;
    if (!url) return;

    var w = window;
    w._mtm = w._mtm || [];
    w._mtm.push({ 'mtm.startTime': new Date().getTime(), event: 'mtm.Start' });

    var d = document;
    var g = d.createElement('script');
    var s = d.getElementsByTagName('script')[0];
    g.async = true;
    g.src = url;
    s.parentNode.insertBefore(g, s);
})();
