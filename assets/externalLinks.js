/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

if (typeof bearCMS === 'undefined') {
    bearCMS = {};
}

bearCMS.externalLinks = bearCMS.externalLinks || (function () {

    var enabled = false;

    var update = function () {
        if (!document.getElementsByTagName) {
            return;
        }
        var links = document.getElementsByTagName("a");
        var host = location.host;
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            var href = link.getAttribute("href");
            if (href !== null && href.indexOf('//') !== -1 && href.indexOf('//' + host) === -1 && href.indexOf("#") !== 0 && href.indexOf("javascript:") !== 0) {
                if (enabled) {
                    if (link.target === null || link.target === '') {
                        link.setAttribute('data-external-link-updated', link.target);
                        link.target = "_blank";
                    }
                } else {
                    var oldTarget = link.getAttribute('data-external-link-updated');
                    if (oldTarget !== null) {
                        link.removeAttribute('data-external-link-updated');
                        link.target = oldTarget;
                    }
                }
            }
        }
    };

    var initialize = function (_enabled, active) {
        enabled = _enabled;
        update();
        if (active) {
            window.setInterval(update, 1000);
        }
    };

    var enable = function () {
        enabled = true;
        update();
    };

    var disable = function () {
        enabled = false;
        update();
    };

    return {
        'initialize': initialize,
        'enable': enable,
        'disable': disable
    };

}());