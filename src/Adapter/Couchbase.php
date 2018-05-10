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
     * @var \Couchbase\Bucket $bucket Couchbase bucket to manage data
     */
    private $bucket;

    /**
     * @var \Ackintosh\Ganesha\Configuration $configuration circuit breaker configuration
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
     * set circuit breaker configuration
     *
     * @param \Ackintosh\Ganesha\Configuration $configuration circuit breaker configuration
     * @return void
     */
    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * load success / failure / rejection count
     *
     * @param  string $service name of the service
     * @return int
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function load($service)
    {
        return $this->get($service, 0);
    }

    /**
     * save success / failure / rejection count
     *
     * @param  string $service name of the service
     * @param  int    $count   success / failure / rejection count of the service
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function save($service, $count)
    {
        $this->upsert($service, $count);
    }

    /**
     * increment success / failure / rejection count
     *
     * @param  string $service name of the service
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    public function increment($service)
    {
        $this->counter($service, 1);
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
        $this->counter($service, -1);
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
        $this->upsert($service, $lastFailureTime);
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
        return $this->get($service, null);
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
        $this->upsert($service, $status);
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
        return $this->get($service, Ganesha::STATUS_CALMED_DOWN);
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

    /**
     * get data from couchbase bucket
     *
     * @param string $key     key of the document
     * @param mixed  $default default value for missing document
     * @return mixed
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    private function get($key, $default)
    {
        try {
            $doc = $this->bucket->get($key, []);
        } catch (CBException $e) {
            if ($e->getCode() === COUCHBASE_KEY_ENOENT) {
                return $default;
            }
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
        return $doc->value;
    }

    /**
     * update or insert value for given key
     *
     * @param string $key   id of the document
     * @param mixed  $value value of the document
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    private function upsert($key, $value)
    {
        try {
            $this->bucket->upsert($key, $value, $this->getOptions());
        } catch (CBException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * increment / decrement counter for given key
     *
     * @param string $key   key of the counter
     * @param int    $delta increment / decrement delta
     * @return void
     * @throws \Ackintosh\Ganesha\Exception\StorageException
     */
    private function counter($key, $delta)
    {
        try {
            $this->bucket->counter($key, $delta, $this->getOptions(['initial' => 0]));
        } catch (\Couchbase\Exception $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
