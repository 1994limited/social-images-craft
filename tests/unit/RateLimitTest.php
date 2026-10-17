<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\db\Query;
use DateTimeImmutable;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\QuotaExhausted;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RateLimited;
use nineteenninetyfour\socialimages\jobs\GenerateImage;

class RateLimitTest extends TestCase
{
    protected function queued(): array
    {
        return (new Query)->select(['delay', 'job'])->from('{{%queue}}')->orderBy(['id' => SORT_DESC])->all();
    }

    public function test_a_refused_render_waits_as_long_as_the_service_asks(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $this->camera->failWith(new RateLimited('Slow down', retryAfter: 42));

        $job = new GenerateImage(['entryId' => $entry->id, 'siteId' => $entry->siteId]);
        $this->assertNull($job->render());

        $this->assertSame(42, (int) $this->queued()[0]['delay']);
    }

    public function test_a_used_up_allowance_waits_for_its_reset_and_pages_dont_try_meanwhile(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $this->camera->failWith(new QuotaExhausted('No more today', new DateTimeImmutable('+2 hours')));

        (new GenerateImage(['entryId' => $entry->id, 'siteId' => $entry->siteId]))->render();

        $this->assertGreaterThan(7000, (int) $this->queued()[0]['delay']);
        $this->assertTrue(GenerateImage::recentlyFailed($this->plugin()->cards->for($entry)));

        $before = Craft::$app->getQueue()->getTotalJobs();
        $this->plugin()->cards->image($entry);
        $this->assertSame($before, Craft::$app->getQueue()->getTotalJobs());
    }

    public function test_outside_the_queue_the_error_is_thrown(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false]);
        $entry = $this->entry();
        $this->camera->failWith(new RateLimited('Slow down'));

        $this->expectException(RateLimited::class);
        (new GenerateImage(['entryId' => $entry->id, 'siteId' => $entry->siteId]))->render(queued: false);
    }

    public function test_it_gives_up_after_the_configured_attempts(): void
    {
        $this->template();
        $this->useTemplate('basic', ['journal']);
        $this->settings(['generateOnSave' => false, 'rateLimitAttempts' => 2]);
        $entry = $this->entry();
        $this->camera->failWith(new RateLimited('Slow down'));

        $this->expectException(RateLimited::class);
        (new GenerateImage(['entryId' => $entry->id, 'siteId' => $entry->siteId, 'attempt' => 2]))->render();
    }
}
