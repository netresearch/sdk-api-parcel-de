<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\Sdk\ParcelDe\Shipping\Http\ClientPlugin;

use Dhl\Sdk\ParcelDe\Shipping\Exception\SchemaErrorException;
use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

final class RequestValidatorPlugin implements Plugin
{
    /**
     * @param bool $strict Stop on validation failure
     */
    public function __construct(private readonly LoggerInterface $logger, private readonly bool $strict)
    {
    }

    /**
     * Convert validation failures into SchemaErrorException with proper exception message.
     *
     * @throws SchemaErrorException
     * @throws \JsonException
     */
    private function validateRequest(RequestInterface $request): void
    {
        $validator = (new ValidatorBuilder())
            ->fromYamlFile(__DIR__ . '/DHL_Parcel_DE_Shipping-v2.1.7.yaml')
            ->getRequestValidator();

        try {
            $validator->validate($request);
        } catch (ValidationFailed $exception) {
            $message = $exception->getMessage();
            $previous = $exception->getPrevious();

            // build more specific message
            if ($previous instanceof SchemaMismatch && $previous->dataBreadCrumb()) {
                $data = $previous->data();
                $chain = $previous->dataBreadCrumb()->buildChain();

                if (is_array($data)) {
                    $data = \json_encode($data, JSON_THROW_ON_ERROR);
                    array_pop($chain);
                }

                $path = array_reduce(
                    $chain,
                    function (string $carry, $link): string {
                        if (is_int($link)) {
                            $carry .= "[$link]";
                        } else {
                            $carry .= ".$link";
                        }
                        return $carry;
                    },
                    ''
                );

                $message = sprintf('%s. Value: "%s". Path: "%s"', $previous->getMessage(), $data, trim($path, '.'));
            }

            $this->logger->error($message, ['exception' => $exception]);
            if ($this->strict) {
                throw new SchemaErrorException(
                    $message,
                    $request,
                    ($previous instanceof \Exception) ? $previous : null
                );
            }
        }
    }

    /**
     * @param callable(RequestInterface): Promise<RequestInterface> $next
     * @param callable(RequestInterface): Promise<RequestInterface> $first
     * @return Promise<RequestInterface>
     * @throws SchemaErrorException
     * @throws \JsonException
     */
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        if ($request->getMethod() === 'POST') {
            $this->validateRequest($request);
        }

        return $next($request);
    }
}
