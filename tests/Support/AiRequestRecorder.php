<?php
/**
 * Records AI provider requests and image-preparation hook calls in tests,
 * answering every request with a fake Gemini reply.
 *
 * @package TinySolutions\mlt
 */

/**
 * TsmltAiRequestRecorder
 */
class TsmltAiRequestRecorder {

	/**
	 * Outgoing request URLs.
	 *
	 * @var string[]
	 */
	public $requests = [];

	/**
	 * Attachment IDs the image-preparation hook ran for.
	 *
	 * @var int[]
	 */
	public $prepared = [];

	/**
	 * Text the fake provider answers with.
	 *
	 * @var string
	 */
	public $reply = '1. Suggested text';

	/**
	 * Start recording.
	 *
	 * @return void
	 */
	public function start(): void {
		add_filter( 'pre_http_request', [ $this, 'answer' ], 10, 3 );
		add_action( 'tsmlt_ai_prepare_image', [ $this, 'record_prepare' ], 1, 2 );
	}

	/**
	 * Stop recording.
	 *
	 * @return void
	 */
	public function stop(): void {
		remove_filter( 'pre_http_request', [ $this, 'answer' ], 10 );
		remove_action( 'tsmlt_ai_prepare_image', [ $this, 'record_prepare' ], 1 );
	}

	/**
	 * Record a request and answer it like Gemini.
	 *
	 * @param false|array $preempt Short-circuit value.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 *
	 * @return array
	 */
	public function answer( $preempt, $args, $url ) {
		$this->requests[] = (string) $url;
		return [
			'headers'  => [],
			'body'     => wp_json_encode(
				[
					'candidates' => [
						[
							'finishReason' => 'STOP',
							'content'      => [ 'parts' => [ [ 'text' => $this->reply ] ] ],
						],
					],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Record an image-preparation hook call.
	 *
	 * @param object $api           AiApi instance.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function record_prepare( $api, $attachment_id ): void {
		$this->prepared[] = (int) $attachment_id;
	}
}
