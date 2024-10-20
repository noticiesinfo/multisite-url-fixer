<?php

namespace Noticiesinfo\Utils;

/**
 * Class URLFixer
 */
class URLFixer
{
    /**
     * Add filters to verify/fix URLs.
     */
    public function addFilters()
    {
        // Apply filters conditionally based on whether it's the main site
        if (is_main_site()) {
            add_filter('option_home', [$this, 'fixHomeURL']);
            add_filter('option_siteurl', [$this, 'fixSiteURL']);
            add_filter('network_site_url', [$this, 'fixNetworkSiteURL'], 10, 3);
        } else {
            add_filter('option_siteurl', [$this, 'fixSubsiteSiteURL']);
            add_filter('login_url', [$this, 'fixSubsiteLoginURL'], 10, 3);
            add_filter('lostpassword_url', [$this, 'fixSubsiteLostPasswordURL'], 10, 2);
            add_filter('logout_url', [$this, 'fixSubsiteLogoutURL'], 10, 2);

        }
    }

    /**
     * Ensure that home URL does not contain the /wp subdirectory for the main site.
     */
    public function fixHomeURL($value)
    {
        if (substr($value, -3) === '/wp') {
            $value = substr($value, 0, -3);
        }
        return $value;
    }

    /**
     * Ensure that site URL contains the /wp subdirectory for the main site.
     */
    public function fixSiteURL($url)
    {
        if (substr($url, -3) !== '/wp') {
            $url .= '/wp';
        }
        return $url;
    }

    /**
     * Ensure that the network site URL contains the /wp subdirectory for the main site.
     */
    public function fixNetworkSiteURL($url, $path, $scheme)
    {
        $path = ltrim($path, '/');
        $base_url = substr($url, 0, strlen($url) - strlen($path));

        if (substr($base_url, -3) !== 'wp/') {
            $base_url .= 'wp/';
        }

        return $base_url . $path;
    }

    /**
     * Ensure that subsites' site URLs include the /wp subdirectory.
     */
    public function fixSubsiteSiteURL($url)
    {
        if (substr($url, -3) !== '/wp') {
            $url .= '/wp';
        }
        return $url;
    }

    /**
     * Ensure that subsite login URL contains the /wp subdirectory.
     *
     * @param string $login_url The current URL for the login page.
     * @param string $redirect The URL to redirect to after successful login.
     * @param bool $force_reauth Whether to force reauthentication.
     * @return string The fixed subsite login URL with necessary parameters added.
     */
    public function fixSubsiteLoginURL($login_url, $redirect, $force_reauth)
    {
        $login_url = home_url('/wp/wp-login.php', 'login');

        if ($redirect) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }
        if ($force_reauth) {
            $login_url = add_query_arg('reauth', '1', $login_url);
        }
        return $login_url;
    }

    /**
     * Fixes the lost password URL by setting the initial URL to the lost password action.
     *
     * @param string $lostpassword_url The original lost password URL to be fixed.
     * @param string|null $redirect Optional. The URL to redirect the user to after resetting the password.
     */
    public function fixSubsiteLostPasswordURL($lostpassword_url, $redirect)
    {
        $lostpassword_url = home_url('/wp/wp-login.php?action=lostpassword', 'login');

        if ($redirect) {
            $lostpassword_url = add_query_arg('redirect_to', urlencode($redirect), $lostpassword_url);
        }
        return $lostpassword_url;
    }

    /**
     * Fixes the logout URL by setting the initial URL to the logout action and adding a redirect parameter if provided.
     *
     * @param string $logout_url The original logout URL to be fixed.
     * @param string|null $redirect Optional. The URL to redirect the user to after logging out.
     */
    public function fixSubsiteLogoutURL($logout_url, $redirect)
    {
        $logout_url = home_url('/wp/wp-login.php?action=logout', 'login');

        if ($redirect) {
            $logout_url = add_query_arg('redirect_to', urlencode($redirect), $logout_url);
        }
        $logout_url = wp_nonce_url($logout_url, 'log-out');
        return $logout_url;
    }
}
