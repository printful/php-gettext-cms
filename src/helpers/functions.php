<?php

use Printful\GettextCms\MessageManager;

if (!function_exists('_n')) {
    /**
     * Plural translation from default domain
     *
     * @param string $singular Original string in singular form
     * @param string $plural Original string in plural form
     * @param int $n Value number
     * @return string
     */
    function _n($singular, $plural, $n)
    {
        return ngettext($singular, $plural, $n);
    }
}

if (!function_exists('_nc')) {
    /**
     * Translation with context from default domain
     *
     * @param string $context Free form string, but should use a know list of keywords.
     * @param string $singular Original string to be translated
     * @param string $plural
     * @param int $n
     * @return string
     */
    function _nc($context, $singular, $plural, $n)
    {
        $singular = "{$context}\004{$singular}";
        $translation = ngettext($singular, $plural, $n);

        return $translation === $singular ? $singular : $translation;
    }
}


if (!function_exists('_c')) {
    /**
     * Translation with context from default domain
     *
     * @param string $context Free form string, but should use a know list of keywords.
     * @param string $message Original string to be translated
     * @return string
     */
    function _c($context, $message)
    {
        $contextString = "{$context}\004{$message}";
        $translation = _($contextString);

        return $translation === $contextString ? $message : $translation;
    }
}


if (!function_exists('_dc')) {
    /**
     * Translation with domain and context
     *
     * @param string $domain Name of the domain
     * @param string $context Free form string, custom string type/category
     * @param string $message Original string
     * @return string
     */
    function _dc($domain, $context, $message)
    {
        $domain = MessageManager::getInstance()->getRevisionedDomain($domain);
        $contextString = "{$context}\004{$message}";
        $translation = dgettext($domain, $contextString);

        return $translation === $contextString ? $message : $translation;
    }
}


if (!function_exists('_d')) {
    /**
     * Translation with domain
     *
     * @param string $domain Name of the domain
     * @param string $message Original string
     * @return string
     */
    function _d($domain, $message)
    {
        $domain = MessageManager::getInstance()->getRevisionedDomain($domain);

        return dgettext($domain, $message);
    }
}


if (!function_exists('_dn')) {
    /**
     * Plural domain translation
     *
     * @param string $domain Name of the domain
     * @param string $singular Original string in singular form
     * @param string $plural Original string in plural form
     * @param int $n Value number
     * @return string
     */
    function _dn($domain, $singular, $plural, $n)
    {
        $domain = MessageManager::getInstance()->getRevisionedDomain($domain);

        return dngettext($domain, $singular, $plural, $n);
    }
}


if (!function_exists('_dnc')) {
    /**
     * Plural translation with domain and context
     *
     * @param string $domain Name of the domain
     * @param string $context
     * @param string $singular Original string in singular form
     * @param string $plural Original string in plural form
     * @param int $n Value number
     * @return string
     */
    function _dnc($domain, $context, $singular, $plural, $n)
    {
        $domain = MessageManager::getInstance()->getRevisionedDomain($domain);
        $message = "{$context}\004{$singular}";
        $translation = dngettext($domain, $message, $plural, $n);

        return $translation === $message ? $singular : $translation;
    }
}