<?php

namespace Drewdan\SentinelClient\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SentinelContextProcessorTest extends TestCase {

	public function testItAddsRequestContextWhenRequestIsBound(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		$request = Request::create('https://app.test/some/path', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5']);
		$request->headers->set('User-Agent', 'PHPUnit');
		$this->app->instance('request', $request);

		Log::channel('sentinel')->info('With request context');

		Http::assertSent(
			function ($sent) {
				$context = $sent['data']['context'];

				return $context['request']['ip'] === '203.0.113.5'
				&& $context['request']['url'] === 'https://app.test/some/path'
				&& $context['request']['method'] === 'POST'
				&& $context['request']['user_agent'] === 'PHPUnit';
			},
		);
	}

	public function testItAddsUserContextWhenAuthenticated(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		Auth::guard()->setUser($this->fakeUser());

		Log::channel('sentinel')->info('With user context');

		Http::assertSent(
			function ($sent) {
				$context = $sent['data']['context'];

				return $context['user']['id'] === 99
				&& $context['user']['email'] === 'person@example.com';
			},
		);
	}

	public function testItDoesNotAddUserContextWhenUnauthenticated(): void {
		config(['sentinel-client.ingest_url' => 'https://sentinel.test/project/abc/environment/def/ingest']);
		Http::fake();

		Log::channel('sentinel')->info('No user context');

		Http::assertSent(fn ($sent) => ! array_key_exists('user', $sent['data']['context'] ?? []));
	}

	private function fakeUser(): Authenticatable {
		return new class implements Authenticatable
		{

			public int $id = 99;

			public string $email = 'person@example.com';

			public function getAuthIdentifierName(): string {
				return 'id';
			}

			public function getAuthIdentifier(): int {
				return $this->id;
			}

			public function getAuthPasswordName(): string {
				return 'password';
			}

			public function getAuthPassword(): string {
				return '';
			}

			public function getRememberToken(): ?string {
				return null;
			}

			public function setRememberToken($value): void {
			}

			public function getRememberTokenName(): string {
				return '';
			}

		};
	}

}
