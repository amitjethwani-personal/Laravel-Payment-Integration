<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */

    public function testBasicExample()
    {
        $response = $this->json('POST', '/triggerpayment',
            [
                'stripeToken' => 'VRVEBXHFlsFwOzCrHFtKiDnkMguYY7uKziPqZK0O',
                'stripeEmail' => 'amitjethwani16@yahoo.in',
                'stripeTokenType' => 'card'
            ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                "status" => "500",
                "message" => "No such token: VRVEBXHFlsFwOzCrHFtKiDnkMguYY7uKziPqZK0O"
            ]);
    }
}
