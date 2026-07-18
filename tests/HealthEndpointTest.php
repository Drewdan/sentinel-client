<?php

namespace Drewdan\SentinelClient\Tests;

class HealthEndpointTest extends TestCase {

	protected function tearDown(): void {
		putenv('SENTINEL_HEALTH_ENDPOINT_PATH');
		putenv('SENTINEL_HEALTH_ENDPOINT_ENABLED');

		parent::tearDown();
	}

	public function testItRespondsOkAtTheDefaultPath(): void {
		$response = $this->getJson('/_sentinel/health');

		$response->assertOk();
		$response->assertJson(['status' => 'ok']);
		$response->assertJsonStructure(['status', 'timestamp']);
	}

	public function testItRespondsAtCustomConfiguredPath(): void {
		putenv('SENTINEL_HEALTH_ENDPOINT_PATH=/custom/health');
		$this->refreshApplication();

		$response = $this->getJson('/custom/health');

		$response->assertOk();
	}

	public function testItIsNotRegisteredWhenDisabled(): void {
		putenv('SENTINEL_HEALTH_ENDPOINT_ENABLED=false');
		$this->refreshApplication();

		$response = $this->getJson('/_sentinel/health');

		$response->assertNotFound();
	}

}
