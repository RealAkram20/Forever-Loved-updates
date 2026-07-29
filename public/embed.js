(function () {
    var scripts = document.getElementsByTagName('script');
    var thisScript = scripts[scripts.length - 1];
    var slug = thisScript.getAttribute('data-memorial');

    if (!slug) {
        console.error('[foreverloved] embed.js: missing data-memorial attribute');
        return;
    }

    var origin = new URL(thisScript.src).origin;

    var iframe = document.createElement('iframe');
    iframe.src = origin + '/widget/' + encodeURIComponent(slug);
    iframe.style.width = '100%';
    iframe.style.border = '0';
    iframe.style.minHeight = '200px';
    iframe.setAttribute('title', 'Memorial');
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
