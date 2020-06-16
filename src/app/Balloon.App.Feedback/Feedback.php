<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Feedback;

use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use Psr\Log\LoggerInterface;

class Feedback
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Client.
     *
     * @var GuzzleHttpClientInterface
     */
    protected $client;

    /**
     * Remote.
     *
     * @var string
     */
    protected $remote;

    /**
     * Constructor.
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Handle.
     */
    public function handle(): bool
    {
        $input = fopen('php://input', 'r');

        $this->logger->info('send feedback report to [remote]', [
            'category' => get_class($this),
            'remote' => $this->client->getConfig()['base_uri'] ?? '',
        ]);

        $res = $this->client->post('', [
            'body' => $input,
            'http_errors' => false,
        ]);

        if ($res->getStatusCode() !== 201) {
            $this->logger->error('sending feedback report failed with code [code] and message [error]', [
                'category' => get_class($this),
                'code' => $res->getStatusCode(),
                'error' => $res->getBody()->read(10240), //only read 2KB which should be enaugh as error description
            ]);

            throw new Exception\InvalidFeedback('processing feedback failed with http code '.$res->getStatusCode());
        }

        return true;
    }
}
