<?php
namespace Solid_Backups\Strauss\Aws\Api\ErrorParser;

use Solid_Backups\Strauss\Aws\Api\Parser\JsonParser;
use Solid_Backups\Strauss\Aws\Api\Service;
use Solid_Backups\Strauss\Aws\CommandInterface;
use Solid_Backups\Strauss\Psr\Http\Message\ResponseInterface;

/**
 * Parsers JSON-RPC errors.
 */
class JsonRpcErrorParser extends AbstractErrorParser
{
    use JsonParserTrait;

    private $parser;

    public function __construct(Service $api = null, JsonParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new JsonParser();
    }

    public function __invoke(
        ResponseInterface $response,
        CommandInterface $command = null
    ) {
        $data = $this->genericHandler($response);

        // Make the casing consistent across services.
        if ($data['parsed']) {
            $data['parsed'] = array_change_key_case($data['parsed']);
        }

        if (isset($data['parsed']['__type'])) {
            if (!isset($data['code'])) {
                $parts = explode('#', $data['parsed']['__type']);
                $data['code'] = isset($parts[1]) ? $parts[1] : $parts[0];
            }
            $data['message'] = isset($data['parsed']['message'])
                ? $data['parsed']['message']
                : null;
        }

        $this->populateShape($data, $response, $command);

        return $data;
    }
}
