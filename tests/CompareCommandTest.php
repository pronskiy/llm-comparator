<?php

namespace App\Tests;

use App\Command\CompareCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CompareCommandTest extends TestCase
{
    public function testExecuteWithoutJudge(): void
    {
        putenv('APP_ENV=test');
        putenv('JUDGE_PROVIDER=');

        $application = new Application();
        $application->add(new CompareCommand());

        $command = $application->find('compare');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'prompt' => 'Hello',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LLM Comparison', $output);
        $this->assertStringContainsString('Mock GPT-5.3', $output);
        $this->assertStringContainsString('Response', $output);
        $this->assertStringNotContainsString('Score', $output);
    }

    public function testExecuteWithJudge(): void
    {
        putenv('APP_ENV=test');
        putenv('JUDGE_PROVIDER=GPT-5.3');

        $application = new Application();
        $application->add(new CompareCommand());

        $command = $application->find('compare');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'prompt' => 'Hello',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('LLM Comparison', $output);
        $this->assertStringContainsString('Asking Judge (Mock GPT-5.3)', $output);
        $this->assertStringContainsString('Score', $output);
        $this->assertStringContainsString('Justification', $output);
        $this->assertStringContainsString('Excellent answer.', $output);
        $this->assertStringContainsString('Judge Verdict', $output);
        $this->assertStringContainsString('Mock GPT-5.3 is the winner', $output);
    }
}
