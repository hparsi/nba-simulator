<?php

namespace Tests\Unit\Requests;

use PHPUnit\Framework\TestCase;
use App\Http\Requests\Game\GetGameEventsRequest;

class GetGameEventsRequestTest extends TestCase
{
    protected $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new GetGameEventsRequest();
    }

    /**
     * Test that the validation rules exist.
     */
    public function test_validation_rules_exist(): void
    {
        $rules = $this->request->rules();
        
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('limit', $rules);
        $this->assertArrayHasKey('since_id', $rules);
    }

    /**
     * Test that the limit validation rule is correct.
     */
    public function test_limit_validation_rule(): void
    {
        $rules = $this->request->rules();
        $this->assertEquals('sometimes|integer|min:1|max:100', $rules['limit']);
    }

    /**
     * Test that the since_id validation rule is correct.
     */
    public function test_since_id_validation_rule(): void
    {
        $rules = $this->request->rules();
        $this->assertEquals('sometimes|integer|min:0', $rules['since_id']);
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
