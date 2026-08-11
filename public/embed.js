(function () {
    var scripts = document.getElementsByTagName('script');
    var thisScript = scripts[scripts.length - 1];

    // Two shapes, one snippet:
    //   data-memorial="jane-doe"                  -> one memorial card
    //   data-memorials="jane-doe,john-doe"        -> a curated directory of those
    //   data-memorials="all"                      -> the site's whole public directory,
    //                                                with search and pagination
    // The serving origin decides whose memorials "all" means: load this script from a
    // reseller's own domain and the directory is theirs, in their branding.
    var single = thisScript.getAttribute('data-memorial');
    var multi = thisScript.getAttribute('data-memorials');

    if (!single && !multi) {
        console.error('[foreverloved] embed.js: add data-memorial="slug" or data-memorials="slug-a,slug-b" / "all"');
        return;
    }

    var origin = new URL(thisScript.src).origin;

    var iframe = document.createElement('iframe');
    iframe.src = multi
        ? origin + '/widget/directory?memorials=' + encodeURIComponent(multi)
        : origin + '/widget/' + encodeURIComponent(single);
    iframe.style.width = '100%';
    iframe.style.border = '0';
    iframe.style.minHeight = multi ? '420px' : '200px';
    iframe.setAttribute('title', multi ? 'Memorials' : 'Memorial');
    iframe.setAttribute('loading', 'lazy');

    thisScript.parentNode.insertBefore(iframe, thisScript);

    window.addEventListener('message', function (event) {
        if (event.origin !== origin || !event.data || event.data.type !== 'foreverloved:resize') {
            return;
        }
        if (event.source === iframe.contentWindow) {
            iframe.style.height = event.data.height + 'px';
        }
    });
})();
