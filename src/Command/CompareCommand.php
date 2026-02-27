<?php

namespace App\Command;

use App\Provider\AnthropicProvider;
use App\Provider\GeminiProvider;
use App\Provider\JudgeProvider;
use App\Provider\LLMProviderInterface;
use App\Provider\OpenAIProvider;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompareCommand extends Command
{
    private array $providers = [];
    private ?JudgeProvider $judge = null;

    protected function configure(): void
    {
        $this
            ->setName('compare')
            ->setDescription('Compare output from multiple LLM providers')
            ->addArgument('prompt', InputArgument::REQUIRED, 'The prompt to send to the LLMs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prompt = $input->getArgument('prompt');

        $this->initializeProviders();

        if (empty($this->providers)) {
            $io->error('No LLM providers configured. Please set API keys in your .env file.');
            return Command::FAILURE;
        }

        $io->title('LLM Comparison');
        $io->section('Prompt: ' . $prompt);

        $io->note('Querying providers in parallel...');

        $promises = [];
        $progressBars = [];
        $isDecorated = $output->isDecorated();
        
        foreach ($this->providers as $provider) {
            /** @var LLMProviderInterface $provider */
            $name = $provider->getName();

            // Create a per-provider progress bar. Use a single step that completes when the promise settles.
            if ($isDecorated && $output instanceof ConsoleOutputInterface) {
                $section = $output->section();
                $section->writeln(sprintf('<info>[%s]</info> starting request...', $name));
                $bar = new ProgressBar($section, 1);
                $bar->setFormat(' [%bar%] %percent:3s%% — %message%');
                // Custom message placeholder
                $bar->setMessage('waiting');
                $bar->start();
                $progressBars[$name] = ['bar' => $bar, 'section' => $section];
            } elseif ($isDecorated) {
                // Fallback if not ConsoleOutputInterface but decorated
                $io->writeln(sprintf('<info>[%s]</info> starting request...', $name));
                $bar = new ProgressBar($output, 1);
                $bar->setFormat(' [%bar%] %percent:3s%% — %message%');
                $bar->setMessage('waiting');
                $bar->start();
                $progressBars[$name] = ['bar' => $bar];
            } else {
                // Fallback simple line for non-decorated outputs (e.g., in tests/CI)
                $io->writeln(sprintf('[%s] request started...', $name));
            }

            $promise = $provider->queryAsync($prompt);

            // When fulfilled or rejected, complete the bar and print status
            $promise = $promise->then(
                function ($value) use ($io, $name, $progressBars) {
                    if (isset($progressBars[$name])) {
                        $bar = $progressBars[$name]['bar'];
                        $bar->setMessage('completed');
                        $bar->finish();
                        if (!isset($progressBars[$name]['section'])) {
                            $io->newLine();
                        }
                    } else {
                        $io->writeln(sprintf('[%s] completed', $name));
                    }
                    return $value;
                },
                function ($reason) use ($io, $name, $progressBars) {
                    $errorMessage = sprintf('<error>[%s] failed: %s</error>', $name, method_exists($reason, 'getMessage') ? $reason->getMessage() : (string) $reason);
                    if (isset($progressBars[$name])) {
                        $bar = $progressBars[$name]['bar'];
                        $bar->setMessage('failed');
                        $bar->finish();
                        if (isset($progressBars[$name]['section'])) {
                            $progressBars[$name]['section']->writeln($errorMessage);
                        } else {
                            $io->newLine();
                            $io->writeln($errorMessage);
                        }
                    } else {
                        $io->writeln($errorMessage);
                    }
                    // Re-throw to keep promise state rejected for settle()
                    throw $reason;
                }
            );

            $promises[$name] = $promise;
        }

        $results = Utils::settle($promises)->wait();

        $providerResponses = [];
        foreach ($results as $name => $result) {
            if ($result['state'] === 'fulfilled') {
                $providerResponses[$name] = $result['value'];
            }
        }

        $judgement = [];
        if ($this->judge && !empty($providerResponses)) {
            $io->note('Asking ' . $this->judge->getName() . ' to score answers...');
            $judgement = $this->judge->judgeAsync($prompt, $providerResponses)->wait();
        }

        $headers = ['Feature'];
        foreach ($this->providers as $provider) {
            $headers[] = $provider->getName();
        }

        $responses = ['Response'];
        $scores = ['Score'];
        $justifications = ['Justification'];

        foreach ($this->providers as $provider) {
            $name = $provider->getName();
            $responses[] = $results[$name]['state'] === 'fulfilled'
                ? $results[$name]['value']
                : '<error>Error: ' . $results[$name]['reason']->getMessage() . '</error>';
            
            $individualEvals = $judgement['individual_evaluations'] ?? [];
            $scores[] = $individualEvals[$name]['score'] ?? '-';
            $justifications[] = $individualEvals[$name]['justification'] ?? '-';
        }

        $tableRows = [$responses];
        if ($this->judge) {
            $tableRows[] = $scores;
            $tableRows[] = $justifications;
        }

        $io->table($headers, $tableRows);

        if ($this->judge && isset($judgement['verdict'])) {
            $io->section('Judge Verdict');
            $io->text($judgement['verdict']);
        }

        return Command::SUCCESS;
    }

    private function initializeProviders(): void
    {
        $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
        if ($appEnv === 'test') {
            $this->providers[] = new \App\Provider\MockProvider('Mock GPT-5.3');
            $this->providers[] = new \App\Provider\MockProvider('Mock Claude Opus 4.6');
            $this->providers[] = new \App\Provider\MockProvider('Mock Gemini 3.1 Pro Preview');

            if (getenv('JUDGE_PROVIDER')) {
                $judgeName = getenv('JUDGE_PROVIDER');
                foreach ($this->providers as $provider) {
                    if (str_contains(strtolower($provider->getName()), strtolower($judgeName))) {
                        $this->judge = new JudgeProvider($provider);
                        break;
                    }
                }
            }
            return;
        }

        if ($_ENV['OPENAI_API_KEY'] ?? false) {
            $this->providers[] = new OpenAIProvider($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODEL'] ?? 'gpt-5.3');
        }

        if ($_ENV['ANTHROPIC_API_KEY'] ?? false) {
            $this->providers[] = new AnthropicProvider($_ENV['ANTHROPIC_API_KEY'], $_ENV['ANTHROPIC_MODEL'] ?? 'claude-opus-4-6');
        }

        if ($_ENV['GEMINI_API_KEY'] ?? false) {
            $this->providers[] = new GeminiProvider($_ENV['GEMINI_API_KEY'], $_ENV['GEMINI_MODEL'] ?? 'gemini-3.1-pro-preview');
        }

        if ($_ENV['JUDGE_PROVIDER'] ?? false) {
            $judgeProvider = null;
            $judgeName = $_ENV['JUDGE_PROVIDER'];

            foreach ($this->providers as $provider) {
                if (str_contains(strtolower($provider->getName()), strtolower($judgeName))) {
                    $judgeProvider = $provider;
                    break;
                }
            }

            if ($judgeProvider) {
                $this->judge = new JudgeProvider($judgeProvider);
            }
        }
    }
}
