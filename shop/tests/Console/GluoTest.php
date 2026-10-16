<?php

namespace Tests\Console;

use Builder\Scripts;
use ByJG\Gluo\Console\Launcher;
use ByJG\Scriptify\CommandProvider;
use Shop\Command\Commands;
use Tests\Controller\BaseApiTestCase;

/**
 * The ./gluo wiring this project owns: the Commands provider and Builder\Scripts, against
 * the real container and the migrated test database.
 * Extends BaseApiTestCase to reuse the database reset and environment guard.
 */
class GluoTest extends BaseApiTestCase
{
    protected const ROOT = __DIR__ . '/../..';

    /**
     * @param array<string, string>|null $commands Replaces the Commands provider when given.
     * @return array{0: int, 1: string}
     */
    protected function gluo(array $arguments, ?array $commands = null): array
    {
        $provider = $commands === null ? new Commands() : new class ($commands) implements CommandProvider {
            /** @param array<string, string> $commands */
            public function __construct(private array $commands)
            {
            }

            #[\Override]
            public function commands(): array
            {
                return $this->commands;
            }
        };
        $launcher = new Launcher(self::ROOT, $provider);

        ob_start();
        try {
            $exitCode = $launcher->run(['./gluo', ...$arguments]);
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$exitCode, $output];
    }

    public function testEveryShippedCommandPassesTheCheck(): void
    {
        [$exitCode, $output] = $this->gluo(['list', '--check']);

        $this->assertStringStartsWith("Environment: test\n", $output);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('migrate:update         OK', $output);
        $this->assertStringContainsString('codegen:all            OK', $output);
    }

    public function testShippedRegistryExposesExactlyThePaymentDoors(): void
    {
        $commands = (new Launcher(self::ROOT, new Commands()))->getCommands();

        // A helper made public by mistake would show up here as an extra command.
        $this->assertSame([
            'payment:charge' => 'Shop\\Command\\PaymentCommands::charge',
            'payment:create' => 'Shop\\Command\\PaymentCommands::create',
            'payment:refund' => 'Shop\\Command\\PaymentCommands::refund',
            'payment:retry' => 'Shop\\Command\\PaymentCommands::retry',
            'payment:sweep' => 'Shop\\Command\\PaymentCommands::sweep',
        ], $commands);
        foreach (array_keys($commands) as $name) {
            $this->assertTrue(Launcher::isValidName($name), "Invalid command name: $name");
            $this->assertFalse(Launcher::isBuiltIn($name), "Reserved command name: $name");
        }
    }

    public function testTheGluoContainerBuildsTheRegisteredServices(): void
    {
        $commands = [
            'jwt:leeway' => 'ByJG/JwtWrapper/JwtWrapper::setLeeway',
            'not:registered' => 'Builder/Scripts::runCheckOpenApi',
        ];

        // A class registered in config/dev/02-security.php passes; one that is not is reported.
        [$exitCode, $output] = $this->gluo(['list', '--check'], $commands);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('jwt:leeway             OK', $output);
        $this->assertStringContainsString('not:registered         NOT_IN_CONTAINER', $output);

        // --help builds the service through the container: JwtWrapper cannot be built with new.
        [$exitCode, $output] = $this->gluo(['jwt:leeway', '--help'], $commands);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString("./gluo jwt:leeway <seconds>", $output);
    }

    public function testBuiltInsRunThroughTheProjectScripts(): void
    {
        $this->assertInstanceOf(Scripts::class, (new Launcher(self::ROOT, new Commands()))->getScripts());
    }

    public function testMigrateVersionAgainstTheTestDatabase(): void
    {
        [$exitCode, $output] = $this->gluo(['migrate:version']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('> Environment: test', $output);
        $this->assertMatchesRegularExpression('/Current version: \d+\nStatus: complete/', $output);
    }
}
