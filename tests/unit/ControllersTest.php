<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\elements\User;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\QuotaExhausted;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ControllersTest extends TestCase
{
    protected function _before(): void
    {
        parent::_before();

        Craft::$app->getRequest()->enableCsrfValidation = false;

        // The tests run from the command line, so the plugin chose its
        // console controllers.
        $this->plugin()->controllerNamespace = 'nineteenninetyfour\\socialimages\\controllers';
    }

    protected function _after(): void
    {
        Craft::$app->getUser()->setIdentity(null);
        unset($_SERVER['REQUEST_METHOD']);
        $this->plugin()->controllerNamespace = 'nineteenninetyfour\\socialimages\\console\\controllers';

        parent::_after();
    }

    /**
     * A user with these permissions (an admin with none given).
     */
    protected function actingAs(?array $permissions = null): User
    {
        $user = $this->user('Ada Lovelace', ['admin' => $permissions === null]);

        if ($permissions !== null) {
            Craft::$app->getUserPermissions()->saveUserPermissions($user->id, ['accesscp', ...$permissions]);
        }

        Craft::$app->getUser()->setIdentity($user);

        return $user;
    }

    protected function action(string $route, array $params = [], string $method = 'POST', array $body = []): Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $request = Craft::$app->getRequest();
        $request->setBodyParams($body);
        $request->setQueryParams($params);
        Craft::$app->getResponse()->clear();

        return Craft::$app->runAction($route, $params);
    }

    protected function json(Response $response): array
    {
        return is_array($response->data) ? $response->data : json_decode((string) $response->content, true);
    }

    public function test_viewing_needs_permission(): void
    {
        $this->actingAs([]);

        $this->expectException(ForbiddenHttpException::class);
        $this->action('social-images/templates/entries', method: 'GET');
    }

    public function test_editing_templates_needs_manage_permission(): void
    {
        $this->template();
        $this->actingAs([Plugin::PERMISSION_VIEW]);

        $this->expectException(ForbiddenHttpException::class);
        $this->action('social-images/templates/update', ['handle' => 'basic'], 'PATCH', ['template' => ['title' => 'Hacked']]);
    }

    public function test_templates_cant_change_without_admin_changes(): void
    {
        $this->template();
        $this->actingAs();
        Craft::$app->getConfig()->getGeneral()->allowAdminChanges = false;

        try {
            $this->expectException(ForbiddenHttpException::class);
            $this->action('social-images/templates/store', body: ['title' => 'New']);
        } finally {
            Craft::$app->getConfig()->getGeneral()->allowAdminChanges = true;
        }
    }

    public function test_creating_from_a_starter_and_saving(): void
    {
        $this->actingAs([Plugin::PERMISSION_MANAGE]);

        $data = $this->json($this->action('social-images/templates/store', body: ['starter' => 'editorial', 'title' => 'Our journal']));
        $this->assertStringEndsWith('/social-images/templates/our_journal', $data['redirect']);

        $template = $this->plugin()->templates->designer('our_journal')->toArray();
        $template['title'] = 'Renamed';

        $data = $this->json($this->action('social-images/templates/update', ['handle' => 'our_journal'], 'PATCH', ['template' => $template]));
        $this->assertSame('Renamed', $data['template']['title']);
    }

    public function test_preview_only_shows_entries_the_user_can_see(): void
    {
        $this->template();
        $entry = $this->entry('journal', 'Secret draft');
        $this->actingAs([Plugin::PERMISSION_VIEW]);

        $layout = $this->plugin()->templates->designer('basic')->layout('og');

        $this->expectException(NotFoundHttpException::class);
        $this->action('social-images/templates/preview', body: ['layout' => $layout, 'entry' => $entry->id.':'.$entry->siteId]);
    }

    public function test_preview_resolves_an_entry(): void
    {
        $this->template();
        $entry = $this->entry('journal', 'Visible');
        $this->actingAs();

        $data = $this->json($this->action('social-images/templates/preview', body: [
            'layout' => $this->plugin()->templates->designer('basic')->layout('og'),
            'entry' => $entry->id.':'.$entry->siteId,
        ]));

        $this->assertSame('Visible', $data['data']['layers']['title']['text']);
    }

    public function test_importing_pasted_json(): void
    {
        $this->actingAs([Plugin::PERMISSION_MANAGE]);
        $json = json_encode(['schema' => Schema::VERSION, 'title' => 'Imported', 'layouts' => ['og' => ['layers' => []]]]);

        $this->json($this->action('social-images/templates/import', body: ['json' => $json]));
        $this->assertNotNull($this->plugin()->templates->designer('imported'));

        $response = $this->action('social-images/templates/import', body: ['json' => '{"not":"a template"}']);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_importing_from_a_private_address_is_refused(): void
    {
        $this->actingAs([Plugin::PERMISSION_MANAGE]);

        $response = $this->action('social-images/templates/import', body: ['url' => 'https://169.254.169.254/latest/meta-data']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('public addresses', $this->json($response)['message']);
    }

    public function test_thumbnails_are_sandboxed(): void
    {
        $this->template();
        $this->actingAs([Plugin::PERMISSION_VIEW]);

        $response = $this->action('social-images/templates/thumbnail', ['handle' => 'basic'], 'GET');

        $this->assertStringStartsWith('sandbox allow-scripts', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('Your entry’s title shows here', $this->pageData($response->content));
    }

    public function test_uploads_are_served_sandboxed_and_only_by_name(): void
    {
        $name = $this->plugin()->uploads()->put(FakeCamera::PNG, 'png');
        $this->actingAs([Plugin::PERMISSION_VIEW]);

        $response = $this->action('social-images/library/show', ['name' => $name], 'GET');
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));

        $this->expectException(NotFoundHttpException::class);
        $this->action('social-images/library/show', ['name' => '../../config/db.php'], 'GET');
    }

    public function test_regenerating_says_when_the_allowance_is_gone(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $this->actingAs();
        $this->camera->failWith(new QuotaExhausted('No more today', new \DateTimeImmutable('2026-10-18T00:00:00Z')));

        $response = $this->action('social-images/images/regenerate', body: ['entryId' => $entry->id, 'siteId' => $entry->siteId]);
        $data = $this->json($response);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($data['quota']);
        $this->assertSame('2026-10-18T00:00:00+00:00', $data['resets_at']);
    }

    public function test_regenerating_needs_edit_rights(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $entry = $this->entry();
        $this->actingAs([Plugin::PERMISSION_VIEW, 'viewentries:'.$entry->getSection()->uid]);

        $this->expectException(ForbiddenHttpException::class);
        $this->action('social-images/images/regenerate', body: ['entryId' => $entry->id, 'siteId' => $entry->siteId]);
    }

    public function test_settings_are_for_admins_only(): void
    {
        $this->actingAs([Plugin::PERMISSION_MANAGE]);

        $this->expectException(ForbiddenHttpException::class);
        $this->action('social-images/settings/save', body: ['seomatic' => 'off']);
    }

    public function test_the_settings_test_button(): void
    {
        $this->actingAs([Plugin::PERMISSION_MANAGE]);

        $data = $this->json($this->action('social-images/settings/check'));

        $this->assertTrue($data['ok']);
        $this->assertSame('fake', $data['driver']);
    }

    public function test_the_images_page_regenerates_with_ids_in_the_url(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $this->actingAs();

        $data = $this->json($this->action('social-images/images/regenerate', ['entryId' => $entry->id, 'siteId' => $entry->siteId, 'layout' => 'og']));

        $this->assertStringContainsString('/social-images/'.$entry->id.'/'.$entry->siteId.'/og-', $data['url']);
    }

    public function test_templates_cant_be_deleted_over_get(): void
    {
        $this->template();
        $this->actingAs();

        try {
            $this->expectException(BadRequestHttpException::class);
            $this->action('social-images/templates/delete', ['handle' => 'basic'], 'GET');
        } finally {
            $this->assertNotNull($this->plugin()->templates->find('basic'));
        }
    }

    public function test_viewers_preview_saved_layouts_only(): void
    {
        $template = $this->template();
        $asset = $this->asset('private.png');
        $this->actingAs([Plugin::PERMISSION_VIEW]);

        $this->json($this->action('social-images/templates/preview', body: ['layout' => $template->layout('og')]));

        $this->expectException(ForbiddenHttpException::class);
        $this->action('social-images/templates/preview', body: ['layout' => Schema::layout(['layers' => [['id' => 'p', 'type' => 'image', 'source' => ['asset:'.$asset->id]]]])]);
    }

    public function test_a_long_title_still_makes_a_valid_handle(): void
    {
        $this->actingAs([Plugin::PERMISSION_MANAGE]);
        $title = 'A remarkably long template title for testing handles';

        $this->action('social-images/templates/store', body: ['title' => $title]);
        $data = $this->json($this->action('social-images/templates/store', body: ['title' => $title]));

        $this->assertMatchesRegularExpression('#/templates/[a-z0-9_]{1,40}$#', $data['redirect']);
    }
}
