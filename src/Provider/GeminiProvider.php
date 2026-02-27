<?php

namespace App\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

class GeminiProvider implements LLMProviderInterface
{
    private Client $client;
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-3.1-pro-preview')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/models/',
            'headers' => [
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
        return $this->client->postAsync($this->model . ':generateContent?key=' . $this->apiKey, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ],
        ])->then(function ($response) {
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Error: No response from Gemini';
        });
    }

    public function getName(): string
    {
        return 'Gemini (' . $this->model . ')';
    }
}
