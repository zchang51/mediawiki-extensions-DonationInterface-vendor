<?php namespace SmashPig\Core\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

class IncomingMessage implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ) {

		$destinationQueue = $msg->getDestinationQueue();

		if ( $destinationQueue ) {
			$queue = Context::get()->getConfiguration()->object( "data-store/{$destinationQueue}" );
			$queueMsg = $msg->normalizeForQueue();

			$queue->push( $queueMsg );
		} else {
			$class = get_class( $msg );
			Logger::warning( "Ignoring message of type {$class}", $msg );
		}

		return true;
	}
}
