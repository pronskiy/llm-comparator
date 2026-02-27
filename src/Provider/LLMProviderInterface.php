<?php

namespace App\Provider;

use GuzzleHttp\Promise\PromiseInterface;

interface LLMProviderInterface
{
    /**
     * @param string $prompt
     * @return string
     * @throws \Exception
     */
    public function query(string $prompt): string;

    /**
     * @param string $prompt
     * @return PromiseInterface
     */
    public function queryAsync(string $prompt): PromiseInterface;

    public function getName(): string;
}
