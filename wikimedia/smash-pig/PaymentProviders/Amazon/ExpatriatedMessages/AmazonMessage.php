<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Amazon\Messages\NormalizedMessage;

abstract class AmazonMessage extends ListenerMessage {

	protected $gateway_txn_id;
	protected $currency;
	protected $date;
	protected $gross;

	/**
	 * Do common normalizations.  Subclasses should perform normalizations
	 * specific to that message type.
	 *
	 * @return array associative queue message thing
	 */
	public function normalizeForQueue() {
		$queueMsg = new NormalizedMessage();

		$queueMsg->correlationId = $this->correlationId;
		$queueMsg->date = $this->date;
		$queueMsg->gateway = 'amazon';
		$queueMsg->gross = $this->gross;

		return $queueMsg;
	}

	public function getDestinationQueue() {
		// stub
		return null;
	}

	public function validate() {
		return true;
	}

	protected function setGatewayIds( $amazonId ) {
		$this->gateway_txn_id = $amazonId;
		$this->correlationId = 'amazon-' . $this->gateway_txn_id;
	}

	public function getGatewayTransactionId() {
		return $this->gateway_txn_id;
	}
}
