<?php

namespace GaneshaPlugin\Adapter;

use Ackintosh\Ganesha;
use Ackintosh\Ganesha\Configuration;
use Ackintosh\Ganesha\Storage\AdapterInterface;
use Ackintosh\Ganesha\Storage\Adapter\TumblingTimeWindowInterface;
use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\PasswordAuthenticator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \GaneshaPlugin\Adapter\Couchbase
 */
class CouchbaseAdapterTest extends TestCase
{
    private function createBucket()
    {
        $host = getenv('TEST_COUCHBASE_HOST');
        $cluster = new Cluster('couchbase://' . $host);
        $auth = new PasswordAuthenticator();
        $auth->username('rusername')->password('rpassword');
        $cluster->authenticate($auth);

        return $cluster->openBucket('bucket');
    }

    private function createExceptionBucket()
    {
        $methods = ['get', 'upsert', 'counter'];
        $bucket = $this->getMockBuilder(Bucket::class)
                       ->disableOriginalConstructor()
                       ->setMethods($methods)
                       ->getMock();
        foreach ($methods as $method) {
            $bucket->method($method)
                   ->will($this->throwException(
                       new \Couchbase\Exception('dummy message', COUCHBASE_NETWORK_ERROR)
                   ));
        }
        return $bucket;
    }

    private function flushBucket($bucket)
    {
        $bucket->manager()->flush();
    }

    /**
     * @covers ::__construct
     */
    public function testInstance()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);

        $this->assertInstanceOf(AdapterInterface::class, $adapter);
        $this->assertInstanceOf(TumblingTimeWindowInterface::class, $adapter);
    }

    /**
     * @covers ::setConfiguration
     */
    public function testSetConfiguration()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);

        $adapter->setConfiguration(new Configuration(['foo' => 'bar']));
        $this->assertTrue(true); // check not to throw exception
    }

    /**
     * @covers ::load
     * @covers ::<private>
     */
    public function testLoadFirst()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $this->assertSame(0, $adapter->load('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @covers ::load
     * @covers ::save
     * @covers ::<private>
     */
    public function testLoadAfterSave()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));
        $count = 42;
        $adapter->save('test-service', $count);

        $this->assertSame($count, $adapter->load('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::load
     * @covers ::<private>
     */
    public function testLoadWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->load('test-service');
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::save
     * @covers ::<private>
     */
    public function testSaveWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->save('test-service', 42);
    }

    /**
     * @covers ::increment
     * @covers ::<private>
     */
    public function testIncrementSuccess()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->increment('test-service');
        $this->assertSame(0, $adapter->load('test-service'));

        $adapter->increment('test-service');
        $this->assertSame(1, $adapter->load('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::increment
     * @covers ::<private>
     */
    public function testIncrementWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->increment('test-service');
    }

    /**
     * @covers ::decrement
     * @covers ::<private>
     */
    public function testDecrementSuccess()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));
        $adapter->save('test-service', 2);

        $adapter->decrement('test-service');
        $this->assertSame(1, $adapter->load('test-service'));

        $adapter->decrement('test-service');
        $this->assertSame(0, $adapter->load('test-service'));

        $adapter->decrement('test-service');
        $this->assertSame(0, $adapter->load('test-service'), 'must not decrement below 0');

        $this->flushBucket($bucket);
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::decrement
     * @covers ::<private>
     */
    public function testDecrementWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->decrement('test-service');
    }

    /**
     * @covers ::loadLastFailureTime
     * @covers ::<private>
     */
    public function testLoadLastFailureTimeFirstTime()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $this->assertSame(null, $adapter->loadLastFailureTime('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @covers ::loadLastFailureTime
     * @covers ::saveLastFailureTime
     * @covers ::<private>
     */
    public function testLoadLastFailureTimeAfterSave()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));
        $time = time();
        $adapter->saveLastFailureTime('test-service', $time);

        $this->assertSame($time, $adapter->loadLastFailureTime('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::loadLastFailureTime
     * @covers ::<private>
     */
    public function testLoadLastFailureTimeWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->loadLastFailureTime('test-service');
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::saveLastFailureTime
     * @covers ::<private>
     */
    public function testSaveLastFailureTimeWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->saveLastFailureTime('test-service', time());
    }

    /**
     * @covers ::loadStatus
     * @covers ::<private>
     */
    public function testLoadStatusFirstTime()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $this->assertSame(Ganesha::STATUS_CALMED_DOWN, $adapter->loadStatus('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @covers ::loadStatus
     * @covers ::saveStatus
     * @covers ::<private>
     */
    public function testLoadStatusAfterSave()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));
        $adapter->saveStatus('test-service', Ganesha::STATUS_TRIPPED);

        $this->assertSame(Ganesha::STATUS_TRIPPED, $adapter->loadStatus('test-service'));

        $this->flushBucket($bucket);
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::loadStatus
     * @covers ::<private>
     */
    public function testLoadStatusWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->loadStatus('test-service');
    }

    /**
     * @expectedException \Ackintosh\Ganesha\Exception\StorageException
     * @covers ::saveStatus
     * @covers ::<private>
     */
    public function testSaveStatusWithException()
    {
        $bucket = $this->createExceptionBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->saveStatus('test-service', Ganesha::STATUS_TRIPPED);
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::reset
     */
    public function testReset()
    {
        $bucket = $this->createBucket();
        $adapter = new Couchbase($bucket);
        $adapter->setConfiguration(new Configuration(['timeWindow' => 10]));

        $adapter->reset();
    }
}
