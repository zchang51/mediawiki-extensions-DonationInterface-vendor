<?php

namespace SmashPig\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\QueueConsumers\PendingQueueConsumer;

class PendingQueueConsumerTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PendingDatabase
	 */
	protected $pendingDb;

	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $paymentsInitialDb;

	public function setUp() {
		parent::setUp();
		// Merge db and queue test configs.
		$config = TestingConfiguration::loadConfigWithFileOverrides( array(
			__DIR__ . '/data/config_smashpig_db.yaml',
			__DIR__ . '/data/config_queue.yaml',
		) );
		Context::initWithLogger( $config );

		$this->pendingDb = PendingDatabase::get();
		$this->pendingDb->createTable();
		$this->paymentsInitialDb = PaymentsInitialDatabase::get();
		$this->paymentsInitialDb->createTable();
	}

	public function tearDown() {
		// FIXME: huh.  I guess we should use class names to avoid possible
		// incomplete destruction in the case that paymentsInitialDb was never
		// initialized.
		TestingDatabase::clearStatics( $this->paymentsInitialDb );
		TestingDatabase::clearStatics( $this->pendingDb );

		parent::tearDown();
	}

	/**
	 * We consume a message normally if there's nothing in the payments_initial
	 * table.
	 */
	public function testPendingMessageNotInInitial() {
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000 );
		$message = self::generateRandomPendingMessage();

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNotNull( $fetched,
			'Message was consumed and stored in the pending database.' );

		unset( $fetched['pending_id'] );
		$this->assertEquals( $message, $fetched,
			'Stored message is equal to the consumed message.' );
	}

	/**
	 * We consume a message normally if the corresponding payments_initial row
	 * is still pending.
	 */
	public function testPendingMessageInitialPending() {
		$initRow = PaymentsInitialDatabaseTest::generateTestMessage();
		$initRow['payments_final_status'] = 'pending';

		$this->paymentsInitialDb->storeMessage( $initRow );

		$message = self::generatePendingMessageFromInitial( $initRow );
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000 );

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNotNull( $fetched,
			'Message was consumed and stored in the pending database.' );

		unset( $fetched['pending_id'] );
		$this->assertEquals( $message, $fetched,
			'Stored message is equal to the consumed message.' );
	}

	/**
	 * We refuse to consume a message and drop it if the corresponding
	 * payments_initial row is complete.
	 */
	public function testPendingMessageInitialComplete() {
		$initRow = PaymentsInitialDatabaseTest::generateTestMessage();
		$initRow['payments_final_status'] = 'complete';

		$this->paymentsInitialDb->storeMessage( $initRow );

		$message = self::generatePendingMessageFromInitial( $initRow );
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000 );

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNull( $fetched,
			'Message consumed and not stored in the pending database.' );
	}

	/**
	 * We refuse to consume a message and drop it if the corresponding
	 * payments_initial row is failed.
	 */
	public function testPendingMessageInitialFailed() {
		$initRow = PaymentsInitialDatabaseTest::generateTestMessage();
		$initRow['payments_final_status'] = 'failed';

		$this->paymentsInitialDb->storeMessage( $initRow );

		$message = self::generatePendingMessageFromInitial( $initRow );
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000 );

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNull( $fetched,
			'Message consumed and not stored in the pending database.' );
	}

	public static function generateRandomPendingMessage() {
		$message = array(
			'gateway' => 'test',
			'date' => time(),
			'order_id' => mt_rand(),
			'cousin' => 'itt',
			'kookiness' => mt_rand(),
		);
		return $message;
	}

	/**
	 * Create an incoming pending message corresponding to a given
	 * payments_initial row.
	 *
	 * @param array $initialRow
	 * @return array Message suitable for the pending queue.
	 */
	public static function generatePendingMessageFromInitial( $initialRow ) {
		$message = array(
			'gateway' => $initialRow['gateway'],
			'date' => $initialRow['date'],
			'order_id' => $initialRow['order_id'],
			'cousin' => 'itt',
			'kookiness' => mt_rand(),
		);
		return $message;
	}
}
