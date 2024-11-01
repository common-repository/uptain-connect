<?php

declare(strict_types=1);

/**
 * @author Timo Förster <tfoerster@webfoersterei.de>
 * @date 02.06.23
 */

namespace Webfoersterei\Wordpress\Plugin\UptainTracking;

class Admin implements RegistrableWordpressInterface
{
    public const OPTIONS_NAMESPACE_SLUG = 'wf-uptain-tracking';
    public const OPTION_NAME_DEBUG = self::OPTIONS_NAMESPACE_SLUG.'-debug';
    public const OPTION_NAME_TRACKING_ID = self::OPTIONS_NAMESPACE_SLUG.'-uptainId';
    public const OPTION_NAME_INCLUDE_DEFERRED = self::OPTIONS_NAMESPACE_SLUG.'-include-deferred';
    public const OPTION_NAME_INCLUDE_PERSONAL_DATA = self::OPTIONS_NAMESPACE_SLUG.'-include-personal-data';
    public const OPTION_NAME_INCLUDE_NET_WORTH = self::OPTIONS_NAMESPACE_SLUG.'-include-net-worth';
    public const OPTION_NAME_INCLUDE_REFRESH_INTERVAL = self::OPTIONS_NAMESPACE_SLUG.'-refresh-interval';
    public const OPTION_NAME_REFRESH_TRANSMISSION_DISABLE = self::OPTIONS_NAMESPACE_SLUG.'-refresh-disable';
    public const OPTION_NAME_INITIAL_TRANSMISSION_DISABLE = self::OPTIONS_NAMESPACE_SLUG.'-initial-disable';
    public const OPTION_NAME_CONSENTMANAGER_VENDORID = self::OPTIONS_NAMESPACE_SLUG.'-consentmanager-vendorid';
    public const DEFAULT_REFRESH_MS = 30000;
    public const MIN_REFRESH_MS = 5000;

    public function registerInWordpress(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'addOptionsPage']);
    }

    public function registerSettings()
    {
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_TRACKING_ID);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_INCLUDE_DEFERRED);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_INCLUDE_PERSONAL_DATA);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_INCLUDE_NET_WORTH);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_INCLUDE_REFRESH_INTERVAL);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_REFRESH_TRANSMISSION_DISABLE);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_INITIAL_TRANSMISSION_DISABLE);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_CONSENTMANAGER_VENDORID);
        register_setting(self::OPTIONS_NAMESPACE_SLUG, self::OPTION_NAME_DEBUG);
    }

    public function addOptionsPage()
    {
        add_options_page(
            'uptain',
            'uptain',
            'manage_options',
            self::OPTIONS_NAMESPACE_SLUG,
            [$this, 'renderOptionsPage']
        );
    }

    public function renderOptionsPage()
    {
        ?>
        <style>
            form#uptain_options {
                display: flex;
                flex-direction: column;
                max-width: 500px;
            }

            form#uptain_options div.row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: max-content;
                max-width: 100%;
                column-gap: 1em;
                margin-bottom: 1em;
            }

            form#uptain_options div.row label {
                font-size: 1.25em;
            }

            div.row.suboption {
                margin-left: 1em;
            }

            div.row.hidden {
                display: none !important;
            }

            textarea.resizable {
                width: 80% !important;
                height: 15vH !important;
                resize: both;
            }

            span.code {
                font-family: monospace;
                background: lightgrey;
                padding: 1px;
            }
        </style>
        <h1>Uptain</h1>
        <form method="post" action="options.php" id="uptain_options">
            <?php settings_fields(self::OPTIONS_NAMESPACE_SLUG); ?>
            <h2>Basis</h2>
            <div class="row">
                <label>uptain-ID:</label>
                <?php printf(
                    '<input type="text" name="%s" value="%s" />',
                    self::OPTION_NAME_TRACKING_ID,
                    esc_attr(get_option(self::OPTION_NAME_TRACKING_ID, ''))
                ); ?>
                <small>Head over to <a href="https://customer.uptain.de/integrations" target="_blank">https://customer.uptain.de/integrations</a>
                    to get your uptain-ID</small>
            </div>
            <div class="row">
                <label>Include personal data?</label>
                <input type="checkbox" id="wf-uptain-personal-data"
                       name="<?php echo self::OPTION_NAME_INCLUDE_PERSONAL_DATA ?>" value="1" <?php checked(
                    1,
                    get_option(self::OPTION_NAME_INCLUDE_PERSONAL_DATA),
                    true
                ); ?> onchange="if(!this.checked) { document.getElementById('wf-uptain-netsum').checked = false; }"/>
                <small>Personal data (e.g. E-Mail, Name) of the customer is only included if this checkbox is set
                    <strong>and</strong> the customer already has at least ordered once in the past (any order status)</small>
            </div>
            <div class="row suboption">
                <label>Include total net sum?</label>
                <input type="checkbox" id="wf-uptain-netsum" name="<?php echo self::OPTION_NAME_INCLUDE_NET_WORTH ?>"
                       value="1" <?php checked(
                    1,
                    get_option(self::OPTION_NAME_INCLUDE_NET_WORTH),
                    true
                ); ?>
                       onchange="if(this.checked) { document.getElementById('wf-uptain-personal-data').checked = true; }"/>
                <small>If personal data is being included also include the customers worth calculated as net sum of all
                    their orders</small>
            </div>
            <div class="row">
                <label>Block inclusion initially?</label>
                <input type="checkbox" name="<?php echo self::OPTION_NAME_INCLUDE_DEFERRED ?>" value="1" <?php checked(
                    1,
                    get_option(self::OPTION_NAME_INCLUDE_DEFERRED),
                    true
                ); ?> />
                <small>If set, initially blocks cookies (and thus uptain) and waits until the JS-function <span
                            class="code">uptainEnable();</span> is executed (e.g. by Cookie-Consent-Tool)</small>
            </div>
            <h2>WordPress</h2>
            <div class="row <?php echo function_exists('consentmanager_start_cmp') ? '' : 'hidden'; ?>">
                <label>Consentmanager Vendor-ID:</label>
                <?php printf(
                    '<input type="text" name="%s" value="%s" />',
                    self::OPTION_NAME_CONSENTMANAGER_VENDORID,
                    esc_attr(function_exists('consentmanager_start_cmp') ? get_option(self::OPTION_NAME_CONSENTMANAGER_VENDORID, '') : '')
                ); ?>
            </div>
            <div class="row">
                <label>Debug-Modus:</label>
                <input type="checkbox" name="<?php echo self::OPTION_NAME_DEBUG ?>" value="1" <?php checked(
                    1,
                    get_option(self::OPTION_NAME_DEBUG),
                    true
                ); ?> />
                <small>Sets some custom HTTP-Headers for debugging. Shows advanced options & information</small>
            </div>
            <div class="row <?php echo get_option(self::OPTION_NAME_DEBUG) ? '' : 'hidden' ?>">
                <label>Disable on-page refresh:</label>
                <input type="checkbox" name="<?php echo self::OPTION_NAME_REFRESH_TRANSMISSION_DISABLE ?>"
                       value="1" <?php checked(
                    1,
                    get_option(self::OPTION_NAME_REFRESH_TRANSMISSION_DISABLE),
                    true
                ); ?> />
                <small>Check to disable on page refreshes. Thus data will only be sent to uptain when the browser
                    performs a full-page-load. See information below when to do so</small>
            </div>
            <div class="row <?php echo get_option(self::OPTION_NAME_DEBUG) ? '' : 'hidden' ?>">
                <label>on-page refresh interval:</label>
                <input type="number" min="<?php echo self::MIN_REFRESH_MS ?>" max="120000"
                       name="<?php echo self::OPTION_NAME_INCLUDE_REFRESH_INTERVAL ?>"
                       value="<?php echo esc_attr(
                           get_option(self::OPTION_NAME_INCLUDE_REFRESH_INTERVAL, self::DEFAULT_REFRESH_MS)
                       ); ?>"/>
                <small>Milliseconds. Do not set below <?php echo self::MIN_REFRESH_MS ?>. Recommended: Between 20000 and
                    30000. Lower values do have
                    impact on WordPress overall Performance. Use option above to disable</small>
            </div>
            <div class="row <?php echo get_option(self::OPTION_NAME_DEBUG) ? '' : 'hidden' ?>">
                <label>Disable initial data sending:</label>
                <input type="checkbox" name="<?php echo self::OPTION_NAME_INITIAL_TRANSMISSION_DISABLE ?>"
                       value="1" <?php checked(
                    1,
                    get_option(self::OPTION_NAME_INITIAL_TRANSMISSION_DISABLE),
                    true
                ); ?> />
                <small>Check to disable data transmission to uptain on full-page-load. See information below when to do
                    so</small>
            </div>
            <div class="row <?php echo get_option(self::OPTION_NAME_DEBUG) ? '' : 'hidden' ?>">
                <label>Information:</label>
                <div><p>This plugin uses the WP-REST-API to update values and push them to uptain when no
                        full-page-refresh is happening client side. If you disabled WP-Rest-API this will not work.
                        Worse, if you added basic authentication in front of your Rest-API, users will be prompted a
                        credential window. Disable on-page refresh in those cases.</p>
                    <p>This plugin may send wrong information if aggressive server-side-caching is being performed. If
                        you encounter such issues you can disable the initial data sending (and should so enable the
                        on-page-refresh which is not being cached). Please also clear your cache after changing those
                        settings.</p></div>
            </div>
            <?php submit_button(); ?>
        </form>
        <?php
    }
}
