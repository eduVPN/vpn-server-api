<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests;

use DateTime;
use LC\Server\Storage;
use PDO;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    /** @var \LC\Server\Storage */
    private $storage;

    protected function setUp()
    {
        $this->storage = new Storage(
            new PDO('sqlite::memory:'),
            'schema'
        );
        $this->storage->setDateTime(new DateTime('2017-12-31T09:00:00+00:00'));
        $this->storage->init();
        $this->storage->addCertificate(
            'user_id',
            'common_name',
            'display_name',
            new DateTime('2018-01-01T00:00:00+00:00'),
            new DateTime('2018-06-06T00:00:00+00:00'),
            null
        );
        $this->storage->addCertificate(
            'other_user_id',
            'other_common_name',
            'other_display_name',
            new DateTime('2018-01-01T00:00:00+00:00'),
            new DateTime('2018-06-06T00:00:00+00:00'),
            null
        );
    }

    public function testClientConnect()
    {
        $this->storage->clientConnect(
            'internet',
            'common_name',
            '10.0.0.1',
            'fd00::',
            new DateTime('2018-02-02T08:00:00+00:00')
        );
        $this->assertSame(
            [
                'user_id' => 'user_id',
                'profile_id' => 'internet',
                'common_name' => 'common_name',
                'ip4' => '10.0.0.1',
                'ip6' => 'fd00::',
                'connected_at' => '2018-02-02T08:00:00+00:00',
                'disconnected_at' => null,
                'client_lost' => '0',
            ],
            $this->storage->getLogEntry(new DateTime('2018-02-02T10:00:00+00:00'), '10.0.0.1')
        );

        $this->assertSame(
            [
                'user_id' => 'user_id',
                'is_disabled' => false,
                'has_totp_secret' => false,
                'session_expires_at' => '2017-12-31T09:00:00+00:00',
                'permission_list' => [],
            ],
            $this->storage->getUsers()[0]
        );
    }

    public function testClientConnectDisconnect()
    {
        $this->storage->clientConnect(
            'internet',
            'common_name',
            '10.0.0.1',
            'fd00::',
            new DateTime('2018-02-02T08:00:00+00:00')
        );
        $this->storage->clientDisconnect(
            'internet',
            'common_name',
            '10.0.0.1',
            'fd00::',
            new DateTime('2018-02-02T08:00:00+00:00'),
            new DateTime('2018-02-02T11:00:00+00:00'),
            12345
        );
        $this->assertSame(
            [
                'user_id' => 'user_id',
                'profile_id' => 'internet',
                'common_name' => 'common_name',
                'ip4' => '10.0.0.1',
                'ip6' => 'fd00::',
                'connected_at' => '2018-02-02T08:00:00+00:00',
                'disconnected_at' => '2018-02-02T11:00:00+00:00',
                'client_lost' => '0',
            ],
            $this->storage->getLogEntry(new DateTime('2018-02-02T10:00:00+00:00'), '10.0.0.1')
        );
    }

    public function testClientConnectNoDisconnectReconnect()
    {
        // this test tries to test the situation where a client disconnected
        // without there being a log entry written, maybe because OpenVPN
        // crashed... so this calls 2x 'clientConnect' using the same IP
        // address which should result in the old entry being "closed" and the
        // "client_lost" boolean set...
        $this->storage->clientConnect(
            'internet',
            'common_name',
            '10.0.0.1',
            'fd00::',
            new DateTime('2018-02-02T08:00:00+00:00')
        );
        $this->storage->clientConnect(
            'internet',
            'other_common_name',
            '10.0.0.1',
            'fd00::',
            new DateTime('2018-02-02T12:00:00+00:00')
        );
        $this->assertSame(
            [
                'user_id' => 'user_id',
                'profile_id' => 'internet',
                'common_name' => 'common_name',
                'ip4' => '10.0.0.1',
                'ip6' => 'fd00::',
                'connected_at' => '2018-02-02T08:00:00+00:00',
                'disconnected_at' => '2018-02-02T12:00:00+00:00',
                'client_lost' => '1',
            ],
            $this->storage->getLogEntry(new DateTime('2018-02-02T10:00:00+00:00'), '10.0.0.1')
        );
        $this->assertSame(
            [
                'user_id' => 'other_user_id',
                'profile_id' => 'internet',
                'common_name' => 'other_common_name',
                'ip4' => '10.0.0.1',
                'ip6' => 'fd00::',
                'connected_at' => '2018-02-02T12:00:00+00:00',
                'disconnected_at' => null,
                'client_lost' => '0',
            ],
            $this->storage->getLogEntry(new DateTime('2018-02-02T12:00:01+00:00'), '10.0.0.1')
        );
    }
}
