<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\Exceptions\WriteCommandNotAllowedException;
use GreenNet\Services\RouterOS\WriteCommandPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WriteCommandPolicyTest extends TestCase
{
    #[DataProvider('validCommands')]
    public function testEveryProductionWriteCommandAcceptsItsCurrentParams(string $command, array $params): void
    {
        (new WriteCommandPolicy())->assertAllowed(new RouterOSWriteCommand($command, $params));

        self::assertTrue(true);
    }

    #[DataProvider('validCommands')]
    public function testEveryCommandRejectsMissingRequiredParams(string $command, array $params): void
    {
        array_shift($params);

        $this->expectException(WriteCommandNotAllowedException::class);
        (new WriteCommandPolicy())->assertAllowed(new RouterOSWriteCommand($command, $params));
    }

    #[DataProvider('validCommands')]
    public function testEveryCommandRejectsUnexpectedParams(string $command, array $params): void
    {
        $params['unexpected'] = 'value';

        $this->expectException(WriteCommandNotAllowedException::class);
        (new WriteCommandPolicy())->assertAllowed(new RouterOSWriteCommand($command, $params));
    }

    #[DataProvider('validCommands')]
    public function testEveryCommandRejectsEmptyIdentityParam(string $command, array $params): void
    {
        $key = array_key_first($params);
        $params[$key] = '';

        $this->expectException(WriteCommandNotAllowedException::class);
        (new WriteCommandPolicy())->assertAllowed(new RouterOSWriteCommand($command, $params));
    }

    public function testUserSetRejectsBothPasswordAndDisabled(): void
    {
        $this->expectException(WriteCommandNotAllowedException::class);
        (new WriteCommandPolicy())->assertAllowed(new RouterOSWriteCommand('/user-manager/user/set', [
            'numbers' => '*1',
            'password' => 'synthetic',
            'disabled' => 'yes',
        ]));
    }

    public function testUnknownAndReadCommandsAreRejected(): void
    {
        $policy = new WriteCommandPolicy();

        foreach (['/unknown/item/add', '/user-manager/user/print', '/ppp/secret/reset-counters'] as $command) {
            try {
                $policy->assertAllowed(new RouterOSWriteCommand($command));
                self::fail('Expected command rejection.');
            } catch (WriteCommandNotAllowedException) {
                self::assertTrue(true);
            }
        }
    }

    public static function validCommands(): iterable
    {
        yield 'user set disabled' => ['/user-manager/user/set', ['numbers' => '*1', 'disabled' => 'yes']];
        yield 'user set password' => ['/user-manager/user/set', ['numbers' => '*1', 'password' => 'synthetic']];
        yield 'user set name' => ['/user-manager/user/set', ['numbers' => '*1', 'name' => 'synthetic-renamed']];
        yield 'limitation set' => ['/user-manager/limitation/set', ['numbers' => '*2', 'transfer-limit' => '1024']];
        yield 'limitation add' => ['/user-manager/limitation/add', ['name' => 'Synthetic Limit', 'rate-limit-rx' => '1000']];
        yield 'limitation remove' => ['/user-manager/limitation/remove', ['numbers' => '*2']];
        yield 'profile set' => ['/user-manager/profile/set', ['numbers' => '*3', 'validity' => '30d']];
        yield 'profile add' => ['/user-manager/profile/add', ['name' => 'Synthetic Profile', 'starts-when' => 'first-auth']];
        yield 'profile remove' => ['/user-manager/profile/remove', ['numbers' => '*3']];
        yield 'profile limitation add' => ['/user-manager/profile-limitation/add', ['profile' => 'Synthetic Profile', 'limitation' => 'Synthetic Limit']];
        yield 'profile limitation remove' => ['/user-manager/profile-limitation/remove', ['numbers' => '*4']];
        yield 'user profile remove' => ['/user-manager/user-profile/remove', ['numbers' => '*4']];
        yield 'user profile add' => ['/user-manager/user-profile/add', ['user' => 'synthetic-user', 'profile' => 'Synthetic Profile']];
        yield 'user add' => ['/user-manager/user/add', ['name' => 'synthetic-user', 'password' => 'synthetic']];
        yield 'session remove' => ['/user-manager/session/remove', ['numbers' => '*5']];
        yield 'user remove' => ['/user-manager/user/remove', ['numbers' => '*6']];
        yield 'hotspot active remove' => ['/ip/hotspot/active/remove', ['numbers' => '*7']];
        yield 'ppp active remove' => ['/ppp/active/remove', ['numbers' => '*8']];
        yield 'hotspot native add' => ['/ip/hotspot/user/add', ['name' => 'synthetic-user', 'password' => 'synthetic', 'profile' => 'Synthetic Profile']];
        yield 'hotspot native set' => ['/ip/hotspot/user/set', ['numbers' => '*9', 'profile' => 'Synthetic Profile']];
        yield 'hotspot native multi-field set' => ['/ip/hotspot/user/set', ['numbers' => '*9', 'name' => 'synthetic-renamed', 'limit-bytes-total' => '1024']];
        yield 'hotspot native remove' => ['/ip/hotspot/user/remove', ['numbers' => '*9']];
        yield 'hotspot reset counters' => ['/ip/hotspot/user/reset-counters', ['numbers' => '*9']];
        yield 'pppoe native add' => ['/ppp/secret/add', ['name' => 'synthetic-user', 'password' => 'synthetic', 'service' => 'pppoe', 'profile' => 'Synthetic Profile']];
        yield 'pppoe native set' => ['/ppp/secret/set', ['numbers' => '*10', 'disabled' => 'yes']];
        yield 'pppoe native address set' => ['/ppp/secret/set', ['numbers' => '*10', 'local-address' => '192.0.2.1', 'remote-address' => '192.0.2.2']];
        yield 'pppoe native remove' => ['/ppp/secret/remove', ['numbers' => '*10']];
        yield 'hotspot profile add' => ['/ip/hotspot/user/profile/add', ['name' => 'GN-test-hotspot', 'rate-limit' => '1M/1M', 'comment' => 'GreenNet package:1 backend:native-hotspot']];
        yield 'hotspot profile set' => ['/ip/hotspot/user/profile/set', ['numbers' => '*11', 'rate-limit' => '2M/2M']];
        yield 'hotspot profile remove' => ['/ip/hotspot/user/profile/remove', ['numbers' => '*11']];
        yield 'ppp profile add' => ['/ppp/profile/add', ['name' => 'GN-test-pppoe', 'rate-limit' => '1M/1M', 'comment' => 'GreenNet package:1 backend:native-pppoe']];
        yield 'ppp profile set' => ['/ppp/profile/set', ['numbers' => '*12', 'comment' => 'GreenNet package:1 backend:native-pppoe']];
        yield 'ppp profile remove' => ['/ppp/profile/remove', ['numbers' => '*12']];
    }
}
