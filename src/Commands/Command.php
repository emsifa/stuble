<?php

namespace Emsifa\Stuble\Commands;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class Command extends SymfonyCommand
{
    /**
     * Command name
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Command arguments
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Command options
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Command help message
     *
     * @var string
     */
    protected string $help = '';

    /**
     * Command description
     *
     * @var string
     */
    protected string $description = '';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName($this->name);
        $this->setDescription($this->description);
        $this->setHelp($this->help);

        if ($this->options) {
            $options = [];
            foreach ($this->options as $key => $opt) {
                $opt = array_merge([
                    'alias' => null,
                    'type' => InputOption::VALUE_NONE,
                    'description' => '',
                    'default' => null,
                ], $opt);

                $options[] = new InputOption($key, $opt['alias'], $opt['type'], $opt['description'], $opt['default']);
            }

            $this->setDefinition(new InputDefinition($options));
        }

        foreach ($this->args as $key => $arg) {
            $arg = array_merge([
                'type' => InputArgument::REQUIRED,
                'description' => '',
                'default' => null,
            ], $arg);

            $this->addArgument($key, $arg['type'], $arg['description'], $arg['default']);
        }
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->registerOutputStyles();

        return $this->handle() ?: SymfonyCommand::SUCCESS;
    }

    /**
     * Handle command execution
     */
    protected function handle()
    {
    }

    /**
     * Get command argument value
     *
     * @param  string $key
     * @return mixed
     */
    protected function argument(string $key): mixed
    {
        return $this->input->getArgument($key);
    }

    /**
     * Get command option value
     *
     * @param  string $key
     * @return mixed
     */
    protected function option(string $key): mixed
    {
        return $this->input->getOption($key);
    }

    /**
     * Ask user to answer a question
     *
     * @param  string $question
     * @param  mixed $defualt
     * @return mixed
     */
    protected function ask(string $question, $default = null): mixed
    {
        $helper = $this->getHelper('question');
        if ($default) {
            $question .= " <fg=magenta>[{$default}]</>";
        }
        $question = new Question($question.' ');

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Ask user to confirm a question
     *
     * @param  string $question
     * @param  mixed $default
     * @return mixed
     */
    protected function confirm(string $question, $default = false): mixed
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($question.' ', $default, '/^(y)/i');

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Write text to OutputInterface
     *
     * @param  string $text
     * @param  string|null $style
     * @param  array $options
     * @return mixed
     */
    protected function write(string $text, ?string $style = null, array $options = [])
    {
        $text = $this->formatText($text, $style, $options);

        return $this->output->write($text);
    }

    /**
     * Write text and ends it with new line "\n"
     *
     * @param  string $text
     * @param  string|null $style
     * @param  array $options
     * @return mixed
     */
    protected function writeln(string $text, ?string $style = null, array $options = [])
    {
        $text = $this->formatText($text, $style, $options);

        return $this->output->writeln($text);
    }

    /**
     * Add style to given text
     *
     * @param  string $text
     * @param  string|null $style
     * @param  array $options
     * @return string
     */
    protected function formatText(string $text, ?string $style = null, array $options = []): string
    {
        return $style ? "<{$style}>{$text}</>" : $text;
    }

    /**
     * Write normal text without any style
     *
     * @param  string $text
     * @return mixed
     */
    protected function text(string $text)
    {
        return $this->writeln($text);
    }

    /**
     * Write gray text
     *
     * @param  string $text
     * @param  array $options
     * @return mixed
     */
    protected function muted(string $text, array $options = [])
    {
        return $this->writeln($text, 'muted', $options);
    }

    /**
     * Write info text (blue)
     *
     * @param  string $text
     * @param  array $options
     * @return mixed
     */
    protected function info(string $text, array $options = [])
    {
        return $this->writeln($text, 'info', $options);
    }

    /**
     * Write danger text (red)
     *
     * @param  string $text
     * @param  array $options
     * @return mixed
     */
    protected function danger(string $text, array $options = [])
    {
        return $this->writeln($text, 'danger', $options);
    }

    /**
     * Write warning text (yellow)
     *
     * @param  string $text
     * @param  array $options
     * @return mixed
     */
    protected function warning(string $text, array $options = [])
    {
        return $this->writeln($text, 'warning', $options);
    }

    /**
     * Write success text (green)
     *
     * @param  string $text
     * @param  array $options
     * @return mixed
     */
    protected function success(string $text, array $options = [])
    {
        return $this->writeln($text, 'success', $options);
    }

    /**
     * Write error text and exit program
     *
     * @param  string $message
     * @return mixed
     */
    protected function error(string $message)
    {
        $this->writeln($message, 'error');
        exit;
    }

    /**
     * Register output styles
     */
    protected function registerOutputStyles()
    {
        $styles = [
            'muted' => ['fg' => 'gray'],
            'info' => ['fg' => 'blue'],
            'success' => ['fg' => 'green'],
            'warning' => ['fg' => 'yellow'],
            'danger' => ['fg' => 'red'],
            'error' => ['fg' => 'white', 'bg' => 'red', ['bold']],
        ];

        foreach ($styles as $key => $style) {
            $style = array_merge([
                'fg' => null,
                'bg' => null,
                'options' => [],
            ], $style);

            $this->output->getFormatter()->setStyle($key, new OutputFormatterStyle($style['fg'], $style['bg'], $style['options']));
        }
    }

    /**
     * Dump argument values and exit program
     */
    protected function dump()
    {
        foreach (func_get_args() as $arg) {
            /**
             * @psalm-suppress ForbiddenCode
             */
            var_dump($arg);
        }
        exit;
    }

    /**
     * Print new line
     */
    protected function nl()
    {
        $this->writeln('');
    }
}
