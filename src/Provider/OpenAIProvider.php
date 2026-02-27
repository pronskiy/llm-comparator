<?php

namespace App\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

class OpenAIProvider implements LLMProviderInterface
{
    private Client $client;
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-5.3')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
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
        return $this->client->postAsync('chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
        ])->then(function ($response) {
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? 'Error: No response from OpenAI';
        });
    }

    public function getName(): string
    {
        return 'OpenAI (' . $this->model . ')';
    }
}
