<?php

namespace Tests\Payment;

use ByJG\Config\Config;
use ByJG\Serializer\Serialize;
use ByJG\StateMachine\FiniteStateMachine;
use PHPUnit\Framework\TestCase;
use Shop\Payment\PaymentState;

/**
 * Slide 29 — the whole graph as YAML. The file is not what the app runs, so this test is what
 * stops it drifting from PaymentMachine: every pair of states must give the same arrow, with
 * the same condition and the same action, in both machines.
 */
class PaymentYamlTest extends TestCase
{
    public function testTheYamlDescribesTheSameMachine(): void
    {
        // The same loading as the commented-out binding in config/dev/05-services.php
        $yaml = file_get_contents(__DIR__ . '/../../src/Payment/payment-machine.yaml');
        $fromYaml = FiniteStateMachine::fromDefinition(
            Serialize::fromYaml($yaml)->toArray(),
            fn (string $class) => Config::get($class)
        );

        $fromPhp = Config::get(FiniteStateMachine::class);

        $arrows = 0;
        foreach (PaymentState::cases() as $from) {
            foreach (PaymentState::cases() as $to) {
                $expected = $fromPhp->getTransition($from, $to);
                $actual = $fromYaml->getTransition($from, $to);
                $move = "{$from->value} -> {$to->value}";

                if ($expected === null) {
                    $this->assertNull($actual, "$move is in the YAML but not in PaymentMachine");
                    continue;
                }

                $arrows++;
                $this->assertNotNull($actual, "$move is in PaymentMachine but not in the YAML");
                $this->assertSame($expected->getTransitionCondition(), $actual->getTransitionCondition(), "$move: condition");
                $this->assertSame($expected->getTransitionAction(), $actual->getTransitionAction(), "$move: action");
            }
        }

        $this->assertSame(7, $arrows);
    }
}
