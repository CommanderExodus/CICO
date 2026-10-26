<?php

namespace App\Support;

use App\Enums\AuditChannel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use PDO;
use WeakMap;

/**
 * Hält fest, wer gerade über welchen Kanal handelt, und reicht das als
 * PostgreSQL-Session-Variablen an die Audit-Trigger (R18, R33) weiter.
 *
 * Die Werte werden nicht sofort, sondern vor der nächsten Abfrage übertragen
 * (siehe AppServiceProvider) – und zwar je Verbindung und PDO-Instanz. So braucht
 * das Setzen keine eigene Verbindung, und nach einem Reconnect oder einem Rollback
 * (der set_config() mit zurückrollt) werden die Werte erneut gesetzt.
 */
class AuditContext
{
    private ?int $userId = null;

    private AuditChannel $channel = AuditChannel::System;

    private ?string $client = null;

    /** Wird bei jeder Änderung erhöht; eine Verbindung ist aktuell, wenn sie diese Version kennt. */
    private int $version = 0;

    /** @var WeakMap<Connection, array{pdo: int, version: int}> */
    private WeakMap $applied;

    private bool $applying = false;

    public function __construct()
    {
        $this->applied = new WeakMap;
    }

    public function set(?Authenticatable $user, AuditChannel $channel, ?string $client = null): void
    {
        $this->userId = $user?->getAuthIdentifier();
        $this->channel = $channel;
        $this->client = $client === null ? null : mb_substr($client, 0, 255);
        $this->version++;
    }

    /**
     * Erzwingt eine erneute Übertragung, z. B. nach einem Rollback.
     */
    public function markPending(): void
    {
        $this->version++;
    }

    public function applyIfPending(Connection $connection): void
    {
        if ($this->applying || $connection->getDriverName() !== 'pgsql') {
            return;
        }

        $pdo = $connection->getRawPdo();
        $state = $this->applied[$connection] ?? null;

        if ($pdo instanceof PDO && $state === ['pdo' => spl_object_id($pdo), 'version' => $this->version]) {
            return;
        }

        $this->applying = true;

        try {
            $connection->select(
                'select set_config(?, ?, false), set_config(?, ?, false), set_config(?, ?, false)',
                [
                    'cico.user_id', (string) ($this->userId ?? ''),
                    'cico.channel', $this->channel->value,
                    'cico.client', (string) ($this->client ?? ''),
                ],
            );

            $this->applied[$connection] = ['pdo' => spl_object_id($connection->getRawPdo()), 'version' => $this->version];
        } finally {
            $this->applying = false;
        }
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function channel(): AuditChannel
    {
        return $this->channel;
    }

    public function client(): ?string
    {
        return $this->client;
    }
}
