<?php

namespace nineteenninetyfour\socialimages;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use NineteenNinetyFour\SocialImages\Core\Library\Fonts;
use NineteenNinetyFour\SocialImages\Core\Library\GoogleFonts;
use NineteenNinetyFour\SocialImages\Core\Library\LocalStorage;
use NineteenNinetyFour\SocialImages\Core\Library\Uploads;
use NineteenNinetyFour\SocialImages\Core\Rendering\Renderer;
use NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager;
use NineteenNinetyFour\SocialImages\Core\Rendering\Resolver;
use NineteenNinetyFour\SocialImages\Core\Rendering\Tokens;
use NineteenNinetyFour\SocialImages\Core\Support\CurlFetcher;
use NineteenNinetyFour\SocialImages\Core\Support\Lang;
use NineteenNinetyFour\SocialImages\Core\Templates\Porter;
use nineteenninetyfour\socialimages\elements\actions\GenerateSocialImages;
use nineteenninetyfour\socialimages\events\TemplateEvent;
use nineteenninetyfour\socialimages\fields\SocialImage;
use nineteenninetyfour\socialimages\integrations\Seomatic;
use nineteenninetyfour\socialimages\jobs\GenerateImage;
use nineteenninetyfour\socialimages\jobs\RemakeImages;
use nineteenninetyfour\socialimages\models\Settings;
use nineteenninetyfour\socialimages\rendering\CraftImages;
use nineteenninetyfour\socialimages\rendering\CraftValues;
use nineteenninetyfour\socialimages\services\Camera;
use nineteenninetyfour\socialimages\services\Cards;
use nineteenninetyfour\socialimages\services\Output;
use nineteenninetyfour\socialimages\services\Templates;
use nineteenninetyfour\socialimages\variables\SocialImagesVariable;
use Throwable;
use yii\base\Event;

/**
 * Social Images for Craft CMS.
 *
 * @property-read Templates $templates
 * @property-read Cards $cards
 * @property-read Output $output
 * @property-read Camera $camera
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const PERMISSION_VIEW = 'socialImages-viewImages';

    public const PERMISSION_MANAGE = 'socialImages-manageTemplates';

    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public bool $hasCpSection = true;

    protected ?RendererManager $renderers = null;

    protected array $made = [];

    public static function config(): array
    {
        return [
            'components' => [
                'templates' => Templates::class,
                'cards' => Cards::class,
                'output' => Output::class,
                'camera' => Camera::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // The core package's messages, translated like the plugin's own.
        Lang::using(fn (string $text, array $replace) => strtr(
            Craft::t('social-images', $text),
            array_combine(array_map(fn ($key) => ':'.$key, array_keys($replace)), array_map('strval', $replace)) ?: [],
        ));

        $this->registerProjectConfig();
        $this->registerFieldsAndActions();
        $this->registerEntryEvents();
        $this->registerTwig();
        $this->registerPermissions();
        Seomatic::register();

        Craft::$app->onInit(function () {
            if (Craft::$app->getRequest()->getIsCpRequest()) {
                $this->registerCp();
            }
        });
    }

    // Services from social-images-core.

    public function library(): LocalStorage
    {
        return $this->made['library'] ??= new LocalStorage(App::parseEnv($this->getSettings()->libraryPath));
    }

    public function fonts(): Fonts
    {
        return $this->made['fonts'] ??= new Fonts($this->library());
    }

    public function uploads(): Uploads
    {
        return $this->made['uploads'] ??= new Uploads($this->library());
    }

    public function googleFonts(): GoogleFonts
    {
        return $this->made['googleFonts'] ??= new GoogleFonts($this->fonts(), new CurlFetcher);
    }

    public function values(): CraftValues
    {
        return $this->made['values'] ??= new CraftValues;
    }

    public function tokens(): Tokens
    {
        return $this->made['tokens'] ??= new Tokens($this->values());
    }

    public function images(): CraftImages
    {
        return $this->made['images'] ??= new CraftImages($this->uploads());
    }

    public function resolver(): Resolver
    {
        return $this->made['resolver'] ??= new Resolver($this->tokens(), $this->images(), $this->fonts());
    }

    public function porter(): Porter
    {
        $settings = $this->getSettings();

        return $this->made['porter'] ??= new Porter(
            $this->templates,
            $this->uploads(),
            $this->fonts(),
            new CurlFetcher,
            function (string $id) {
                $asset = $this->images()->find(['asset:'.$id], null);

                return $asset instanceof Asset ? [$asset->getContents(), $asset->getExtension()] : null;
            },
            $settings->importMaxKilobytes,
            $settings->importTimeout,
        );
    }

    /**
     * The renderers, set up from config/social-images.php. Add your own
     * with SocialImages::extend() (see docs/rendering.md).
     */
    public function renderers(): RendererManager
    {
        return $this->renderers ??= new RendererManager($this->getSettings()->rendererConfig());
    }

    public function renderer(?string $driver = null): Renderer
    {
        return $this->renderers()->driver($driver);
    }

    // Settings.

    protected function createSettingsModel(): ?Model
    {
        return new Settings;
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('social-images/settings'));
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        if (! $user->checkPermission(self::PERMISSION_VIEW) && ! $user->checkPermission(self::PERMISSION_MANAGE)) {
            return null;
        }

        $item['subnav'] = [
            'templates' => ['label' => Craft::t('social-images', 'Templates'), 'url' => 'social-images'],
            'images' => ['label' => Craft::t('social-images', 'Images'), 'url' => 'social-images/images'],
        ];

        if (Craft::$app->getConfig()->getGeneral()->allowAdminChanges && $user->getIsAdmin()) {
            $item['subnav']['settings'] = ['label' => Craft::t('social-images', 'Settings'), 'url' => 'social-images/settings'];
        }

        return $item;
    }

    // Registration.

    protected function registerProjectConfig(): void
    {
        Craft::$app->getProjectConfig()
            ->onAdd(Templates::CONFIG_KEY.'.{uid}', [$this->templates, 'handleChanged'])
            ->onUpdate(Templates::CONFIG_KEY.'.{uid}', [$this->templates, 'handleChanged'])
            ->onRemove(Templates::CONFIG_KEY.'.{uid}', [$this->templates, 'handleRemoved']);

        // Its images are remade, a few at a time, in the queue.
        Event::on(Templates::class, Templates::EVENT_AFTER_SAVE_TEMPLATE, function (TemplateEvent $event) {
            if ($this->getSettings()->enabled) {
                Craft::$app->getQueue()->push(new RemakeImages(['template' => $event->template->handle()]));
            }
        });
    }

    protected function registerFieldsAndActions(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = SocialImage::class;
        });

        Event::on(Entry::class, Element::EVENT_REGISTER_ACTIONS, function (RegisterElementActionsEvent $event) {
            $event->actions[] = GenerateSocialImages::class;
        });
    }

    protected function registerEntryEvents(): void
    {
        Event::on(Entry::class, Element::EVENT_AFTER_SAVE, function (ModelEvent $event) {
            /** @var Entry $entry */
            $entry = $event->sender;
            $settings = $this->getSettings();

            // Fires once per site the entry is saved in, so each site's image is made.
            if (! $settings->enabled || ! $settings->generateOnSave || ! $this->cards->supports($entry)) {
                return;
            }

            try {
                foreach ($this->cards->layoutsFor($entry) as $layout) {
                    $card = $this->cards->for($entry, $layout);

                    if ($card && ! $card->exists()) {
                        GenerateImage::queue($card);
                    }
                }
            } catch (Throwable $exception) {
                // A bad template mustn't stop an entry saving.
                Craft::error('Social Images couldn’t queue an image: '.$exception->getMessage(), 'social-images');
            }
        });

        Event::on(Entry::class, Element::EVENT_AFTER_DELETE, function (Event $event) {
            /** @var Entry $entry */
            $entry = $event->sender;

            if ($entry->id && $entry->sectionId) {
                $this->cards->forget($entry);
            }
        });
    }

    protected function registerTwig(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (Event $event) {
            $event->sender->set('socialImages', SocialImagesVariable::class);
        });
    }

    protected function registerCp(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['social-images'] = 'social-images/templates/index';
            $event->rules['social-images/templates/<handle:[a-z0-9_]+>'] = 'social-images/templates/edit';
            $event->rules['social-images/images'] = 'social-images/images/index';
            $event->rules['social-images/settings'] = 'social-images/settings/edit';
        });
    }

    /**
     * Always, not only in the control panel: saving permissions anywhere
     * (applying Project Config from the command line, say) keeps only those
     * registered.
     */
    protected function registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function (RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => Craft::t('social-images', 'Social Images'),
                'permissions' => [
                    self::PERMISSION_VIEW => ['label' => Craft::t('social-images', 'View social images')],
                    self::PERMISSION_MANAGE => ['label' => Craft::t('social-images', 'Manage social image templates')],
                ],
            ];
        });
    }
}
