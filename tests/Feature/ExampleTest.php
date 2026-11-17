<?php

namespace Webtack\Ecms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The home page of the public site answers 200 (details: tests/Feature/Public/PublicRoutesTest.php).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
