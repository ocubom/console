<?php

namespace Box\Component\Console\Tests;

use Box\Component\Console\Application;
use Box\Component\Console\Test\CommandTestCase;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies that the class functions as intended.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 *
 * @covers \Box\Component\Console\Application
 * @covers \Box\Component\Console\DependencyInjection\Compiler\AbstractTaggedPass
 * @covers \Box\Component\Console\DependencyInjection\Compiler\CommandPass
 * @covers \Box\Component\Console\DependencyInjection\Compiler\HelperPass
 */
class ApplicationTest extends CommandTestCase
{
    /**
     * Verifies that we can set and retrieve the container.
     */
    public function testContainer()
    {
        $container = new Container();

        $app = new Application($container);

        self::assertSame($container, $app->getContainer());
    }

    /**
     * Verifies that we can run the application.
     */
    public function testRun()
    {
        // registers a simple event listener
        $this
            ->container
            ->get(Application::getId('event_dispatcher'))
            ->addListener(
                ConsoleEvents::COMMAND,
                function (ConsoleCommandEvent $event) {
                    $event->getOutput()->writeln('Event listened.');
                }
            )
        ;

        // register our own IO
        $input = new ArrayInput(array());
        $output = new StreamOutput(fopen('php://memory', 'r+'));

        $this->container->set(Application::getId('input'), $input);
        $this->container->set(Application::getId('output'), $output);

        // compile the container ourselves
        if ($this->container instanceof ContainerBuilder) {
            $this->container->compile();
        }

        // make sure the exit status is returned
        self::assertEquals(0, $this->application->run());

        // make sure it uses our output
        $output = $this->readOutput($output);

        self::assertContains('help', $output);

        // make sure that the event dispatcher is used
        self::assertContains('Event listened.', $output);

        // make sure the helper set is registered
        self::assertSame(
            $this->container->get(Application::getId('helper_set')),
            $this->container->get(Application::getId())->getHelperSet()
        );

        // make sure the default helpers are registered
        self::assertNotNull(
            $this
                ->container
                ->has(Application::getId('helper.formatter'))
        );

        // make sure the helpers are registered with the helper set
        $helperSet = $this->container->get(Application::getId('helper_set'));

        self::assertSame(
            $this
                ->container
                ->get(Application::getId('helper.formatter')),
            $helperSet->get('formatter')
        );

        // make sure that the container helper is registered
        self::assertSame(
            $this->container,
            $helperSet->get('container')->getContainer()
        );

        // make sure the default commands are registered
        self::assertNotNull(
            $this
                ->container
                ->has(Application::getId('command.help'))
        );

        // make sure the commands are registered with the application.
        $command = $this
            ->container
            ->get(Application::getId('command.help'))
        ;

        self::assertSame(
            $command,
            $this
                ->container
                ->get(Application::getId())
                ->find($command->getName())
        );

        // make sure the commands use the helper set service
        self::assertSame(
            $helperSet,
            $command->getHelperSet()
        );
    }
}
