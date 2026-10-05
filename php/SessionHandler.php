<?php
namespace creamy;
require_once __DIR__ . '/CRMDefaults.php';
require_once __DIR__ . '/SessionCipher.php';

/** Encrypted database sessions with strict ID validation and checked persistence. */
class SessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public $table = 'go_sessions';
    public $lifeTime = 7200;
    private $db;
    private $cipher;
    private static $lastWriteSuccessful = true;

    public static function lastWriteSucceeded(): bool { return self::$lastWriteSuccessful; }

    public function __construct($table = null, $lifeTime = 0, $encrypt = true)
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $this->table = $table ?? 'go_sessions';
        $this->lifeTime = $lifeTime ?: CRM_SESSION_EXPIRATION;
        if (CRM_SESSION_DRIVER === 'files') {
            if (RuntimeConfig::production()) {
                throw new \RuntimeException('File sessions are only available in development.');
            }
        } elseif (CRM_SESSION_DRIVER === 'database') {
            require_once __DIR__ . '/DatabaseConnectorFactory.php';
            $this->cipher = new SessionCipher();
            $this->db = DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
            $this->db->getOne($this->table, 'session_id');
            if ($this->db->getLastError() !== '') {
                throw new \RuntimeException('Session storage is unavailable.');
            }
            session_set_save_handler($this, true);
        } else {
            throw new \RuntimeException('Unknown session driver.');
        }
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_name(CRM_SESSION_COOKIE_NAME);
        session_set_cookie_params(array('lifetime' => 0, 'path' => '/',
            'secure' => RuntimeConfig::production() || RuntimeConfig::secureRequest(),
            'httponly' => true, 'samesite' => 'Lax'));
        if (!session_start()) throw new \RuntimeException('Session could not be started.');
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    private function record($id)
    {
        $this->db->where('session_id', md5($id));
        $this->db->where('last_activity', time(), '>');
        if (CRM_SESSION_MATCH_IP) $this->db->where('ip_address', $this->getUserIP());
        $row = $this->db->getOne($this->table);
        if ($this->db->getLastError() !== '') throw new \RuntimeException('Session storage read failed.');
        return $row;
    }

    public function read(string $id): string|false
    {
        $row = $this->record($id);
        return $row ? $this->cipher->decrypt($row['user_data']) : '';
    }

    public function validateId(string $id): bool
    {
        $row = $this->record($id);
        return $row && $this->cipher->decrypt($row['user_data']) !== '';
    }

    public function write(string $id, string $data): bool
    {
        try {
            $values = array('session_id' => md5($id), 'user_agent' => substr($this->getUserAgent(), 0, 255),
                'last_activity' => time() + $this->lifeTime, 'user_data' => $this->cipher->encrypt($data),
                'ip_address' => $this->getUserIP());
            if ($this->record($id)) {
                unset($values['session_id']);
                $this->db->where('session_id', md5($id));
                $ok = $this->db->update($this->table, $values);
            } else {
                $ok = $this->db->insert($this->table, $values);
                if (!$ok && strpos($this->db->getLastError(), 'Duplicate entry') === 0) {
                    unset($values['session_id']);
                    $this->db->where('session_id', md5($id));
                    $ok = $this->db->update($this->table, $values);
                }
            }
            self::$lastWriteSuccessful = (bool) $ok && $this->db->getLastError() === '';
            return self::$lastWriteSuccessful;
        } catch (\Throwable $exception) {
            error_log('GOautodial session storage write failed.');
            self::$lastWriteSuccessful = false;
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool { return $this->write($id, $data); }
    public function destroy(string $id): bool
    {
        $this->db->where('session_id', md5($id));
        return (bool) $this->db->delete($this->table);
    }
    public function gc(int $max_lifetime): int|false
    {
        $this->db->where('last_activity', time(), '<');
        return $this->db->delete($this->table) ? $this->db->getRowCount() : false;
    }
    public function getUserIP() { return (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }
    public function getUserAgent() { return (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''); }
}
