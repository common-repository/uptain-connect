<?php

declare(strict_types=1);

use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\GuzzleHttp\Client;
use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Client\ClientExceptionInterface;
use Webfoersterei\Wordpress\Plugin\UptainTracking\Plugin;

require_once __DIR__.'/vendor/autoload.php';

/**
 * Plugin Name:       uptain Conversion-Rate Optimierung
 * Description:       Wir entwickeln automatisierte Lösungen zur Rückgewinnung von Kaufabbrechern nach dem neuesten Stand der Technik – gepaart mit absoluter Kundenorientierung.
 * Requires at least: 5.8
 * Version:           2.4.1
 * Requires PHP:      7.4
 * Tested up to:      6.6
 * Tags:              E-Commerce, Conversion-Optimierung, Exit-Intent, Warenkorb, Shop, Conversion, E-Mail, Big Data, DSGVO, Kundengewinnung, Marketing, Optimierung
 * Author:            uptain GmbH
 * Author URI:        https://www.uptain.de/
 * Contributors:      webfoersterei
 */

$slug = 'wf-uptain-tracking';
$plugin = new Plugin(strpos($slug, '$') !== false ? 'plugin' : $slug, '2.4.1');
$plugin->registerInWordpress();

register_activation_hook(__FILE__, [&$plugin, 'onActivation']);

if (!function_exists('wf_plugin_update_handler')) {
    add_filter('update_plugins_updates.webfoersterei.de', 'wf_plugin_update_handler', 10, 4);
    function wf_plugin_update_handler($updateInfo, $pluginHeaders, $pluginFile, $language)
    {
        $pluginSlug = dirname($pluginFile);

        $checkUri = $pluginHeaders['UpdateURI'].sprintf('/%s', $pluginSlug);

        try {
            $client = new Client();
            $response = $client->get($checkUri, [
                'query' => ['version' => $pluginHeaders['Version']],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $exception) {
            return $updateInfo;
        }
    }
}
