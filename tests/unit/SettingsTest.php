<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;

class SettingsTest extends TestCase
{
    public function test_secrets_and_config_only_settings_never_reach_project_config(): void
    {
        $plugin = $this->plugin();
        $plugin->getSettings()->renderer['drivers']['cloudflare']['api_token'] = 'secret-token';

        Craft::$app->getPlugins()->savePluginSettings($plugin, [
            'rules' => [],
            'seomatic' => 'always',
            'renderer' => ['driver' => 'cloudflare', 'drivers' => ['cloudflare' => ['api_token' => 'posted-token']]],
            'libraryPath' => '/tmp/elsewhere',
        ]);

        $saved = Craft::$app->getProjectConfig()->get('plugins.social-images.settings');

        $this->assertSame('always', $saved['seomatic']);
        $this->assertArrayNotHasKey('renderer', $saved);
        $this->assertArrayNotHasKey('libraryPath', $saved);
        $this->assertStringNotContainsString('token', json_encode(Craft::$app->getProjectConfig()->get('plugins')));
    }

    public function test_rules_match_section_and_site(): void
    {
        $this->template();
        $site = Craft::$app->getSites()->getPrimarySite();
        $this->useTemplate('basic', ['journal'], sites: ['another-site-uid']);

        $this->assertNull($this->plugin()->cards->templateFor($this->entry()));

        $this->useTemplate('basic', ['journal'], sites: [$site->uid]);
        $this->assertSame('basic', $this->plugin()->cards->templateFor($this->entry())->handle());
    }
}
