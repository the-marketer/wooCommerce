<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Valid;
use Mktr\Tracker\Config;

class refreshJS
{
    private static $init = null;
    private static $map = array();

    public static function get($f = 'fileName')
    {
        if (isset(self::$map[$f])) {
            return self::$map[$f];
        }
        return null;
    }

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    /** How long a replaced mktr.<time>.js stays on disk for pages still cached with it. */
    const KEEP_OLD_FILES = 30 * DAY_IN_SECONDS;

    /** Removes generated scripts older than $maxAge seconds, never the one in $keep. */
    public static function pruneFiles($keep = null, $maxAge = 0)
    {
        $files = glob(Config::getDir() . 'assets/mktr.*.js');

        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            if (!preg_match('/^mktr\.(\d+)\.js$/', basename($file), $m)) {
                continue;
            }
            if ($keep !== null && (string) $m[1] === (string) $keep) {
                continue;
            }
            if (time() - (int) $m[1] >= $maxAge) {
                wp_delete_file($file);
            }
        }
    }

    public static function execute( $mime = true )
    {
        if ($mime) {
            Valid::setParam('mime-type', 'json');
        }
        

        if (Config::getOnboarding() === 2 && Config::getStatus() === 1 && !empty(Config::getKey())) {
            $js = array(
'"use strict";
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */
',''
);
            $js[] = '/* -- Mktr Script START -- */';
            $js[] = 'window.mktr = window.mktr || {};';
            $js[] = 'window.dataLayer = window.dataLayer || [];';
            $js[] = 'window.mktr.LoadEventsBool = true;';
            $js[] = 'window.mktr.Load = true;';
            $js[] = 'window.mktr.try = 0;';
            $js[] = 'window.mktr.tryLoadEventsFunc = 0;';
            $js[] = 'window.mktr.selectors = "'.Config::getSelectors().'";';
            // $js[] = 'window.mktr.url = window.location.protocol + "//" + window.location.host + "/en";';
            $js[] = 'window.mktr.url = mktr_data.base_url;';
            $js[] = 'window.mktr.version = "' . \Mktr\Tracker\Run::$version . '"';
            $js[] = 'window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr","Google",i); } } };';
            $js[] = '';
            $js[] = '(function(d, s, i) { var f = d.getElementsByTagName(s)[0], j = d.createElement(s); j.async = true; j.src = "https://t.themarketer.com/t/j/" + i; f.parentNode.insertBefore(j, f); })(document, "script", "' . Config::getKey() . '");';
            $js[] = '';
            if (defined('MKTR_DEBUG') && MKTR_DEBUG) {
                $js[] = 'console.log("EAX", mktr_data.debug);';
            }
            $js[] = 'window.mktr.addToDataLayer = function(push = []) {
    if (typeof dataLayer != "undefined") {
        for (let dataEvent of push) { dataLayer.push(dataEvent); }
    }
};
window.mktr.setSM = function(name, value, daysToExpire = 365) {
    const expirationDate = new Date(); expirationDate.setDate(expirationDate.getDate() + daysToExpire);
    const cookieValue = encodeURIComponent(value) + (daysToExpire ? `; expires=${expirationDate.toUTCString()}` : "");
    document.cookie = `${name}=${cookieValue}; path=/`;
};
/* Cache compatible mode: the HTML is the same for every visitor, so the visitor id is made
   here and events are fetched only when the server or the cart says something changed. */
window.mktr.cacheMode = mktr_data.cache_mode === "1";
window.mktr.getCookie = function(name) {
    var parts = document.cookie ? document.cookie.split(";") : [];
    for (var i = 0; i < parts.length; i++) {
        var part = parts[i].trim();
        if (part.indexOf(name + "=") === 0) {
            try { return decodeURIComponent(part.substring(name.length + 1)); } catch (e) { return part.substring(name.length + 1); }
        }
    }
    return null;
};
window.mktr.setCookie = function(name, value, maxAge) {
    var cookie = name + "=" + encodeURIComponent(value) + "; path=" + (mktr_data.cookie_path || "/") + "; max-age=" + maxAge + "; SameSite=Lax";
    if (mktr_data.cookie_domain) { cookie += "; domain=" + mktr_data.cookie_domain; }
    if (window.location.protocol === "https:") { cookie += "; Secure"; }
    document.cookie = cookie;
};
window.mktr.newUid = function() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") { return window.crypto.randomUUID(); }
    var b = new Uint8Array(16), h = "", i;
    if (window.crypto && window.crypto.getRandomValues) { window.crypto.getRandomValues(b); } else { for (i = 0; i < 16; i++) { b[i] = Math.floor(Math.random() * 256); } }
    b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
    for (i = 0; i < 16; i++) { h += (b[i] + 256).toString(16).substring(1); }
    return h.substring(0, 8) + "-" + h.substring(8, 12) + "-" + h.substring(12, 16) + "-" + h.substring(16, 20) + "-" + h.substring(20);
};
/* An id the server set as HttpOnly is invisible here; the browser then ignores this write. */
window.mktr.ensureUid = function() {
    if (window.mktr.getCookie("__sm__uid") === null) { window.mktr.setCookie("__sm__uid", window.mktr.newUid(), 31536000); }
};
window.mktr.cartHash = function() { return window.mktr.getCookie("woocommerce_cart_hash") || ""; };
window.mktr.rememberCart = function() { try { window.localStorage.setItem("mktr_cart_hash", window.mktr.cartHash()); } catch (e) {} };
window.mktr.cartChanged = function() {
    var last = null;
    try { last = window.localStorage.getItem("mktr_cart_hash"); } catch (e) { return false; }
    return last === null ? window.mktr.cartHash() !== "" : last !== window.mktr.cartHash();
};
window.mktr.needsSync = function() {
    return mktr_data.sync === "1" || window.mktr.getCookie("__mktr_sync") !== null || window.mktr.cartChanged();
};
window.mktr.syncEvents = function() {
    var done = function(data) {
        if (Array.isArray(data)) { window.mktr.addToDataLayer(data); }
        /* The response clears it too; this covers a proxy that drops Set-Cookie. */
        if (window.mktr.getCookie("__mktr_sync") !== null) { window.mktr.setCookie("__mktr_sync", "", 0); }
        window.mktr.rememberCart();
        window.mktr.LoadEventsBool = true;
    };
    var url = window.mktr.url + "?mktr=syncEvents";
    if (typeof fetch === "function") {
        fetch(url, { method: "POST", credentials: "same-origin", cache: "no-store" })
        .then(function(response) { return response.json(); })
        .then(done).catch(function() { window.mktr.LoadEventsBool = true; });
    } else if (typeof jQuery === "function") {
        jQuery.ajax({ url: url, method: "POST", dataType: "json", cache: false })
        .done(done).fail(function() { window.mktr.LoadEventsBool = true; });
    }
};
window.mktr.LoadEventsFunc = function() {
    if (window.mktr.LoadEventsBool) {
        window.mktr.LoadEventsBool = false;
        try {
            setTimeout(window.mktr.events, 2000);
        } catch (error) {
            console.error("An error occurred while executing setTimeout:", error);
        }
    }
};
window.addEventListener("beforeunload", function(event) {
    if (event.currentTarget.performance.navigation.type === PerformanceNavigation.TYPE_RELOAD ||
        event.currentTarget.performance.navigation.type === PerformanceNavigation.TYPE_REDIRECT ||
        event.currentTarget.performance.navigation.type === PerformanceNavigation.TYPE_NAVIGATE) {
        window.mktr.Load = false;
    } else {
        window.mktr.Load = true;
    }
});

window.mktr.xhrFetch = function(url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                var data = JSON.parse(xhr.responseText);
                callback(data);
            } else {
                // Handle errors
            }
        }
    };

    xhr.open("GET", url, true);
    xhr.send();
}

window.mktr.events = function () {
    /*
    window.mktr.xhrFetch(window.mktr.url + "?mktr=loadEvents&mktr_time=" + (new Date()).getTime(), function(data) { window.mktr.addToDataLayer(data); });
    */
    if ( window.mktr.Load === true && window.mktr.cacheMode) {
        window.mktr.syncEvents();
    } else if ( window.mktr.Load === true) {
        if (typeof jQuery === "function") {
            jQuery.ajax({ url: window.mktr.url, data: { mktr: "loadEvents", mktr_time: (new Date()).getTime() }, method: "GET", dataType: "json" })
            .done(function(data) {
                window.mktr.addToDataLayer(data);
                window.mktr.LoadEventsBool = true;
            }).fail(function(xhr, status, error) {
                console.error("AJAX error:", error);
            });
        } else {
            fetch(window.mktr.url + "?mktr=loadEvents&mktr_time="+(new Date()).getTime(), { method: "GET" })
            .then(response => response.json())
            .then(data => { window.mktr.addToDataLayer(data); window.mktr.LoadEventsBool = true; }).catch((error) => {  });
        }
    }
};';
            $js[] = '';
            $js[] = 'window.mktr.LoadOn = function () { if (window.mktr.tryLoadEventsFunc <= 5 && typeof jQuery != "undefined") {';
            $js[] = '(function($) {
    $(document.body).on("added_to_cart", window.mktr.LoadEventsFunc);
    $(document.body).on("removed_from_cart", window.mktr.LoadEventsFunc);
    $(document.body).on("added_to_wishlist", window.mktr.LoadEventsFunc);
    $(document.body).on("removed_from_wishlist", window.mktr.LoadEventsFunc);
})(jQuery);';
            $js[] = '} else if(window.mktr.tryLoadEventsFunc <= 5) { window.mktr.tryLoadEventsFunc++; setTimeout(window.mktr.LoadOn, 1500); } };';
            $js[] = 'window.mktr.LoadOn();';
            $js[] = '';
            $js[] = 'document.addEventListener("click", function(event){ if (window.mktr.selectors !== "" && (event.target.matches(window.mktr.selectors) || event.target.closest(window.mktr.selectors))) { window.mktr.LoadEventsFunc(); } });';
            $js[] = 'window.addEventListener("load", function() { if (!window.mktr.cacheMode || window.mktr.needsSync()) { window.mktr.LoadEventsFunc(); } });';
            $js[] = '/* window.mktr.addToDataLayer(mktr_data.evData); */';
            $js[] = '';
            $js[] = 'if (window.mktr.cacheMode) { window.mktr.ensureUid(); } else if (mktr_data.uuid !== null) { window.mktr.setSM("__sm__uid", mktr_data.uuid); }';
            $js[] = '';
            $js[] = 'if (mktr_data.isWoodMart === "1") {
    mktr.checkAdded = function(n, o) { return Object.keys(n).filter(i => !o[i]); }
    mktr.jsonDecode = function(j = null) { try { return j !== null ? JSON.parse(j) : {}; } catch (error) { console.error("Error parsing JSON:", error); return {}; } }
    mktr.cookie = function(name, cookieName = "",  decodedCookie = "", cookieArray = [], i = 0, cookie = null) {
        cookieName = name + "="; decodedCookie = decodeURIComponent(document.cookie); cookieArray = decodedCookie.split(";");
        for (i = 0; i < cookieArray.length; i++) {
            cookie = cookieArray[i]; while (cookie.charAt(0) == " ") { cookie = cookie.substring(1); }
            if (cookie.indexOf(cookieName) == 0) { return cookie.substring(cookieName.length, cookie.length); }
        }
        return null;
    }

    mktr.storage = {
        _wishlist: mktr_data.wishList !== undefined ? mktr_data.wishList : mktr.jsonDecode(mktr.cookie("woodmart_wishlist_products")),
        get wishlist() { return this._wishlist; },
        set wishlist(value) {
            let add = mktr.checkAdded(value, this._wishlist); let remove = mktr.checkAdded(this._wishlist, value);
            if (add.length !== 0 || remove.length !== 0) { window.mktr.LoadEventsFunc(); this._wishlist = value; }
        }
    };

    setInterval(function (c = null) {
        if (mktr.cookie("woodmart_wishlist_products") !== null) { mktr.storage.wishlist = mktr.jsonDecode(mktr.cookie("woodmart_wishlist_products")); }
    }, 5000);
}';
            $js[] = '';
            $js[] = 'window.mktr.LoadEvents = function () { if (window.mktr.try <= 5 && typeof dataLayer != "undefined") {';
            $js[] = 'for (let dataEvent of mktr_data.push) { dataLayer.push(dataEvent); }';
            $js[] = 'for (let key of Object.keys(mktr_data.js)) { fetch(window.mktr.url + "?mktr="+key+"&mktr_time="+(new Date()).getTime(), { method: "GET" }).then(response => response.json()).then(data => { console.log("LoadEvents1", data); }).catch((error) => {  }); }';
            $js[] = 'if (mktr_data.clear === "1") { fetch(window.mktr.url + "?mktr=clearEvents&mktr_time="+(new Date()).getTime(), { method: "GET" }).then(response => response.json()).then(data => { console.log("LoadEvents2", data); }).catch((error) => {  }); }';
            $js[] = '} else if(window.mktr.try <= 5) { window.mktr.try++; setTimeout(window.mktr.LoadEvents, 1500); } };';
            $js[] = '';
            $js[] = 'window.mktr.LoadEvents();';
            $js[] = '';
            $js[] = '/* -- Email Capture in Checkout START -- */';
            $js[] = 'if (mktr_data.email_capture_checkout === "1") {
            
    window.mktr.lastCapturedEmail = null;
    window.mktr.captureEmail = function(email) {
        if (email && email.indexOf("@") > -1 && email !== window.mktr.lastCapturedEmail) {
            window.mktr.lastCapturedEmail = email;
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ "event": "__sm__set_email", "email_address": email });
        }
    };
    window.mktr.attachEmailCapture = function() {
        var selectors = "#billing_email, input[name=\\"billing_email\\"], .wc-block-components-text-input input[type=\\"email\\"], input#email";
        var emailFields = document.querySelectorAll(selectors);
        emailFields.forEach(function(field) {
            if (!field.dataset.mktrEmailCapture) {
                field.dataset.mktrEmailCapture = "1";
                field.addEventListener("blur", function() {
                    window.mktr.captureEmail(this.value.trim());
                });
            }
        });
    };
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", window.mktr.attachEmailCapture);
    } else {
        window.mktr.attachEmailCapture();
    }
    window.addEventListener("load", window.mktr.attachEmailCapture);
    setInterval(window.mktr.attachEmailCapture, 2000);
}';
            $js[] = '/* -- Email Capture in Checkout END -- */';
            $js[] = '';
            $js[] = '/* -- Mktr Script END -- */';
            if (Config::getValue('google_status')) {
                $key = Config::getValue('google_tagCode');
                if (!empty($key)) {
                    $js[] = '/* -- Google Tag Manager START -- */';
                    $js[] = '(function(w,d,s,l,i){
    w[l]=w[l]||[];w[l].push({"gtm.start": new Date().getTime(),event:"gtm.js"});
    var f=d.getElementsByTagName(s)[0], j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";
    j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);
})(window, document, "script", "dataLayer", "'.$key.'");';
                    $js[] = '/* -- Google Tag Manager END -- */';
                }
            }

            $js_file = time();
            
            Config::setValue('js_file', $js_file);

            \Mktr\Tracker\FileSystem::setWorkDirectory('assets/');
            \Mktr\Tracker\FileSystem::writeFile('mktr.' . $js_file . '.js', implode(PHP_EOL, $js));

            /* Pages already in a full-page cache still load the file they were rendered with,
               so earlier versions stay until such a cache has surely expired. */
            self::pruneFiles($js_file, self::KEEP_OLD_FILES);
        } else {
            /* Tracking was turned off: cached pages must stop tracking too. */
            self::pruneFiles(null, 0);
            Config::setValue('js_file', null);
        }

        \wp_remote_post('https://connector.themarketer.com/feedback/install', array(
            'method'      => 'POST',
            'timeout'     => 5,
            'user-agent'  => 'mktr:'.\get_bloginfo( 'url' ),
            'body' => array(
                'status' => 2,
                't' => time(),
                'platform' => \Mktr\Tracker\Run::platform()
            )
        ));
        
        return array('status' => 'succes');
    }
}
