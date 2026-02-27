<?php

namespace App\Provider;

use GuzzleHttp\Promise\PromiseInterface;

class JudgeProvider
{
    private LLMProviderInterface $judgeProvider;

    public function __construct(LLMProviderInterface $judgeProvider)
    {
        $this->judgeProvider = $judgeProvider;
    }

    /**
     * @param string $prompt The original prompt
     * @param array<string, string> $responses Array of provider names to their responses
     */
    public function judgeAsync(string $prompt, array $responses): PromiseInterface
    {
        $judgePrompt = "You are an expert judge evaluating LLM responses.\n\n";
        $judgePrompt .= "Original Prompt: " . $prompt . "\n\n";
        $judgePrompt .= "Here are the responses from different providers:\n\n";

        foreach ($responses as $name => $response) {
            $judgePrompt .= "--- Provider: $name ---\n";
            $judgePrompt .= $response . "\n\n";
        }

        $judgePrompt .= "Please evaluate each response and provide a score from 1 to 10 and a brief justification.\n";
        $judgePrompt .= "Also, provide a final 'verdict' which is a summary of which provider performed best and why.\n";
        $judgePrompt .= "Format your output as a JSON object with two keys: 'individual_evaluations' (an object where keys are provider names and values are objects with 'score' and 'justification' fields) and 'verdict' (a string).\n";
        $judgePrompt .= "Example: {\"individual_evaluations\": {\"Provider A\": {\"score\": 8, \"justification\": \"...\"}}, \"verdict\": \"Provider A is the best because...\"}\n";
        $judgePrompt .= "Only return the JSON object.";

        return $this->judgeProvider->queryAsync($judgePrompt)->then(function (string $result) {
            // Strip markdown code blocks if present
            $json = preg_replace('/^```json\s*|\s*```$/i', '', trim($result));
            return json_decode($json, true) ?? [];
        });
    }

    public function getName(): string
    {
        return 'Judge (' . $this->judgeProvider->getName() . ')';
    }
}
