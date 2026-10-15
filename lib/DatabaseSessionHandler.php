<?php

final class ChoreHiveDatabaseSessionHandler implements SessionHandlerInterface
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->connection->prepare('SELECT session_data, expires_at FROM app_sessions WHERE session_id=?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || (int)$row['expires_at'] < time()) {
            if ($row) $this->destroy($id);
            return '';
        }

        return (string)$row['session_data'];
    }

    public function write(string $id, string $data): bool
    {
        $expiresAt = time() + (int)ini_get('session.gc_maxlifetime');
        $stmt = $this->connection->prepare(
            'INSERT INTO app_sessions (session_id, session_data, expires_at) VALUES (?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE session_data=VALUES(session_data), expires_at=VALUES(expires_at)'
        );
        $stmt->bind_param('ssi', $id, $data, $expiresAt);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->connection->prepare('DELETE FROM app_sessions WHERE session_id=?');
        $stmt->bind_param('s', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function gc(int $max_lifetime): int|false
    {
        $expiredBefore = time();
        $stmt = $this->connection->prepare('DELETE FROM app_sessions WHERE expires_at < ?');
        $stmt->bind_param('i', $expiredBefore);
        $ok = $stmt->execute();
        $deleted = $ok ? $stmt->affected_rows : false;
        $stmt->close();
        return $deleted;
    }
}
