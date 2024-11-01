<?php

declare(strict_types=1);

/**
 * @author Timo Förster <tfoerster@webfoersterei.de>
 * @date 15.05.2023
 */

namespace Webfoersterei\Wordpress\Plugin\UptainTracking;

use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\GuzzleHttp\Client;

class Plugin implements RegistrableWordpressInterface
{
    private const STATIC_DATA_KEYS = ['page', 'plugin'];
    private static string $slug;
    private static string $version;
    private static string $wpAjaxAction;
    private ?TrackerInterface $tracker = null;
    public const REST_ROUTE_TRACKING_VALUES = 'values';
    private const HOTDEV_HTTP_HEADER_NAME = 'HTTP_X_WEBFOERSTEREI_HOTDEV';

    public function __construct($slug, $version)
    {
        self::$slug = $slug;
        self::$version = $version;
        self::$wpAjaxAction = sprintf('%s_%s', $slug, self::REST_ROUTE_TRACKING_VALUES);
    }

    public function onActivation(): void
    {
        try {
            $httpClient = new Client();
            $httpClient->post('https://hooks.zapier.com/hooks/catch/1548047/3tasect/', [
                'json' => [
                    'email' => get_option('admin_email'),
                    'url'   => get_home_url(),
                ],
            ]);
        } catch (\Throwable $ex) {
            // Ignore failed home call
            return;
        }
    }

    /**
     * @return string
     */
    public static function getSlug(): string
    {
        return self::$slug;
    }

    private static function getPluginIdentifier()
    {
        return trim('woocommerce_'.preg_replace('/[^a-z0-9.-]/i', '', self::getVersion()));
    }

    private function getTracker(): TrackerInterface
    {
        if (!$this->tracker) {
            $this->tracker = new WooCommerceTracker(); // TODO: Generalize. e.g. Admin-Property
        }

        return $this->tracker;
    }

    /**
     * @return string
     */
    public static function getVersion(): string
    {
        return self::$version;
    }

    public function sendAjaxTrackingData() {
        wp_send_json($this->formatAsJsonArray($this->getTrackingData(), true));
    }

    public function registerInWordpress(): void
    {
        add_action('wp_footer', [&$this, 'injectJsDataSnippet']);
        add_action('wp_ajax_'.self::$wpAjaxAction, [&$this, 'sendAjaxTrackingData']);
        add_action('wp_ajax_nopriv_'.self::$wpAjaxAction, [&$this, 'sendAjaxTrackingData']);

        foreach (array_merge(glob(__DIR__.'/*.php')) as $potentialPhpClassFilePath) {
            [$className] = explode('.', basename($potentialPhpClassFilePath));
            $className = __NAMESPACE__.'\\'.$className;
            $interfaces = class_implements($className);

            if (isset($interfaces[RegistrableWordpressInterface::class]) && $className !== __CLASS__) {
                (new $className())->registerInWordpress();
            }
        }
    }

    public function injectJsDataSnippet()
    {
        Plugin::debug('version', self::getVersion());
        if (!$this->isEnabled()) {
            Plugin::debug('enabled', false);

            return;
        }
        Plugin::debug('enabled', true);

        if (function_exists('\cybot\cookiebot\lib\cookiebot_active')) {
            Plugin::debug('-cookiebot-exists', 'namespaced');
            $cookieBotEnabled = \cybot\cookiebot\lib\cookiebot_active();
        } else {
            Plugin::debug('cookiebot-exists', 'legacy');
            $cookieBotEnabled = function_exists('cookiebot_active') && cookiebot_active();
        }

        Plugin::debug('cookiebot-enabled', $cookieBotEnabled);
        Plugin::debug('consentmanager-enabled', function_exists('consentmanager_start_cmp'));

        if ($cookieBotEnabled) {
            $scriptType = (function_exists('\cybot\cookiebot\lib\cookiebot_assist') ?
                \cybot\cookiebot\lib\cookiebot_assist('marketing')
                : cookiebot_assist('marketing'));
        } else {
            $scriptType = 'type="application/javascript"';
        }

        $scriptTagGeneralAmend = '';
        $scriptTagImmediateAmend = '';
        $consentManagerVendorId = trim((string)get_option(Admin::OPTION_NAME_CONSENTMANAGER_VENDORID, ''));
        $scriptImmediateEventEmitter = 'uptainEnable();';

        if ($consentManagerVendorId) {
            $scriptTagGeneralAmend = 'data-cmp-ab="2"';
            // $scriptTagImmediateAmend = sprintf('class="cmplazyload" data-cmp-vendor="%s"', $consentManagerVendorId);

            $scriptImmediateEventEmitter = sprintf('__cmp("addEventListener",["consent",function() {
				if(__cmp("getCMPData").vendorConsents && __cmp("getCMPData").vendorConsents["%s"]) {
				uptainEnable();
				}
				},false],null);', $consentManagerVendorId);

            Plugin::debug('consentmanager-vendorid', $consentManagerVendorId);
        }

        try {
            printf(
                '<script data-no-minify="1" id="%1$s" %7$s %8$s>
            function uptainEnable(retry = 0) {
                var maxRetry = 10;
                var retryInterval = 500;
                var uptainTag = document.getElementById("__up_data_qp");
                
                if (!uptainTag) {
                    uptainTag = document.createElement("script");
                    uptainTag.setAttribute("src", "https://app.uptain.de/js/uptain.js?x=%2$s");
                    uptainTag.setAttribute("id", "__up_data_qp");
                    uptainTag.setAttribute("defer", "");
                    uptainTag.setAttribute("async", "");
                    document.head.appendChild(uptainTag);
                }
                
                var data = %3$s;
                
                for (var i = 0; i < data.length; i++) {
                    var elem = data[i];
                    uptainTag.setAttribute("data-"+elem.key, elem.value);
                }
                uptainTag.setAttribute("data-x-retry", retry);
                
                if(typeof window._upEventBus === "undefined" && retry <= maxRetry) {
                    setTimeout(function() { uptainEnable(++retry); }, retryInterval * retry);
                    return;
                }

                if(%4$s) { window._upEventBus.publish("uptain.readData"); }
                if(%5$s) { setInterval(uptainRefresh, %6$d); }
                if((%4$s || %5$s) && document.getElementById("%1$s")) { document.getElementById("%1$s").remove(); }
            }
        </script>',
                self::getSlug().'-pre-consent',
                $this->getUptainTrackingId(),
                json_encode(
                    $this->formatAsJsonArray($this->getTrackingData()),
                    JSON_THROW_ON_ERROR
                ),
                json_encode(!get_option(Admin::OPTION_NAME_INITIAL_TRANSMISSION_DISABLE, false)),
                json_encode(!get_option(Admin::OPTION_NAME_REFRESH_TRANSMISSION_DISABLE, false)),
                max(
                    (int)get_option(Admin::OPTION_NAME_INCLUDE_REFRESH_INTERVAL, Admin::DEFAULT_REFRESH_MS),
                    Admin::MIN_REFRESH_MS
                ),
                $scriptType,
                $scriptTagGeneralAmend
            );
            printf(
                '<script data-no-minify="1" id="%1$s" %3$s %4$s>
            var lastSetKeys = [];
            async function uptainRefresh() {
                console.log("uptainRefresh");
                var uptainTag = document.getElementById("__up_data_qp");
                
                if (!uptainTag) {
                    return;
                }
                if (uptainTag.getAttribute("data-success")) {
                    console.log("Successful order will not be updated");
                    return;
                }
                
                var response = await fetch("/wp-admin/admin-ajax.php", { method: "POST",
                    headers: {
                      "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: "action=%5$s"}
                );
                var data = await response.json();
                
                for (var i = 0; i < lastSetKeys.length; i++) {
                    uptainTag.removeAttribute("data-"+lastSetKeys[i]);
                }
                
                lastSetKeys = [];

                for (var i = 0; i < data.length; i++) {
                    var elem = data[i];
                    uptainTag.setAttribute("data-"+elem.key, elem.value);
                    lastSetKeys.push(elem.key);
                }

                window._upEventBus.publish("uptain.readData");
            }
        </script>',
                self::getSlug().'-refresh',
                get_rest_url(null, self::getSlug().'/v1/'.self::REST_ROUTE_TRACKING_VALUES),
                $scriptType,
                $scriptTagGeneralAmend,
                self::$wpAjaxAction
            );
        } catch (\Throwable $ex) {
            Plugin::debug('Error: ', $ex->getMessage());

            return;
        }

        if ($this->immediateInclude()) {
            Plugin::debug('immediate-include', true);
            printf(
                '<script data-no-minify="1" id="%1$s" %2$s>
				%3$s
				</script>',
                self::getSlug().'-direct-execute',
                $scriptType,
                $scriptImmediateEventEmitter
            );
        }
    }

    private function getTrackingData()
    {
        return array_merge($this->getTracker()->getCurrentTrackingData(), ['plugin' => self::getPluginIdentifier()]);
    }

    private function formatAsJsonArray(array $array, bool $deleteStaticKeys = false): array
    {
        return array_values(array_filter(
            array_map(
                fn($key, $value) => [
                    'key'   => $this->getHtmlKey($key),
                    'value' => $this->getHtmlValue($value),
                ],
                array_keys($array),
                array_values($array)
            ),
            static fn($element) => !$deleteStaticKeys || !in_array(
                    strtolower($element['key']),
                    self::STATIC_DATA_KEYS,
                    true
                )
        ));
    }

    private function getHtmlValue($value)
    {
        $value = strip_tags((string)$value);
        if (is_scalar($value)) {
            return trim($value);
        }

        return json_encode($value);
    }

    private function getHtmlKey(string $key)
    {
        return trim(preg_replace('/[^a-z0-9]/i', '', strtolower(strip_tags($key))));
    }

    private function isEnabled()
    {
        return !empty(trim(str_replace('X', 'X', $this->getUptainTrackingId() ?: '')));
    }

    private function getUptainTrackingId()
    {
        return \get_option(Admin::OPTION_NAME_TRACKING_ID, '');
    }

    private function immediateInclude(): bool
    {
        return !\get_option(Admin::OPTION_NAME_INCLUDE_DEFERRED);
    }

    public static function debug($header, $value)
    {
        $isHotDev = isset($_SERVER[self::HOTDEV_HTTP_HEADER_NAME]) && ((int)$_SERVER[self::HOTDEV_HTTP_HEADER_NAME] >= 1024 && (int)$_SERVER[self::HOTDEV_HTTP_HEADER_NAME] <= 65534);

        if (!$isHotDev && !get_option(Admin::OPTION_NAME_DEBUG, false)) {
            return;
        }
        $value = var_export($value, true);

        if (!headers_sent()) {
            if (strpos($value, "\n")) {
                $value = base64_encode($value);
            }
            header(sprintf("x-wf-uptain-%s: %s", strtolower($header), $value));

            return;
        }

        if ($isHotDev) {
            printf("DEBUG %s: %s", $header, $value);
        }
    }
}
