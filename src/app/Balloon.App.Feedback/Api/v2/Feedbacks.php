<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Feedback\Api\v2;

use Balloon\App\Feedback\Feedback as FeedbackHandler;
use Micro\Http\Response;

class Feedbacks
{
    /**
     * Feedback.
     *
     * @var FeedbackHandler
     */
    protected $feedback_handler;

    /**
     * Initialize.
     */
    public function __construct(FeedbackHandler $feedback_handler)
    {
        $this->feedback_handler = $feedback_handler;
    }

    /**
     * Post feedback.
     */
    public function post(): Response
    {
        $this->feedback_handler->handle();
        return (new Response())->setCode(201);
    }
}
