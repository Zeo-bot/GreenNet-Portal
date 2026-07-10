<?php

declare(strict_types=1);

namespace GreenNet\Services\Access;

interface AccessServiceInterface
{
    public function sourceName(): string;

    public function getSubscriberStatus(string $username): array;
}