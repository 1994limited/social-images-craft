<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use craft\test\console\ConsoleTest;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\NotConfigured;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\QuotaExhausted;
use yii\console\ExitCode;

class CommandsTest extends ConsoleTest
{
    use Helpers;

    protected function _before(): void
    {
        parent::_before();

        $this->setUpSocialImages();
    }

    public function test_it_makes_missing_images_and_skips_current_ones(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal'], ['square']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();

        $this->consoleCommand('social-images/generate', [], true)->exitCode(ExitCode::OK)->run();

        // og only: the template has no square layout.
        $this->assertCount(1, $this->camera->captures);
        $this->assertTrue($this->plugin()->cards->for($entry)->exists());

        $this->consoleCommand('social-images/generate', [], true)->exitCode(ExitCode::OK)->run();
        $this->assertCount(1, $this->camera->captures);

        $this->consoleCommand('social-images/generate', ['force' => true, 'entry' => (string) $entry->id], true)->exitCode(ExitCode::OK)->run();
        $this->assertCount(2, $this->camera->captures);
    }

    public function test_it_stops_at_once_when_the_allowance_is_gone(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $this->entry('journal', 'One');
        $this->entry('journal', 'Two');
        $this->camera->failWith(new QuotaExhausted('No more today'));

        $this->consoleCommand('social-images/generate', [], true)
            ->stderr("The screenshot service’s allowance has run out. Run this again then to carry on.\n")
            ->exitCode(ExitCode::UNSPECIFIED_ERROR)
            ->run();

        $this->assertCount(0, $this->camera->captures);
    }

    public function test_other_failures_are_reported_and_the_run_goes_on(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $this->entry('journal', 'One');
        $this->entry('journal', 'Two');
        $this->camera->failWith(new NotConfigured('No Chrome', 'Install Chrome.'));

        $this->consoleCommand('social-images/generate', [], true)
            ->stderr("  No Chrome\n")
            ->stderr("  ⇂ Install Chrome.\n")
            ->exitCode(ExitCode::UNSPECIFIED_ERROR)
            ->run();

        $this->assertCount(1, $this->camera->captures);
    }

    public function test_the_doctor(): void
    {
        $this->consoleCommand('social-images/doctor', [], true)->exitCode(ExitCode::OK)->run();
        $this->consoleCommand('social-images/doctor', ['driver' => 'nope'], true)
            ->stderr("There’s no renderer called “nope”.\n")
            ->stderr("⇂ Use one of: browsershot, cloudflare, fake, or register yours first.\n")
            ->exitCode(ExitCode::UNSPECIFIED_ERROR)
            ->run();
    }
}
