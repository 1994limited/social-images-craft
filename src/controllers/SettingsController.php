<?php

namespace nineteenninetyfour\socialimages\controllers;

use Craft;
use craft\elements\Asset;
use craft\helpers\UrlHelper;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\integrations\Seomatic;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\Diagnose;
use yii\web\Response;

/**
 * Social Images → Settings: which template entries use, generating, the
 * fallback image, where images are kept and SEOmatic. Saved to Project
 * Config, so only where admin changes are allowed.
 */
class SettingsController extends BaseController
{
    public function actionEdit(): Response
    {
        // Shown read-only where admin changes aren't allowed.
        $this->requireAdmin(false);

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $sections = [];
        $sites = [];
        $templates = [];
        $layouts = [];
        $filesystems = [['label' => Craft::t('social-images', 'The site’s web folder (web/social-images)'), 'value' => '']];

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $sections[] = ['label' => $section->name, 'value' => $section->uid];
        }

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $sites[] = ['label' => $site->getName(), 'value' => $site->uid];
        }

        foreach ($plugin->templates->all() as $handle => $template) {
            $templates[] = ['label' => $template->title(), 'value' => $handle];
        }

        foreach (Schema::PRESETS as $handle => $preset) {
            if ($handle !== Schema::BASE_LAYOUT) {
                $layouts[] = ['label' => Craft::t('social-images', $preset['title']).' ('.$preset['width'].' × '.$preset['height'].')', 'value' => $handle];
            }
        }

        foreach (Craft::$app->getFs()->getAllFilesystems() as $fs) {
            if ($fs->hasUrls) {
                $filesystems[] = ['label' => $fs->name, 'value' => $fs->handle];
            }
        }

        return $this->renderTemplate('social-images/settings', [
            'title' => Craft::t('social-images', 'Settings'),
            'selectedSubnavItem' => 'settings',
            'settings' => $settings,
            'rules' => $settings->templateRules() ?: [['sections' => [], 'sites' => [], 'template' => '', 'layouts' => []]],
            'sections' => $sections,
            'sites' => $sites,
            'templates' => $templates,
            'layouts' => $layouts,
            'filesystems' => $filesystems,
            'multisite' => Craft::$app->getIsMultiSite(),
            'fallback' => $settings->fallbackImageId ? Asset::find()->id($settings->fallbackImageId)->one() : null,
            'seomaticInstalled' => Seomatic::installed(),
            'readOnly' => ! Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
            'driver' => $plugin->renderers()->defaultDriver(),
            'checkUrl' => UrlHelper::actionUrl('social-images/settings/check'),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        // Settings are Project Config: Craft's check refuses where admin
        // changes aren't allowed.
        $this->requireAdmin();

        $plugin = Plugin::getInstance();
        $rules = [];

        foreach ((array) $this->request->getBodyParam('rules', []) as $rule) {
            if (! is_array($rule) || empty($rule['template'])) {
                continue;
            }

            $rules[] = [
                'sections' => array_values(array_filter((array) ($rule['sections'] ?? []), fn ($value) => is_string($value) && $value !== '')),
                'sites' => array_values(array_filter((array) ($rule['sites'] ?? []), fn ($value) => is_string($value) && $value !== '')),
                'template' => (string) $rule['template'],
                'layouts' => array_values(array_filter((array) ($rule['layouts'] ?? []), fn ($value) => is_string($value) && $value !== '')),
            ];
        }

        $fallback = $this->request->getBodyParam('fallbackImageId');
        $fallback = is_array($fallback) ? (reset($fallback) ?: null) : ($fallback ?: null);

        $saved = Craft::$app->getPlugins()->savePluginSettings($plugin, [
            'rules' => $rules,
            'generateOnSave' => (bool) $this->request->getBodyParam('generateOnSave'),
            'fallbackImageId' => $fallback ? (int) $fallback : null,
            'seomatic' => (string) $this->request->getBodyParam('seomatic', 'replace'),
            'imagesFs' => (string) $this->request->getBodyParam('imagesFs', ''),
            'imagesPath' => trim((string) $this->request->getBodyParam('imagesPath', 'social-images'), '/'),
        ]);

        if (! $saved) {
            $this->setFailFlash(Craft::t('social-images', 'Couldn’t save the settings.'));

            return null;
        }

        return $this->asSuccess(Craft::t('social-images', 'Settings saved.'));
    }

    /**
     * The Test button: one test render with the renderer in use.
     */
    public function actionCheck(): Response
    {
        $this->requirePostRequest();
        // A render spends the screenshot service's allowance.
        $this->requireManage();

        return $this->asJson(Diagnose::driver());
    }
}
