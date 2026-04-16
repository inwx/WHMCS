<?php

namespace INWX\Markups\Helpers;

/**
 * UrlHelper.
 *
 * Builds URLs for the addon module.
 */
class UrlHelper
{
    /**
     * Build module URL with tool parameter.
     *
     * @param string $moduleLink Base module link
     * @param string $tool       Tool name (default: 'markups')
     * @param array  $params     Additional query parameters
     *
     * @return string Complete URL
     */
    public static function build(string $moduleLink, string $tool = 'markups', array $params = []): string
    {
        $url = $moduleLink;
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'tool=' . urlencode($tool);

        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $url .= '&' . urlencode($key) . '=' . urlencode($value);
            }
        }

        return $url;
    }
}
