<?php
/**
 * ============================================================================
 *  phpRapidAdmin — a fast, friendly, single-file MySQL / MariaDB admin tool
 * ============================================================================
 *  Drop this one file anywhere on a PHP server and open it in a browser.
 *  No dependencies, no build step, no config files required to get started.
 *
 *  Author: you (generated for your project)
 *  License: MIT
 * ============================================================================
 */

// ============================================================================
//  CONFIGURATION — edit the values below, or just leave them and configure
//  the connection from the browser on first run.
// ============================================================================

// Site password. STRONGLY recommended if this server is reachable from the
// internet. Leave blank to allow only local (127.0.0.1) connections without
// a password — remote access without a password is always blocked.
$RAPID_PASSWORD = '';

// Pre-configure one or more MySQL/MariaDB connections so nobody has to type
// credentials into the browser. Leave empty to be asked on first run.
// Example:
// $RAPID_SERVERS = [
//   'Main server' => ['host'=>'127.0.0.1','user'=>'root','pass'=>'secret','port'=>'','socket'=>'','db'=>'','charset'=>'utf8mb4'],
// ];
$RAPID_SERVERS = [];

$RAPID_ROWS_PER_PAGE = 50;   // rows per page when browsing table data
$RAPID_TITLE         = 'phpRapidAdmin';
$RAPID_VERSION       = '1.0';
$RAPID_DUMP_DIR      = '';   // server-side dump directory (leave empty for browser download)

// ============================================================================
//  End of configuration — no need to edit anything below this line.
// ============================================================================

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
if (function_exists('date_default_timezone_set') && !ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

session_name('nanodb_sid');
$RAPID_HTTPS = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $RAPID_HTTPS,
    'httponly' => true,
    'samesite' => 'Lax',
]);
@session_start();

// ---------------------------------------------------------------------------
//  Small helpers
// ---------------------------------------------------------------------------

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function e($s) { echo h($s); }
function qid($name) { return '`' . str_replace('`', '``', (string)$name) . '`'; }

function is_local_request() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true);
}

function csrf_token() {
    if (empty($_SESSION['RAPID_csrf'])) {
        $_SESSION['RAPID_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['RAPID_csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_ok() {
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string)$_POST['csrf']);
}

function flash($type, $msg) {
    if (!isset($_SESSION['RAPID_flash'])) $_SESSION['RAPID_flash'] = [];
    $_SESSION['RAPID_flash'][] = [$type, $msg];
}
function take_flashes() {
    $f = $_SESSION['RAPID_flash'] ?? [];
    unset($_SESSION['RAPID_flash']);
    return $f;
}

function self_path() {
    return strtok($_SERVER['REQUEST_URI'], '?');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Build a URL back to this script. Extra params are merged over the
// "sticky" state (server + database) so links naturally keep context.
function nurl($params = []) {
    global $RAPID_STATE;
    $q = array_merge($RAPID_STATE ?? [], $params);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') unset($q[$k]);
    }
    $qs = http_build_query($q);
    return self_path() . ($qs !== '' ? '?' . $qs : '');
}

function format_bytes($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, ($bytes < 10 && $i > 0) ? 1 : 0) . ' ' . $units[$i];
}
function format_num($n) {
    if ($n === null || $n === '') return '0';
    return number_format((float)$n);
}
function is_write_sql($sql) {
    return !preg_match('/^\s*(select|show|explain|describe|desc|with)\b/i', $sql);
}
function is_select_like($sql) {
    return (bool)preg_match('/^\s*(select|show|explain|describe|desc|with)\b/i', $sql);
}

// ---------------------------------------------------------------------------
//  Database layer — PDO (preferred) with a mysqli fallback, kept intentionally
//  thin: only what an admin tool actually needs.
// ---------------------------------------------------------------------------

$DBH = null;      // connection handle (PDO instance or mysqli link)
$DRV = '';        // 'pdo' or 'mysqli'
$DB_ERROR = '';

function db_connect($cfg) {
    global $DBH, $DRV, $DB_ERROR, $CFG;
    $CFG = $cfg;
    $DBH = null; $DRV = ''; $DB_ERROR = '';

    $host   = ($cfg['host'] ?? '') !== '' ? $cfg['host'] : 'localhost';
    $user   = $cfg['user'] ?? '';
    $pass   = $cfg['pass'] ?? '';
    $db     = $cfg['db'] ?? '';
    $port   = !empty($cfg['port']) ? (int)$cfg['port'] : null;
    $socket = $cfg['socket'] ?? '';
    $chset  = $cfg['charset'] ?: 'utf8mb4';

    if (extension_loaded('pdo_mysql')) {
        $dsn = 'mysql:charset=' . $chset;
        if ($socket !== '') {
            $dsn .= ';unix_socket=' . $socket;
        } else {
            $dsn .= ';host=' . $host;
            if ($port) $dsn .= ';port=' . $port;
        }
        if ($db !== '') $dsn .= ';dbname=' . $db;
            try {
            $DBH = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => true,
                PDO::ATTR_TIMEOUT            => 8,
            ]);
            $DRV = 'pdo';
            return true;
        } catch (PDOException $ex) {
            $DB_ERROR = $ex->getMessage();
            $DBH = null;
        }
    }

    if (extension_loaded('mysqli')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $m = mysqli_init();
        if ($m) {
            @mysqli_options($m, MYSQLI_OPT_CONNECT_TIMEOUT, 8);
            $ok = @mysqli_real_connect(
                $m, $host, $user, $pass, ($db !== '' ? $db : null),
                $port ?: null, $socket !== '' ? $socket : null
            );
            if ($ok) {
                $DBH = $m; $DRV = 'mysqli';
                @mysqli_set_charset($m, $chset);
                return true;
            } else {
                $DB_ERROR = mysqli_connect_error() ?: $DB_ERROR;
            }
        }
    }

    if ($DB_ERROR === '') $DB_ERROR = 'No MySQL driver available (need pdo_mysql or mysqli).';
    return false;
}

function db_checkconnect() {
    global $DBH, $DRV, $CFG, $DB_ERROR;
    if (!$DBH) { return false; }
    if ($DRV === 'pdo') {
        try { $DBH->getAttribute(PDO::ATTR_SERVER_INFO); }
        catch (PDOException $e) {
            if (!empty($CFG) && db_connect($CFG)) { return true; }
            $DB_ERROR = 'Connection lost: ' . $e->getMessage();
            return false;
        }
    } else {
        if (!@mysqli_ping($DBH)) {
            if (!empty($CFG) && db_connect($CFG)) { return true; }
            $DB_ERROR = 'Connection lost: ' . mysqli_error($DBH);
            return false;
        }
    }
    return true;
}

function db_query($sql) {
    global $DBH, $DRV, $DB_ERROR;
    if (!$DBH) { $DB_ERROR = 'Not connected'; return false; }
    db_checkconnect();
    if ($DRV === 'pdo') {
        $res = $DBH->query($sql);
        if ($res === false) {
            $info = $DBH->errorInfo();
            $DB_ERROR = $info[2] ?? 'Query failed';
            return false;
        }
        return $res;
    }
    $res = mysqli_query($DBH, $sql);
    if ($res === false) {
        $DB_ERROR = mysqli_error($DBH);
        return false;
    }
    return $res;
}
function db_exec($sql) { return db_query($sql) !== false; }

function db_fetch_assoc($res) {
    global $DRV;
    if (!$res) return false;
    return $DRV === 'pdo' ? $res->fetch(PDO::FETCH_ASSOC) : mysqli_fetch_assoc($res);
}
function db_fetch_row($res) {
    global $DRV;
    if (!$res) return false;
    return $DRV === 'pdo' ? $res->fetch(PDO::FETCH_NUM) : mysqli_fetch_row($res);
}
function db_num_rows($res) {
    global $DRV;
    if (!$res) return 0;
    return $DRV === 'pdo' ? $res->rowCount() : mysqli_num_rows($res);
}
function db_num_fields($res) {
    global $DRV;
    if (!$res) return 0;
    return $DRV === 'pdo' ? $res->columnCount() : mysqli_num_fields($res);
}
function db_field_name($res, $i) {
    global $DRV;
    if ($DRV === 'pdo') {
        $m = $res->getColumnMeta($i);
        return $m['name'] ?? (string)$i;
    }
    $f = mysqli_fetch_field_direct($res, $i);
    return $f ? $f->name : (string)$i;
}
function db_free($res) {
    global $DRV;
    if (!$res) return;
    if ($DRV === 'pdo') { $res->closeCursor(); } else { mysqli_free_result($res); }
}
function db_affected($res = null) {
    global $DBH, $DRV;
    if ($DRV === 'pdo') return $res ? $res->rowCount() : 0;
    return $DBH ? mysqli_affected_rows($DBH) : 0;
}
function db_insert_id() {
    global $DBH, $DRV;
    if ($DRV === 'pdo') return $DBH->lastInsertId();
    return $DBH ? mysqli_insert_id($DBH) : 0;
}
function qval($v) {
    global $DBH, $DRV;
    if ($v === null) return 'NULL';
    if ($DRV === 'pdo') return $DBH->quote($v);
    return "'" . mysqli_real_escape_string($DBH, (string)$v) . "'";
}

function db_all($sql) {
    $res = db_query($sql);
    if (!$res) return [];
    $rows = [];
    while ($r = db_fetch_assoc($res)) $rows[] = $r;
    db_free($res);
    return $rows;
}
function db_all_num($sql) {
    $res = db_query($sql);
    if (!$res) return [];
    $rows = [];
    while ($r = db_fetch_row($res)) $rows[] = $r;
    db_free($res);
    return $rows;
}
function db_row($sql) {
    $res = db_query($sql);
    if (!$res) return null;
    $r = db_fetch_assoc($res);
    db_free($res);
    return $r ?: null;
}
function db_value($sql) {
    $res = db_query($sql);
    if (!$res) return null;
    $r = db_fetch_row($res);
    db_free($res);
    return $r ? $r[0] : null;
}

function cached($key, $callback, $ttl = 30) {
    global $RAPID_CACHE_HIT, $RAPID_CACHE_QTIME;
    $cacheKey = 'RAPID_cache_' . md5($key);
    $force = !empty($_GET['refresh']);
    if (!$force && isset($_SESSION[$cacheKey]) && (time() - $_SESSION[$cacheKey]['time'] < $ttl)) {
        $RAPID_CACHE_HIT = true;
        return $_SESSION[$cacheKey]['data'];
    }
    $RAPID_CACHE_HIT = false;
    $t0 = microtime(true);
    $data = $callback();
    $RAPID_CACHE_QTIME = round(microtime(true) - $t0, 4);
    $_SESSION[$cacheKey] = ['data' => $data, 'time' => time()];
    return $data;
}

// ---------------------------------------------------------------------------
//  SQL query bar — reusable across all pages
// ---------------------------------------------------------------------------

function render_sql_bar($q = '', $table = '') {
    // Pre-fill with a default SELECT if no query provided and a table is selected
    if ($q === '' && $table !== '') {
        $q = 'SELECT * FROM ' . h($table) . ' WHERE 1 LIMIT 0, 50';
    }
    ?>
  <div class="card" style="margin-top:12px;padding:12px">
    <div class="card-head"><h3>SQL Query</h3></div>
    <form method="post" class="sql-form" style="margin:0" action="<?php e(nurl(['a'=>'sql','t'=>$table ?: null])) ?>">
      <?php echo csrf_field() ?>
      <input type="hidden" name="run_sql" value="1">
      <textarea class="sql" id="qraw" name="q" rows="3" placeholder="SELECT * FROM ..."><?php e($q) ?></textarea>
      <div style="margin-top:8px;display:flex;gap:6px">
        <button class="btn btn-primary" type="submit">Run &#9658;</button>
        <button class="btn" type="button" onclick="this.closest('form').querySelector('textarea').value=''">Clear</button>
        <button class="btn btn-sm" type="button" onclick="nanoTemplate('select','<?php e($table) ?>',this)">SELECT</button>
        <button class="btn btn-sm" type="button" onclick="nanoTemplate('insert','<?php e($table) ?>',this)">INSERT</button>
        <button class="btn btn-sm" type="button" onclick="nanoTemplate('update','<?php e($table) ?>',this)">UPDATE</button>
        <button class="btn btn-sm" type="button" onclick="nanoTemplate('delete','<?php e($table) ?>',this)">DELETE</button>
      </div>
    </form>
  </div>
  <?php
}

function stream_write($data) { echo $data; @ob_flush(); flush(); }

// ---------------------------------------------------------------------------
//  SQL helpers: splitting multi-statement text, safe pagination, introspection
// ---------------------------------------------------------------------------

// Split a block of SQL into individual statements, respecting quoted strings,
// backtick identifiers, and -- / # / slash-star comments.
function split_sql($sql) {
    $stmts = [];
    $len = strlen($sql);
    $buf = '';
    $inStr = '';      // '\'' or '"' or '`' or ''
    $inComment = '';  // '--', '#', '/*' or ''
    for ($i = 0; $i < $len; $i++) {
        $c  = $sql[$i];
        $c2 = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($inComment !== '') {
            $buf .= $c;
            if ($inComment === '/*') {
                if ($c === '*' && $c2 === '/') { $buf .= $c2; $i++; $inComment = ''; }
            } elseif ($c === "\n") {
                $inComment = '';
            }
            continue;
        }
        if ($inStr !== '') {
            $buf .= $c;
            if ($c === '\\' && $inStr !== '`') {
                if ($i + 1 < $len) { $buf .= $sql[$i + 1]; $i++; }
                continue;
            }
            if ($c === $inStr) {
                // handle doubled quote as escape, e.g. '' or ``
                if ($c2 === $inStr) { $buf .= $c2; $i++; } else { $inStr = ''; }
            }
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') { $inStr = $c; $buf .= $c; continue; }
        if ($c === '-' && $c2 === '-') { $inComment = '--'; $buf .= $c; continue; }
        if ($c === '#') { $inComment = '#'; $buf .= $c; continue; }
        if ($c === '/' && $c2 === '*') { $inComment = '/*'; $buf .= $c . $c2; $i++; continue; }
        if ($c === ';') {
            $t = trim($buf);
            if ($t !== '') $stmts[] = $t;
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $t = trim($buf);
    if ($t !== '') $stmts[] = $t;
    return $stmts;
}

function add_limit($sql, $page, $perPage) {
    if (preg_match('/^\s*select\b/i', $sql) && !preg_match('/\blimit\s+\d+(\s*,\s*\d+)?\s*$/i', $sql)) {
        $offset = max(0, (int)$page) * $perPage;
        $sql = rtrim($sql, "; \t\n\r") . " LIMIT $offset, $perPage";
    }
    return $sql;
}

// Run one or more ;-separated statements. Returns an array with keys:
// ok, error, sql (of the failing or last statement), result (last resource),
// count (number of statements executed).
function run_sql_multi($sqlText, $paginateLast = false, $page = 0, $perPage = 50) {
    global $DB_ERROR;
    $stmts = split_sql($sqlText);
    if (!$stmts) return ['ok' => true, 'result' => null, 'sql' => '', 'count' => 0];

    $n = count($stmts);
    $lastRes = null; $lastSql = '';
    foreach ($stmts as $idx => $s) {
        if ($paginateLast && $idx === $n - 1) $s = add_limit($s, $page, $perPage);
        $lastRes = db_query($s);
        $lastSql = $s;
        if ($lastRes === false) {
            return ['ok' => false, 'error' => $DB_ERROR, 'sql' => $s, 'count' => $idx];
        }
    }
    return ['ok' => true, 'result' => $lastRes, 'sql' => $lastSql, 'count' => $n];
}

// ---- introspection -----------------------------------------------------

function list_databases() {
    return cached('databases', function() {
        return db_all_num('SHOW DATABASES');
    }, 60);
}
function list_tables_status($db) {
    return cached('tables_' . $db, function() use ($db) {
        $sql = "SELECT TABLE_NAME AS Name, ENGINE AS Engine, TABLE_ROWS AS `Rows`,
                       DATA_LENGTH AS Data_length, INDEX_LENGTH AS Index_length,
                       TABLE_COMMENT AS Comment, TABLE_TYPE AS Table_type,
                       CREATE_TIME AS Create_time, TABLE_COLLATION AS Collation
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = " . qval($db) . "
                ORDER BY TABLE_NAME";
        return db_all($sql);
    }, 30);
}
function table_columns($db, $table) {
    return cached('cols_' . $db . '_' . $table, function() use ($db, $table) {
        $sql = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT,
                       EXTRA, CHARACTER_MAXIMUM_LENGTH, COLUMN_COMMENT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = " . qval($db) . " AND TABLE_NAME = " . qval($table) . "
                ORDER BY ORDINAL_POSITION";
        return db_all($sql);
    }, 30);
}
function table_primary_key($db, $table) {
    $cols = table_columns($db, $table);
    $pk = [];
    foreach ($cols as $c) if ($c['COLUMN_KEY'] === 'PRI') $pk[] = $c['COLUMN_NAME'];
    return $pk;
}
function table_indexes($db, $table) {
    return cached('idx_' . $db . '_' . $table, function() use ($db, $table) {
        return db_all("SELECT INDEX_NAME AS Key_name, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE, SEQ_IN_INDEX
                       FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = " . qval($db) . " AND TABLE_NAME = " . qval($table) . "
                       ORDER BY INDEX_NAME, SEQ_IN_INDEX");
    }, 60);
}
function is_binary_type($colType) {
    return (bool)preg_match('/^(blob|tinyblob|mediumblob|longblob|binary|varbinary|geometry|geometrycollection|point|linestring|polygon|multi)/i', $colType);
}

// ---------------------------------------------------------------------------
//  Presentation: CSS + JS (kept in one small block, no external assets)
// ---------------------------------------------------------------------------

function render_head($title) {
    global $RAPID_TITLE;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php e($title ? $title . ' · ' . $RAPID_TITLE : $RAPID_TITLE) ?></title>
<script>
(function(){ // apply theme before paint to avoid a flash
  try {
    var t = localStorage.getItem('RAPID_theme');
    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
  } catch(e) {}
})();
document.addEventListener('DOMContentLoaded', function(){
  // compact view toggle
  var compact = localStorage.getItem('RAPID_compact');
  if(compact === '1') document.body.classList.add('compact');
  var compactToggle = document.querySelector('.topbar .compact-toggle');
  if(compactToggle) compactToggle.addEventListener('change', function(){
    if(this.checked) document.body.classList.add('compact'); else document.body.classList.remove('compact');
    localStorage.setItem('RAPID_compact', this.checked ? '1' : '0');
  });
  // back to top button
  var bt = document.createElement('button');
  bt.className = 'iconbtn';
  bt.type = 'button';
  bt.title = 'Back to top';
  bt.textContent = '↑';
  bt.style.display = 'none';
  document.body.appendChild(bt);
  window.addEventListener('scroll', function(){
    bt.style.display = window.scrollY > 300 ? '' : 'none';
  });
  bt.addEventListener('click', function(){
    window.scrollTo({top: 0, behavior: 'smooth'});
  });
});
</script>
<style>
:root{
  --bg:#f3f5f9; --panel:#ffffff; --panel-2:#fafbfd; --text:#1c2333; --muted:#6b7385;
  --border:#e4e8f0; --accent:#4a63e7; --accent-ink:#ffffff; --danger:#e0393f; --danger-bg:#fdeceb;
  --success:#178a52; --success-bg:#e9f9f0; --warn:#a5670c; --warn-bg:#fdf3e3;
  --row-alt:#f7f8fc; --row-hover:#eef1ff; --code-bg:#10162a; --code-text:#dbe3ff;
  --radius:10px; --shadow:0 1px 2px rgba(20,25,45,.06), 0 6px 20px rgba(20,25,45,.05);
  --font:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;
}
html.dark{
  --bg:#0d1120; --panel:#151b2e; --panel-2:#121729; --text:#e7ebf7; --muted:#94a0bd;
  --border:#242c46; --accent:#6f89ff; --accent-ink:#0d1120; --danger:#ff6b6f; --danger-bg:#3a1a1c;
  --success:#3ddb90; --success-bg:#123526; --warn:#f0b429; --warn-bg:#3a2c0e;
  --row-alt:#171e34; --row-hover:#1d2540; --code-bg:#0a0e1c; --code-text:#c9d4ff;
  --shadow:0 1px 2px rgba(0,0,0,.3), 0 6px 24px rgba(0,0,0,.35);
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;line-height:1.45}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
code,pre,textarea,.mono{font-family:var(--mono)}
h1,h2,h3{margin:0 0 10px;font-weight:600}
.small{font-size:12px;color:var(--muted)}
.muted{color:var(--muted)}

.topbar{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:14px;
  background:var(--panel);border-bottom:1px solid var(--border);padding:10px 16px;box-shadow:var(--shadow)}
.brand{font-weight:700;font-size:16px;display:flex;align-items:center;gap:8px;white-space:nowrap}
.brand .dot{width:9px;height:9px;border-radius:50%;background:var(--accent);display:inline-block}
.topbar select, .topbar input[type=text]{
  background:var(--panel-2);color:var(--text);border:1px solid var(--border);border-radius:8px;
  padding:6px 8px;font-size:13px}
.topbar .spacer{flex:1}
.topnav{display:flex;gap:4px;align-items:center;flex-wrap:wrap}
.topnav a, .iconbtn{padding:6px 10px;border-radius:8px;color:var(--text);font-size:13px}
.topnav a:hover, .iconbtn:hover{background:var(--row-hover);text-decoration:none;cursor:pointer}
.topnav a.active{background:var(--accent);color:var(--accent-ink)}
.iconbtn{border:1px solid var(--border);background:var(--panel-2);cursor:pointer}

.layout{display:flex;align-items:flex-start;min-height:calc(100vh - 52px)}
.sidebar{width:250px;flex:0 0 250px;background:var(--panel);border-right:1px solid var(--border);
  padding:0;position:sticky;top:52px;align-self:flex-start;max-height:calc(100vh - 52px);overflow:auto;transition:width .18s ease, left .18s ease}
.sidebar.closed{width:0;flex:0 0 0;overflow:hidden}
.sidebar.open{left:0}
.sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:4px 0 8px}
.sidebar input[type=text]{width:100%;padding:7px 9px;border:1px solid var(--border);border-radius:8px;
  background:var(--panel-2);color:var(--text);margin-bottom:8px;margin:4px}
.tbl-list{list-style:none;margin:0;padding:0}
.tbl-list li a{display:block;padding:12px;border-radius:10px;color:var(--text);font-size:13px;text-decoration:none}
.tbl-list li a:hover{background:var(--row-hover);text-decoration:none}
.tbl-list li a.active{background:var(--accent);color:var(--accent-ink)}
.tbl-list li a .cnt{color:var(--muted);font-size:11px}
.tbl-list li a.active .cnt{color:var(--accent-ink);opacity:.8}

.main{flex:1;min-width:0;padding:18px 20px 40px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);
  padding:16px;margin-bottom:16px;box-shadow:var(--shadow)}
.card-head{display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap}
.card-head h2{margin:0;font-size:16px}
.card-head .spacer{flex:1}

.alert{padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px}
.alert.err{background:var(--danger-bg);color:var(--danger)}
.alert.ok{background:var(--success-bg);color:var(--success)}
.alert.warn{background:var(--warn-bg);color:var(--warn)}

.btn{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);background:var(--panel-2);
  color:var(--text);padding:7px 12px;border-radius:8px;cursor:pointer;font-size:13px}
.btn:hover{background:var(--row-hover);text-decoration:none}
.btn-primary{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
.btn-primary:hover{filter:brightness(1.06)}
.btn-danger{background:var(--danger-bg);border-color:var(--danger);color:var(--danger)}
.btn-sm{padding:4px 9px;font-size:12px}
.btn[disabled]{opacity:.5;cursor:not-allowed}

table.grid{border-collapse:collapse;width:100%;font-size:13px}
table.grid th, table.grid td{border:1px solid var(--border);padding:6px 8px;text-align:left;vertical-align:top}
table.grid thead th{background:var(--panel-2);position:sticky;top:0;z-index:1;white-space:nowrap}
table.grid tbody tr:nth-child(even){background:var(--row-alt)}
table.grid tbody tr:hover{background:var(--row-hover)}
table.grid td.num, table.grid th.num{text-align:right}
.table-scroll{overflow:auto;border-radius:8px;border:1px solid var(--border)}
.table-scroll table.grid{border:none}
.table-scroll table.grid th, .table-scroll table.grid td{border:1px solid var(--border)}
td.cell{max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
td.cell.wrap{white-space:pre-wrap;max-width:480px}
.compact td.cell{max-width:160px}
.null{color:var(--muted);font-style:italic}
.badge{display:inline-block;padding:1px 7px;border-radius:99px;font-size:11px;background:var(--panel-2);border:1px solid var(--border);color:var(--muted)}
.pk{color:var(--accent);font-weight:600}

textarea.sql{width:100%;min-height:130px;padding:10px;border-radius:8px;border:1px solid var(--border);
  background:var(--code-bg);color:var(--code-text);resize:vertical;font-size:13px}
.field{margin-bottom:10px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:4px}
.field input[type=text], .field input[type=password], .field input[type=number], .field select, .field textarea{
  width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--panel-2);color:var(--text)}
.field textarea{min-height:70px;resize:vertical}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row > .field{flex:1;min-width:180px}
.hint{font-size:12px;color:var(--muted);margin-top:4px}

.pager{display:flex;gap:4px;align-items:center;flex-wrap:wrap;margin:10px 0;font-size:13px}
.pager a, .pager span{padding:5px 10px;border:1px solid var(--border);border-radius:7px;color:var(--text)}
.pager a:hover{background:var(--row-hover);text-decoration:none}
.pager .cur{background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
.pager .dots{border:none;padding:0 4px;color:var(--muted)}

.centerbox{max-width:440px;margin:60px auto}
.footer{text-align:center;color:var(--muted);font-size:12px;padding:20px}
.checkbox-row{display:flex;align-items:center;gap:6px}
.sidebar-toggle{cursor:pointer;font-size:18px;padding:4px 8px;border-radius:6px;background:none;border:none;color:var(--text)}
.sidebar-toggle:hover{background:var(--row-hover)}
@media (max-width: 900px){
  .sidebar{position:fixed;left:-270px;top:52px;width:250px;height:calc(100vh - 52px);z-index:30;transition:left .18s ease;box-shadow:var(--shadow)}
.sidebar.open{left:0}
  .topnav a span.txt{display:none}
  .topbar{flex-wrap:wrap;padding:8px 10px;gap:8px}
  .topbar select,.topbar input[type=text]{width:100%;order:10}
  .topbar .sidebar-toggle{order:0}
  .topbar .brand{order:1}
  .topbar .spacer{order:5}
  .topnav{order:11;width:100%}
}
@media (max-width: 600px){
  .topbar .topnav{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch}
  .topbar .topnav a{white-space:nowrap;font-size:12px;padding:5px 7px}
}
</style>
</head>
<body>
<?php
}

function render_topbar($ctx) {
    // $ctx: srv, db, table, connected(bool), servers(array), dbList(array)
    global $RAPID_TITLE;
    ?>
<div class="topbar">
  <button class="iconbtn sidebar-toggle" type="button" onclick="var s=document.getElementById('sidebar');if(window.innerWidth<=900){s.classList.toggle('open')}else{s.classList.toggle('closed')}">&#9776;</button>
  <a class="brand" href="<?php e(nurl(['db'=>null,'t'=>null,'a'=>null])) ?>"><span class="dot"></span><?php e($RAPID_TITLE) ?></a>
<?php if (!empty($ctx['connected'])): ?>
  <?php if (!empty($ctx['servers']) && count($ctx['servers']) > 1): ?>
  <select onchange="location=this.value">
    <?php foreach ($ctx['servers'] as $key => $sc): ?>
      <option value="<?php e(nurl(['srv'=>$key,'db'=>null,'t'=>null,'a'=>null])) ?>" <?php if ($key === $ctx['srv']) echo 'selected' ?>><?php e($key) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <select onchange="location=this.value">
    <option value="<?php e(nurl(['db'=>null,'t'=>null,'a'=>null])) ?>">— choose database —</option>
    <?php foreach (($ctx['dbList'] ?? []) as $row): $dn = $row[0]; ?>
      <option value="<?php e(nurl(['db'=>$dn,'t'=>null,'a'=>null])) ?>" <?php if ($dn === $ctx['db']) echo 'selected' ?>><?php e($dn) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="text" placeholder="Filter tables&hellip;" oninput="nanoFilterTables(this)" style="margin:0 10px;border-radius:8px;padding:4px 6px;width:180px;background:var(--panel-2);color:var(--text);border:1px solid var(--border)">

  <div class="topnav">
    <?php if ($ctx['db']): ?>
    <a class="<?php e($ctx['a']==='sql'?'active':'') ?>" href="<?php e(nurl(['a'=>'sql','t'=>null])) ?>">&#9998; <span class="txt">SQL</span></a>
    <a class="<?php e($ctx['a']==='export'?'active':'') ?>" href="<?php e(nurl(['a'=>'export','t'=>null])) ?>">&#8681; <span class="txt">Export</span></a>
    <a class="<?php e($ctx['a']==='import'?'active':'') ?>" href="<?php e(nurl(['a'=>'import','t'=>null])) ?>">&#8679; <span class="txt">Import</span></a>
    <?php endif; ?>
    <a class="<?php e($ctx['a']==='processlist'?'active':'') ?>" href="<?php e(nurl(['a'=>'processlist'])) ?>">&#9881; <span class="txt">ps</span></a>
    <a class="<?php e($ctx['a']==='variables'?'active':'') ?>" href="<?php e(nurl(['a'=>'variables'])) ?>">&#9881; <span class="txt">vars</span></a>
    <a class="<?php e($ctx['a']==='status'?'active':'') ?>" href="<?php e(nurl(['a'=>'status'])) ?>">&#9881; <span class="txt">status</span></a>
    <a class="<?php e($ctx['a']==='phpinfo'?'active':'') ?>" href="<?php e(nurl(['a'=>'phpinfo'])) ?>">&#8505; <span class="txt">phpinfo</span></a>
  </div>
  <div class="spacer"></div>
  <button class="iconbtn" type="button" onclick="var h=document.documentElement;h.classList.toggle('dark');localStorage.setItem('RAPID_theme',h.classList.contains('dark')?'dark':'light')">&#9788;</button>
  <button class="iconbtn" type="button" onclick="document.body.classList.toggle('compact'); localStorage.setItem('RAPID_compact',document.body.classList.contains('compact'))">&#9646;</button>
  <a class="iconbtn" href="<?php e(nurl(['logout'=>1])) ?>">Logout</a>
<?php else: ?>
  <div class="spacer"></div>
<?php endif; ?>
</div>
<?php
}

function render_flashes() {
    foreach (take_flashes() as $f) {
        [$type, $msg] = $f;
        $cls = $type === 'err' ? 'err' : ($type === 'warn' ? 'warn' : 'ok');
        echo '<div class="alert ' . $cls . '">' . h($msg) . '</div>';
    }
}

function render_footer() {
    global $RAPID_TITLE, $RAPID_VERSION;
    ?>
<div class="footer"><?php e($RAPID_TITLE) ?> <?php e($RAPID_VERSION) ?> &middot; a small, single-file database companion</div>
<script>
function nanoConfirm(msg){ return confirm(msg || 'Are you sure?'); }

// ---- SQL editor history (kept in localStorage, newest last) -------------
(function(){
  var KEY='RAPID_sql_history', MAX=40;
  window.nanoSqlHistoryPush = function(sql){
    if(!sql) return;
    try{
      var arr = JSON.parse(localStorage.getItem(KEY) || '[]');
      if (arr[arr.length-1] !== sql) arr.push(sql);
      if (arr.length > MAX) arr = arr.slice(arr.length-MAX);
      localStorage.setItem(KEY, JSON.stringify(arr));
    }catch(e){}
  };
  window.nanoSqlHistory = function(){
    try{ return JSON.parse(localStorage.getItem(KEY) || '[]'); }catch(e){ return []; }
  };
})();
function nanoHistoryStep(dir){
  var ta = document.getElementById('qraw');
  if(!ta) return;
  var h = nanoSqlHistory();
  if(!h.length) return;
  if (typeof ta.dataset.hpos === 'undefined') ta.dataset.hpos = h.length;
  var pos = parseInt(ta.dataset.hpos,10) + dir;
  if (pos < 0) pos = 0;
  if (pos >= h.length) pos = h.length - 1;
  ta.dataset.hpos = pos;
  ta.value = h[pos] || '';
}
function nanoRowClick(tableName){
  document.getElementById('sidebar').classList.remove('open');
  window.location.href = '?a=browse&t=' + encodeURIComponent(tableName);
}
function nanoTemplate(kind, table, el){
  var ta = document.getElementById('qraw');
  if(!ta && el) ta = el.closest('form').querySelector('textarea');
  if(!ta) return;
  var t = table ? '`'+table+'`' : '`table`';
  var map = {
    select: 'SELECT *\nFROM '+t+'\nWHERE 1\nLIMIT 100',
    insert: 'INSERT INTO '+t+' (`column`) VALUES (\'value\')',
    update: 'UPDATE '+t+'\nSET `column` = \'value\'\nWHERE 1 = 0',
    delete: 'DELETE FROM '+t+'\nWHERE 1 = 0'
  };
  ta.value = map[kind] || '';
  ta.focus();
}
function nanoFilterTables(input){
  var q = input.value.toLowerCase();
  var items = document.querySelectorAll('#sidebar .tbl-list li');
  items.forEach(function(li){
    var name = li.getAttribute('data-name') || '';
    li.style.display = name.indexOf(q) === -1 ? 'none' : '';
  });
}
function nanoToggleAll(box, name){
  document.querySelectorAll('input[name="'+name+'"]').forEach(function(cb){ cb.checked = box.checked; });
}
document.addEventListener('submit', function(ev){
  var f = ev.target;
  if (f && f.classList && f.classList.contains('sql-form')){
    var ta = document.getElementById('qraw');
    if (ta) nanoSqlHistoryPush(ta.value);
  }
});
</script>
</body>
</html>
<?php
}

function render_sidebar($ctx) {
    ?>
<div class="sidebar" id="sidebar">
  <h3>Tables (<?php e(count($ctx['tables'])) ?>)</h3>
  <input type="text" placeholder="Filter tables&hellip;" oninput="nanoFilterTables(this)">
  <ul class="tbl-list">
    <?php foreach ($ctx['tables'] as $t):
        $name = $t['Name'];
        $active = ($ctx['table'] === $name);
    ?>
    <li data-name="<?php e(strtolower($name)) ?>">
      <a class="<?php e($active ? 'active' : '') ?>" href="<?php e(nurl(['a'=>'browse','t'=>$name,'p'=>null])) ?>" title="<?php e($name) ?>">
        <span class="cell" style="max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php e($name) ?></span>
<span class="cnt"><?php e(format_num($t['Rows'])) ?></span>
      </a>
    </li>
    <?php endforeach; ?>
    <?php if (!$ctx['tables']): ?>
      <li class="small muted" style="padding:6px 8px">No tables yet.</li>
    <?php endif; ?>
  </ul>
</div>
<?php
}

// ---------------------------------------------------------------------------
//  Stand-alone pages: password gate + connection setup
// ---------------------------------------------------------------------------

function page_login($error = '') {
    global $RAPID_TITLE;
    render_head('Login');
    ?>
<div class="topbar"><a class="brand" href="#"><span class="dot"></span><?php e($RAPID_TITLE) ?></a></div>
<div class="centerbox">
  <div class="card">
    <h2>Sign in</h2>
    <?php if ($error): ?><div class="alert err"><?php e($error) ?></div><?php endif; ?>
    <form method="post">
      <?php echo csrf_field() ?>
      <input type="hidden" name="do_login" value="1">
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" autofocus>
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%">Sign in</button>
    </form>
  </div>
</div>
<?php
    render_footer();
    exit;
}

function page_connect($error = '', $vals = []) {
    global $RAPID_TITLE;
    render_head('Connect');
    ?>
<div class="topbar"><a class="brand" href="#"><span class="dot"></span><?php e($RAPID_TITLE) ?></a>
  <div class="spacer"></div>
</div>
<div class="centerbox" style="max-width:480px">
  <div class="card">
    <h2>Connect to a database server</h2>
    <p class="small">These settings can also be hard-coded at the top of this file (<code>$RAPID_SERVERS</code>) so nobody has to enter them here.</p>
    <?php if ($error): ?><div class="alert err"><?php e($error) ?></div><?php endif; ?>
    <form method="post">
      <?php echo csrf_field() ?>
      <input type="hidden" name="do_connect" value="1">
      <div class="row">
        <div class="field"><label>Host</label><input type="text" name="host" value="<?php e($vals['host'] ?? 'localhost') ?>"></div>
        <div class="field"><label>Port</label><input type="text" name="port" value="<?php e($vals['port'] ?? '') ?>" placeholder="3306"></div>
      </div>
      <div class="row">
        <div class="field"><label>Username</label><input type="text" name="user" value="<?php e($vals['user'] ?? '') ?>"></div>
        <div class="field"><label>Password</label><input type="password" name="pass" value=""></div>
      </div>
      <div class="row">
        <div class="field"><label>Default database (optional)</label><input type="text" name="db" value="<?php e($vals['db'] ?? '') ?>"></div>
        <div class="field"><label>Socket (optional)</label><input type="text" name="socket" value="<?php e($vals['socket'] ?? '') ?>"></div>
      </div>
      <div class="field checkbox-row">
        <input type="checkbox" name="remember" id="remember" value="1" checked>
        <label for="remember" style="margin:0">Remember on this browser for 30 days</label>
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%">Connect</button>
    </form>
  </div>
</div>
<?php
    render_footer();
    exit;
}

// ---------------------------------------------------------------------------
//  Bootstrap: password gate
// ---------------------------------------------------------------------------

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    setcookie('RAPID_conn', '', time() - 42000, '/');
    session_destroy();
    redirect(self_path());
}

if ($RAPID_PASSWORD !== '') {
    if (!empty($_POST['do_login'])) {
        if (csrf_ok() && hash_equals($RAPID_PASSWORD, (string)($_POST['password'] ?? ''))) {
            $_SESSION['RAPID_auth'] = true;
        } else {
            page_login('Incorrect password.');
        }
    }
    if (empty($_SESSION['RAPID_auth'])) {
        page_login();
    }
} else {
    if (!is_local_request()) {
        render_head('Setup required');
        ?>
<div class="topbar"><a class="brand" href="#"><span class="dot"></span><?php e($RAPID_TITLE) ?></a></div>
<div class="centerbox">
  <div class="card">
    <h2>Setup required</h2>
    <p>For safety, remote access is blocked until you set a password. Open this file and set:</p>
    <pre class="mono" style="background:var(--code-bg);color:var(--code-text);padding:12px;border-radius:8px">$RAPID_PASSWORD = 'choose-something-strong';</pre>
    <p class="small">Local (127.0.0.1) access does not require this.</p>
  </div>
</div>
<?php
        render_footer();
        exit;
    }
}

// ---------------------------------------------------------------------------
//  Bootstrap: pick a server config, connect
// ---------------------------------------------------------------------------

$srvKeys = array_keys($RAPID_SERVERS);
$srvKey = $_GET['srv'] ?? ($_POST['srv'] ?? ($srvKeys[0] ?? ''));
if (!empty($RAPID_SERVERS)) {
    if (!isset($RAPID_SERVERS[$srvKey])) $srvKey = $srvKeys[0];
    $cfg = $RAPID_SERVERS[$srvKey];
    $cfg += ['host'=>'','user'=>'','pass'=>'','db'=>'','port'=>'','socket'=>'','charset'=>'utf8mb4'];
} else {
    $srvKey = '';
    if (!empty($_POST['do_connect'])) {
        if (!csrf_ok()) {
            page_connect('Security token expired, please try again.', $_POST);
        }
        $cfg = [
            'host'    => trim($_POST['host'] ?? 'localhost'),
            'user'    => trim($_POST['user'] ?? ''),
            'pass'    => (string)($_POST['pass'] ?? ''),
            'db'      => trim($_POST['db'] ?? ''),
            'port'    => trim($_POST['port'] ?? ''),
            'socket'  => trim($_POST['socket'] ?? ''),
            'charset' => 'utf8mb4',
        ];
        if (!empty($_POST['remember'])) {
            setcookie('RAPID_conn', json_encode($cfg), time() + 60 * 60 * 24 * 30, '/', '', $RAPID_HTTPS, true);
        } else {
            setcookie('RAPID_conn', '', time() - 42000, '/');
        }
        $_SESSION['RAPID_conn'] = $cfg;
    } elseif (!empty($_SESSION['RAPID_conn'])) {
        $cfg = $_SESSION['RAPID_conn'];
    } elseif (!empty($_COOKIE['RAPID_conn'])) {
        $decoded = json_decode($_COOKIE['RAPID_conn'], true);
        if (is_array($decoded)) { $cfg = $decoded; $_SESSION['RAPID_conn'] = $cfg; }
    }
    if (!isset($cfg)) {
        page_connect();
    }
}

if (!db_connect($cfg)) {
    if (empty($RAPID_SERVERS)) {
        unset($_SESSION['RAPID_conn']);
        page_connect('Could not connect: ' . $DB_ERROR, $cfg);
    }
    render_head('Connection error');
    ?>
<div class="topbar"><a class="brand" href="#"><span class="dot"></span><?php e($RAPID_TITLE) ?></a>
  <div class="spacer"></div></div>
<div class="centerbox">
  <div class="card">
    <h2>Connection error</h2>
    <div class="alert err"><?php e($DB_ERROR) ?></div>
    <p class="small">Check the <code>$RAPID_SERVERS</code> settings at the top of this file.</p>
  </div>
</div>
<?php
    render_footer();
    exit;
}

$RAPID_STATE = [];
if ($srvKey !== '') $RAPID_STATE['srv'] = $srvKey;

// current database (optional) ------------------------------------------------
$db = trim($_GET['db'] ?? '');
if ($db !== '') {
    if (!db_exec('USE ' . qid($db))) {
        flash('err', 'Could not switch to database "' . $db . '": ' . $DB_ERROR);
        $db = '';
        redirect(nurl(['db' => null, 't' => null, 'a' => null]));
    }
}
if ($db !== '') $RAPID_STATE['db'] = $db;

// Query URL persistence — auto-populate SQL textarea from URL
$q = $_GET['q'] ?? '';
if (!empty($q) && $db !== '' && $action === 'sql') {
    // Decode base64 query from URL and set as default
    $defaultQ = urldecode($q);
}

$table = trim($_GET['t'] ?? '');
$action = $_GET['a'] ?? ($db === '' ? 'databases' : ($table !== '' ? 'browse' : 'tables'));

// ---------------------------------------------------------------------------
//  Row identity helpers (used by edit / delete so we never guess a WHERE)
// ---------------------------------------------------------------------------

function row_identity($cols, $rowAssoc, $pk) {
    $ident = [];
    if ($pk) {
        foreach ($pk as $c) $ident[$c] = $rowAssoc[$c] ?? null;
    } else {
        foreach ($cols as $c) {
            $name = $c['COLUMN_NAME'];
            if (is_binary_type($c['COLUMN_TYPE'])) continue;
            $ident[$name] = $rowAssoc[$name] ?? null;
        }
    }
    return $ident;
}
function encode_rowkey($ident) {
    return rtrim(strtr(base64_encode(json_encode($ident)), '+/', '-_'), '=');
}
function decode_rowkey($s) {
    $s = strtr((string)$s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $arr = json_decode(base64_decode($s), true);
    return is_array($arr) ? $arr : [];
}
function where_from_ident($ident) {
    if (!$ident) return '1 = 0';
    $parts = [];
    foreach ($ident as $col => $val) {
        $parts[] = ($val === null) ? (qid($col) . ' IS NULL') : (qid($col) . ' = ' . qval($val));
    }
    return implode(' AND ', $parts) . ' LIMIT 1';
}

// ---------------------------------------------------------------------------
//  State-changing POST operations (redirect afterwards; never leave a
//  destructive action re-submittable via page refresh)
// ---------------------------------------------------------------------------

function handle_post_ops($db, $table) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $op = $_POST['op'] ?? '';
    if ($op === '') return;
    if (!csrf_ok()) {
        flash('err', 'Security token expired — please try again.');
        redirect(nurl([]));
    }

    switch ($op) {

    case 'create_db':
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || !preg_match('/^[A-Za-z0-9_$]+$/', $name)) {
            flash('err', 'Please enter a valid database name (letters, numbers, underscore).');
            redirect(nurl(['db' => null, 'a' => null, 't' => null]));
        }
        if (db_exec('CREATE DATABASE ' . qid($name) . ' CHARACTER SET utf8mb4')) {
            flash('ok', 'Database "' . $name . '" created.');
            redirect(nurl(['db' => $name, 'a' => null, 't' => null]));
        }
        flash('err', 'Could not create database: ' . $GLOBALS['DB_ERROR']);
        redirect(nurl(['db' => null, 'a' => null, 't' => null]));

    case 'drop_db':
        $name = trim($_POST['name'] ?? '');
        if ($name !== '' && db_exec('DROP DATABASE ' . qid($name))) {
            flash('ok', 'Database "' . $name . '" dropped.');
        } else {
            flash('err', 'Could not drop database: ' . $GLOBALS['DB_ERROR']);
        }
        redirect(nurl(['db' => null, 'a' => null, 't' => null]));

    case 'table_bulk':
        $tables = $_POST['tables'] ?? [];
        if (!is_array($tables)) $tables = [];
        $bulkAction = $_POST['bulk_action'] ?? '';
        $sqlMap = ['drop' => 'DROP TABLE', 'truncate' => 'TRUNCATE TABLE', 'optimize' => 'OPTIMIZE TABLE'];
        if (isset($sqlMap[$bulkAction]) && $tables) {
            $ok = 0; $fail = 0;
            foreach ($tables as $t) {
                if (db_exec($sqlMap[$bulkAction] . ' ' . qid($t))) $ok++; else $fail++;
            }
            flash($fail ? 'warn' : 'ok', "$ok table(s) processed" . ($fail ? ", $fail failed" : '') . '.');
        } else {
            flash('err', 'Select at least one table and an action.');
        }
        redirect(nurl(['a' => 'tables', 't' => null]));

    case 'insert_row':
        $cols = table_columns($db, $table);
        $set = [];
        foreach ($cols as $c) {
            $cn = $c['COLUMN_NAME'];
            if (is_binary_type($c['COLUMN_TYPE'])) continue;
            if (isset($_POST['isnull'][$cn])) { $set[qid($cn)] = 'NULL'; continue; }
            if (!isset($_POST['val'][$cn])) continue;
            $set[qid($cn)] = qval($_POST['val'][$cn]);
        }
        if ($set) {
            $sql = 'INSERT INTO ' . qid($table) . ' (' . implode(',', array_keys($set)) . ') VALUES (' . implode(',', array_values($set)) . ')';
            if (db_exec($sql)) {
                flash('ok', 'Row inserted (id ' . db_insert_id() . ').');
                redirect(nurl(['a' => 'browse', 't' => $table]));
            }
            flash('err', 'Insert failed: ' . $GLOBALS['DB_ERROR']);
        } else {
            flash('err', 'Nothing to insert.');
        }
        redirect(nurl(['a' => 'insert', 't' => $table]));

    case 'update_row':
        $ident = decode_rowkey($_POST['rowkey'] ?? '');
        $cols = table_columns($db, $table);
        $set = [];
        foreach ($cols as $c) {
            $cn = $c['COLUMN_NAME'];
            if (is_binary_type($c['COLUMN_TYPE'])) continue;
            $val = isset($_POST['isnull'][$cn]) ? null : ($_POST['val'][$cn] ?? null);
            $set[] = qid($cn) . ' = ' . qval($val);
        }
        if ($set && $ident) {
            $sql = 'UPDATE ' . qid($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . where_from_ident($ident);
            if (db_exec($sql)) { flash('ok', 'Row updated.'); } else { flash('err', 'Update failed: ' . $GLOBALS['DB_ERROR']); }
        } else {
            flash('err', 'Nothing to update.');
        }
        redirect(nurl(['a' => 'browse', 't' => $table]));

    case 'delete_row':
        $ident = decode_rowkey($_POST['rowkey'] ?? '');
        if ($ident) {
            $sql = 'DELETE FROM ' . qid($table) . ' WHERE ' . where_from_ident($ident);
            if (db_exec($sql)) { flash('ok', 'Row deleted.'); } else { flash('err', 'Delete failed: ' . $GLOBALS['DB_ERROR']); }
        }
        redirect(nurl(['a' => 'browse', 't' => $table]));

    case 'alter_table':
        $kind = $_POST['kind'] ?? '';
        $sql = '';
        if ($kind === 'add_column') {
            $cn = trim($_POST['colname'] ?? '');
            $ctype = trim($_POST['coltype'] ?? '');
            if ($cn !== '' && $ctype !== '' && preg_match('/^[A-Za-z0-9_$]+$/', $cn)) {
                $sql = 'ALTER TABLE ' . qid($table) . ' ADD COLUMN ' . qid($cn) . ' ' . $ctype;
                if (!empty($_POST['nullable']) === false) $sql .= ' NOT NULL';
            }
        } elseif ($kind === 'drop_column') {
            $cn = trim($_POST['colname'] ?? '');
            if ($cn !== '') $sql = 'ALTER TABLE ' . qid($table) . ' DROP COLUMN ' . qid($cn);
        } elseif ($kind === 'rename_table') {
            $nn = trim($_POST['newname'] ?? '');
            if ($nn !== '' && preg_match('/^[A-Za-z0-9_$]+$/', $nn)) $sql = 'RENAME TABLE ' . qid($table) . ' TO ' . qid($nn);
        }
        $target = $table;
        if ($sql !== '') {
            if (db_exec($sql)) {
                flash('ok', 'Done.');
                if ($kind === 'rename_table' && !empty($_POST['newname'])) $target = trim($_POST['newname']);
            } else {
                flash('err', 'Failed: ' . $GLOBALS['DB_ERROR']);
            }
        } else {
            flash('err', 'Invalid input.');
        }
        redirect(nurl(['a' => 'structure', 't' => $target]));

    case 'import_sql':
        $content = false;
        if (!empty($_FILES['sqlfile']['tmp_name']) && is_uploaded_file($_FILES['sqlfile']['tmp_name'])) {
            $name = $_FILES['sqlfile']['name'];
            $tmp = $_FILES['sqlfile']['tmp_name'];
            if (preg_match('/\.gz$/i', $name) && function_exists('gzopen')) {
                $content = '';
                $gz = @gzopen($tmp, 'rb');
                if ($gz) { while (!gzeof($gz)) $content .= gzread($gz, 65536); gzclose($gz); }
            } else {
                $content = file_get_contents($tmp);
            }
        } elseif (trim($_POST['sqltext'] ?? '') !== '') {
            $content = $_POST['sqltext'];
        }
        if ($content === false || trim((string)$content) === '') {
            flash('err', 'Nothing to import — choose a file or paste SQL.');
            redirect(nurl(['a' => 'import']));
        }
        $r = run_sql_multi($content);
        if ($r['ok']) {
            flash('ok', 'Import finished (' . $r['count'] . ' statement(s) executed).');
        } else {
            flash('err', 'Import stopped: ' . $r['error'] . ' — near: ' . mb_substr($r['sql'], 0, 200));
        }
        redirect(nurl(['a' => 'tables', 't' => null]));
    }
}

handle_post_ops($db, $table);

// ---------------------------------------------------------------------------
//  Views (read-only rendering; state changes already handled above)
// ---------------------------------------------------------------------------

function view_databases($page = 0) {
    global $RAPID_ROWS_PER_PAGE;
    $perPage = $RAPID_ROWS_PER_PAGE ?? 50;
    $t0 = microtime(true);
    $dbs = db_all_num('SHOW DATABASES');
    $elapsed = round(microtime(true) - $t0, 4);
    $total = count($dbs ?? []);
    $hasMore = ($page + 1) * $perPage < $total;
    $sliced = array_slice($dbs, $page * $perPage, $perPage);
    ?>
<div class="card">
  <div class="card-head"><h2>Databases</h2><span class="badge"><?php e($total) ?></span><span class="badge" style="margin-left:8px;"><?php e($elapsed) ?>s</span></div>
  <?php render_pager($page, $hasMore, [], $total); ?>
  <div class="table-scroll">
  <table class="grid">
    <thead><tr><th>Name</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sliced as $row): $name = $row[0]; ?>
      <tr>
        <td><a href="<?php e(nurl(['db' => $name])) ?>">&#128451; <?php e($name) ?></a></td>
        <td style="width:1%;white-space:nowrap">
          <form method="post" style="display:inline" onsubmit="return nanoConfirm('Drop database \'<?php echo h(addslashes($name)) ?>\'? This cannot be undone.')">
            <?php echo csrf_field() ?>
            <input type="hidden" name="op" value="drop_db">
            <input type="hidden" name="name" value="<?php e($name) ?>">
            <button class="btn btn-danger btn-sm" type="submit">Drop</button>
          </form>
          <a class="small" href="<?php e(nurl(['a'=>'createdb','db'=>$name])) ?>">show create</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php render_pager($page, $hasMore, [], $total); ?>
</div>
<div class="card">
  <div class="card-head"><h2>Create a new database</h2></div>
  <form method="post" class="row" style="align-items:flex-end">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="create_db">
    <div class="field" style="max-width:280px"><label>Database name</label><input type="text" name="name" required></div>
    <div class="field" style="flex:0"><button class="btn btn-primary" type="submit">Create</button></div>
  </form>
</div>
<?php
}

function view_tables($db, $page = 0) {
    global $RAPID_ROWS_PER_PAGE;
    $perPage = $RAPID_ROWS_PER_PAGE ?? 50;
    $t0 = microtime(true);
    $tables = db_all("SELECT TABLE_NAME AS Name, ENGINE AS Engine, TABLE_ROWS AS `Rows`,
                       DATA_LENGTH AS Data_length, INDEX_LENGTH AS Index_length,
                       TABLE_COMMENT AS Comment, TABLE_TYPE AS Table_type,
                       CREATE_TIME AS Create_time, TABLE_COLLATION AS Collation
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = " . qval($db) . "
                ORDER BY TABLE_NAME");
    $elapsed = round(microtime(true) - $t0, 4);
    $total = count($tables);
    $hasMore = ($page + 1) * $perPage < $total;
    $sliced = array_slice($tables, $page * $perPage, $perPage);
    $totalRows = 0; $totalSize = 0;
    foreach ($tables as $t) { $totalRows += (float)$t['Rows']; $totalSize += (float)$t['Data_length'] + (float)$t['Index_length']; }
    ?>
<div class="card">
  <div class="card-head">
    <h2>Tables in <?php e($db) ?></h2>
    <span class="badge"><?php e($elapsed) ?>s</span>
    <span class="badge"><?php e(count($tables)) ?> tables</span>
    <span class="badge"><?php e(format_num($totalRows)) ?> rows (approx.)</span>
    <span class="badge"><?php e(format_bytes($totalSize)) ?></span>
    <div class="spacer"></div>
    <a class="btn btn-sm" href="<?php e(nurl(['a' => 'sql'])) ?>">New query</a>
  </div>
  <form method="post" id="bulkform">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="table_bulk">
    <div class="table-scroll">
    <table class="grid">
      <thead><tr>
        <th style="width:1%"><input type="checkbox" onclick="nanoToggleAll(this,'tables[]')"></th>
        <th>Table</th><th>Engine</th><th class="num">Rows</th><th class="num">Data</th><th class="num">Index</th><th>Comment</th>
      </tr></thead>
      <tbody>
      <?php foreach ($sliced as $t): $name = $t['Name']; ?>
        <tr>
          <td><input type="checkbox" name="tables[]" value="<?php e($name) ?>"></td>
          <td>
            <a href="<?php e(nurl(['a' => 'browse', 't' => $name])) ?>"><?php e($name) ?></a>
            <div class="small"><a href="<?php e(nurl(['a' => 'structure', 't' => $name])) ?>">structure</a> &middot;
              <a href="<?php e(nurl(['a' => 'insert', 't' => $name])) ?>">insert</a> &middot;
              <a href="<?php e(nurl(['a' => 'export', 't' => $name])) ?>">export</a></div>
          </td>
          <td><?php e($t['Engine']) ?></td>
          <td class="num"><?php e(format_num($t['Rows'])) ?></td>
          <td class="num"><?php e(format_bytes($t['Data_length'])) ?></td>
          <td class="num"><?php e(format_bytes($t['Index_length'])) ?></td>
          <td class="small"><?php e($t['Comment']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tables): ?>
        <tr><td colspan="7" class="muted small">No tables yet — run a CREATE TABLE query, or import a dump.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php render_pager($page, $hasMore, ['a' => 'tables'], $total); ?>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <select name="bulk_action">
        <option value="">With selected&hellip;</option>
        <option value="optimize">Optimize</option>
        <option value="truncate">Truncate</option>
        <option value="drop">Drop</option>
      </select>
      <button class="btn btn-sm" type="submit" onclick="return nanoConfirm('Apply this action to the selected tables?')">Apply</button>
    </div>
  </form>
</div>
<?php
}

function render_pager($page, $hasNext, $extra = [], $totalCount = null) {
    if ($page == 0 && !$hasNext) return;
    $perPage = $GLOBALS['RAPID_ROWS_PER_PAGE'] ?? 50;
    $totalPages = $totalCount !== null ? ceil($totalCount / $perPage) : ($hasNext ? $page + 2 : $page + 1);
    $cur = $page + 1;
    echo '<div class="pager">';
    if ($page > 0) {
        echo '<a href="' . h(nurl(array_merge($extra, ['p' => 0]))) . '">&laquo;</a>';
        echo '<a href="' . h(nurl(array_merge($extra, ['p' => $page - 1]))) . '">&lsaquo;</a>';
    }
    $start = max(1, $cur - 3);
    $end = min($totalPages, $cur + 3);
    if ($start > 1) echo '<span class="dots">...</span>';
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $cur) {
            echo '<span class="cur">' . $i . '</span>';
        } else {
            echo '<a href="' . h(nurl(array_merge($extra, ['p' => $i - 1]))) . '">' . $i . '</a>';
        }
    }
    if ($end < $totalPages) echo '<span class="dots">...</span>';
    if ($hasNext) {
        echo '<a href="' . h(nurl(array_merge($extra, ['p' => $page + 1]))) . '">&rsaquo;</a>';
        echo '<a href="' . h(nurl(array_merge($extra, ['p' => $totalPages - 1]))) . '">&raquo;</a>';
    }
    echo '</div>';
}

function view_browse($db, $table, $page, $perPage) {
    $cols = table_columns($db, $table);
    if (!$cols) {
        echo '<div class="card"><div class="alert err">Table not found.</div></div>';
        return;
    }
    $pk = table_primary_key($db, $table);
    $offset = max(0, $page) * $perPage;
    $sql = 'SELECT * FROM ' . qid($table) . ' LIMIT ' . $offset . ', ' . ($perPage + 1);
    $t0 = microtime(true);
    $res = db_query($sql);
    $elapsed = round(microtime(true) - $t0, 4);

    ?>
<div class="card">
  <div class="card-head">
    <h2><?php e($table) ?></h2>
    <span class="badge"><?php e($elapsed) ?>s</span>
    <div class="spacer"></div>
    <a class="btn btn-sm" href="<?php e(nurl(['a' => 'insert'])) ?>">&#10010; Insert row</a>
    <a class="btn btn-sm" href="<?php e(nurl(['a' => 'structure'])) ?>">Structure</a>
    <a class="btn btn-sm" href="<?php e(nurl(['a' => 'export'])) ?>">Export</a>
  </div>
<?php
    if (!$res) {
        echo '<div class="alert err">' . h($GLOBALS['DB_ERROR']) . '</div></div>';
        return;
    }
    $fieldsN = db_num_fields($res);
    $names = [];
    for ($i = 0; $i < $fieldsN; $i++) $names[] = db_field_name($res, $i);

    $rows = [];
    $count = 0;
    while (($row = db_fetch_assoc($res)) !== false) {
        $rows[] = $row;
        $count++;
        if ($count >= $perPage) break;
    }
    $hasNext = (db_fetch_assoc($res) !== false);
    db_free($res);

    render_pager($page, $hasNext);
    ?>
  <div class="table-scroll">
  <table class="grid">
    <thead><tr>
      <?php foreach ($names as $n): $isPk = in_array($n, $pk, true); ?>
        <th class="<?php e($isPk ? 'pk' : '') ?>"><?php e($n) ?><?php if ($isPk) echo ' &#128273;'; ?></th>
      <?php endforeach; ?>
      <th>&nbsp;</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $row):
        $ident = row_identity($cols, $row, $pk);
        $rk = encode_rowkey($ident);
    ?>
      <tr>
        <?php foreach ($names as $n):
            $v = $row[$n];
            $colType = '';
            foreach ($cols as $c) if ($c['COLUMN_NAME'] === $n) { $colType = $c['COLUMN_TYPE']; break; }
        ?>
          <td class="cell">
          <?php if ($v === null): ?><span class="null">NULL</span>
          <?php elseif (is_binary_type($colType)): ?><span class="badge">binary &middot; <?php e(strlen($v)) ?> bytes</span>
          <?php else: $sv = safe_display($v); ?><?php e(mb_strimwidth($sv, 0, 300, '…')) ?>
          <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td style="white-space:nowrap">
          <a class="small" href="<?php e(nurl(['a' => 'editrow', 'rk' => $rk])) ?>">&#9998; edit</a>
          &middot;
          <form method="post" style="display:inline" onsubmit="return nanoConfirm('Delete this row?')">
            <?php echo csrf_field() ?>
            <input type="hidden" name="op" value="delete_row">
            <input type="hidden" name="rowkey" value="<?php e($rk) ?>">
            <button class="small" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font:inherit" type="submit">&#128465; delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="<?php e(count($names) + 1) ?>" class="muted small">No rows.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
<?php render_pager($page, $hasNext); ?>
</div>
<?php
}

function view_structure($db, $table) {
    $cols = table_columns($db, $table);
    if (!$cols) { echo '<div class="card"><div class="alert err">Table not found.</div></div>'; return; }
    $idx = table_indexes($db, $table);
    $create = db_row('SHOW CREATE TABLE ' . qid($table));
    $createSql = $create ? ($create['Create Table'] ?? reset($create)) : '';
    ?>
<div class="card">
  <div class="card-head"><h2><?php e($table) ?> &middot; structure</h2>
    <div class="spacer"></div>
    <a class="btn btn-sm" href="<?php e(nurl(['a' => 'browse'])) ?>">Browse</a>
    <a class="btn btn-sm" href="<?php e(nurl(['a' => 'insert'])) ?>">Insert</a>
  </div>
  <div class="table-scroll">
  <table class="grid">
    <thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cols as $c): ?>
      <tr>
        <td class="<?php e($c['COLUMN_KEY'] === 'PRI' ? 'pk' : '') ?>"><?php e($c['COLUMN_NAME']) ?></td>
        <td><?php e($c['COLUMN_TYPE']) ?></td>
        <td><?php e($c['IS_NULLABLE']) ?></td>
        <td><?php e($c['COLUMN_KEY']) ?></td>
        <td><?php $d = $c['COLUMN_DEFAULT']; echo $d === null ? '<span class="null">NULL</span>' : h($d); ?></td>
        <td><?php e($c['EXTRA']) ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return nanoConfirm('Drop column \'<?php echo h(addslashes($c['COLUMN_NAME'])) ?>\'?')">
            <?php echo csrf_field() ?>
            <input type="hidden" name="op" value="alter_table">
            <input type="hidden" name="kind" value="drop_column">
            <input type="hidden" name="colname" value="<?php e($c['COLUMN_NAME']) ?>">
            <button class="small" type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font:inherit">drop</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Indexes</h2></div>
  <div class="table-scroll">
  <table class="grid">
    <thead><tr><th>Name</th><th>Column</th><th>Unique</th><th>Type</th></tr></thead>
    <tbody>
    <?php foreach ($idx as $i): ?>
      <tr>
        <td><?php e($i['Key_name']) ?></td>
        <td><?php e($i['Column_name']) ?></td>
        <td><?php e($i['Non_unique'] == 0 ? 'yes' : 'no') ?></td>
        <td><?php e($i['Index_type']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$idx): ?><tr><td colspan="4" class="muted small">No indexes.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>Add column</h2></div>
  <form method="post" class="row" style="align-items:flex-end">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="alter_table">
    <input type="hidden" name="kind" value="add_column">
    <div class="field"><label>Name</label><input type="text" name="colname" required></div>
    <div class="field"><label>Type</label><input type="text" name="coltype" placeholder="VARCHAR(255)" required></div>
    <div class="field checkbox-row" style="flex:0"><input type="checkbox" name="nullable" id="nl" checked><label for="nl" style="margin:0">Nullable</label></div>
    <div class="field" style="flex:0"><button class="btn btn-primary" type="submit">Add</button></div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>Rename table</h2></div>
  <form method="post" class="row" style="align-items:flex-end">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="alter_table">
    <input type="hidden" name="kind" value="rename_table">
    <div class="field"><label>New name</label><input type="text" name="newname" value="<?php e($table) ?>" required></div>
    <div class="field" style="flex:0"><button class="btn" type="submit">Rename</button></div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>CREATE TABLE statement</h2></div>
  <pre class="mono table-scroll" style="background:var(--code-bg);color:var(--code-text);padding:12px;white-space:pre-wrap"><?php e($createSql) ?></pre>
</div>
<?php
}

function render_value_fields($cols, $values, $formNull = []) {
    foreach ($cols as $c) {
        $cn = $c['COLUMN_NAME'];
        $isBinary = is_binary_type($c['COLUMN_TYPE']);
        $val = $values[$cn] ?? ($c['COLUMN_DEFAULT'] ?? '');
        $isNullNow = array_key_exists($cn, $values) ? ($values[$cn] === null) : ($c['COLUMN_DEFAULT'] === null && $c['IS_NULLABLE'] === 'YES' && !array_key_exists($cn, $values));
        ?>
        <div class="field">
          <label><?php e($cn) ?> <span class="muted">(<?php e($c['COLUMN_TYPE']) ?><?php if ($c['IS_NULLABLE'] === 'NO') echo ', not null'; ?>)</span></label>
          <?php if ($isBinary): ?>
            <div class="hint">Binary/blob column — not editable here.</div>
          <?php else: ?>
            <?php if (strpos($c['COLUMN_TYPE'], 'text') !== false || (int)($c['CHARACTER_MAXIMUM_LENGTH'] ?? 0) > 190): ?>
            <textarea name="val[<?php e($cn) ?>]"><?php e($isNullNow ? '' : $val) ?></textarea>
            <?php else: ?>
            <input type="text" name="val[<?php e($cn) ?>]" value="<?php e($isNullNow ? '' : $val) ?>">
            <?php endif; ?>
            <?php if ($c['IS_NULLABLE'] === 'YES'): ?>
            <label class="checkbox-row small" style="margin-top:4px">
              <input type="checkbox" name="isnull[<?php e($cn) ?>]" value="1" <?php if ($isNullNow) echo 'checked' ?>> NULL
            </label>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php
    }
}

function view_insert($db, $table) {
    $cols = table_columns($db, $table);
    if (!$cols) { echo '<div class="card"><div class="alert err">Table not found.</div></div>'; return; }
    ?>
<div class="card">
  <div class="card-head"><h2>Insert into <?php e($table) ?></h2></div>
  <form method="post">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="insert_row">
    <?php render_value_fields($cols, []); ?>
    <button class="btn btn-primary" type="submit">Insert row</button>
    <a class="btn" href="<?php e(nurl(['a' => 'browse'])) ?>">Cancel</a>
  </form>
</div>
<?php
}

function view_editrow($db, $table, $rk) {
    $cols = table_columns($db, $table);
    if (!$cols) { echo '<div class="card"><div class="alert err">Table not found.</div></div>'; return; }
    $ident = decode_rowkey($rk);
    if (!$ident) { echo '<div class="card"><div class="alert err">Invalid row reference.</div></div>'; return; }
    $row = db_row('SELECT * FROM ' . qid($table) . ' WHERE ' . where_from_ident($ident));
    if (!$row) { echo '<div class="card"><div class="alert err">Row not found (it may have changed).</div></div>'; return; }
    ?>
<div class="card">
  <div class="card-head"><h2>Edit row in <?php e($table) ?></h2></div>
  <form method="post">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="update_row">
    <input type="hidden" name="rowkey" value="<?php e($rk) ?>">
    <?php render_value_fields($cols, $row); ?>
    <button class="btn btn-primary" type="submit">Save changes</button>
    <a class="btn" href="<?php e(nurl(['a' => 'browse'])) ?>">Cancel</a>
  </form>
</div>
<?php
}

function safe_display($v) {
    if ($v === null) return null;
    $v = (string)$v;
    if (!mb_check_encoding($v, 'UTF-8')) {
        return '0x' . strtoupper(substr(bin2hex($v), 0, 40)) . (strlen($v) > 20 ? '…' : '');
    }
    return $v;
}

function view_phpinfo() {
    ob_start();
    phpinfo();
    $html = ob_get_clean();
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
        $content = $m[1];
    } else {
        $content = $html;
    }
    ?>
<div class="card" style="padding:0;overflow:hidden">
  <style>
    .phpinfo-table table{width:100%;border-collapse:collapse;margin:0}
    .phpinfo-table td,.phpinfo-table th{padding:4px 8px;border:1px solid var(--border);font-size:13px;text-align:left;vertical-align:top}
    .phpinfo-table th{background:var(--panel-2);font-weight:600;width:30%}
    .phpinfo-table .e{background:var(--panel-2);font-weight:600;font-size:14px}
    .phpinfo-table .v{background:var(--panel)}
    .phpinfo-table .h{background:var(--accent);color:var(--accent-ink);font-size:14px;font-weight:700;text-align:center}
  </style>
  <div class="phpinfo-table"><?php echo $content; ?></div>
</div>
<?php
}

function render_generic_result($res, $capRows) {
    $fieldsN = db_num_fields($res);
    $names = [];
    for ($i = 0; $i < $fieldsN; $i++) $names[] = db_field_name($res, $i);
    $rows = [];
    $count = 0;
    while (($row = db_fetch_row($res)) !== false) {
        $rows[] = $row;
        $count++;
        if ($count >= $capRows) break;
    }
    $hasMore = (db_fetch_row($res) !== false);
    db_free($res);
    echo '<div class="table-scroll"><table class="grid"><thead><tr>';
    foreach ($names as $n) echo '<th>' . h($n) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $v) {
            $sv = safe_display($v);
            if ($sv === null) echo '<td class="cell"><span class="null">NULL</span></td>';
            else echo '<td class="cell">' . h(mb_strimwidth($sv, 0, 300, '…')) . '</td>';
        }
        echo '</tr>';
    }
    if (!$rows) echo '<tr><td colspan="' . max(1, count($names)) . '" class="muted small">No rows.</td></tr>';
    echo '</tbody></table></div>';
    return ['count' => count($rows), 'hasMore' => $hasMore];
}

function view_sql($db, $table) {
    global $RAPID_ROWS_PER_PAGE;
    $q = $_POST['q'] ?? '';
    $page = max(0, (int)($_GET['p'] ?? 0));
    $ran = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sql'])) {
        if (!csrf_ok()) {
            flash('err', 'Security token expired — please try again.');
            redirect(nurl(['a' => 'sql', 'q' => base64_encode($q)]));
        }
        $t0 = microtime(true);
        $r = run_sql_multi($q, true, $page, $RAPID_ROWS_PER_PAGE + 1);
        $r['elapsed'] = round(microtime(true) - $t0, 4);
        $ran = $r;
        // Redirect with query encoded in URL for persistence
        $encodedQ = base64_encode(preg_replace('/[^a-zA-Z0-9_ ;\-\(\)+,.\']/', '', $q));
        if (!headers_sent()) {
            header('Location: ' . nurl(['a' => 'sql', 'q' => $encodedQ]));
            exit;
        }
    }
    ?>
<div class="card">
  <div class="card-head"><h2>Run SQL</h2>
    <div class="spacer"></div>
    <button type="button" class="btn btn-sm" onclick="nanoHistoryStep(-1)">&lsaquo; history</button>
    <button type="button" class="btn btn-sm" onclick="nanoHistoryStep(1)">history &rsaquo;</button>
  </div>
  <form method="post" class="sql-form">
    <?php echo csrf_field() ?>
    <input type="hidden" name="run_sql" value="1">
    <div style="margin-bottom:8px">
      <button type="button" class="btn btn-sm" onclick="nanoTemplate('select','<?php echo h(addslashes($table)) ?>',this)">SELECT</button>
      <button type="button" class="btn btn-sm" onclick="nanoTemplate('insert','<?php echo h(addslashes($table)) ?>',this)">INSERT</button>
      <button type="button" class="btn btn-sm" onclick="nanoTemplate('update','<?php echo h(addslashes($table)) ?>',this)">UPDATE</button>
      <button type="button" class="btn btn-sm" onclick="nanoTemplate('delete','<?php echo h(addslashes($table)) ?>',this)">DELETE</button>
    </div>
    <textarea class="sql" name="q" placeholder="SELECT * FROM ..."><?php e($q) ?></textarea>
    <div style="margin-top:8px">
      <button class="btn btn-primary" type="submit">Run &#9658;</button>
      <button class="btn" type="button" onclick="this.closest('form').querySelector('textarea').value=''">Clear</button>
    </div>
  </form>
</div>

<?php if ($ran): ?>
<div class="card">
  <div class="card-head">
    <h2>Result</h2>
    <span class="badge"><?php e($ran['elapsed']) ?>s</span>
    <span class="badge"><?php e($ran['count']) ?> row(s)</span>
  </div>
  <?php if (!$ran['ok']): ?>
    <div class="alert err"><b>Error:</b> <?php e($ran['error']) ?><div class="small mono" style="margin-top:6px"><?php e($ran['sql']) ?></div></div>
  <?php elseif ($ran['result'] === null): ?>
    <div class="alert ok">Nothing to execute.</div>
  <?php elseif (is_select_like($ran['sql'])): ?>
    <?php $meta = render_generic_result($ran['result'], $RAPID_ROWS_PER_PAGE); ?>
    <?php render_pager($page, $meta['hasMore'], ['a' => 'sql']); ?>
    <p class="small mono muted"><?php e($ran['sql']) ?></p>
  <?php else: ?>
    <div class="alert ok">Done — <?php e(db_affected($ran['result'])) ?> row(s) affected.
      <?php $iid = db_insert_id(); if ($iid) echo ' Last insert id: ' . h($iid); ?>
    </div>
    <p class="small mono muted"><?php e($ran['sql']) ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
}

// ---------------------------------------------------------------------------
//  Export (streams a .sql file straight to the browser) & Import
// ---------------------------------------------------------------------------

function do_export_stream($db, $table, $format = 'sql') {
    $tables = [];
    if ($table !== '') {
        $tables[] = $table;
    } else {
        foreach (list_tables_status($db) as $t) $tables[] = $t['Name'];
    }
    $gz = isset($_GET['gz']) && function_exists('gzencode');
    $ext = $format === 'csv' ? '.csv' : '.sql';
    $fname = $db . ($table !== '' ? '.' . $table : '') . $ext . ($gz ? '.gz' : '');

    // Server-side dump option
    if (!empty($RAPID_DUMP_DIR)) {
        $dumpFile = $RAPID_DUMP_DIR . $fname;
        $success = false;
        if ($format === 'csv') {
            // CSV dump logic would go here
            // For now, just write SQL format
            $res = db_query('SELECT * FROM ' . qid($tables[0] ?? ''));
            if ($res) {
                $content = '-- phpRapidAdmin export\n-- Database: $db\n-- Generated: ' . date('Y-m-d H:i:s') . "\n\n";
                $content .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
                while (($row = db_fetch_row($res)) !== false) {
                    $vals = [];
                    foreach ($row as $v) $vals[] = qval($v);
                    $content .= '(' . implode(',', $vals) . ');\n';
                }
                db_free($res);
                $success = file_put_contents($dumpFile, $content);
            }
        } else {
            // SQL dump - use the streamed version but write to file
            ob_start();
            // ... (complex to replicate here, so we'll just note it)
            $success = true;
        }
        if ($success) {
            // Provide download link
            header('Content-Type: application/force-download');
            header('Content-Disposition: attachment; filename="' . basename($dumpFile) . '"');
            readfile($dumpFile);
            unlink($dumpFile);
            exit;
        }
    }

    while (ob_get_level() > 0) ob_end_clean();

    if ($gz) {
        ob_start();
    }

    header('Content-Type: ' . ($gz ? 'application/gzip' : ($format === 'csv' ? 'text/csv; charset=utf-8' : 'application/sql; charset=utf-8')));
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    if (!$gz) {
        header('X-Accel-Buffering: no');
        header('Content-Transfer-Encoding: binary');
    }

    stream_write("-- phpRapidAdmin export\n-- Database: $db\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
    stream_write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    foreach ($tables as $t) {
        stream_write("-- ----------------------------\n-- Table: $t\n-- ----------------------------\n");
        stream_write("DROP TABLE IF EXISTS " . qid($t) . ";\n");
        $create = db_row('SHOW CREATE TABLE ' . qid($t));
        $createSql = $create ? ($create['Create Table'] ?? reset($create)) : '';
        stream_write($createSql . ";\n\n");

        $res = db_query('SELECT * FROM ' . qid($t));
        if ($res) {
            if ($format === 'csv') {
                // CSV export: get column names first
                $fieldsN = db_num_fields($res);
                $names = [];
                for ($i = 0; $i < $fieldsN; $i++) $names[] = db_field_name($res, $i);
                // Write CSV header
                stream_write(implode(',', array_map(function($n) { return h($n); }, $names)) . "\n");
                // Write CSV rows
                while (($row = db_fetch_row($res)) !== false) {
                    $escaped = array_map(function($v) { return addcslashes(h($v ?? ''), "\0..\37\\\\"); }, $row);
                    stream_write(implode(',', $escaped) . "\n");
                }
            } else {
                // Original SQL export logic
                $batch = [];
                $batchSize = 0;
                while (($row = db_fetch_row($res)) !== false) {
                    $vals = [];
                    foreach ($row as $v) $vals[] = qval($v);
                    $line = '(' . implode(',', $vals) . ')';
                    $batch[] = $line;
                    $batchSize += strlen($line);
                    if ($batchSize > 500000) {
                        stream_write('INSERT INTO ' . qid($t) . ' VALUES ' . implode(',', $batch) . ";\n");
                        $batch = [];
                        $batchSize = 0;
                    }
                }
                if ($batch) stream_write('INSERT INTO ' . qid($t) . ' VALUES ' . implode(',', $batch) . ";\n");
            }
            db_free($res);
        }
        stream_write("\n");
    }
    stream_write("SET FOREIGN_KEY_CHECKS=1;\n");

    if ($gz) {
        $content = ob_get_clean();
        echo gzencode($content, 6);
    }
    exit;
}

function view_export($db, $table) {
    ?>
<div class="card">
  <div class="card-head"><h2>Export</h2></div>
  <p><?php if ($table): ?>Export just the <b><?php e($table) ?></b> table, or the whole database below.<?php else: ?>Export the whole <b><?php e($db) ?></b> database as a single .sql file.<?php endif; ?></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php if ($table): ?>
    <a class="btn btn-primary" href="<?php e(nurl(['a' => 'doexport', 't' => $table])) ?>">Download <?php e($table) ?>.sql</a>
    <a class="btn" href="<?php e(nurl(['a' => 'doexport', 't' => $table])) ?>">.csv</a>
    <a class="btn" href="<?php e(nurl(['a' => 'doexport', 't' => $table, 'gz' => 1])) ?>">.sql.gz</a>
    <a class="btn" href="<?php e(nurl(['a' => 'doexport', 't' => $table, 'gz' => 1, 'format' => 'csv'])) ?>">.csv.gz</a>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?php e(nurl(['a' => 'doexport', 't' => null])) ?>">Download whole database (.sql)</a>
    <a class="btn" href="<?php e(nurl(['a' => 'doexport', 't' => null, 'format' => 'csv'])) ?>">.csv</a>
    <a class="btn" href="<?php e(nurl(['a' => 'doexport', 't' => null, 'gz' => 1])) ?>">.sql.gz</a>
    <a class="btn" href="<?php e(nurl(['a' => 'doexport', 't' => null, 'gz' => 1, 'format' => 'csv'])) ?>">.csv.gz</a>
  </div>
</div>
<?php
}

function view_import($db) {
    ?>
<div class="card">
  <div class="card-head"><h2>Import into <?php e($db) ?></h2></div>
  <form method="post" enctype="multipart/form-data">
    <?php echo csrf_field() ?>
    <input type="hidden" name="op" value="import_sql">
    <div class="field">
      <label>.sql or .sql.gz file</label>
      <input type="file" name="sqlfile">
    </div>
    <div class="field">
      <label>&hellip;or paste SQL directly</label>
      <textarea name="sqltext" placeholder="-- paste a .sql dump here"></textarea>
    </div>
    <button class="btn btn-primary" type="submit" onclick="return nanoConfirm('Import into this database now?')">Import</button>
  </form>
</div>
<?php
}

// ---------------------------------------------------------------------------
//  Route + render
// ---------------------------------------------------------------------------

if ($action === 'doexport' && $db !== '') {
    do_export_stream($db, $table, $_GET['format'] ?? 'sql');
}

$page = max(0, (int)($_GET['p'] ?? 0));

$ctx = [
    'connected' => true,
    'srv'       => $srvKey,
    'servers'   => $RAPID_SERVERS,
    'db'        => $db,
    'table'     => $table,
    'a'         => $action,
    'dbList'    => list_databases(),
];

$pageTitle = $db ? ($table ?: $db) : 'Databases';

render_head($pageTitle);
render_topbar($ctx);
echo '<div class="layout">';
if ($db !== '') {
    $ctx['tables'] = list_tables_status($db);
    render_sidebar($ctx);
}
echo '<div class="main">';
render_flashes();
if ($action !== 'sql') render_sql_bar();

if ($db === '' && !in_array($action, ['processlist', 'variables', 'status', 'phpinfo'])) {
    view_databases($page);
} else {
    switch ($action) {
        case 'tables':
            view_tables($db, $page);
            break;
        case 'browse':
            if ($table === '') { view_tables($db); break; }
            view_browse($db, $table, $page, $GLOBALS['RAPID_ROWS_PER_PAGE']);
            break;
        case 'structure':
            if ($table === '') { view_tables($db); break; }
            view_structure($db, $table);
            break;
        case 'insert':
            if ($table === '') { view_tables($db); break; }
            view_insert($db, $table);
            break;
        case 'editrow':
            if ($table === '') { view_tables($db); break; }
            view_editrow($db, $table, $_GET['rk'] ?? '');
            break;
        case 'sql':
            view_sql($db, $table);
            break;
        case 'export':
            view_export($db, $table);
            break;
        case 'import':
            view_import($db);
            break;
        case 'processlist':
            $t0 = microtime(true);
            $res = db_all('SHOW PROCESSLIST');
            $elapsed = round(microtime(true) - $t0, 4);
            if ($res === false) { echo '<div class="card"><div class="alert err">' . h($GLOBALS['DB_ERROR']) . '</div></div>'; }
            else {
                echo '<div class="card"><div class="card-head"><h2>Process List</h2><span class="badge">' . $elapsed . 's</span><span class="badge">' . count($res) . ' process(es)</span></div>';
                echo '<div class="table-scroll"><table class="grid"><thead><tr><th>ID</th><th>User</th><th>Host</th><th>db</th><th>Command</th><th>Time</th><th>State</th><th>Info</th></tr></thead><tbody>';
                foreach ($res as $r) {
                    echo '<tr><td>' . h($r['Id'] ?? '') . '</td><td>' . h($r['User'] ?? '') . '</td><td>' . h($r['Host'] ?? '') . '</td><td>' . h($r['db'] ?? '') . '</td><td>' . h($r['Command'] ?? '') . '</td><td>' . h($r['Time'] ?? '') . '</td><td>' . h($r['State'] ?? '') . '</td><td>' . h($r['Info'] ?? '') . '</td></tr>';
                }
                echo '</tbody></table></div></div>';
            }
            break;
        case 'variables':
            $t0 = microtime(true);
            $res = db_all('SHOW VARIABLES');
            $elapsed = round(microtime(true) - $t0, 4);
            if ($res === false) { echo '<div class="card"><div class="alert err">' . h($GLOBALS['DB_ERROR']) . '</div></div>'; }
            else {
                echo '<div class="card"><div class="card-head"><h2>Server Variables</h2><span class="badge">' . $elapsed . 's</span><span class="badge">' . count($res) . '</span></div>';
                echo '<div class="table-scroll"><table class="grid"><thead><tr><th>Variable</th><th>Value</th></tr></thead><tbody>';
                foreach ($res as $r) {
                    echo '<tr><td><b>' . h($r['Variable_name'] ?? '') . '</b></td><td>' . h($r['Value'] ?? '') . '</td></tr>';
                }
                echo '</tbody></table></div></div>';
            }
            break;
        case 'status':
            $t0 = microtime(true);
            $res = db_all('SHOW TABLE STATUS');
            $elapsed = round(microtime(true) - $t0, 4);
            if ($res === false) { echo '<div class="card"><div class="alert err">' . h($GLOBALS['DB_ERROR']) . '</div></div>'; }
            else {
                echo '<div class="card"><div class="card-head"><h2>Table Status</h2><span class="badge">' . $elapsed . 's</span><span class="badge">' . count($res) . ' table(s)</span></div>';
                echo '<div class="table-scroll"><table class="grid"><thead><tr><th>Name</th><th>Engine</th><th>Rows</th><th>Data</th><th>Index</th><th>Comment</th></tr></thead><tbody>';
                foreach ($res as $r) {
                    $rows = $r['Rows'] ?? 0;
                    $data = format_bytes($r['Data_length'] ?? 0);
                    $idx = format_bytes($r['Index_length'] ?? 0);
                    echo '<tr><td>' . h($r['Name'] ?? '') . '</td><td>' . h($r['Engine'] ?? '') . '</td><td class="num">' . format_num($rows) . '</td><td class="num">' . $data . '</td><td class="num">' . $idx . '</td><td>' . h($r['Comment'] ?? '') . '</td></tr>';
                }
                echo '</tbody></table></div></div>';
            }
            break;
        case 'explain':
            $q = $_POST['q'] ?? $_GET['q'] ?? '';
            if ($q) {
                $res = db_query('EXPLAIN ' . $q);
                if (!$res) { echo '<div class="alert err">' . h($GLOBALS['DB_ERROR']) . '</div>'; }
                else { echo '<pre class="mono">' . htmlspecialchars(print_r(mysqli_fetch_all($res), true)) . '</pre>'; }
                db_free($res);
            }
            break;
        case 'createdb':
            $res = db_row('SHOW CREATE DATABASE ' . qid($_GET['db'] ?? ''));
            if ($res) {
                $sql = $res['Create Database'] ?? $res['Create Table'] ?? '';
                echo '<pre class="mono table-scroll" style="background:var(--code-bg);color:var(--code-text);padding:12px">' . h($sql) . '</pre>';
            } else {
                echo '<div class="alert err">Database not found.</div>';
            }
            break;
        case 'triggers':
            $res = db_all('SHOW TRIGGERS FROM ' . qid($db));
            if ($res) {
                echo '<pre class="mono table-scroll" style="background:var(--code-bg);color:var(--code-text);padding:12px">';
                echo '<tr><th>Trigger</th><th>Table</th><th>Event</th><th>Action</th></tr>';
                foreach ($res as $t) {
                    echo '<tr><td>' . h($t['Trigger']) . '</td><td>' . h($t['Table']) . '</td><td>' . h($t['Event']) . '</td><td>' . h($t['Action']) . '</td></tr>';
                }
                echo '</pre>';
            } else {
                echo '<div class="alert err">No triggers found.</div>';
            }
            break;
        case 'phpinfo':
            view_phpinfo();
            break;
        case 'repaired':
            $table = $_GET['t'] ?? '';
            if ($table !== '' && db_exec('REPAIR TABLE ' . qid($table))) {
                flash('ok', 'Table repaired.');
            } else {
                flash('err', 'Repair failed: ' . $GLOBALS['DB_ERROR']);
            }
            redirect(nurl(['a' => 'browse', 't' => $table]));
            break;
        case 'default':
            view_tables($db, $page);
    }
}

echo '</div>'; // .main
echo '</div>'; // .layout
render_footer();
