<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS\Internal;

use BearFramework\App;
use BearCMS\Internal\Cookies;
use BearCMS\Internal\Themes as InternalThemes;

/**
 * Information about the current theme
 */
class CurrentTheme
{

    /**
     * Local cache
     * 
     * @var array 
     */
    private static $cache = [];

    /**
     * Returns the id of the current active theme or theme in preview
     * 
     * @return string The id of the current active theme or theme in preview
     */
    static public function getID(): string
    {
        if (!isset(self::$cache['id'])) {
            $cookies = Cookies::getList(Cookies::TYPE_SERVER);
            self::$cache['id'] = isset($cookies['tmpr']) ? $cookies['tmpr'] : \BearCMS\Internal\Themes::getActiveThemeID();
        }
        return self::$cache['id'];
    }

    /**
     * 
     * @param string|null $userID
     * @return string|null
     */
    static public function getCacheItemKey($userID = null)
    {
        $themeID = self::getID();
        $version = InternalThemes::getVersion($themeID);
        if ($version === null) {
            return null;
        }
        return 'bearcms-theme-options-' . \BearCMS\Internal\Options::$dataCachePrefix . '-' . md5($themeID) . '-' . md5($version) . '-' . md5($userID) . '-2';
    }

    /**
     * Returns an array containing all theme options
     * 
     * @return array An array containing all theme options
     */
    static public function getOptions(): \BearCMS\Themes\Options
    {
        if (!isset(self::$cache['options'])) {
            $app = App::get();
            $cacheKey = self::getCacheItemKey($app->bearCMS->currentUser->exists() ? $app->bearCMS->currentUser->getID() : null);
            if ($cacheKey === null) {
                $value = [self::walkOptions(1), self::getOptionsHtml()];
            } else {
                $value = $app->cache->getValue($cacheKey);
                if ($value === null) {
                    $value = [self::walkOptions(1), self::getOptionsHtml()];
                    $app->cache->set($app->cache->make($cacheKey, json_encode($value)));
                } else {
                    $value = json_decode($value, true);
                }
            }

            $applyImageUrls = function($text) use ($app) {
                $matches = [];
                preg_match_all('/url\((.*?)\)/', $text, $matches);
                if (!empty($matches[1])) {
                    $matches[1] = array_unique($matches[1]);
                    $search = [];
                    $replace = [];
                    foreach ($matches[1] as $key) {
                        $filename = $app->bearCMS->data->getRealFilename($key);
                        if ($filename !== $key) {
                            $search[] = $key;
                            $replace[] = $app->assets->getUrl($filename, ['cacheMaxAge' => 999999999]);
                        }
                    }
                    $text = str_replace($search, $replace, $text);
                }
                return $text;
            };

            $value[1] = $applyImageUrls($value[1]);
            self::$cache['options'] = new \BearCMS\Themes\Options($value[0], $value[1]);
        }
        return self::$cache['options'];
    }

    /**
     * Iterates over all options and returns specific result
     * 
     * @param int $resultType 1 - values, 2 - definition
     * @return array
     */
    static private function walkOptions(int $resultType): array
    {
        $cacheKey = 'options' . $resultType; //todo optimize
        $app = App::get();
        if (!isset(self::$cache[$cacheKey])) {
            $currentThemeID = self::getID();
            $result = [];
            $values = null;
            if ($app->bearCMS->currentUser->exists()) {
                $userOptions = $app->bearCMS->data->themes->getTempOptions($currentThemeID, $app->bearCMS->currentUser->getID());
                if (is_array($userOptions)) {
                    $values = $userOptions;
                }
            }
            if ($values === null) {
                $values = $app->bearCMS->data->themes->getOptions($currentThemeID);
            }
// todo optimize
            $themeOptions = \BearCMS\Internal\Themes::getOptions($currentThemeID);
            if (!empty($themeOptions)) {
                $walkOptions = function($options) use (&$result, $values, &$walkOptions, $resultType) {
                    foreach ($options as $option) {
                        if (isset($option['id'])) {
                            if (isset($values[$option['id']])) {
                                $result[$option['id']] = $values[$option['id']];
                            } else {
                                $result[$option['id']] = isset($option['defaultValue']) ? (is_array($option['defaultValue']) ? json_encode($option['defaultValue']) : $option['defaultValue']) : null;
                            }
                            if ($resultType === 2) {
                                $result[$option['id']] = [$result[$option['id']], $option];
                            }
                        }
                        if (isset($option['options'])) {
                            $walkOptions($option['options']);
                        }
                    }
                };
                $walkOptions($themeOptions);
            }
            self::$cache[$cacheKey] = $result;
        }
        return self::$cache[$cacheKey];
    }

    /**
     * Returns HTML code generated by the options
     * 
     * @return string The HTML code for the options
     */
    static private function getOptionsHtml(): string
    {
        $linkTags = [];
        $app = App::get();
        $result = [];
        $options = self::walkOptions(2);
        $applyFontNames = function($text) use (&$linkTags) {
            $webSafeFonts = [
                'Arial' => 'Arial,Helvetica,sans-serif',
                'Arial Black' => '"Arial Black",Gadget,sans-serif',
                'Comic Sans' => '"Comic Sans MS",cursive,sans-serif',
                'Courier' => '"Courier New",Courier,monospace',
                'Georgia' => 'Georgia,serif',
                'Impact' => 'Impact,Charcoal,sans-serif',
                'Lucida' => '"Lucida Sans Unicode","Lucida Grande",sans-serif',
                'Lucida Console' => '"Lucida Console",Monaco,monospace',
                'Palatino' => '"Palatino Linotype","Book Antiqua",Palatino,serif',
                'Tahoma' => 'Tahoma,Geneva,sans-serif',
                'Times New Roman' => '"Times New Roman",Times,serif',
                'Trebuchet' => '"Trebuchet MS",Helvetica,sans-serif',
                'Verdana' => 'Verdana,Geneva,sans-serif'
            ];

            $matches = [];
            preg_match_all('/font\-family\:(.*?);/', $text, $matches);
            foreach ($matches[0] as $i => $match) {
                $fontName = $matches[1][$i];
                if (isset($webSafeFonts[$fontName])) {
                    $text = str_replace($match, 'font-family:' . $webSafeFonts[$fontName] . ';', $text);
                } elseif (strpos($fontName, 'googlefonts:') === 0) {
                    $googleFontName = substr($fontName, strlen('googlefonts:'));
                    $text = str_replace($match, 'font-family:\'' . $googleFontName . '\';', $text);
                    if (!isset($linkTags[$googleFontName])) {
                        $linkTags[$googleFontName] = '<link href="//fonts.googleapis.com/css?family=' . urlencode($googleFontName) . '" rel="stylesheet" type="text/css" />';
                    }
                }
            }
            return $text;
        };

        $cssCode = '';
        foreach ($options as $optionData) {
            $optionValue = (string) $optionData[0];
            $optionDefinition = $optionData[1];
            $optionType = $optionDefinition['type'];
            if ($optionType === 'cssCode') {
                $cssCode .= $optionValue;
            } else {
                if (isset($optionDefinition['cssOutput'])) {
                    foreach ($optionDefinition['cssOutput'] as $outputDefinition) {
                        if (is_array($outputDefinition)) {
                            if (isset($outputDefinition[0], $outputDefinition[1]) && $outputDefinition[0] === 'selector') {
                                $selector = $outputDefinition[1];
                                $selectorVariants = ['', '', ''];
                                if ($optionType === 'css' || $optionType === 'cssText' || $optionType === 'cssTextShadow' || $optionType === 'cssBackground' || $optionType === 'cssPadding' || $optionType === 'cssMargin' || $optionType === 'cssBorder' || $optionType === 'cssRadius' || $optionType === 'cssShadow' || $optionType === 'cssSize' || $optionType === 'cssTextAlign') {
                                    $temp = isset($optionValue[0]) ? json_decode($optionValue, true) : [];
                                    foreach ($temp as $key => $value) {
                                        $pseudo = substr($key, -6);
                                        if ($pseudo === ':hover') {
                                            $selectorVariants[1] .= substr($key, 0, -6) . ':' . $value . ';';
                                        } else if ($pseudo === 'active') { // optimization
                                            if (substr($key, -7) === ':active') {
                                                $selectorVariants[2] .= substr($key, 0, -7) . ':' . $value . ';';
                                            } else {
                                                $selectorVariants[0] .= $key . ':' . $value . ';';
                                            }
                                        } else {
                                            $selectorVariants[0] .= $key . ':' . $value . ';';
                                        }
                                    }
                                }
                                if ($selectorVariants[0] !== '') {
                                    if (!isset($result[$selector])) {
                                        $result[$selector] = '';
                                    }
                                    $result[$selector] .= $selectorVariants[0];
                                }
                                if ($selectorVariants[1] !== '') {
                                    if (!isset($result[$selector . ':hover'])) {
                                        $result[$selector . ':hover'] = '';
                                    }
                                    $result[$selector . ':hover'] .= $selectorVariants[1];
                                }
                                if ($selectorVariants[2] !== '') {
                                    if (!isset($result[$selector . ':active'])) {
                                        $result[$selector . ':active'] = '';
                                    }
                                    $result[$selector . ':active'] .= $selectorVariants[2];
                                }
                            } elseif (isset($outputDefinition[0], $outputDefinition[1], $outputDefinition[2]) && $outputDefinition[0] === 'rule') {
                                $selector = $outputDefinition[1];
                                if (!isset($result[$selector])) {
                                    $result[$selector] = '';
                                }
                                $result[$selector] .= $outputDefinition[2];
                            }
                        }
                    }
                }
            }
        }
        $style = '';
        foreach ($result as $key => $value) {
            $style .= $key . '{' . $value . '}';
        }
        $style = $applyFontNames($style);
        $cssCode = trim($cssCode); // Positioned in different style tag just in case it's invalid
        return '<html><head>' . implode('', $linkTags) . '<style>' . $style . '</style>' . ($cssCode !== '' ? '<style>' . $cssCode . '</style>' : '') . '</head></html>';
    }

}
