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

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function execute( $mime = true )
    {
        if ($mime) {
            Valid::setParam('mime-type', 'json');
        }
        

        if (Config::getOnboarding() === 2 && Config::getStatus() === 1 && !empty(Config::getKey())) {
            $js = array(
'/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */',''
);
            $js[] = '/* -- Mktr Script START -- */';
            $js[] = 'window.mktr = window.mktr || {};';
            $js[] = 'window.mktr.LoadEventsBool = true;';
            $js[] = 'window.mktr.try = 0;';
            $js[] = 'window.mktr.url = "'.Config::getBaseURL().'";';
            $js[] = 'window.mktr.version = "' . \Mktr\Tracker\Run::$version . '"';
            $js[] = 'window.mktr.debug = function () { if (typeof dataLayer != "undefined") { for (let i of dataLayer) { console.log("Mktr","Google",i); } } };';
            $js[] = '';
            $js[] = '(function(d, s, i) { var f = d.getElementsByTagName(s)[0], j = d.createElement(s); j.async = true; j.src = "https://t.themarketer.com/t/j/" + i; f.parentNode.insertBefore(j, f); })(document, "script", "'. Config::getKey() .'");';
            $js[] = '';
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
window.mktr.LoadEventsFunc = function() {
    if (window.mktr.LoadEventsBool) {
        window.mktr.LoadEventsBool = false;
        setTimeout(window.mktr.events, 2000);
    }
};
window.mktr.events = function () {
    fetch(window.mktr.url + "?mktr=loadEvents&mktr_time="+(new Date()).getTime(), { method: "GET" }).then(response => response.json()).then(data => { window.mktr.addToDataLayer(data); }).catch((error) => {  });
    window.mktr.LoadEventsBool = true;
};';
            $js[] = '';
            $js[] = '(function($) {
    $(document.body).on("added_to_cart", window.mktr.LoadEventsFunc);
    $(document.body).on("removed_from_cart", window.mktr.LoadEventsFunc);
    $(document.body).on("added_to_wishlist", window.mktr.LoadEventsFunc);
    $(document.body).on("removed_from_wishlist", window.mktr.LoadEventsFunc);
})(jQuery);';
            $js[] = '';
            $js[] = 'document.addEventListener("click", function(event){ if (event.target.matches("'.Config::getSelectors().'") || event.target.closest("'.Config::getSelectors().'")) { window.mktr.LoadEventsFunc(); } });';
            $js[] = 'window.mktr.LoadEventsFunc();';
            $js[] = '';
            $js[] = 'if (mktr_data.uuid !== null) { window.mktr.setSM("__sm__uid", mktr_data.uuid); }';
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
        _wishlist: mktr_data.wishList,
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

            $js_file = Config::getValue('js_file');
            if ($js_file !== null && file_exists(Config::getDir() . 'assets/mktr.' . $js_file . '.js')) {
                unlink(Config::getDir() . 'assets/mktr.' . $js_file . '.js');
            }
            $js_file = time();
            
            Config::setValue('js_file', $js_file);

            \Mktr\Tracker\FileSystem::setWorkDirectory('assets');
            \Mktr\Tracker\FileSystem::writeFile('mktr.' . $js_file . '.js', implode(PHP_EOL, $js));
        } else {
            $js_file = Config::getValue('js_file');
            if ($js_file !== null && file_exists(Config::getDir() . 'assets/mktr.' . $js_file . '.js')) {
                unlink(Config::getDir() . 'assets/mktr.' . $js_file . '.js');
            }
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
