<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) 2016 Amplilabs Ltd.
 * Free to use under the MIT license.
 */

namespace BearCMS;

use BearFramework\App;

/**
 * Contains reference to the different data types
 * 
 * @property \BearCMS\Data\Addons $addons Information about the addons managed by Bear CMS
 * @property \BearCMS\Data\Blog $blog Information about the blog posts
 * @property \BearCMS\Data\Comments $comments Information about the comments
 * @property \BearCMS\Data\CommentsThreads $commentsThreads Information about the comments threads
 * @property \BearCMS\Data\Pages $pages Information about the site pages
 * @property \BearCMS\Data\Settings $settings Information about the site settings
 * @property \BearCMS\Data\Themes $themes Information about the site themes
 * @property \BearCMS\Data\Users $users Information about the CMS users (administrators)
 */
class Data
{

    /**
     * Dependency Injection container
     * 
     * @var \BearFramework\App\ServiceContainer 
     */
    public $container = null;

    function __construct()
    {
        $this->container = new \BearFramework\App\Container();

        $this->container->set('addons', \BearCMS\Data\Addons::class);
        $this->container->set('blog', \BearCMS\Data\Blog::class);
        $this->container->set('comments', \BearCMS\Data\Comments::class);
        $this->container->set('commentsThreads', \BearCMS\Data\CommentsThreads::class);
        $this->container->set('pages', \BearCMS\Data\Pages::class);
        $this->container->set('settings', \BearCMS\Data\Settings::class);
        $this->container->set('themes', \BearCMS\Data\Themes::class);
        $this->container->set('users', \BearCMS\Data\Users::class);
    }

    /**
     * Returns an object from the dependency injection container
     * 
     * @param string $name The service name
     * @return object Object from the dependency injection container
     * @throws \Exception
     */
    public function __get($name)
    {
        if ($this->container->exists($name)) {
            return $this->container->get($name);
        }
        throw new \Exception('Invalid property name');
    }

    /**
     * Returns information about whether the service is added in the dependency injection container
     * 
     * @param string $name The name of the service
     * @return boolen TRUE if services is added. FALSE otherwise.
     */
    public function __isset($name)
    {
        return $this->container->exists($name);
    }

    /**
     * Converts data:, app:, addon:id: filenames to real filenames
     * 
     * @param string $filename
     * @return string The real filename
     * @throws \InvalidArgumentException
     */
    public function getRealFilename($filename)
    {
        if (!is_string($filename)) {
            throw new \InvalidArgumentException('');
        }
        $app = App::get();
        if (substr($filename, 0, 5) === 'data:') {
            $filename = $app->data->getFilename(substr($filename, 5));
        } elseif (substr($filename, 0, 4) === 'app:') {
            $filename = $app->config->appDir . DIRECTORY_SEPARATOR . substr($filename, 4);
        } elseif (substr($filename, 0, 6) === 'addon:') {
            $temp = explode(':', $filename, 3);
            if (sizeof($temp) === 3) {
                $addonDir = \BearFramework\Addons::get($temp[1])['dir'];
                $filename = $addonDir . DIRECTORY_SEPARATOR . $temp[2];
            }
        }
        return $filename;
    }

}
