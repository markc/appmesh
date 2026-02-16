<?php
/**
 * AppMesh Nextcloud Plugin
 *
 * Provides Nextcloud integration via rclone (WebDAV) and OCS API.
 * Tools: upload, download, list, share, delete.
 */

declare(strict_types=1);

// ============================================================================
// Nextcloud Helper Functions
// ============================================================================

function nc_rclone(string $command): array
{
    return appmesh_exec("rclone {$command}");
}

function nc_ocs_api(string $endpoint, string $method = 'GET', array $data = []): array
{
    $host = appmesh_env('NEXTCLOUD_HOST', 'cloud.goldcoast.org');
    $user = appmesh_env('NEXTCLOUD_USER', 'markc');
    $pass = appmesh_env('NEXTCLOUD_PASS');

    if (!$pass) {
        return ['success' => false, 'output' => 'Error: NEXTCLOUD_PASS not set in .env'];
    }

    $url = "https://{$host}/ocs/v2.php/apps/{$endpoint}";
    $cmd = "curl -s -u " . escapeshellarg("{$user}:{$pass}")
        . " -H 'OCS-APIRequest: true'";

    if ($method === 'POST' && $data) {
        $postFields = http_build_query($data);
        $cmd .= " -d " . escapeshellarg($postFields);
    } elseif ($method === 'DELETE') {
        $cmd .= " -X DELETE";
    }

    $cmd .= " " . escapeshellarg($url);

    return appmesh_exec($cmd);
}

// ============================================================================
// Tool Definitions
// ============================================================================

return [
    // -------------------------------------------------------------------------
    // Upload
    // -------------------------------------------------------------------------
    'appmesh_nextcloud_upload' => new Tool(
        description: 'Upload a local file to Nextcloud via rclone',
        inputSchema: schema(
            [
                'local_path' => prop('string', 'Absolute path to the local file'),
                'remote_folder' => prop('string', 'Nextcloud folder to upload to (e.g., Podcasts, Documents)'),
            ],
            ['local_path', 'remote_folder']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['local_path', 'remote_folder'])) {
                return "Error: {$error}";
            }

            $localPath = $args['local_path'];
            $remoteFolder = $args['remote_folder'];

            if (!file_exists($localPath)) {
                return "Error: File not found: {$localPath}";
            }

            $result = nc_rclone("copy " . escapeshellarg($localPath) . " " . escapeshellarg("nextcloud:{$remoteFolder}/"));

            if ($result['success']) {
                $filename = basename($localPath);
                $size = filesize($localPath);
                $sizeStr = $size > 1048576 ? round($size / 1048576, 1) . 'MB' : round($size / 1024) . 'KB';
                return "Uploaded {$filename} ({$sizeStr}) to nextcloud:{$remoteFolder}/";
            }

            return "Upload failed: {$result['output']}";
        }
    ),

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------
    'appmesh_nextcloud_download' => new Tool(
        description: 'Download a file from Nextcloud to local filesystem via rclone',
        inputSchema: schema(
            [
                'remote_path' => prop('string', 'Nextcloud path to the file (e.g., Podcasts/clip.mp3)'),
                'local_dir' => prop('string', 'Local directory to download to (default: ~/Downloads)'),
            ],
            ['remote_path']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['remote_path'])) {
                return "Error: {$error}";
            }

            $remotePath = $args['remote_path'];
            $localDir = $args['local_dir'] ?? getenv('HOME') . '/Downloads';

            $result = nc_rclone("copy " . escapeshellarg("nextcloud:{$remotePath}") . " " . escapeshellarg($localDir));

            if ($result['success']) {
                $filename = basename($remotePath);
                return "Downloaded {$filename} to {$localDir}/";
            }

            return "Download failed: {$result['output']}";
        }
    ),

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------
    'appmesh_nextcloud_list' => new Tool(
        description: 'List files and folders in a Nextcloud directory',
        inputSchema: schema(
            [
                'path' => prop('string', 'Nextcloud path to list (default: root). E.g., Podcasts, Documents'),
            ]
        ),
        handler: function (array $args): string {
            $path = $args['path'] ?? '';

            $result = nc_rclone("ls " . escapeshellarg("nextcloud:{$path}"));

            if ($result['success']) {
                return $result['output'] ?: "(empty directory)";
            }

            // Try lsd for directories
            $result2 = nc_rclone("lsd " . escapeshellarg("nextcloud:{$path}"));
            if ($result2['success']) {
                return $result2['output'] ?: "(empty directory)";
            }

            return "List failed: {$result['output']}";
        }
    ),

    // -------------------------------------------------------------------------
    // Share (create public link)
    // -------------------------------------------------------------------------
    'appmesh_nextcloud_share' => new Tool(
        description: 'Create a public share link for a file or folder on Nextcloud',
        inputSchema: schema(
            [
                'path' => prop('string', 'Nextcloud path to share (e.g., /Podcasts/clip.mp3)'),
            ],
            ['path']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['path'])) {
                return "Error: {$error}";
            }

            $path = $args['path'];
            // Ensure leading slash
            if (!str_starts_with($path, '/')) {
                $path = '/' . $path;
            }

            $result = nc_ocs_api('files_sharing/api/v1/shares', 'POST', [
                'path' => $path,
                'shareType' => 3,  // public link
            ]);

            if ($result['success']) {
                if (preg_match('/<url>([^<]+)</', $result['output'], $matches)) {
                    return $matches[1];
                }
                if (str_contains($result['output'], 'statuscode>400') || str_contains($result['output'], 'statuscode>404')) {
                    return "Error: File not found at {$path}";
                }
            }

            return "Share creation failed: " . substr($result['output'], 0, 500);
        }
    ),

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------
    'appmesh_nextcloud_delete' => new Tool(
        description: 'Delete a file or folder from Nextcloud',
        inputSchema: schema(
            [
                'path' => prop('string', 'Nextcloud path to delete (e.g., Podcasts/old-clip.mp3)'),
            ],
            ['path']
        ),
        handler: function (array $args): string {
            if ($error = appmesh_validate($args, ['path'])) {
                return "Error: {$error}";
            }

            $path = $args['path'];
            $result = nc_rclone("delete " . escapeshellarg("nextcloud:{$path}"));

            if ($result['success']) {
                return "Deleted: {$path}";
            }

            return "Delete failed: {$result['output']}";
        }
    ),
];
