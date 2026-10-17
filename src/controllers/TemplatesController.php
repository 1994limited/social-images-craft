<?php

namespace nineteenninetyfour\socialimages\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\web\UploadedFile;
use NineteenNinetyFour\SocialImages\Core\Rendering\Page;
use NineteenNinetyFour\SocialImages\Core\Rendering\Tokens;
use NineteenNinetyFour\SocialImages\Core\Templates\InvalidTemplate;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\base\Template;
use nineteenninetyfour\socialimages\models\CodeTemplate;
use nineteenninetyfour\socialimages\models\DesignerTemplate;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\Catalog;
use nineteenninetyfour\socialimages\support\InSite;
use nineteenninetyfour\socialimages\support\RateLimits;
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Social Images → Templates: the templates, the designer, and importing
 * and exporting.
 */
class TemplatesController extends BaseController
{
    public function actionIndex(): Response
    {
        $this->requireView();

        $plugin = Plugin::getInstance();
        $used = array_count_values(array_column($plugin->getSettings()->templateRules(), 'template'));
        $templates = [];

        foreach ($plugin->templates->all() as $template) {
            $designer = $template instanceof DesignerTemplate;
            $editable = $designer && $this->canChangeTemplates();

            $templates[] = [
                'handle' => $template->handle(),
                'title' => $template->title(),
                'kind' => $designer ? 'designer' : 'view',
                'view' => $template instanceof CodeTemplate ? $template->template() : null,
                'layouts' => $template->layouts(),
                'layout' => $designer ? $template->layout(Schema::BASE_LAYOUT) : null,
                'used_by' => $used[$template->handle()] ?? 0,
                'edit_url' => $editable ? UrlHelper::cpUrl('social-images/templates/'.$template->handle()) : null,
                'export_url' => $designer && $this->canManage() ? UrlHelper::actionUrl('social-images/templates/export', ['handle' => $template->handle()]) : null,
                'delete_url' => $editable ? UrlHelper::actionUrl('social-images/templates/delete', ['handle' => $template->handle()]) : null,
                'duplicate_url' => $editable ? UrlHelper::actionUrl('social-images/templates/duplicate', ['handle' => $template->handle()]) : null,
                'preview_url' => UrlHelper::actionUrl('social-images/templates/thumbnail', ['handle' => $template->handle()]),
            ];
        }

        $starters = [];

        foreach ($plugin->templates->starters() as $key => $data) {
            $starters[] = ['key' => $key, 'title' => $data['title'] ?? $key, 'description' => $data['description'] ?? '', 'layout' => $data['layouts'][Schema::BASE_LAYOUT] ?? null];
        }

        return $this->page('Templates', Craft::t('social-images', 'Social Images'), 'templates', [
            'templates' => $templates,
            'starters' => $starters,
            'canManage' => $this->canChangeTemplates(),
            'urls' => [
                'store' => UrlHelper::actionUrl('social-images/templates/store'),
                'import' => UrlHelper::actionUrl('social-images/templates/import'),
                'images' => UrlHelper::cpUrl('social-images/images'),
                'settings' => Craft::$app->getUser()->getIsAdmin() ? UrlHelper::cpUrl('social-images/settings') : null,
                'preview' => UrlHelper::actionUrl('social-images/templates/preview'),
            ],
            'gallery' => 'https://github.com/1994limited/social-images-statamic/tree/main/gallery',
        ]);
    }

    /**
     * A new template: blank, or from a starter.
     */
    public function actionStore(): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $templates = Plugin::getInstance()->templates;
        $starter = $this->request->getBodyParam('starter');

        if ($starter) {
            $data = $templates->starters()[$starter] ?? throw new NotFoundHttpException;
        } else {
            $data = ['schema' => Schema::VERSION, 'layouts' => [Schema::BASE_LAYOUT => ['width' => 1200, 'height' => 630, 'layers' => []]]];
        }

        $title = trim((string) $this->request->getBodyParam('title'));
        $data['title'] = mb_substr($title, 0, 100) ?: ($data['title'] ?? Craft::t('social-images', 'New template'));

        $template = $templates->save($templates->uniqueHandle($data['title']), $data);

        return $this->asJson(['redirect' => UrlHelper::cpUrl('social-images/templates/'.$template->handle())]);
    }

    public function actionDuplicate(string $handle): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $templates = Plugin::getInstance()->templates;
        $template = $this->designerTemplate($handle);
        $data = $template->toArray();
        $data['title'] = Craft::t('social-images', '{title} (copy)', ['title' => $template->title()]);

        $copy = $templates->save($templates->uniqueHandle($data['title']), $data);

        return $this->asJson(['redirect' => UrlHelper::cpUrl('social-images/templates/'.$copy->handle())]);
    }

    /**
     * Import from pasted JSON, an uploaded file or a URL.
     */
    public function actionImport(): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $porter = Plugin::getInstance()->porter();
        $file = UploadedFile::getInstanceByName('file');
        $url = trim((string) $this->request->getBodyParam('url'));
        $json = (string) $this->request->getBodyParam('json');
        $limit = Plugin::getInstance()->getSettings()->importMaxKilobytes * 1024;

        try {
            $data = match (true) {
                $file !== null => $file->size > $limit
                    ? throw new InvalidTemplate(Craft::t('social-images', 'That template is larger than the import limit.'))
                    : $porter->decode((string) file_get_contents($file->tempName)),
                $url !== '' => $porter->fetch($url),
                $json !== '' => strlen($json) > $limit
                    ? throw new InvalidTemplate(Craft::t('social-images', 'That template is larger than the import limit.'))
                    : $porter->decode($json),
                default => throw new InvalidTemplate(Craft::t('social-images', 'Paste a template, choose a file or give its address.')),
            };

            $title = trim((string) $this->request->getBodyParam('title'));
            $template = $porter->import($data, $title !== '' ? mb_substr($title, 0, 100) : null);
        } catch (InvalidTemplate $exception) {
            return $this->asJson(['message' => $exception->getMessage()])->setStatusCode(422);
        }

        Craft::$app->getSession()->setNotice(Craft::t('social-images', 'Template imported'));

        return $this->asJson(['redirect' => UrlHelper::cpUrl('social-images/templates/'.$template->handle())]);
    }

    public function actionEdit(string $handle): Response
    {
        $this->requireAdminChanges();

        $plugin = Plugin::getInstance();
        $template = $this->designerTemplate($handle);
        $catalog = new Catalog;

        return $this->page('Designer', $template->title(), 'templates', [
            'template' => ['handle' => $template->handle(), ...$template->toArray()],
            'fonts' => $plugin->fonts()->families(),
            'presets' => Schema::PRESETS,
            'collections' => $catalog->collections(),
            'entries' => $catalog->entries(user: Craft::$app->getUser()->getIdentity()),
            'samples' => Tokens::SAMPLES,
            'urls' => [
                'index' => UrlHelper::cpUrl('social-images'),
                'update' => UrlHelper::actionUrl('social-images/templates/update', ['handle' => $handle]),
                'export' => UrlHelper::actionUrl('social-images/templates/export', ['handle' => $handle]),
                'preview' => UrlHelper::actionUrl('social-images/templates/preview'),
                'render' => UrlHelper::actionUrl('social-images/templates/render'),
                'entries' => UrlHelper::actionUrl('social-images/templates/entries'),
                'fonts' => UrlHelper::actionUrl('social-images/library/fonts'),
                'google' => UrlHelper::actionUrl('social-images/library/google'),
                'font_upload' => UrlHelper::actionUrl('social-images/library/upload-font'),
                'upload' => UrlHelper::actionUrl('social-images/library/upload'),
                'upload_show' => UrlHelper::actionUrl('social-images/library/show', ['name' => '__NAME__']),
            ],
        ]);
    }

    public function actionUpdate(string $handle): Response
    {
        $this->requireAdminChanges();

        $this->designerTemplate($handle);

        try {
            $template = Plugin::getInstance()->templates->save($handle, (array) $this->request->getBodyParam('template'));
        } catch (InvalidTemplate $exception) {
            return $this->asJson(['message' => $exception->getMessage()])->setStatusCode(422);
        }

        return $this->asJson(['template' => ['handle' => $template->handle(), ...$template->toArray()]]);
    }

    public function actionDelete(string $handle): Response
    {
        $this->requireAdminChanges();

        $this->designerTemplate($handle);
        Plugin::getInstance()->templates->delete($handle);

        return $this->asJson(['deleted' => true]);
    }

    public function actionExport(string $handle): Response
    {
        $this->requireManage();

        $template = $this->designerTemplate($handle);
        $json = json_encode(Plugin::getInstance()->porter()->export($template->toArray()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->response->sendContentAsFile($json, $handle.'.social-image.json', ['mimeType' => 'application/json']);
    }

    /**
     * What the renderer needs to draw an unsaved layout, for the designer's
     * canvas: each layer's text or picture for the chosen entry (or sample
     * text), and the fonts.
     */
    public function actionPreview(): Response
    {
        $this->requirePostRequest();
        $this->requireView();

        $layout = Schema::layout((array) $this->request->getBodyParam('layout'));
        $entry = $this->entry($this->request->getBodyParam('entry'));

        return $this->asJson([
            'layout' => $layout,
            'data' => InSite::run($entry, fn () => Plugin::getInstance()->resolver()->resolve($layout, $entry)),
        ]);
    }

    /**
     * A real render, through the renderer: of an unsaved designer layout,
     * or of a saved template.
     */
    public function actionRender(): Response
    {
        $this->requirePostRequest();
        // Renders spend the screenshot service's allowance.
        $this->requireManage();

        $plugin = Plugin::getInstance();
        $entry = $this->entry($this->request->getBodyParam('entry'));

        try {
            if ($handle = $this->request->getBodyParam('template')) {
                $template = $plugin->templates->find((string) $handle) ?? throw new NotFoundHttpException;
                $layoutHandle = $template->hasLayout((string) $this->request->getBodyParam('layout_handle')) ? (string) $this->request->getBodyParam('layout_handle') : Schema::BASE_LAYOUT;
                $size = $template->layouts()[$layoutHandle];
                $html = $template->html($entry, $layoutHandle);
            } else {
                $layout = Schema::layout((array) $this->request->getBodyParam('layout'));
                $size = ['width' => $layout['width'], 'height' => $layout['height']];
                $html = InSite::run($entry, fn () => (new Page)->designer($layout, $plugin->resolver()->resolve($layout, $entry)));
            }

            $png = $plugin->camera->capture($html, $size['width'], $size['height']);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Craft::error('Social Images couldn’t render: '.$exception->getMessage(), 'social-images');

            return $this->asJson([
                'message' => Craft::t('social-images', 'The image couldn’t be rendered: {message}', ['message' => mb_substr($exception->getMessage(), 0, 300)]),
                'fix' => RateLimits::fix($exception),
            ])->setStatusCode(RateLimits::caused($exception) ? 429 : 500);
        }

        return $this->asJson(['image' => 'data:image/png;base64,'.base64_encode($png)]);
    }

    /**
     * A template's page with sample text (or a recent entry, for a code
     * template), for the index's thumbnails. Served as HTML into a
     * sandboxed frame.
     */
    public function actionThumbnail(string $handle): Response
    {
        $this->requireView();

        $template = Plugin::getInstance()->templates->find($handle) ?? throw new NotFoundHttpException;
        $entry = $this->entry($this->request->getQueryParam('entry'))
            ?? ($template instanceof CodeTemplate ? $this->exampleEntry($template) : null);

        try {
            $html = $template->html($entry, Schema::BASE_LAYOUT);
        } catch (Throwable $exception) {
            $html = '<p style="font:14px system-ui;padding:20px">'.htmlspecialchars(Craft::t('social-images', 'This template couldn’t be shown: {message}', ['message' => $exception->getMessage()])).'</p>';
        }

        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->content = $html;
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        // Sandboxed even when opened directly: a code template shows entry
        // values unescaped, so its script must not run as the control panel.
        $response->headers->set('Content-Security-Policy', "sandbox allow-scripts; default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'");
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    public function actionEntries(): Response
    {
        $this->requireView();

        return $this->asJson(['entries' => (new Catalog)->entries(
            (string) $this->request->getQueryParam('search') ?: null,
            (string) $this->request->getQueryParam('collection') ?: null,
            Craft::$app->getUser()->getIdentity(),
        )]);
    }

    /**
     * A recent entry that uses the template, which the user can see.
     */
    protected function exampleEntry(Template $template): ?Entry
    {
        $plugin = Plugin::getInstance();
        $user = Craft::$app->getUser()->getIdentity();

        foreach (Entry::find()->sectionId(':notempty:')->orderBy(['postDate' => SORT_DESC])->limit(200)->all() as $entry) {
            if ($plugin->cards->supports($entry) && $plugin->cards->templateFor($entry)?->handle() === $template->handle() && Craft::$app->getElements()->canView($entry, $user)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The entry to preview with, if the user may see it: its values are
     * shown, so they're as private as the entry.
     */
    protected function entry(mixed $id): ?Entry
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        $entry = (new Catalog)->find($id);

        if (! $entry || ! Craft::$app->getElements()->canView($entry, Craft::$app->getUser()->getIdentity())) {
            throw new NotFoundHttpException;
        }

        return $entry;
    }

    protected function designerTemplate(string $handle): DesignerTemplate
    {
        return Plugin::getInstance()->templates->designer($handle) ?? throw new NotFoundHttpException;
    }
}
