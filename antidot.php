<?php
$ROOT_PATH = str_replace('\\', '/', dirname(__FILE__));
$SELF = basename(__FILE__);

session_start();

define('FM_PASSWORD', '');

if (FM_PASSWORD !== '') {
    if (isset($_POST['fm_login'])) {
        if ($_POST['fm_password'] === FM_PASSWORD) {
            $_SESSION['fm_auth'] = true;
        } else {
            $auth_error = 'Wrong password!';
        }
    }
    if (isset($_GET['fm_logout'])) {
        session_destroy();
        header('Location: ' . $SELF . '');
        exit;
    }
    if (empty($_SESSION['fm_auth'])) {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - FileManager</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f5f6f8;margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#fff;border:1px solid #e5e7eb;padding:24px;width:320px}
h1{font-size:16px;margin:0 0 16px}
input{width:100%;padding:8px 10px;border:1px solid #d1d5db;margin-bottom:10px;box-sizing:border-box;font-size:14px}
button{width:100%;padding:8px;background:#2563eb;color:#fff;border:none;cursor:pointer;font-size:14px}
.err{color:#dc2626;font-size:13px;margin-bottom:10px}
</style>
</head>
<body>
<div class="box">
    <h1>File Manager</h1>
    <?php if(!empty($auth_error)) echo '<p class="err">'.$auth_error.'</p>'; ?>
    <form method="post">
        <input type="password" name="fm_password" placeholder="Password" autofocus>
        <button type="submit" name="fm_login">Sign In</button>
    </form>
</div>
</body>
</html>
        <?php
        exit;
    }
}

function normalize_path($path) {
    $path = str_replace('\\', '/', $path);
    $parts = explode('/', $path);
    $safe = [];
    $prefix = '';
    foreach ($parts as $i => $p) {
        if ($i === 0 && preg_match('/^[a-zA-Z]:$/', $p)) {
            $prefix = $p;
            continue;
        }
        if ($p === '' || $p === '.') continue;
        if ($p === '..') {
            if (!empty($safe)) array_pop($safe);
            continue;
        }
        $safe[] = $p;
    }
    if ($prefix !== '') {
        return $prefix . '/' . implode('/', $safe);
    }
    return '/' . implode('/', $safe);
}

function format_size($bytes) {
    if ($bytes === false || $bytes === null) return '-';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function perm_to_string($perms) {
    $info = '';
    $info .= (($perms & 0x4000) ? 'd' : '-');
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

function svg_icon($name) {
    $icons = [
        'folder' => '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
        'file'   => '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    ];
    return $icons[$name] ?? $icons['file'];
}

$flash = '';
$flash_type = 'info';

function set_flash($msg, $type = 'info') {
    global $flash, $flash_type;
    $flash = $msg;
    $flash_type = $type;
}

$current_path = isset($_GET['dir']) && $_GET['dir'] !== '' ? normalize_path($_GET['dir']) : $ROOT_PATH;

if (isset($_GET['goto'])) {
    $target = $_GET['goto'];
    if ($target === 'up') {
        $parent = normalize_path($current_path . '/..');
        if ($parent !== $current_path) {
            header('Location: ' . $SELF . '?dir=' . urlencode($parent));
            exit;
        }
        set_flash('Already at the root directory.', 'error');
    } else {
        $new_path = normalize_path($current_path . '/' . $target);
        header('Location: ' . $SELF . '?dir=' . urlencode($new_path));
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $target = $current_path . '/' . $_POST['name'];
    if (file_exists($target)) {
        if (is_dir($target)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            $ok = true;
            foreach ($it as $f) {
                if ($f->isDir()) { if(!rmdir($f->getRealPath())) $ok=false; }
                else { if(!unlink($f->getRealPath())) $ok=false; }
            }
            if (rmdir($target) && $ok) {
                set_flash('Directory deleted.', 'success');
            } else {
                set_flash('Could not delete the directory.', 'error');
            }
        } else {
            if (unlink($target)) set_flash('File deleted.', 'success');
            else set_flash('Could not delete the file.', 'error');
        }
    } else {
        set_flash('File not found.', 'error');
    }
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $names = isset($_POST['sel']) ? $_POST['sel'] : [];
    if (!is_array($names)) $names = [];
    $ok = 0; $fail = 0;
    foreach ($names as $name) {
        $target = $current_path . '/' . $name;
        if (!file_exists($target)) { $fail++; continue; }
        if (is_dir($target)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            $drok = true;
            foreach ($it as $f) {
                if ($f->isDir()) { if(!@rmdir($f->getRealPath())) $drok=false; }
                else { if(!@unlink($f->getRealPath())) $drok=false; }
            }
            if (@rmdir($target) && $drok) $ok++; else $fail++;
        } else {
            if (@unlink($target)) $ok++; else $fail++;
        }
    }
    if ($ok > 0 && $fail === 0) set_flash($ok . ' item(s) deleted.', 'success');
    elseif ($ok > 0 && $fail > 0) set_flash($ok . ' item(s) deleted, ' . $fail . ' failed.', 'info');
    elseif ($ok === 0 && $fail > 0) set_flash($fail . ' item(s) could not be deleted.', 'error');
    else set_flash('Nothing was selected.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'mkdir') {
    $name = trim($_POST['name']);
    if ($name !== '' && preg_match('/^[a-zA-Z0-9_\- .]+$/', $name)) {
        $target = $current_path . '/' . $name;
        if (!file_exists($target)) {
            if (mkdir($target, 0755, true)) set_flash('Folder created.', 'success');
            else set_flash('Could not create the folder.', 'error');
        } else set_flash('An item with the same name already exists.', 'error');
    } else set_flash('Invalid folder name.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'touch') {
    $name = trim($_POST['name']);
    if ($name !== '') {
        $target = $current_path . '/' . $name;
        if (!file_exists($target)) {
            if (touch($target)) set_flash('File created.', 'success');
            else set_flash('Could not create the file.', 'error');
        } else set_flash('File already exists.', 'error');
    } else set_flash('Invalid file name.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'perm') {
    $name = $_POST['name'];
    $mode = $_POST['mode'];
    $target = $current_path . '/' . $name;
    if (file_exists($target)) {
        $oct = octdec(str_pad($mode, 4, '0', STR_PAD_LEFT));
        if (chmod($target, $oct)) set_flash('Permissions changed.', 'success');
        else set_flash('Could not change permissions (server privileges required).', 'error');
    } else set_flash('File not found.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'rename') {
    $old = $_POST['old_name'];
    $new = trim($_POST['new_name']);
    $old_path = $current_path . '/' . $old;
    $new_path = $current_path . '/' . $new;
    if ($new !== '' && file_exists($old_path)) {
        if (rename($old_path, $new_path)) set_flash('Renamed successfully.', 'success');
        else set_flash('Could not rename.', 'error');
    } else set_flash('Invalid name.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (isset($_FILES['file']) && $_FILES['file']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        $count = count($_FILES['file']['name']);
        $ok = 0; $fail = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['file']['error'][$i] === UPLOAD_ERR_OK) {
                $name = $_FILES['file']['name'][$i];
                $tmp = $_FILES['file']['tmp_name'][$i];
                $dest = $current_path . '/' . basename($name);
                if (move_uploaded_file($tmp, $dest)) $ok++;
                else $fail++;
            } else {
                $fail++;
            }
        }
        if ($ok > 0) set_flash($ok . ' file(s) uploaded.' . ($fail>0 ? ' '.$fail.' file(s) failed.' : ''), 'success');
        else set_flash('Upload failed.', 'error');
    } else set_flash('No file selected.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

if (isset($_GET['download'])) {
    $target = $current_path . '/' . $_GET['download'];
    if (is_file($target)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($target) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($target));
        readfile($target);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'save_edit') {
    $name = $_POST['name'];
    $content = $_POST['content'];
    if (!empty($_POST['b64'])) {
        $dec = base64_decode($content);
        if ($dec !== false) $content = $dec;
    }
    $target = $current_path . '/' . $name;
    if (is_file($target)) {
        if (file_put_contents($target, $content) !== false) set_flash('File saved.', 'success');
        else set_flash('Could not save the file.', 'error');
    } else set_flash('File not found.', 'error');
    header('Location: ' . $SELF . '?dir=' . urlencode($current_path));
    exit;
}

$entries = [];
if (is_dir($current_path)) {
    $items = @scandir($current_path);
    if ($items === false) $items = [];
    $base = rtrim($current_path, '/');
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $base . '/' . $item;
        $is_dir = @is_dir($full);
        $perms = @fileperms($full);
        $entries[] = [
            'name' => $item,
            'is_dir' => $is_dir,
            'size' => $is_dir ? null : @filesize($full),
            'perms_str' => $perms ? perm_to_string($perms) : '---------',
            'perms_oct' => $perms ? substr(sprintf('%o', $perms), -4) : '----',
            'mtime' => @filemtime($full),
        ];
    }
    usort($entries, function($a, $b) {
        if ($a['is_dir'] === $b['is_dir']) return strcasecmp($a['name'], $b['name']);
        return $a['is_dir'] ? -1 : 1;
    });
} else {
    set_flash('Directory not found.', 'error');
}

$crumbs = [];
$parts = explode('/', $current_path);
$acc = '';
foreach ($parts as $p) {
    if ($p === '') continue;
    if (preg_match('/^[a-zA-Z]:$/', $p)) {
        $acc = $p;
    } else {
        $acc .= '/' . $p;
    }
    $crumbs[] = ['name' => $p, 'path' => $acc];
}

$edit_file = null;
if (isset($_GET['edit'])) {
    $target = $current_path . '/' . $_GET['edit'];
    if (is_file($target) && is_readable($target)) {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $editable = ['txt','php','html','css','js','json','xml','md','log','ini','conf','py','sql','sh','yml','yaml','csv'];
        if (in_array($ext, $editable) || filesize($target) < 500000) {
            $edit_file = ['name' => $_GET['edit'], 'content' => file_get_contents($target)];
        } else {
            set_flash('This file type cannot be edited. You can download it instead.', 'error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FileManager - <?php echo htmlspecialchars($current_path); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;font-size:14px;color:#1f2937;background:#f5f6f8}
a{color:#1d4ed8;text-decoration:none}
a:hover{text-decoration:underline}
.wrap{max-width:1100px;margin:0 auto;padding:0 16px}
header{background:#fff;border-bottom:1px solid #e5e7eb}
.hbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 0}
.brand{font-weight:700}
.crumbs{font-size:13px;color:#6b7280;flex:1;min-width:200px}
.crumbs b{color:#1f2937}
.count{font-size:12px;color:#6b7280}
main{padding:16px 0 40px}
.msg{padding:10px 14px;border:1px solid #e5e7eb;background:#fff;margin-bottom:14px;font-size:13px}
.msg.success{border-left:4px solid #16a34a}
.msg.error{border-left:4px solid #dc2626}
.msg.info{border-left:4px solid #2563eb}
.toolbar{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.btn{display:inline-block;padding:7px 12px;border:1px solid #d1d5db;background:#fff;color:#1f2937;font-size:13px;cursor:pointer;text-decoration:none;font-family:inherit}
.btn:hover{background:#f3f4f6;text-decoration:none}
.btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}
.btn.primary:hover{background:#1d4ed8}
.btn.danger{color:#dc2626;border-color:#fca5a5}
.btn.danger:hover{background:#fef2f2}
.panel{background:#fff;border:1px solid #e5e7eb;padding:14px;margin-bottom:14px}
.panel h2{font-size:14px;margin-bottom:10px}
label{display:block;font-size:12px;color:#6b7280;margin-bottom:4px}
input[type=text]{width:100%;max-width:360px;padding:8px 10px;border:1px solid #d1d5db;font-size:13px;font-family:inherit}
input[type=file]{font-size:13px;margin-bottom:10px}
textarea{width:100%;min-height:420px;padding:10px;border:1px solid #d1d5db;font-family:monospace;font-size:13px;margin-bottom:10px}
.frow{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}
.bulk{display:flex;gap:12px;align-items:center;padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-bottom:none;font-size:12px;color:#6b7280}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb}
th,td{padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px;vertical-align:top}
th{background:#f9fafb;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:#f9fafb}
td.name-cell{font-weight:500}
.dir{font-weight:600}
.dim{color:#6b7280}
.mono{font-family:monospace;font-size:12px}
.ic{width:14px;height:14px;vertical-align:-2px;color:#9ca3af}
.actions a,.actions button{margin-right:6px;font-size:12px}
.link{background:none;border:none;color:#1d4ed8;cursor:pointer;font-size:12px;padding:0;font-family:inherit;text-decoration:underline}
.link.danger{color:#dc2626}
.empty{padding:30px;text-align:center;color:#9ca3af}
footer{padding:14px 0 30px;font-size:12px;color:#9ca3af;text-align:center}
@media(max-width:700px){th:nth-child(4),td:nth-child(4),th:nth-child(5),td:nth-child(5){display:none}}
</style>
</head>
<body>
<header>
    <div class="wrap hbar">
        <span class="brand">File Manager</span>
        <nav class="crumbs">
            <a href="<?php echo $SELF; ?>?dir=">Root</a>
            <?php foreach($crumbs as $i => $c): ?>
                 <?php if ($i === count($crumbs) - 1): ?> / <b><?php echo htmlspecialchars($c['name']); ?></b>
                 <?php else: ?> / <a href="<?php echo $SELF; ?>?dir=<?php echo urlencode($c['path']); ?>"><?php echo htmlspecialchars($c['name']); ?></a>
                 <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <span class="count"><?php echo count($entries); ?> items</span>
        <?php if(FM_PASSWORD !== ''): ?>
        <a class="btn" href="?fm_logout=1">Sign out</a>
        <?php endif; ?>
    </div>
</header>

<main class="wrap">

    <?php if($flash): ?>
        <div class="msg <?php echo htmlspecialchars($flash_type); ?>"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <?php if($edit_file): ?>
        <div class="panel">
            <h2>Edit: <?php echo htmlspecialchars($edit_file['name']); ?></h2>
            <form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>" onsubmit="return encEdit(this)">
                <input type="hidden" name="action" value="save_edit">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($edit_file['name']); ?>">
                <textarea name="content" spellcheck="false"><?php echo htmlspecialchars($edit_file['content']); ?></textarea>
                <div class="frow">
                    <button type="submit" class="btn primary">Save</button>
                    <a class="btn" href="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>">Cancel</a>
                </div>
            </form>
        </div>
    <?php else: ?>

    <div class="toolbar">
        <a class="btn" href="<?php echo $SELF; ?>?goto=up&dir=<?php echo urlencode($current_path); ?>">&uarr; Up</a>
        <button type="button" class="btn" onclick="togglePanel('p-mkdir')">New Folder</button>
        <button type="button" class="btn" onclick="togglePanel('p-file')">New File</button>
        <button type="button" class="btn" onclick="togglePanel('p-upload')">Upload</button>
    </div>

    <div class="panel" id="p-mkdir" style="display:none">
        <form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>">
            <input type="hidden" name="action" value="mkdir">
            <label>Folder name</label>
            <input type="text" name="name" placeholder="new-folder" required pattern="[a-zA-Z0-9_\- .]+">
            <div class="frow"><button type="submit" class="btn primary">Create</button></div>
        </form>
    </div>

    <div class="panel" id="p-file" style="display:none">
        <form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>">
            <input type="hidden" name="action" value="touch">
            <label>File name</label>
            <input type="text" name="name" placeholder="new-file.txt" required>
            <div class="frow"><button type="submit" class="btn primary">Create</button></div>
        </form>
    </div>

    <div class="panel" id="p-upload" style="display:none">
        <form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <label>File(s)</label>
            <input type="file" name="file[]" multiple>
            <div class="frow"><button type="submit" class="btn primary">Upload</button></div>
        </form>
    </div>

    <form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>" onsubmit="return confirm('Delete selected items?')">
    <input type="hidden" name="action" value="bulk_delete">
    <div class="bulk">
        <label><input type="checkbox" onclick="selAll(this)"> Select all</label>
        <button type="submit" class="btn danger">Delete selected</button>
    </div>
    <table>
        <thead>
            <tr><th></th><th>Name</th><th>Size</th><th>Permissions</th><th>Modified</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php $parent_path = normalize_path($current_path . '/..');
        if ($parent_path !== $current_path): ?>
            <tr>
                <td></td>
                <td colspan="5"><a href="<?php echo $SELF; ?>?dir=<?php echo urlencode($parent_path); ?>">[..] Parent directory</a></td>
            </tr>
        <?php endif; ?>
        <?php
        $editable_exts = ['txt','php','html','css','js','json','xml','md','log','ini','conf','py','sql','sh','yml','yaml','csv'];
        foreach($entries as $e):
            $ext = strtolower(pathinfo($e['name'],PATHINFO_EXTENSION));
            $editable = !$e['is_dir'] && in_array($ext, $editable_exts);
        ?>
            <tr>
                <td><input type="checkbox" class="ck" name="sel[]" value="<?php echo htmlspecialchars($e['name'],ENT_QUOTES); ?>"></td>
                <td class="name-cell">
                    <?php echo svg_icon($e['is_dir']?'folder':'file'); ?>
                    <?php if($e['is_dir']): ?>
                        <a class="dir" href="<?php echo $SELF; ?>?goto=<?php echo urlencode($e['name']); ?>&dir=<?php echo urlencode($current_path); ?>"><?php echo htmlspecialchars($e['name']); ?></a>
                    <?php else: ?>
                        <a href="<?php echo $SELF; ?>?download=<?php echo urlencode($e['name']); ?>&dir=<?php echo urlencode($current_path); ?>"><?php echo htmlspecialchars($e['name']); ?></a>
                    <?php endif; ?>
                </td>
                <td class="dim"><?php echo $e['is_dir'] ? '-' : format_size($e['size']); ?></td>
                <td class="mono dim"><?php echo $e['perms_str']; ?> (<?php echo $e['perms_oct']; ?>)</td>
                <td class="dim"><?php echo $e['mtime'] ? date('Y-m-d H:i', $e['mtime']) : '-'; ?></td>
                <td class="actions">
                    <?php if($editable): ?>
                        <a href="<?php echo $SELF; ?>?edit=<?php echo urlencode($e['name']); ?>&dir=<?php echo urlencode($current_path); ?>">Edit</a>
                    <?php endif; ?>
                    <?php if(!$e['is_dir']): ?>
                        <a href="<?php echo $SELF; ?>?download=<?php echo urlencode($e['name']); ?>&dir=<?php echo urlencode($current_path); ?>">Download</a>
                    <?php endif; ?>
                    <button type="button" class="link" onclick="renOne('<?php echo htmlspecialchars($e['name'],ENT_QUOTES); ?>')">Rename</button>
                    <button type="button" class="link" onclick="chmOne('<?php echo htmlspecialchars($e['name'],ENT_QUOTES); ?>','<?php echo $e['perms_oct']; ?>')">Chmod</button>
                    <button type="button" class="link danger" onclick="delOne('<?php echo htmlspecialchars($e['name'],ENT_QUOTES); ?>')">Delete</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if(count($entries)===0): ?>
            <tr><td colspan="6" class="empty">This folder is empty</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </form>

    <?php endif; ?>

    <footer><?php echo htmlspecialchars($current_path); ?></footer>
</main>

<form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>" id="chm-form">
    <input type="hidden" name="action" value="perm">
    <input type="hidden" name="name" id="chm-name">
    <input type="hidden" name="mode" id="chm-mode">
</form>
<form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>" id="ren-form">
    <input type="hidden" name="action" value="rename">
    <input type="hidden" name="old_name" id="ren-old">
    <input type="hidden" name="new_name" id="ren-new">
</form>
<form method="post" action="<?php echo $SELF; ?>?dir=<?php echo urlencode($current_path); ?>" id="del-form">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="name" id="del-name">
</form>

<script>
function togglePanel(id){var e=document.getElementById(id);e.style.display=e.style.display==='block'?'none':'block';}
function selAll(c){document.querySelectorAll('.ck').forEach(function(x){x.checked=c.checked;});}
function delOne(n){if(confirm('Delete "'+n+'"?')){document.getElementById('del-name').value=n;document.getElementById('del-form').submit();}}
function renOne(n){var v=prompt('New name:',n);if(v&&v!==n){document.getElementById('ren-old').value=n;document.getElementById('ren-new').value=v;document.getElementById('ren-form').submit();}}
function chmOne(n,cur){var v=prompt('New permissions (octal):',cur);if(v!==null&&/^[0-7]{3,4}$/.test(v)){document.getElementById('chm-name').value=n;document.getElementById('chm-mode').value=v;document.getElementById('chm-form').submit();}}
function encEdit(f){var i=document.createElement('input');i.type='hidden';i.name='b64';i.value='1';f.appendChild(i);f.elements.content.value=btoa(unescape(encodeURIComponent(f.elements.content.value)));return true;}
</script>
</body>
</html>
