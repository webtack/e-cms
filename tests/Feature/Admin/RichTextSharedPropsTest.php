<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class RichTextSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_iframe_hosts_are_shared_with_the_client(): void
    {
        config(['content.richtext.iframe_hosts' => ['www.youtube-nocookie.com/', 'drive.google.com/']]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/content-types/create')
            ->assertInertia(fn (Assert $page) => $page
                ->where('richtext.iframeHosts', ['www.youtube-nocookie.com/', 'drive.google.com/']));
    }
}
