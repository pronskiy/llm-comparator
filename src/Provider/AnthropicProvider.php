<?php

namespace App\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

class AnthropicProvider implements LLMProviderInterface
{
    private Client $client;
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'claude-opus-4-6')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function query(string $prompt): string
    {
        return $this->queryAsync($prompt)->wait();
    }

    public function queryAsync(string $prompt): PromiseInterface
    {
        return $this->client->postAsync('messages', [
            'json' => [
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
        ])->then(function ($response) {
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['content'][0]['text'] ?? 'Error: No response from Anthropic';
        });
    }

    public function getName(): string
    {
        return 'Anthropic (' . $this->model . ')';
    }
}
