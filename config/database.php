<?php
/**
 * ==========================================================================
 * config/database.php
 * --------------------------------------------------------------------------
 * One single PDO (MySQL) connection for the whole application.
 *
 * IMPORTANT POINTS FOR THE ASSIGNMENT:
 *  - We use PDO PREPARED STATEMENTS everywhere (no string concatenation of
 *    user input into SQL). This protects us from SQL injection.
 *  - EMULATE_PREPARES is switched OFF so that MySQL receives a real
 *    prepared statement and really checks the parameter types.
 *  - Results are read STREAMING (row by row) with ->fetch().
 *    ->fetchAll() is NEVER used anywhere in this project.
 * ==========================================================================
 */

/**
 * Get the shared PDO connection (singleton).
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        // Throw exceptions when something goes wrong
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Return associative arrays: ['id' => 1, 'name' => '...']
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Use REAL prepared statements (not emulated ones)
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Use the MySQL native driver
        PDO::ATTR_DRIVER_NAME        => 'mysql',
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Friendly message for a beginner who has not imported the SQL file yet
        if (APP_DEBUG) {
            http_response_code(500);
            echo '<div style="font-family:sans-serif;max-width:720px;margin:60px auto;">';
            echo '<h2>Database connection failed</h2>';
            echo '<p><strong>MySQL said:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<ol>';
            echo '<li>Make sure Laragon MySQL is <strong>started</strong>.</li>';
            echo '<li>Import <code>database/bakery.sql</code> (see README.md, step 3).</li>';
            echo '<li>Check DB_USER / DB_PASS in <code>config/config.php</code>.</li>';
            echo '</ol></div>';
            exit;
        }
        throw $e;
    }

    return $pdo;
}

/**
 * Prepare a statement and return it (already executed when params given).
 *
 * We return the STATEMENT instead of the rows, so the caller can read the
 * result row by row (streaming) with a while() loop.
 *
 * @param string $sql    SQL with ? placeholders
 * @param array  $params parameters for the prepared statement
 * @return PDOStatement
 */
function stream_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Return a single scalar value, e.g. SUM(daily_demand_units).
 *
 * @param string $sql
 * @param array  $params
 * @return string|null
 */
function scalar(string $sql, array $params = [])
{
    $stmt = stream_query($sql, $params);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}
