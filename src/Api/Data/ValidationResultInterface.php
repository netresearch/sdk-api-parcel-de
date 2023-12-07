<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\Sdk\ParcelDe\Shipping\Api\Data;

/**
 * @api
 */
interface ValidationResultInterface
{
    public function getSequenceNumber(): string;

    public function isValid(): bool;

    public function getMessage(): string;
}
