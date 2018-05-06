<?php
/**
 * Couchbase Adapter for ackintosh/ganesha
 */
namespace GaneshaPlugin\Adapter;

use Ackintosh\Ganesha;
use Ackintosh\Ganesha\Exception\StorageException;
use Ackintosh\Ganesha\Configuration;
use Ackintosh\Ganesha\Storage\AdapterInterface;
use Ackintosh\Ganesha\Storage\Adapter\TumblingTimeWindowInterface;
use Couchbase\Bucket;
use Couchbase\Exception as CBException;

/**
 * Adapter class using Couchbase
 */
class Couchbase implements AdapterInterface, TumblingTimeWindowInterface
{
    /**
     * @var \Couchbase\Bucket $bucket
     */
    private $bucket;

    /**
     * @var \Ackintosh\Ganesha\Configuration $configuration
     */
    private $configuration;

    /**
     * constructor
     *
     * @param \Couchbase\Bucket $bucket Couchbase bucket to manage data
     */
    public function __construct(Bucket $bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * @override
     * @param \Ackintosh\Ganesha\Configuration $configuration circuit breaker configuration
     * @return void
     */
    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param  string $service name of the service
     * @return int
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function load($service)
    {
        try {
            $doc = $this->bucket->get($service, []);
        } catch (CBException $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                return 0;
            }
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
        return $doc->value;
    }

    /**
     * @param  string $service name of the service
     * @param  int    $count   success / failure / rejection count of the service
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function save($service, $count)
    {
        try {
            $this->bucket->upsert($service, $count, $this->getOptions());
        } catch (CBException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param  string $service name of the service
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function increment($service)
    {
        try {
            $this->bucket->counter($service, 1, $this->getOptions(['initial' => 0]));
        } catch (\Couchbase\Exception $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * decrement failure count
     *
     * If the operation would decrease the value below 0, the new value must be 0.
     *
     * @param  string $service name of the service
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function decrement($service)
    {
        try {
            $this->bucket->counter($service, -1, $this->getOptions(['initial' => 0]));
        } catch (\Couchbase\Exception $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * sets last failure time
     *
     * @param  string $service         name of the service
     * @param  int    $lastFailureTime last failure time (unix time)
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function saveLastFailureTime($service, $lastFailureTime)
    {
        try {
            $this->bucket->upsert($service, $lastFailureTime, $this->getOptions());
        } catch (CBException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * returns last failure time
     *
     * @param string $service name of the service
     * @return int | null
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function loadLastFailureTime($service)
    {
        try {
            $doc = $this->bucket->get($service, []);
        } catch (CBException $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                return null;
            }
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
        return $doc->value;
    }

    /**
     * sets status
     *
     * @param  string $service name of the service
     * @param  int    $status  status of the service
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function saveStatus($service, $status)
    {
        try {
            $this->bucket->upsert($service, $status, $this->getOptions());
        } catch (CBException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * returns status
     *
     * @param  string $service name of the service
     * @return int
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function loadStatus($service)
    {
        try {
            $doc = $this->bucket->get($service, []);
        } catch (CBException $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                return Ganesha::STATUS_CALMED_DOWN;
            }
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
        return $doc->value;
    }

    /**
     * resets all counts
     *
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function reset()
    {
        throw new \RuntimeException('not implemented');
    }

    /**
     * return option parameters for Couchbase operation
     *
     * @param array $additional additional option
     * @return array
     */
    private function getOptions(array $additional = [])
    {
        $expiry = isset($this->configuration['timeWindow']) ?
                $this->configuration['timeWindow'] * 2 : // current + previous
                0;
        $initial = [
            'expiry' => $expiry,
        ];
        return array_merge($initial, $additional);
    }
}
