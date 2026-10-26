<?php

namespace App\Enums;

/**
 * Über welchen Weg eine Änderung ins System kam. Muss zu audit_logs_channel_check passen.
 */
enum AuditChannel: string
{
    case Web = 'web';
    case Public = 'public';
    case Mcp = 'mcp';
    case Console = 'console';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Weboberfläche',
            self::Public => 'Öffentliche Seite',
            self::Mcp => 'via MCP',
            self::Console => 'Konsole',
            self::System => 'System',
        };
    }
}
