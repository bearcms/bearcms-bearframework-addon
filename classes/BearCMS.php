<?php

/*
 * Bear CMS addon for Bear Framework
 * https://bearcms.com/
 * Copyright (c) Amplilabs Ltd.
 * Free to use under the MIT license.
 */

use BearFramework\App;
use BearCMS\Internal;
use BearCMS\Internal\Config;
use IvoPetkov\HTML5DOMDocument;
use BearCMS\Internal2;

/**
 * 
 * @property-read \BearCMS\CurrentUser $currentUser Information about the current CMS administrator.
 * @property-read \BearCMS\Themes $themes Information about the enabled Bear CMS themes.
 * @property-read \BearCMS\Addons $addons Information about the enabled Bear CMS addons.
 */
class BearCMS
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * Bear CMS version.
     */
    const VERSION = '0.5.0';

    /**
     *
     * @var \BearFramework\App 
     */
    private $app;

    /**
     *
     * @var \BearFramework\App\Context 
     */
    private $context;

    /**
     * Constructs a new Bear CMS instance.
     */
    function __construct()
    {
        $this
                ->defineProperty('currentUser', [
                    'init' => function() {
                        return new \BearCMS\CurrentUser();
                    },
                    'readonly' => true
                ])
                ->defineProperty('themes', [
                    'init' => function() {
                        return new \BearCMS\Themes();
                    },
                    'readonly' => true
                ])
                ->defineProperty('addons', [
                    'init' => function() {
                        return new \BearCMS\Addons();
                    },
                    'readonly' => true
                ])
        ;

        $this->app = App::get();
        $this->context = $this->app->context->get(__FILE__);
    }

    /**
     * Initializes the Bear CMS instance.
     * 
     * @param array $config A list of configuration variables.
     * @return void
     */
    public function initialize(array $config): void
    {
        Config::set($config);

        $hasServer = Config::hasServer();

        // Automatically log in the user
        if ($hasServer && (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_DEFAULT'))) {
            $cookies = Internal\Cookies::getList(Internal\Cookies::TYPE_SERVER);
            if (isset($cookies['_a']) && !$this->currentUser->exists()) {
                $data = Internal\Server::call('autologin', [], true);
                if (isset($data['error'])) {
                    $this->currentUser->logout(); // kill the autologin cookie
                }
            }
        }

        // Enable elements
        $hasElements = Config::hasFeature('ELEMENTS');
        if ($hasElements || Config::hasFeature('ELEMENTS_*')) {
            $this->app->components
                    ->addAlias('bearcms-elements', 'file:' . $this->context->dir . '/components/bearcmsElements.php');
            Internal\ElementsTypes::addDefault();

            $this->app->hooks
                    ->add('componentCreated', function($component) {
                        // Updates the BearCMS components when created
                        if ($component->src === 'bearcms-elements') {
                            Internal\ElementsHelper::updateContainerComponent($component);
                        } elseif (isset(Internal\ElementsHelper::$elementsTypesFilenames[$component->src])) {
                            $component->setAttribute('bearcms-internal-attribute-type', Internal\ElementsHelper::$elementsTypesCodes[$component->src]);
                            $component->setAttribute('bearcms-internal-attribute-filename', Internal\ElementsHelper::$elementsTypesFilenames[$component->src]);
                            Internal\ElementsHelper::updateElementComponent($component);
                        }
                    });
            $this->app->serverRequests
                    ->add('bearcms-elements-load-more', function($data) {
                        if (isset($data['serverData'])) {
                            $serverData = Internal\TempClientData::get($data['serverData']);
                            if (is_array($serverData) && isset($serverData['componentHTML'])) {
                                $content = $this->app->components->process($serverData['componentHTML']);
                                $editorContent = Internal\ElementsHelper::getEditableElementsHtml();
                                return json_encode([
                                    'content' => $content,
                                    'editorContent' => (isset($editorContent[0]) ? $editorContent : ''),
                                    'nextLazyLoadData' => (string) Internal\ElementsHelper::$lastLoadMoreServerData
                                ]);
                            }
                        }
                    });

            $this->app->tasks
                    ->define('bearcms-send-contact-form-email', function($data) {
                        $email = $this->app->emails->make();
                        $email->sender->email = $data['senderEmail'];
                        $email->sender->name = $data['senderName'];
                        $email->subject = $data['subject'];
                        $email->content->add($data['content']);
                        $email->recipients->add($data['recipientEmail']);
                        $email->replyToRecipients->add($data['replyToEmail']);
                        $this->app->emails->send($email);
                    });

            if (Config::hasFeature('THEMES')) {
                if ($hasElements || Config::hasFeature('ELEMENTS_HEADING')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Heading"),
                            "options" => [
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Large"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "HeadingLargeCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-heading-element-large", "font-weight:normal;"],
                                                ["selector", $parentSelector . " .bearcms-heading-element-large"]
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Medium"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "HeadingMediumCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-heading-element-medium", "font-weight:normal;"],
                                                ["selector", $parentSelector . " .bearcms-heading-element-medium"]
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Small"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "HeadingSmallCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-heading-element-small", "font-weight:normal;"],
                                                ["selector", $parentSelector . " .bearcms-heading-element-small"]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_TEXT')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Text"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "TextCSS",
                                    "type" => "css",
                                    "cssOutput" => [
                                        ["selector", $parentSelector . " .bearcms-text-element"],
                                        ["rule", $parentSelector . " .bearcms-text-element ul,ol,li", "list-style-position:inside;"]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Links"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "TextLinkCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["selector", $parentSelector . " .bearcms-text-element a"]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_LINK')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Link"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "LinkCSS",
                                    "type" => "css",
                                    "cssOutput" => [
                                        ["rule", $parentSelector . " .bearcms-link-element", "display:inline-block;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;"],
                                        ["selector", $parentSelector . " .bearcms-link-element"]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_IMAGE')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Image"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "ImageCSS",
                                    "type" => "css",
                                    "cssTypes" => ["cssBorder", "cssRadius", "cssShadow"],
                                    "cssOutput" => [
                                        ["rule", $parentSelector . " .bearcms-image-element", "overflow:hidden;"],
                                        ["rule", $parentSelector . " .bearcms-image-element img", "border:0;"],
                                        ["selector", $parentSelector . " .bearcms-image-element"]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_IMAGE_GALLERY')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Image gallery"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "ImageGalleryCSS",
                                    "type" => "css",
                                    "cssTypes" => ["cssPadding", "cssBorder", "cssRadius", "cssShadow", "cssBackground"],
                                    "cssOutput" => [
                                        ["selector", $parentSelector . " .bearcms-image-gallery-element"]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Image"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "ImageGalleryImageCSS",
                                            "type" => "css",
                                            "cssTypes" => ["cssBorder", "cssRadius", "cssShadow"],
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-image-gallery-element-image", "overflow:hidden;"],
                                                ["rule", $parentSelector . " .bearcms-image-gallery-element-image img", "border:0;"],
                                                ["selector", $parentSelector . " .bearcms-image-gallery-element-image"]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_VIDEO')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Video"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "VideoCSS",
                                    "type" => "css",
                                    "cssTypes" => ["cssBorder", "cssRadius", "cssShadow"],
                                    "cssOutput" => [
                                        ["rule", $parentSelector . " .bearcms-video-element", "overflow:hidden;"],
                                        ["selector", $parentSelector . " .bearcms-video-element"]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_NAVIGATION')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Navigation"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "NavigationCSS",
                                    "type" => "css",
                                    "cssTypes" => ["cssBorder", "cssBackground"],
                                    "cssOutput" => [
                                        ["selector", $parentSelector . " .bearcms-navigation-element"]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Elements"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "NavigationItemLinkCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-navigation-element-item a", "display:inline-block;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;"],
                                                ["selector", $parentSelector . " .bearcms-navigation-element-item a"]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_HTML')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.HTML code"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "HtmlCSS",
                                    "type" => "css",
                                    "cssOutput" => [
                                        ["selector", $parentSelector . " .bearcms-html-element"],
                                        ["rule", $parentSelector . " .bearcms-html-element ul,ol,li", "list-style-position:inside;"]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Links"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "HtmlLinkCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-html-element a", "display:inline-block;"],
                                                ["selector", $parentSelector . " .bearcms-html-element a"]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
                if ($hasElements || Config::hasFeature('ELEMENTS_BLOGPOSTS')) {
                    Internal\Themes::$elementsOptions[] = function($idPrefix, $parentSelector) {
                        return [
                            "type" => "group",
                            "name" => __("bearcms.themes.options.Blog posts"),
                            "options" => [
                                [
                                    "id" => $idPrefix . "BlogPostsCSS",
                                    "type" => "css",
                                    "cssTypes" => ["cssPadding", "cssBorder", "cssRadius", "cssShadow", "cssBackground"],
                                    "cssOutput" => [
                                        ["selector", $parentSelector . " .bearcms-blog-posts-element"]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.Post"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "BlogPostsPostCSS",
                                            "type" => "css",
                                            "cssTypes" => ["cssBorder", "cssBackground", "cssShadow"],
                                            "cssOutput" => [
                                                ["selector", $parentSelector . " .bearcms-blog-posts-element-post"]
                                            ]
                                        ],
                                        [
                                            "type" => "group",
                                            "name" => __("bearcms.themes.options.Title"),
                                            "options" => [
                                                [
                                                    "id" => $idPrefix . "BlogPostsPostTitleCSS",
                                                    "type" => "css",
                                                    "cssOutput" => [
                                                        ["selector", $parentSelector . " .bearcms-blog-posts-element-post-title"]
                                                    ]
                                                ],
                                                [
                                                    "type" => "group",
                                                    "name" => __("bearcms.themes.options.Container"),
                                                    "options" => [
                                                        [
                                                            "id" => $idPrefix . "BlogPostsPostTitleContainerCSS",
                                                            "type" => "css",
                                                            "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                                            "cssOutput" => [
                                                                ["selector", $parentSelector . " .bearcms-blog-posts-element-post-title-container"]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ],
                                        [
                                            "type" => "group",
                                            "name" => __("bearcms.themes.options.Date"),
                                            "options" => [
                                                [
                                                    "id" => $idPrefix . "BlogPostsPostDateCSS",
                                                    "type" => "css",
                                                    "cssOutput" => [
                                                        ["selector", $parentSelector . " .bearcms-blog-posts-element-post-date"]
                                                    ]
                                                ],
                                                [
                                                    "type" => "group",
                                                    "name" => __("bearcms.themes.options.Container"),
                                                    "options" => [
                                                        [
                                                            "id" => $idPrefix . "BlogPostsPostDateContainerCSS",
                                                            "type" => "css",
                                                            "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                                            "cssOutput" => [
                                                                ["selector", $parentSelector . " .bearcms-blog-posts-element-post-date-container"]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ],
                                        [
                                            "type" => "group",
                                            "name" => __("bearcms.themes.options.Content"),
                                            "options" => [
                                                [
                                                    "id" => $idPrefix . "BlogPostsPostContentCSS",
                                                    "type" => "css",
                                                    "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                                    "cssOutput" => [
                                                        ["selector", $parentSelector . " .bearcms-blog-posts-element-post-content"]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    "type" => "group",
                                    "name" => __("bearcms.themes.options.blogPosts.Show more button"),
                                    "options" => [
                                        [
                                            "id" => $idPrefix . "BlogPostsShowMoreButtonCSS",
                                            "type" => "css",
                                            "cssOutput" => [
                                                ["rule", $parentSelector . " .bearcms-blog-posts-element-show-more-button", "display:inline-block;"],
                                                ["selector", $parentSelector . " .bearcms-blog-posts-element-show-more-button"]
                                            ]
                                        ],
                                        [
                                            "type" => "group",
                                            "name" => __("bearcms.themes.options.blogPosts.Container"),
                                            "options" => [
                                                [
                                                    "id" => $idPrefix . "BlogPostsShowMoreButtonContainerCSS",
                                                    "type" => "css",
                                                    "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                                    "cssOutput" => [
                                                        ["selector", $parentSelector . " .bearcms-blog-posts-element-show-more-button-container"]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    };
                }
            }
        }

        // Load the CMS managed addons
        if (Config::hasFeature('ADDONS')) {
            Internal\Data\Addons::addToApp();
        }

        $onResponseCreated = function($response) {
            $this->applyDefaults($response);
            $this->app->hooks->execute('bearCMSResponseCreated', $response);
            $this->applyTheme($response);
            $this->applyAdminUI($response);
        };

        // Register the system pages
        if ($hasServer) {
            if (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_DEFAULT')) {
                $this->app->routes
                        ->add(Config::$adminPagesPathPrefix . 'loggedin/', function() {
                            return new App\Response\TemporaryRedirect($this->app->request->base . '/');
                        })
                        ->add([Config::$adminPagesPathPrefix, Config::$adminPagesPathPrefix . '*/'], function() {
                            return Internal\Controller::handleAdminPage();
                        })
                        ->add([rtrim(Config::$adminPagesPathPrefix, '/'), Config::$adminPagesPathPrefix . '*'], function() {
                            return new App\Response\PermanentRedirect($this->app->request->base . $this->app->request->path . '/');
                        });
            }
            if (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*')) {
                $this->app->routes
                        ->add('/-aj/', function() {
                            return Internal\Controller::handleAjax();
                        }, ['POST'])
                        ->add('/-au/', function() {
                            return Internal\Controller::handleFileUpload();
                        }, ['POST']);
            }
        }

        // Register the file handlers
        if (Config::hasFeature('FILES')) {
            $this->app->routes
                    ->add('/files/preview/*', function() {
                        return Internal\Controller::handleFilePreview();
                    })
                    ->add('/files/download/*', function() {
                        return Internal\Controller::handleFileDownload();
                    });
        }

        // Register some other pages
        $this->app->routes
                ->add('/rss.xml', [
                    [$this, 'disabledCheck'],
                    function() {
                        $settings = Internal2::$data2->settings->get();
                        if ($settings['enableRSS']) {
                            return Internal\Controller::handleRSS();
                        }
                    }
                ])
                ->add('/sitemap.xml', [
                    [$this, 'disabledCheck'],
                    function() {
                        return Internal\Controller::handleSitemap();
                    }
                ])
                ->add('/robots.txt', [
                    [$this, 'disabledCheck'],
                    function() {
                        return Internal\Controller::handleRobots();
                    }
                ])
                ->add('/-link-rel-icon-*', [
                    [$this, 'disabledCheck'],
                    function() {
                        $size = str_replace('/-link-rel-icon-', '', (string) $this->app->request->path);
                        if (is_numeric($size)) {
                            $settings = Internal2::$data2->settings->get();
                            $icon = $settings['icon'];
                            if (isset($icon{0})) {
                                $filename = Internal2::$data2->getRealFilename($icon);
                                if ($filename !== null) {
                                    $url = $this->app->assets->getUrl($filename, ['cacheMaxAge' => 999999999, 'width' => (int) $size, 'height' => (int) $size]);
                                    return new App\Response\TemporaryRedirect($url);
                                }
                            }
                        }
                    }
        ]);

        if (Config::hasFeature('COMMENTS')) {
            $this->app->serverRequests
                    ->add('bearcms-comments-load-more', function($data) {
                        if (isset($data['serverData'], $data['listElementID'], $data['listCommentsCount'])) {
                            $listElementID = (string) $data['listElementID'];
                            $listCommentsCount = (int) $data['listCommentsCount'];
                            $serverData = Internal\TempClientData::get($data['serverData']);
                            if (is_array($serverData) && isset($serverData['threadID'])) {
                                $threadID = $serverData['threadID'];
                                $listContent = $this->app->components->process('<component src="file:' . $this->context->dir . '/components/bearcmsCommentsElement/commentsList.php" count="' . htmlentities($listCommentsCount) . '" threadID="' . htmlentities($threadID) . '" />');
                                return json_encode([
                                    'listElementID' => $listElementID,
                                    'listContent' => $listContent
                                ]);
                            }
                        }
                    });
        }

        if (Config::hasFeature('FORUMS')) {
            $this->app->routes
                    ->add('/f/?/', [
                        [$this, 'disabledCheck'],
                        function() use ($onResponseCreated) {
                            $forumCategoryID = $this->app->request->path->getSegment(1);
                            $forumCategory = Internal2::$data2->forumCategories->get($forumCategoryID);
                            if ($forumCategory !== null) {
                                $content = '<html>';
                                $content .= '<head>';
                                $content .= '<title>' . sprintf(__('bearcms.New post in %s'), htmlspecialchars($forumCategory->name)) . '</title>';
                                $content .= '</head>';
                                $content .= '<body>';
                                $content .= '<div class="bearcms-forum-post-page-title-container"><h1 class="bearcms-forum-post-page-title">' . sprintf(__('bearcms.New post in %s'), htmlspecialchars($forumCategory->name)) . '</h1></div>';
                                $content .= '<div class="bearcms-forum-post-page-content">';
                                $content .= '<component src="form" filename="' . $this->context->dir . '/components/bearcmsForumPostsElement/forumPostNewForm.php" categoryID="' . htmlentities($forumCategoryID) . '" />';
                                $content .= '</div>';
                                $content .= '</body>';
                                $content .= '</html>';

                                $this->app->hooks->execute('bearCMSForumCategoryPageContentCreated', $content, $forumCategoryID);

                                $response = new App\Response\HTML($this->app->components->process($content));
                                $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex'));
                                $onResponseCreated($response);
                                return $response;
                            }
                        }
                    ])
                    ->add('/f/?/?/', [
                        [$this, 'disabledCheck'],
                        function() use ($onResponseCreated) {
                            $forumPostSlug = $this->app->request->path->getSegment(1); // todo validate
                            $forumPostID = $this->app->request->path->getSegment(2);
                            $forumPost = Internal2::$data2->forumPosts->get($forumPostID);
                            if ($forumPost !== null) {

                                $render = false;
                                if ($forumPost->status === 'approved') {
                                    $render = true;
                                } elseif ($forumPost->status === 'pendingApproval') {
                                    if ($this->app->currentUser->exists()) {
                                        $render = $this->app->currentUser->provider === $forumPost->author['provider'] && $this->app->currentUser->id === $forumPost->author['id'];
                                    }
                                }
                                if (!$render) {
                                    return;
                                }

                                $content = '<html>';
                                $content .= '<head>';
                                $content .= '<title>' . htmlspecialchars($forumPost->title) . '</title>';
                                $content .= '</head>';
                                $content .= '<body>';
                                $content .= '<div class="bearcms-forum-post-page-title-container"><h1 class="bearcms-forum-post-page-title">' . htmlspecialchars($forumPost->title) . '</h1></div>';
                                //$content .= '<div class="bearcms-forum-post-page-date-container"><div class="bearcms-forum-post-page-date">' . Internal\Localization::getDate($forumPost->createdTime) . '</div></div>';
                                $content .= '<div class="bearcms-forum-post-page-content">';
                                $content .= '<component src="file:' . $this->context->dir . '/components/bearcmsForumPostsElement/forumPostRepliesList.php" includePost="true" forumPostID="' . htmlentities($forumPost->id) . '" />';
                                $content .= '</div>';
                                $content .= '<component src="form" filename="' . $this->context->dir . '/components/bearcmsForumPostsElement/forumPostReplyForm.php" forumPostID="' . htmlentities($forumPost->id) . '" />';
                                $content .= '</body>';
                                $content .= '</html>';

                                $forumPostID = $forumPost->id;
                                $this->app->hooks->execute('bearCMSForumPostPageContentCreated', $content, $forumPostID);

                                $response = new App\Response\HTML($this->app->components->process($content));
                                $onResponseCreated($response);
                                return $response;
                            }
                        }
            ]);
            $this->app->serverRequests
                    ->add('bearcms-forumposts-load-more', function($data) {
                        if (isset($data['serverData'], $data['serverData'])) {
                            $serverData = Internal\TempClientData::get($data['serverData']);
                            if (is_array($serverData) && isset($serverData['componentHTML'])) {
                                $content = $this->app->components->process($serverData['componentHTML']);
                                return json_encode([
                                    'content' => $content
                                ]);
                            }
                        }
                    });
        }

        if (Config::hasFeature('BLOG')) {
            $this->app->routes
                    ->add([Config::$blogPagesPathPrefix . '?', Config::$blogPagesPathPrefix . '?/'], [
                        [$this, 'disabledCheck'],
                        function() use ($onResponseCreated) {
                            $slug = (string) $this->app->request->path->getSegment(1);
                            $slugsList = Internal\Data\BlogPosts::getSlugsList('published');
                            $blogPostID = array_search($slug, $slugsList);
                            if ($blogPostID === false && substr($slug, 0, 6) === 'draft-' && (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*')) && $this->currentUser->exists()) {
                                $blogPost = Internal2::$data2->blogPosts->get(substr($slug, 6));
                                if ($blogPost !== null) {
                                    if ($blogPost->status === 'published') {
                                        return new App\Response\PermanentRedirect($this->app->urls->get(Config::$blogPagesPathPrefix . $blogPost->slug . '/'));
                                    }
                                    $blogPostID = $blogPost['id'];
                                }
                            }
                            if ($blogPostID !== false) {
                                $blogPost = Internal2::$data2->blogPosts->get($blogPostID);
                                if ($blogPost !== null) {
                                    $path = $this->app->request->path->get();
                                    $hasSlash = substr($path, -1) === '/';
                                    if (!$hasSlash) {
                                        return new App\Response\PermanentRedirect($this->app->request->base . $this->app->request->path . '/');
                                    }
                                    $content = '<html><head>';
                                    $title = isset($blogPost->titleTagContent) ? trim($blogPost->titleTagContent) : '';
                                    if (!isset($title{0})) {
                                        $title = isset($blogPost->title) ? trim($blogPost->title) : '';
                                    }
                                    $description = isset($blogPost->descriptionTagContent) ? trim($blogPost->descriptionTagContent) : '';
                                    $keywords = isset($blogPost->keywordsTagContent) ? trim($blogPost->keywordsTagContent) : '';
                                    if (isset($title{0})) {
                                        $content .= '<title>' . htmlspecialchars($title) . '</title>';
                                    }
                                    if (isset($description{0})) {
                                        $content .= '<meta name="description" content="' . htmlentities($description) . '"/>';
                                    }
                                    if (isset($keywords{0})) {
                                        $content .= '<meta name="keywords" content="' . htmlentities($keywords) . '"/>';
                                    }
                                    $content .= '</head><body>';
                                    $content .= '<div class="bearcms-blogpost-page-title-container"><h1 class="bearcms-blogpost-page-title">' . htmlspecialchars($blogPost['title']) . '</h1></div>';
                                    $content .= '<div class="bearcms-blogpost-page-date-container"><div class="bearcms-blogpost-page-date">' . ($blogPost['status'] === 'published' ? $this->app->localization->formatDate($blogPost['publishedTime'], ['date']) : __('bearcms.blogPost.draft')) . '</div></div>';
                                    $content .= '<div class="bearcms-blogpost-page-content"><component src="bearcms-elements" id="bearcms-blogpost-' . $blogPostID . '"/></div>';
                                    $content .= '</body></html>';

                                    $this->app->hooks->execute('bearCMSBlogPostPageContentCreated', $content, $blogPostID);

                                    $content = $this->app->components->process($content);

                                    $response = new App\Response\HTML($content);
                                    $onResponseCreated($response);
                                    return $response;
                                }
                            }
                        }
            ]);
            $this->app->serverRequests
                    ->add('bearcms-blogposts-load-more', function($data) {
                        if (isset($data['serverData'], $data['serverData'])) {
                            $serverData = Internal\TempClientData::get($data['serverData']);
                            if (is_array($serverData) && isset($serverData['componentHTML'])) {
                                $content = $this->app->components->process($serverData['componentHTML']);
                                return json_encode([
                                    'content' => $content
                                ]);
                            }
                        }
                    });

            if (Config::hasFeature('THEMES')) {
                Internal\Themes::$pagesOptions[] = function() {
                    return [
                        "type" => "group",
                        "name" => __("bearcms.themes.options.Blog post page"),
                        "options" => [
                            [
                                "type" => "group",
                                "name" => __("bearcms.themes.options.Title"),
                                "options" => [
                                    [
                                        "id" => "blogPostPageTitleCSS",
                                        "type" => "css",
                                        "cssOutput" => [
                                            ["rule", ".bearcms-blogpost-page-title", "font-weight:normal;"],
                                            ["selector", ".bearcms-blogpost-page-title"]
                                        ]
                                    ],
                                    [
                                        "type" => "group",
                                        "name" => __("bearcms.themes.options.Container"),
                                        "options" => [
                                            [
                                                "id" => "blogPostPageTitleContainerCSS",
                                                "type" => "css",
                                                "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                                "cssOutput" => [
                                                    ["selector", ".bearcms-blogpost-page-title-container"]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                "type" => "group",
                                "name" => __("bearcms.themes.options.Date"),
                                "options" => [
                                    [
                                        "id" => "blogPostPageDateVisibility",
                                        "type" => "list",
                                        "name" => __('bearcms.themes.options.Visibility'),
                                        "values" => [
                                            [
                                                "value" => "1",
                                                "name" => __('bearcms.themes.options.Visible')
                                            ],
                                            [
                                                "value" => "0",
                                                "name" => __('bearcms.themes.options.Hidden')
                                            ]
                                        ]
                                    ],
                                    [
                                        "id" => "blogPostPageDateCSS",
                                        "type" => "css",
                                        "cssOutput" => [
                                            ["selector", ".bearcms-blogpost-page-date"]
                                        ]
                                    ],
                                    [
                                        "type" => "group",
                                        "name" => __("bearcms.themes.options.Container"),
                                        "options" => [
                                            [
                                                "id" => "blogPostPageDateContainerCSS",
                                                "type" => "css",
                                                "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                                "cssOutput" => [
                                                    ["selector", ".bearcms-blogpost-page-date-container"]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                "type" => "group",
                                "name" => __("bearcms.themes.options.Content"),
                                "options" => [
                                    [
                                        "id" => "blogPostPageContentCSS",
                                        "type" => "css",
                                        "cssTypes" => ["cssPadding", "cssMargin", "cssBorder", "cssRadius", "cssShadow", "cssBackground", "cssSize"],
                                        "cssOutput" => [
                                            ["selector", ".bearcms-blogpost-page-content"]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                };
            }
        }

        // Register a home page and the dynamic pages handler
        if (Config::hasFeature('PAGES')) {
            $this->app->routes
                    ->add('*', [
                        [$this, 'disabledCheck'],
                        function() use ($onResponseCreated) {
                            $path = $this->app->request->path->get();
                            $path = implode('/', array_map('urldecode', explode('/', $path))); // waiting for next bearframework version
                            if ($path === '/') {
                                if (Config::$autoCreateHomePage) {
                                    $pageID = 'home';
                                } else {
                                    $pageID = false;
                                }
                            } else {
                                $hasSlash = substr($path, -1) === '/';
                                $pathsList = Internal\Data\Pages::getPathsList((Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*')) && $this->currentUser->exists() ? 'all' : 'published');
                                if ($hasSlash) {
                                    $pageID = array_search($path, $pathsList);
                                } else {
                                    $pageID = array_search($path . '/', $pathsList);
                                    if ($pageID !== false) {
                                        return new App\Response\PermanentRedirect($this->app->request->base . $this->app->request->path . '/');
                                    }
                                }
                            }
                            if ($pageID !== false) {
                                $response = $this->disabledCheck();
                                if ($response !== null) {
                                    return $response;
                                }
                                $found = false;
                                if ($pageID === 'home') {
                                    $settings = Internal2::$data2->settings->get();
                                    $title = trim($settings->title);
                                    $description = trim($settings->description);
                                    $keywords = trim($settings->keywords);
                                    $found = true;
                                } else {
                                    $page = Internal2::$data2->pages->get($pageID);
                                    if ($page !== null) {
                                        $title = isset($page->titleTagContent) ? trim($page->titleTagContent) : '';
                                        if (!isset($title{0})) {
                                            $title = isset($page->name) ? trim($page->name) : '';
                                        }
                                        $description = isset($page->descriptionTagContent) ? trim($page->descriptionTagContent) : '';
                                        $keywords = isset($page->keywordsTagContent) ? trim($page->keywordsTagContent) : '';
                                        $found = true;
                                    }
                                }
                                if ($found) {
                                    $content = '<html><head>';
                                    if (isset($title{0})) {
                                        $content .= '<title>' . htmlspecialchars($title) . '</title>';
                                    }
                                    if (isset($description{0})) {
                                        $content .= '<meta name="description" content="' . htmlentities($description) . '"/>';
                                    }
                                    if (isset($keywords{0})) {
                                        $content .= '<meta name="keywords" content="' . htmlentities($keywords) . '"/>';
                                    }
                                    $content .= '</head><body>';
                                    $content .= '<component src="bearcms-elements" id="bearcms-page-' . $pageID . '" editable="true"/>';
                                    $content .= '</body></html>';

                                    $this->app->hooks->execute('bearCMSPageContentCreated', $content, $pageID);

                                    $content = $this->app->components->process($content);

                                    $response = new App\Response\HTML($content);
                                    $onResponseCreated($response);
                                    return $response;
                                }
                            }
                        }
            ]);
        }

        $this->app->hooks
                ->add('responseCreated', function($response) {
                    if (strpos((string) $this->app->request->path, $this->app->config->assetsPathPrefix) !== 0) {
                        if ($response instanceof App\Response\NotFound) {
                            $response->headers->set($response->headers->make('Content-Type', 'text/html'));
                            $this->apply($response);
                        } elseif ($response instanceof App\Response\TemporaryUnavailable) {
                            $response->headers->set($response->headers->make('Content-Type', 'text/html'));
                            $this->apply($response);
                        }
                    }
                })
                ->add('assetPrepare', function(&$filename) {
                    $addonAssetsDir = $this->context->dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
                    if (strpos($filename, $addonAssetsDir) === 0) {

                        $downloadUrl = function($url) {
                            $tempFileKey = '.temp/bearcms/urlassets/' . md5($url) . '.' . pathinfo($url, PATHINFO_EXTENSION);
                            $tempFilename = $this->app->data->getFilename($tempFileKey);
                            if ($this->app->data->exists($tempFileKey)) {
                                return $tempFilename;
                            } else {
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                $response = curl_exec($ch);
                                $valid = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && strlen($response) > 0;
                                curl_close($ch);
                                if ($valid) {
                                    $this->app->data->set($this->app->data->make($tempFileKey, $response));
                                    return $tempFilename;
                                } else {
                                    throw new Exception('Cannot download file from URL (' . $url . ')');
                                }
                            }
                        };

                        // Proxy
                        $matchingDir = $addonAssetsDir . 'p' . DIRECTORY_SEPARATOR;
                        if (strpos($filename, $matchingDir) === 0) {
                            $pathParts = explode(DIRECTORY_SEPARATOR, substr($filename, strlen($matchingDir)), 3);
                            if (isset($pathParts[0], $pathParts[1], $pathParts[2])) {
                                $url = $pathParts[0] . '://' . $pathParts[1] . '/' . str_replace('\\', '/', $pathParts[2]);
                                $filename = null;
                                $filename = $downloadUrl($url);
                            }
                        } else { // Theme media file
                            $matchingDir = $addonAssetsDir . 'tm' . DIRECTORY_SEPARATOR;
                            if (strpos($filename, $matchingDir) === 0) {
                                $pathParts = explode(DIRECTORY_SEPARATOR, substr($filename, strlen($matchingDir)), 2);
                                if (isset($pathParts[0], $pathParts[1])) {
                                    $themeIDMD5 = $pathParts[0];
                                    $mediaFilenameMD5 = $pathParts[1];
                                    $themes = Internal\Themes::getIDs();
                                    foreach ($themes as $id) {
                                        if ($themeIDMD5 === md5($id)) {
                                            $themeManifest = Internal\Themes::getManifest($id, false);
                                            if (isset($themeManifest['media'])) {
                                                foreach ($themeManifest['media'] as $i => $mediaItem) {
                                                    if (isset($mediaItem['filename'])) {
                                                        if ($mediaFilenameMD5 === md5($mediaItem['filename']) . '.' . pathinfo($mediaItem['filename'], PATHINFO_EXTENSION)) {
                                                            $filename = $mediaItem['filename'];
                                                            return;
                                                        }
                                                    }
                                                }
                                            }
                                            $themeStyles = Internal\Themes::getStyles($id, false);
                                            foreach ($themeStyles as $themeStyle) {
                                                if (isset($themeStyle['media'])) {
                                                    foreach ($themeStyle['media'] as $i => $mediaItem) {
                                                        if (isset($mediaItem['filename'])) {
                                                            if ($mediaFilenameMD5 === md5($mediaItem['filename']) . '.' . pathinfo($mediaItem['filename'], PATHINFO_EXTENSION)) {
                                                                $filename = $mediaItem['filename'];
                                                                return;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $filename = null;
                            } else {
                                // Download the server files
                                $matchingDir = $addonAssetsDir . 's' . DIRECTORY_SEPARATOR;
                                if (strpos($filename, $matchingDir) === 0) {
                                    $url = Config::$serverUrl . str_replace('\\', '/', substr($filename, strlen($matchingDir)));
                                    $filename = null;
                                    $filename = $downloadUrl($url);
                                }
                            }
                        }
                    }
                });

        if (Config::hasFeature('THEMES') && Config::$addDefaultThemes) {
            $this->themes->addDefault();
        }

        if ($hasServer && (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*'))) {
            if (Config::$useDefaultUserProfile) {
                $this->app->users
                        ->addProvider('bearcms', Internal\UserProvider::class);

                if ($this->currentUser->exists()) {
                    if (!$this->app->currentUser->exists()) {
                        $this->app->currentUser->login('bearcms', $this->currentUser->getID());
                    }
                } else {
                    if ($this->app->currentUser->exists()) {
                        if ($this->app->currentUser->provider === 'bearcms') {
                            $this->app->currentUser->logout();
                        }
                    }
                }
            }
            $this->app->hooks
                    ->add('responseCreated', function($response) {
                        Internal\Cookies::apply($response);
                    });
        }


        if (Config::hasFeature('NOTIFICATIONS')) {
            $this->app->tasks
                    ->define('bearcms-send-new-comment-notification', function($data) {
                        $threadID = $data['threadID'];
                        $commentID = $data['commentID'];
                        $comments = Internal2::$data2->comments->getList()
                                ->filterBy('threadID', $threadID)
                                ->filterBy('id', $commentID);
                        if (isset($comments[0])) {
                            $comment = $comments[0];
                            $comments = Internal2::$data2->comments->getList()
                                    ->filterBy('status', 'pendingApproval');
                            $pendingApprovalCount = $comments->length;
                            $profile = Internal\PublicProfile::getFromAuthor($comment->author);
                            Internal\Data::sendNotification('comments', $comment->status, $profile->name, $comment->text, $pendingApprovalCount);
                        }
                    })
                    ->define('bearcms-send-new-forum-post-notification', function($data) {
                        $forumPostID = $data['forumPostID'];
                        $forumPost = Internal2::$data2->forumPosts->get($forumPostID);
                        if ($forumPost !== null) {
                            $forumPosts = Internal2::$data2->forumPosts->getList()
                                    ->filterBy('status', 'pendingApproval');
                            $pendingApprovalCount = $forumPosts->length;
                            $profile = Internal\PublicProfile::getFromAuthor($forumPost->author);
                            Internal\Data::sendNotification('forum-posts', $forumPost->status, $profile->name, $forumPost->title, $pendingApprovalCount);
                        }
                    })
                    ->define('bearcms-send-new-forum-post-reply-notification', function($data) {
                        $forumPostID = $data['forumPostID'];
                        $forumPostReplyID = $data['forumPostReplyID'];
                        $forumPostsReplies = Internal2::$data2->forumPostsReplies->getList()
                                ->filterBy('forumPostID', $forumPostID)
                                ->filterBy('id', $forumPostReplyID);
                        if (isset($forumPostsReplies[0])) {
                            $forumPostsReply = $forumPostsReplies[0];
                            $forumPostsReplies = Internal2::$data2->forumPostsReplies->getList()
                                    ->filterBy('status', 'pendingApproval');
                            $pendingApprovalCount = $forumPostsReplies->length;
                            $profile = Internal\PublicProfile::getFromAuthor($forumPostsReply->author);
                            Internal\Data::sendNotification('forum-posts-replies', $forumPostsReply->status, $profile->name, $forumPostsReply->text, $pendingApprovalCount);
                        }
                    });
        }
    }

    /**
     * Applies all Bear CMS modifications (the default HTML, theme and admin UI) to the response.
     * 
     * @param \BearFramework\App\Response $response The response to modify.
     * @return void
     */
    public function apply(\BearFramework\App\Response $response): void
    {
        $this->applyDefaults($response);
        $this->applyTheme($response);
        $this->applyAdminUI($response);
    }

    /**
     * Add the default Bear CMS HTML to the response.
     * 
     * @param \BearFramework\App\Response $response The response to modify.
     * @return void
     */
    public function applyDefaults(\BearFramework\App\Response $response): void
    {
        if (!$response->headers->exists('Cache-Control')) {
            $response->headers->set($response->headers->make('Cache-Control', 'private, max-age=0, no-cache, no-store'));
        }

        $currentUserExists = Config::hasServer() && (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*')) ? $this->currentUser->exists() : false;
        $settings = Internal2::$data2->settings->get();

        $document = new HTML5DOMDocument();
        $document->loadHTML($response->content);

        if (isset($settings['language']) && strlen($settings['language']) > 0) {
            $html = '<html lang="' . htmlentities($settings['language']) . '">';
        } else {
            $html = '<html>';
        }
        $html .= '<head>';

        $title = '';
        $titleElement = $document->querySelector('title');
        if ($titleElement !== null && strlen($titleElement->innerHTML) > 0) {
            $title = html_entity_decode($titleElement->innerHTML);
        } else {
            $h1Element = $document->querySelector('h1');
            if ($h1Element !== null) {
                $innerHTML = $h1Element->innerHTML;
                if (isset($innerHTML{0})) {
                    $title = $innerHTML;
                    $html .= '<title>' . $innerHTML . '</title>';
                }
            }
        }

        $strlen = function(string $string) {
            return function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);
        };

        $substr = function(string $string, int $start, int $length = null) {
            return function_exists('mb_substr') ? mb_substr($string, $start, $length) : substr($string, $start, $length);
        };

        $strtolower = function(string $string) {
            return function_exists('mb_strtolower') ? mb_strtolower($string) : strtolower($string);
        };

        $metaElements = $document->querySelectorAll('meta');
        $generateDescriptionMetaTag = true;
        $generateKeywordsMetaTag = true;
        foreach ($metaElements as $metaElement) {
            $metaElementName = $metaElement->getAttribute('name');
            if ($metaElementName === 'description' && $strlen($metaElement->getAttribute('content')) > 0) {
                $generateDescriptionMetaTag = false;
            } elseif ($metaElementName === 'keywords' && $strlen($metaElement->getAttribute('content')) > 0) {
                $generateKeywordsMetaTag = false;
            }
        }

        if ($generateDescriptionMetaTag || $generateKeywordsMetaTag) {
            $bodyElement = $document->querySelector('body');
            if ($bodyElement !== null) {
                $textContent = $bodyElement->innerHTML;

                $textContent = preg_replace('/<script.*?<\/script>/', '', $textContent);
                $textContent = preg_replace('/<.*?>/', ' $0 ', $textContent);
                $textContent = preg_replace('/\s/', ' ', $textContent);
                $textContent = strip_tags($textContent);
                while (strpos($textContent, '  ') !== false) {
                    $textContent = str_replace('  ', ' ', $textContent);
                }

                $textContent = html_entity_decode(trim($textContent));

                if (isset($textContent{0})) {
                    if ($generateDescriptionMetaTag) {
                        $description = $substr($textContent, 0, 150);
                        $html .= '<meta name="description" content="' . htmlentities($description . ' ...') . '"/>';
                    }
                    $wordsText = str_replace(['.', ',', '/', '\\'], '', $strtolower($textContent));
                    $words = explode(' ', $wordsText);
                    $wordsCount = array_count_values($words);
                    arsort($wordsCount);
                    $selectedWords = [];
                    foreach ($wordsCount as $word => $wordCount) {
                        $wordLength = $strlen($word);
                        if ($wordLength >= 3 && !is_numeric($word)) {
                            $selectedWords[] = $word;
                            if (sizeof($selectedWords) === 7) {
                                break;
                            }
                        }
                    }
                    $html .= '<meta name="keywords" content="' . htmlentities(implode(', ', $selectedWords)) . '"/>';
                }
            }
        }

        if (!Config::$whitelabel) {
            $html .= '<meta name="generator" content="Bear CMS (powered by Bear Framework)"/>';
        }
        $icon = $settings['icon'];
        if (isset($icon{0})) {
            $baseUrl = $this->app->urls->get();
            $html .= '<link rel="apple-touch-icon" sizes="57x57" href="' . htmlentities($baseUrl . '-link-rel-icon-57') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="60x60" href="' . htmlentities($baseUrl . '-link-rel-icon-60') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="72x72" href="' . htmlentities($baseUrl . '-link-rel-icon-72') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="76x76" href="' . htmlentities($baseUrl . '-link-rel-icon-76') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="114x114" href="' . htmlentities($baseUrl . '-link-rel-icon-114') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="120x120" href="' . htmlentities($baseUrl . '-link-rel-icon-120') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="144x144" href="' . htmlentities($baseUrl . '-link-rel-icon-144') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="152x152" href="' . htmlentities($baseUrl . '-link-rel-icon-152') . '">';
            $html .= '<link rel="apple-touch-icon" sizes="180x180" href="' . htmlentities($baseUrl . '-link-rel-icon-180') . '">';
            $html .= '<link rel="icon" sizes="32x32" href="' . htmlentities($baseUrl . '-link-rel-icon-32') . '">';
            $html .= '<link rel="icon" sizes="192x192" href="' . htmlentities($baseUrl . '-link-rel-icon-192') . '">';
            $html .= '<link rel="icon" sizes="96x96" href="' . htmlentities($baseUrl . '-link-rel-icon-96') . '">';
            $html .= '<link rel="icon" sizes="16x16" href="' . htmlentities($baseUrl . '-link-rel-icon-16') . '">';
        } else if ($currentUserExists) {
            $html .= '<link rel="apple-touch-icon" sizes="57x57" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="60x60" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="72x72" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="76x76" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="114x114" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="120x120" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="144x144" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="152x152" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="apple-touch-icon" sizes="180x180" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="icon" sizes="32x32" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="icon" sizes="192x192" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="icon" sizes="96x96" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
            $html .= '<link rel="icon" sizes="16x16" href="data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
        }
        if (empty($settings['allowSearchEngines'])) {
            $html .= '<meta name="robots" content="noindex">';
        }
        $html .= '<link rel="canonical" href="' . htmlentities(rtrim($this->app->request->base . $this->app->request->path, '/') . '/') . '"/>';
        if ($settings['enableRSS']) {
            $html .= '<link rel="alternate" type="application/rss+xml" title="' . (isset($settings['title']) ? htmlentities(trim($settings['title'])) : '') . '" href="' . $this->app->request->base . '/rss.xml" />';
        }
        $html .= '</head><body>';

        if ($response instanceof App\Response\HTML) { // is not temporary disabled
            $externalLinksAreEnabled = !empty($settings['externalLinks']);
            if ($externalLinksAreEnabled || $currentUserExists) {
                $html .= '<script id="bearcms-bearframework-addon-script-10" src="' . htmlentities($this->context->assets->getUrl('assets/externalLinks.min.js', ['cacheMaxAge' => 999999999, 'version' => 1])) . '" async onload="bearCMS.externalLinks.initialize(' . ($externalLinksAreEnabled ? 1 : 0) . ',' . ($currentUserExists ? 1 : 0) . ');"></script>';
            }
        }
        $html .= '</body></html>';
        $document->insertHTML($html);

        if (strlen($title) > 0) {
            $imageElements = $document->querySelectorAll('img');
            foreach ($imageElements as $imageElement) {
                if (strlen($imageElement->getAttribute('alt')) === 0) {
                    $imageElement->setAttribute('alt', $title);
                }
            }
        }

        $response->content = $document->saveHTML();

        $this->app->users->applyUI($response);
    }

    /**
     * Add the Bear CMS admin UI to the response, if an administrator is logged in.
     * 
     * @param \BearFramework\App\Response $response The response to modify.
     * @return void
     */
    public function applyAdminUI(\BearFramework\App\Response $response): void
    {
        $currentUserExists = Config::hasServer() && (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*')) ? $this->currentUser->exists() : false;
        if (!$currentUserExists) {
            return;
        }

        $settings = Internal2::$data2->settings->get();

        $serverCookies = Internal\Cookies::getList(Internal\Cookies::TYPE_SERVER);
        if (!empty($serverCookies['tmcs']) || !empty($serverCookies['tmpr'])) {
            Internal\ElementsHelper::$editorData = [];
        }

        $requestArguments = [];
        $requestArguments['hasEditableElements'] = empty(Internal\ElementsHelper::$editorData) ? '0' : '1';
        $requestArguments['hasEditableContainers'] = '0';
        $requestArguments['isDisabled'] = $settings->disabled ? '1' : '0';
        foreach (Internal\ElementsHelper::$editorData as $itemData) {
            if ($itemData[0] === 'container') {
                $requestArguments['hasEditableContainers'] = '1';
            }
        }

        $cacheKey = json_encode([
            'adminUI',
            $this->app->request->base,
            $this->currentUser->getSessionKey(),
            $this->currentUser->getPermissions(),
            get_class_vars('\BearCMS\Internal\Config'),
            $serverCookies
        ]);

        $adminUIData = Internal\Server::call('adminui', $requestArguments, true, $cacheKey);
        if (is_array($adminUIData) && isset($adminUIData['result'])) {
            if ($adminUIData['result'] === 'noUser') { // The user does not exists on the server
                $this->currentUser->logout();
                return;
            }
            if (is_array($adminUIData['result']) && isset($adminUIData['result']['content']) && strlen($adminUIData['result']['content']) > 0) {
                $content = $adminUIData['result']['content'];
                $content = Internal\Server::updateAssetsUrls($content, false);
                $document = new HTML5DOMDocument();
                $htmlToInsert = [];
                if (strpos($content, '{body}')) {
                    $content = str_replace('{body}', (string) $document->createInsertTarget('body'), $content);
                    $htmlToInsert[] = ['source' => $response->content, 'target' => 'body'];
                } elseif (strpos($content, '{jsonEncodedBody}')) {
                    $content = str_replace('{jsonEncodedBody}', json_encode($this->app->components->process($response->content)), $content);
                }
                $document->loadHTML($content);
                $elementsHtml = Internal\ElementsHelper::getEditableElementsHtml();
                if (isset($elementsHtml[0])) {
                    $htmlToInsert[] = ['source' => $elementsHtml];
                }
                $htmlToInsert[] = ['source' => '<html><body><script id="bearcms-bearframework-addon-script-4" src="' . htmlentities($this->context->assets->getUrl('assets/HTML5DOMDocument.min.js', ['cacheMaxAge' => 999999999, 'version' => 1])) . '" async></script></body></html>'];
                $document->insertHTMLMulti($htmlToInsert);
                $response->content = $document->saveHTML();
            }
        }
    }

    /**
     * Applies the currently selected Bear CMS theme to the response provided.
     * 
     * @param \BearFramework\App\Response $response The response to modify.
     * @return void
     */
    public function applyTheme(\BearFramework\App\Response $response): void
    {
        $currentThemeID = Internal\CurrentTheme::getID();
        $currentUserID = $this->currentUser->exists() ? $this->currentUser->getID() : null;
        $currentThemeOptions = Internal\Themes::getOptions($currentThemeID, $currentUserID);
        if ($this->app->hooks->exists('bearCMSThemeApply')) {
            $this->app->hooks->execute('bearCMSThemeApply', $currentThemeID, $response, $currentThemeOptions);
        }

        $content = $response->content;

        $hasChange = false;
        $domDocument = null;
        $getDocument = function() use ($content, &$domDocument) {
            if ($domDocument === null) {
                $domDocument = new HTML5DOMDocument();
                $domDocument->loadHTML($content);
            }
            return $domDocument;
        };
        if ($response instanceof App\Response\HTML) {
            if (strpos($content, 'class="bearcms-blogpost-page-date-container"') !== false && $currentThemeOptions['blogPostPageDateVisibility'] === '0') {
                $domDocument = $getDocument();
                $element = $domDocument->querySelector('div.bearcms-blogpost-page-date-container');
                if ($element) {
                    $element->parentNode->removeChild($element);
                    $hasChange = true;
                }
            }

            if (!Config::$whitelabel) {
                $domDocument = $getDocument();
                $logoSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="75.93" height="45.65" viewBox="0 0 75.929546 45.649438"><path fill="#666" d="M62.2 0c1.04-.02 2.13.8 2.55 2.14.15.56.1 1.3.43 1.6 2.02 1.88 5.34 1.64 6.04 4.9.12.75 2 2.3 2.92 3.2.8.77 2 2.13 1.76 2.86-.5 1.66-1.16 3.65-3.65 3.6-3.64-.06-7.3-.04-10.94 0-4.66.04-7.44 2.82-7.5 7.53-.05 3.8.07 7.63-.03 11.46-.08 3 1.25 4.67 4.18 5.35.93.24 1.5 1.1.84 1.9-.8 1-4.3 1-4.4 1-2.8.33-6.5-.7-8.78-6.4-1.3 1.7-2.2 2.56-3.4 2.94-.7.22-4.17 1.1-4.3.3-.25-1.44 3.9-5.03 4.07-6.5.3-2.84-2.18-3.9-5.05-4.6-2.9-.74-6 .57-7.3 1.95-1.8 1.9-1.7 7.77-.76 8.26.5.26 1.46.8 1.5 1.6 0 .6-.76 1.5-1.2 1.5-2.5.17-5.03.26-7.48-.05-.65-.08-1.6-1.66-1.6-2.54.04-2.87-5.5-7.9-6.4-6.6-1.52 2.16-6.04 3.23-5.5 6.04.34 1.8 3.9.6 4.25 2 .76 3.2-6.8 2.1-9.87 1.7-2.58-.33-3.63-1.83-1.32-6.9 2.8-5.1 3.23-10.4 2.75-16.17C3.08 9.6 11.53.97 24.08 1.3c10.9.24 21.9-.2 32.7 1.3 6.1.82 2.72.1 3.77-1.6.42-.67 1.03-1 1.65-1z"/></svg>';
                $codeToInsert = '<div style=background-color:#000;padding:15px;width:100%;text-align:center;"><a href="https://bearcms.com/" target="_blank" rel="nofollow noopener" title="This website is powered by Bear CMS" style="width:40px;height:40px;display:inline-block;background-size:80%;background-repeat:no-repeat;background-position:center center;background-image:url(data:image/svg+xml;base64,' . base64_encode($logoSvg) . ');"></a></div>';
                $html = '<body><script>document.body.insertAdjacentHTML("beforeend",' . json_encode($codeToInsert) . ');</script></body>';
                $domDocument->insertHTML($html);
                $hasChange = true;
            }
        }

        if ($hasChange) {
            $response->content = $domDocument->saveHTML();
        }

        if (isset(Internal\Themes::$announcements[$currentThemeID])) {
            $theme = Internal\Themes::get($currentThemeID);
            if (is_callable($theme->initialize)) {
                call_user_func($theme->initialize);
            }
            if (is_callable($theme->get)) {
                if ($response instanceof App\Response\HTML) {
                    $templateContent = call_user_func($theme->get, $currentThemeOptions);
                    $template = new \BearFramework\HTMLTemplate($templateContent);
                    $template->insert($currentThemeOptions->toHTML());
                    $template->insert($response->content, 'body');
                    $response->content = $this->app->components->process($template->get());
                }
            } elseif (is_callable($theme->apply)) {
                call_user_func($theme->apply, $response, $currentThemeOptions);
            }
        }

        if ($this->app->hooks->exists('bearCMSThemeApplied')) {
            $this->app->hooks->execute('bearCMSThemeApplied', $currentThemeID, $response, $currentThemeOptions);
        }
    }

    /**
     * A middleware to be used in routes that returns a temporary unavailable response if an administrator has disabled the app.
     * 
     * @return \BearFramework\App\Response|null
     */
    public function disabledCheck(): ?\BearFramework\App\Response
    {
        $currentUserExists = Config::hasServer() && (Config::hasFeature('USERS') || Config::hasFeature('USERS_LOGIN_*')) ? $this->currentUser->exists() : false;
        $settings = Internal2::$data2->settings->get();
        $isDisabled = !$currentUserExists && $settings->disabled;
        if ($isDisabled) {
            return new App\Response\TemporaryUnavailable(htmlspecialchars($settings->disabledText));
        }
        return null;
    }

}
