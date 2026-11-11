<?php
switch ($action) {
    case 'list':
        json_out(['ok' => true, 'files' => t_rows("SELECT id, path, name, mime, size FROM files WHERE mime LIKE 'image/%' ORDER BY id DESC LIMIT 300")]);

    case 'upload':
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_err('Upload failed');
        if ($f['size'] > 20 * 1024 * 1024) json_err('Files must be 20 MB or smaller');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif', 'video/mp4' => 'mp4', 'video/webm' => 'webm'][$mime] ?? null;
        if (!$ext) json_err('Only JPG, PNG, GIF, WEBP, AVIF images or MP4/WEBM videos are allowed');
        $dir = upload_dir();
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($f['name'], PATHINFO_FILENAME)) ?: 'file';
        $name = substr($base, 0, 60) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) json_err('Could not store file', 500);
        $rel = 'uploads/' . $_SESSION['upload_folder'] . '/' . $name;
        $id = t_exec("INSERT INTO files (path, name, mime, size) VALUES (?,?,?,?)", [$rel, $name, $mime, (int)$f['size']]);
        json_out(['ok' => true, 'file' => ['id' => $id, 'path' => $rel, 'name' => $name, 'mime' => $mime]]);
}
