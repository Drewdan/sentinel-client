<?php

namespace Drewdan\SentinelClient\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FailingTestJob implements ShouldQueue {

	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	public function handle(): void {
		throw new \RuntimeException('The job blew up');
	}

}
