<?php

declare(strict_types=1);

/**
 * @author Timo Förster <tfoerster@webfoersterei.de>
 * @date 02.06.2023
 */

namespace Webfoersterei\Wordpress\Plugin\UptainTracking;

class WooCommerceTracker implements TrackerInterface, RegistrableWordpressInterface
{
    private $WC;
    private static $overrideOrderId = null;

    private function getWooCommerce()
    {
        global $woocommerce;

        if (function_exists('WC')) {
            $this->WC = WC();
            Plugin::debug('get-woo', 'wc-function');
        } else {
            $this->WC = $woocommerce;
            Plugin::debug('get-woo', 'global-var');
        }

        if (!$this->WC) {
            Plugin::debug('get-woo-EMER', 'No WooCommerce');

            return null;
        }

        Plugin::debug('get-woo-version', $this->WC->version);

        if (null === $this->WC->cart) {
            $this->WC->frontend_includes();
            Plugin::debug('get-woo-cart', 'load');
            wc_load_cart();
        }

        return $this->WC;
    }

    public function registerInWordpress(): void
    {
        add_action('woocommerce_checkout_order_created', function ($order) {
            Plugin::debug('wc-hook-order-created', true);
            Plugin::debug('wc-hook-order-created-id', $order->get_id());
            if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['freshOrder'] = $order->get_id();
            self::$overrideOrderId = $order->get_id();
        });

        add_action('woocommerce_thankyou', function ($orderID) {
            Plugin::debug('wc-hook-thankyou-orderid', $orderID);
            if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $order = wc_get_order($orderID);
            Plugin::debug(
                'wc-hook-thankyou-created-ts',
                $order->get_date_created() ? $order->get_date_created()->getTimestamp() : null
            );
            Plugin::debug(
                'wc-hook-thankyou-now-ts',
                (new \DateTimeImmutable('now -20 seconds'))->getTimestamp()
            );
            if ($order->get_date_created() && $order->get_date_created()->getTimestamp() > (new \DateTimeImmutable(
                    'now -20 seconds'
                ))->getTimestamp()) {
                self::$overrideOrderId = $orderID;
                $_SESSION['freshOrder'] = $orderID;
            }
        });
    }

    public function getCurrentTrackingData(): array
    {
        $result = array_merge($this->getCartTrackingData(), $this->getPersonalData());

        return array_filter($result, static function ($element) {
            return $element !== null && $element !== '';
        });
    }

    public function getCartTrackingData(): array
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $result = [];

        Plugin::debug('override-orderid', self::$overrideOrderId);
        Plugin::debug('session-orderid', $_SESSION['freshOrder'] ?? null);

        if (null === self::$overrideOrderId && isset($_SESSION['freshOrder']) && (int)$_SESSION['freshOrder']) {
            self::$overrideOrderId = $_SESSION['freshOrder'];
            $_SESSION['freshOrder'] = null;
        }

        if ($this->getWooCommerce()) {
            Plugin::debug('ctd-wc', true);
            Plugin::debug('ctd-wc-cart', (bool)$this->getWooCommerce()->cart);
            Plugin::debug(
                'ctd-wc-cart-total',
                $this->getWooCommerce()->cart ? $this->getWooCommerce()->cart->cart_contents_total : null
            );
            $result['currency'] = get_woocommerce_currency();
            $result['scv'] = $this->getWooCommerce()->cart ? ($this->getWooCommerce(
            )->cart->cart_contents_total ?? 0) : 0;
            $coupons = $this->getWooCommerce()->cart ? $this->getWooCommerce()->cart->get_applied_coupons() : [];
            $result['usedvoucher'] = ($this->getWooCommerce()->cart && count($coupons) >= 1) ? array_pop($coupons) : '';
            $result['voucher-amount'] = $this->getWooCommerce()->cart ? number_format(
                $this->getWooCommerce()->cart->get_cart_discount_total(),
                2
            ) : null;
            $result['page'] = $this->getPageData();
            $result['success'] = null;

            if ((int)self::$overrideOrderId) {
                $order = wc_get_order((int)self::$overrideOrderId);
                if ($order) {
                    $discountExcludingTax = $order->get_total_discount(true);
                    $result['success'] = '1';
                    $result['scv'] = $order->get_subtotal(
                        ) - $discountExcludingTax; // subtotal is excluding Tax, but also excluding vouchers
                    $coupons = $order->get_coupon_codes();
                    $result['usedvoucher'] = count($coupons) >= 1 ? array_pop($coupons) : '';
                    $result['voucher-amount'] = number_format($discountExcludingTax ?: 0, 2) ?: null;
                    $result['ordernumber'] = $order->get_id();
                }
            }
        }

        return $result;
    }

    public function getPersonalData(): array
    {
        $currentUser = \wp_get_current_user();

        $result = [];

        Plugin::debug('user-id', $currentUser->ID);

        if ($this->shouldIncludePersonalData($currentUser)) {
            Plugin::debug('will-include-personal-data', 'yes');
            $result['email'] = $currentUser->user_email;
            $nameParts = explode(' ', $currentUser->display_name);
            $firstname = array_pop($nameParts);
            $result['firstname'] = $currentUser->user_firstname ?: ($firstname ?? '');
            $result['lastname'] = $currentUser->user_lastname ?: implode(' ', $nameParts);
            // $result['gender'] = ''; // Call 2.6. mit hn: Weglassen
            // $result['customergroup'] = ''; // Call 2.6. mit hn: Weglassen oder filtern aus beliebten Plugins. WooCommerce Groups sind eher Memberships für Subscriptions. Daher weggelassen. https://woocommerce.com/document/groups-woocommerce/
            $result['uid'] = $currentUser->ID;

            if (get_option(Admin::OPTION_NAME_INCLUDE_NET_WORTH, false)) {
                Plugin::debug('will-include-net-worth', 'yes');
                $orders = wc_get_orders(['customer' => $currentUser->ID, 'limit' => -1]);
                $subtotals = array_map(fn($order) => $order->get_subtotal(), $orders);
                $result['revenue'] = array_sum($subtotals);
            }
        }

        return $result;
    }

    private function shouldIncludePersonalData($currentUser): bool
    {
        if (!$currentUser || !$currentUser->ID || !get_option(Admin::OPTION_NAME_INCLUDE_PERSONAL_DATA, false)) {
            Plugin::debug('will-include-personal-data', 'no-user-or-no-setting');

            return false;
        }

        if (!$this->getWooCommerce()) {
            return false;
        }

        $completedOrders = wc_get_orders(['customer' => $currentUser->ID, 'limit' => 1]);

        return count($completedOrders) >= 1;
    }

    private function getPageData()
    {
        if (is_front_page()) {
            return 'home';
        }

        if (is_search()) {
            return 'search';
        }

        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
            return 'success';
        }

        if (function_exists('is_product') && is_product()) {
            return 'product';
        }

        if (function_exists('is_product_category') && is_product_category()) {
            return 'category';
        }

        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }

        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }

        return 'other';
    }
}
