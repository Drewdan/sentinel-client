<?php

namespace Drewdan\SentinelClient;

use DateTimeInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Ships queued job executions to Sentinel, so failures (and, if configured,
 * successful runs too) show up alongside logs and exceptions. Governed by
 * the `sentinel-client.track_jobs` setting: "failed" (default), "all", or
 * "none". Never throws — a failure here must never break job processing.
 */
class SentinelJobSubscriber {

	/** @var array<string, float> */
	private array $startedAt = [];

	public function handleJobProcessing(JobProcessing $event): void {
		try {
			$this->startedAt[$event->job->getJobId()] = microtime(true);
		} catch (\Throwable $e) {
			unset($e);
		}
	}

	public function handleJobProcessed(JobProcessed $event): void {
		try {
			if (config('sentinel-client.track_jobs', 'failed') !== 'all') {
				unset($this->startedAt[$event->job->getJobId()]);

				return;
			}

			IngestClient::send($this->buildPayload($event->job, 'completed'));
		} catch (\Throwable $e) {
			unset($e);
		}
	}

	public function handleJobFailed(JobFailed $event): void {
		try {
			if (! in_array(config('sentinel-client.track_jobs', 'failed'), ['failed', 'all'], true)) {
				unset($this->startedAt[$event->job->getJobId()]);

				return;
			}

			IngestClient::send($this->buildPayload($event->job, 'failed', $event->exception));
		} catch (\Throwable $e) {
			unset($e);
		}
	}

	public function subscribe(Dispatcher $events): void {
		// Bound to $this (not the class name) so the same instance handles
		// processing/processed/failed for a given job — that's what lets us
		// correlate the start time recorded in handleJobProcessing() with the
		// duration computed later. A class-string listener would resolve a
		// fresh instance per event and lose that state.
		$events->listen(JobProcessing::class, [$this, 'handleJobProcessing']);
		$events->listen(JobProcessed::class, [$this, 'handleJobProcessed']);
		$events->listen(JobFailed::class, [$this, 'handleJobFailed']);
	}

	private function buildPayload(Job $job, string $status, ?\Throwable $exception = null): array {
		$jobId = $job->getJobId();
		$startedAt = $this->startedAt[$jobId] ?? null;
		unset($this->startedAt[$jobId]);

		$data = [
			'status' => $status,
			'class' => $job->resolveName(),
			'connection' => $job->getConnectionName(),
			'queue' => $job->getQueue(),
			'duration_ms' => $startedAt !== null ? (int) round((microtime(true) - $startedAt) * 1000) : null,
			'exception' => $exception ? $this->buildException($exception) : null,
			'context' => array_filter(['attempts' => $job->attempts()]),
			'occurred_at' => now()->format(DateTimeInterface::ATOM),
		];

		return ['type' => 'job', 'data' => $data];
	}

	private function buildException(\Throwable $exception): array {
		$data = ExceptionSerializer::toArray($exception);

		if (config('sentinel-client.capture_code_snippets', true)) {
			$data['snippet'] = SourceSnippetExtractor::around(
				$data['file'],
				$data['line'],
				(int) config('sentinel-client.code_snippet_lines', 5),
			);
		}

		return $data;
	}

}
