<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Console;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Internal]
class ConsoleApplication extends SymfonyConsoleApplication
{
    /**
     * Override the default error style to be more readable and less eye-searing.
     */
    #[\Override]
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        parent::configureIO($input, $output);
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red'));
    }

    /**
     * Override the default error style to be more readable and less eye-searing.
     */
    #[\Override]
    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void
    {
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('default', 'red'));
        parent::doRenderThrowable($e, $output);
    }
}
