<?php

namespace Emsifa\Stuble\Commands;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class Command extends SymfonyCommand
{

    protected $name = '';
    protected $args = [];
    protected $options = [];
    protected $help = '';
    protected $description = '';

    protected $input;
    protected $output;

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
                'default' => null
            ], $arg);

            $this->addArgument($key, $arg['type'], $arg['description'], $arg['default']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->registerOutputStyles();

        return $this->handle() ?: SymfonyCommand::SUCCESS;
    }

    protected function handle()
    {

    }

    protected function argument(string $key)
    {
        return $this->input->getArgument($key);
    }

    protected function option(string $key)
    {
        return $this->input->getOption($key);
    }

    protected function ask(string $question, $default = null)
    {
        $helper = $this->getHelper('question');
        if ($default) {
            $question .= " <fg=magenta>[{$default}]</>";
        }
        $question = new Question($question.' ');
        return $helper->ask($this->input, $this->output, $question);
    }

    protected function confirm(string $question, $default = false)
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($question.' ', $default, '/^(y)/i');
        return $helper->ask($this->input, $this->output, $question);
    }

    protected function write(string $text, $style = null, array $options = [])
    {
        $text = $this->formatText($text, $style, $options);
        return $this->output->write($text);
    }

    protected function writeln(string $text, $style = null, array $options = [])
    {
        $text = $this->formatText($text, $style, $options);
        return $this->output->writeln($text);
    }

    protected function formatText(string $text, $style = null, array $options = [])
    {
        return $style ? "<{$style}>{$text}</>" : $text;
    }

    protected function text(string $text)
    {
        return $this->writeln($text);
    }

    protected function info(string $text, array $options = [])
    {
        return $this->writeln($text, 'info', $options);
    }

    protected function danger(string $text, array $options = [])
    {
        return $this->writeln($text, 'danger', $options);
    }

    protected function warning(string $text, array $options = [])
    {
        return $this->writeln($text, 'warning', $options);
    }

    protected function success(string $text, array $options = [])
    {
        return $this->writeln($text, 'success', $options);
    }

    protected function error(string $message)
    {
        $this->writeln($message, 'error');
        exit;
    }

    protected function registerOutputStyles()
    {
        $styles = [
            'info'      => ['fg' => 'blue'],
            'success'   => ['fg' => 'green'],
            'warning'   => ['fg' => 'yellow'],
            'danger'    => ['fg' => 'red'],
            'error'     => ['fg' => 'white', 'bg' => 'red', ['bold']],
        ];

        foreach ($styles as $key => $style) {
            $style = array_merge([
                'fg' => null,
                'bg' => null,
                'options' => []
            ], $style);

            $this->output->getFormatter()->setStyle($key, new OutputFormatterStyle($style['fg'], $style['bg'], $style['options']));
        }
    }

    protected function dump()
    {
        foreach(func_get_args() as $arg) {
            var_dump($arg);
        }
        exit;
    }

    protected function nl()
    {
        $this->writeln('');
    }


}