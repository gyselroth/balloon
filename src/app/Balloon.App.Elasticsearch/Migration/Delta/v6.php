<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Migration\Delta;

use Balloon\App\Elasticsearch\Elasticsearch;
use Balloon\App\Elasticsearch\Exception;
use Balloon\Migration\Delta\DeltaInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class v6 implements DeltaInterface
{
    /**
     * Elasticsearch.
     *
     * @var Elasticsearch
     */
    protected $es;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Index configuration.
     *
     * @var string
     */
    protected $index_configuration = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'index.json';

    /**
     * Construct.
     *
     * @param iterable $config
     */
    public function __construct(Elasticsearch $es, LoggerInterface $logger, Iterable $config = null)
    {
        $this->es = $es;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(Iterable $config = null): self
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $key => $value) {
            switch ($key) {
                case 'index_configuration':
                    $this->{$key} = (string) $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$key.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        /*$this->logger->info('create elasticsearch index ['.$this->es->getIndex().']', [
            'category' => get_class($this),
        ]);

        $this->logger->debug('read index configuration from ['.$this->index_configuration.']', [
            'category' => get_class($this),
        ]);

        if (!is_readable($this->index_configuration)) {
            throw new Exception\IndexConfigurationNotFound('index configuration '.$this->index_configuration.' not found');
        }

        $index = json_decode(file_get_contents($this->index_configuration));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception\InvalidIndexConfiguration('invalid elasticsearch index configuration json given');
        }

        $index = [
            'index' => $this->es->getIndex(),
            'body' => $index,
        ];*/

        $this->es->getEsClient()->indices()->create(['index' => 'blobs']);
        $this->es->getEsClient()->indices()->create(['index' => 'nodes']);

        return true;
    }
}
