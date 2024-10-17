<?php

/**
 * @file
 * Contains Site Audit render helper
 */

namespace SiteAudit\Util;

class RenderHelper {

    /**
     * Output a Drupal render array, object or string as plain text.
     *
     * @param string $data
     *   Data to render.
     *
     * @return string
     *   The plain-text representation of the input.
     */
    public static function render($data)
    {
        if (is_array($data)) {
            if (self::isDrupal7()) {
                $data = drupal_render($data);
            } else {
                $data = \Drupal::service('renderer')->renderRoot($data);
            }
        }

        if (self::isDrupal7()) {
            $data = drupal_html_to_text($data);
        } else {
            $data = \Drupal\Core\Mail\MailFormatHelper::htmlToText($data);
        }

        return $data;
    }

    /**
     * Check if the current Drupal version is 7.
     *
     * @return bool
     *   TRUE if Drupal 7, FALSE otherwise.
     */
    protected static function isDrupal7() {
        return version_compare(VERSION, '8.0', '<');
    }
}
