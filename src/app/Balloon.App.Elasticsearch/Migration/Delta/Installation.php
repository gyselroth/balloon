<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Migration\Delta;

use Balloon\App\Elasticsearch\Exception;
use Balloon\Migration\Delta\DeltaInterface;
use Elasticsearch\Client;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Installation implements DeltaInterface
{
    /**
     * Elasticsearch.
     *
     * @var Client
     */
    protected $client;

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
     */
    public function __construct(Client $client, LoggerInterface $logger, array $config = [])
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = [])
    {
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
        $this->logger->info('create elasticsearch indices blobs and nodes', [
            'category' => get_class($this),
        ]);

        $this->logger->debug('read index configuration from ['.$this->index_configuration.']', [
            'category' => get_class($this),
        ]);

        if (!is_readable($this->index_configuration)) {
            throw new Exception\IndexConfigurationNotFound('index configuration '.$this->index_configuration.' not found');
        }

        $index = json_decode(file_get_contents($this->index_configuration), false, 512, JSON_THROW_ON_ERROR);

        foreach ($index as $name => $settings) {
            $this->logger->info('create elasticsearch index ['.$name.']', [
                'category' => get_class($this),
                'settings' => $settings,
            ]);

            try {
                $this->client->indices()->create([
                    'index' => $name,
                    'body' => $settings,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('can not create index, try to update existing index', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                $this->client->indices()->putMapping([
                    'index' => $name,
                    'type' => '_doc',
                    'body' => $settings->mappings,
                ]);
            }
        }

        $this->client->ingest()->putPipeline([
            'id' => 'attachments',
            'body' => [
                'processors' => [
                    [
                        'attachment' => [
                            'field' => 'content',
                            'indexed_chars' => -1,
                        ],
                    ],
                ],
            ],
        ]);

        return true;
    }
}
