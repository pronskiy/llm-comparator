<?php

namespace App\Provider;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

class MockProvider implements LLMProviderInterface
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function query(string $prompt): string
    {
        return $this->queryAsync($prompt)->wait();
    }

    public function queryAsync(string $prompt): PromiseInterface
    {
        if (str_contains($prompt, 'evaluate each response and provide a score')) {
            $evaluations = [
                'individual_evaluations' => [
                    'Mock GPT-5.3' => ['score' => 9, 'justification' => 'Excellent answer.'],
                    'Mock Claude Opus 4.6' => ['score' => 8, 'justification' => 'Very good, but slightly verbose.'],
                    'Mock Gemini 3.1 Pro Preview' => ['score' => 7, 'justification' => 'Good, but missed some details.'],
                ],
                'verdict' => 'Mock GPT-5.3 is the winner for its concise and accurate response.'
            ];
            return new FulfilledPromise(json_encode($evaluations));
        }

        return new FulfilledPromise("This is a mock response from " . $this->name . " for the prompt: " . $prompt);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
