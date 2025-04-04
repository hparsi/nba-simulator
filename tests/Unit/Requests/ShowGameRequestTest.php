<?php

namespace Tests\Unit\Requests;

use PHPUnit\Framework\TestCase;
use App\Http\Requests\Game\ShowGameRequest;

class ShowGameRequestTest extends TestCase
{
    protected $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new ShowGameRequest();
    }

    /**
     * Test that validation rules exist but are empty (because validation happens via route parameter).
     */
    public function test_validation_rules_exist(): void
    {
        $rules = $this->request->rules();
        
        $this->assertIsArray($rules);
        $this->assertEmpty($rules);
    }

    /**
     * Test that authorization is granted.
     */
    public function test_authorization_is_granted(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    /**
     * Test that prepare for validation method exists.
     */
    public function test_prepare_for_validation_exists(): void
    {
        $this->assertTrue(method_exists($this->request, 'prepareForValidation'));
    }
}
