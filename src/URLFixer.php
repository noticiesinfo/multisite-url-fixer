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
        add_filter('option_siteurl', [$this, 'fixSubsiteSiteURL']);
        add_filter('login_url', [$this, 'fixSubsiteLoginURL'], 10, 3);
        add_filter('lostpassword_url', [$this, 'fixSubsiteLostPasswordURL'], 10, 2);
        add_filter('logout_url', [$this, 'fixSubsiteLogoutURL'], 10, 2);
        add_filter('plugins_url', [$this, 'fixSubstitutePluginsURL'], 10, 3);
        add_filter('upload_dir', [$this, 'fixUploadURL']);
        add_filter('wp_get_attachment_url', [$this, 'fixAttachmentURL'], 10, 2);
        add_filter('wp_get_attachment_metadata', [$this, 'fixAttachmentMetadataURL'], 10, 2);
    }

    /**
     * Rewrites URLs (like plugin/content assets) that incorrectly use the defunct
     * network domain, replacing it with the current site's domain.
     *
     * @param string      $url     The full URL being filtered (e.g., https://sesam.disquet.net/app/plugins/...).
     * @param string      $path    The relative path requested (e.g., 'wordpress-seo/css/dist/notifications-2490.css').
     * @param string|null $plugin  Plugin-specific path info (for plugins_url filter). Null for content_url.
     * @return string The potentially corrected URL.
     */
    public function fixSubstitutePluginsURL($url, $path, $plugin = null)
    {
        // 1. Only proceed if this is a Multisite installation.
        if (!is_multisite()) {
            return $url;
        }

        // 2. Get the IDs for the current site and the main network site.
        $current_blog_id = get_current_blog_id();
        $main_site_id = get_main_site_id(); // ID of the site designated as primary for the network

        // 3. Apply rewrite logic ONLY if we are on a subsite (not the main site).
        // Also ensures we have valid (>0) blog IDs.
        if ($current_blog_id <= 0 || $main_site_id <= 0 || $current_blog_id === $main_site_id) {
            // Do not rewrite if not on a subsite, or if IDs are invalid.
            return $url;
        }

        // 4. Parse the incoming URL to extract its hostname.
        $input_url_parts = wp_parse_url($url);
        // Check if parsing was successful and host exists, convert to lowercase for comparison
        $input_url_host = isset($input_url_parts['host']) ? strtolower($input_url_parts['host']) : null;

        // If URL is malformed or lacks a host, return it unchanged.
        if (empty($input_url_host)) {
            return $url;
        }

        // 5. Get the correct full URL and hostname for the *current* site context.
        $current_site_url = get_site_url($current_blog_id); // e.g., "http://noticies.info"
        $current_site_parts = wp_parse_url($current_site_url);
        $current_site_host = isset($current_site_parts['host']) ? strtolower($current_site_parts['host']) : null;

        // If we can't get the current site's host, bail.
        if (empty($current_site_host)) {
            return $url;
        }

        // 6. Check if the input URL's host *already* matches the current site's host.
        if ($input_url_host === $current_site_host) {
            // The URL is already using the correct domain for this site. No change needed.
            return $url;
        }

        // 7. Get the hostname of the *main network site*.
        $network_main_site_url = get_site_url($main_site_id); // e.g., "https://sesam.disquet.net"
        $network_main_parts = wp_parse_url($network_main_site_url);
        $network_main_host = isset($network_main_parts['host']) ? strtolower($network_main_parts['host']) : null;

        // If we can't get the main network site's host, bail.
        if (empty($network_main_host)) {
            return $url;
        }

        // 8. Core Rewrite Condition:
        // Rewrite if the input URL's host matches the main network site's host
        // (and we already established it *doesn't* match the current site's host).
        if ($input_url_host === $network_main_host) {
            // Extract the components (path, query, fragment) from the original URL.
            $url_path     = isset($input_url_parts['path']) ? $input_url_parts['path'] : '';
            $url_query    = isset($input_url_parts['query']) ? '?' . $input_url_parts['query'] : '';
            $url_fragment = isset($input_url_parts['fragment']) ? '#' . $input_url_parts['fragment'] : '';

            // Rebuild the URL using the *current site's* full URL as the base.
            // Ensure correct handling of slashes between base URL and path.
            $new_url = rtrim($current_site_url, '/') . $url_path . $url_query . $url_fragment;

            // Return the corrected URL if it was successfully constructed.
            if (!empty($new_url)) {
                // Optional: Log the change for debugging purposes. Remove in production if not needed.
                // error_log("URLFixer: Rewriting asset URL from '$url' to '$new_url' on Site ID $current_blog_id");
                return $new_url;
            }
        }

        // If no conditions were met for rewriting, return the original URL.
        return $url;
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

    /**
     * Fixes the upload directory URLs for subsites to ensure they point to the correct content directory.
     *
     * @param array $dirs The upload directory information.
     * @return array The modified upload directory information with corrected URLs.
     */
    public function fixUploadURL($dirs)
    {
        if (!is_main_site()) {
            $site_url = get_site_url();
            $content_url = content_url();

            $dirs['baseurl'] = $content_url . '/uploads';
            $dirs['basedir'] = WP_CONTENT_DIR . '/uploads';
            $dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
            $dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
        }
        return $dirs;
    }

    /**
     * Fixes the attachment URL for subsites to ensure it points to the correct content directory.
     *
     * @param string $url The original attachment URL.
     * @param int $post_id The ID of the post associated with the attachment.
     * @return string The modified attachment URL with the correct site URL.
     */
    public function fixAttachmentURL($url, $post_id)
    {
        if (!is_main_site()) {
            $site_url = get_site_url();
            $relative = wp_make_link_relative($url);
            if (strpos($site_url, '/wp') !== false) {
                $site_url = str_replace('/wp', '', $site_url);
            }
            $url = $site_url . $relative;
        }
        return $url;
    }

    /**
     * Fixes the attachment metadata URLs for subsites to ensure they point to the correct content directory.
     *
     * @param array $data The attachment metadata.
     * @param int $post_id The ID of the post associated with the attachment.
     * @return array The modified attachment metadata with corrected URLs.
     */
    public function fixAttachmentMetadataURL($data, $post_id)
    {
        if (!is_main_site() && isset($data['sizes'])) {
            $base_url = content_url('/uploads') . '/' . dirname($data['file']);
            foreach ($data['sizes'] as &$size) {
                if (isset($size['file'])) {
                    $size['url'] = $base_url . '/' . $size['file'];
                }
            }
        }
        return $data;
    }
}
