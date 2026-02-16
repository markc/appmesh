# Nextcloud Cloud Storage

<skill>
name: nextcloud
description: Upload, download, list, share, and delete files on Nextcloud via rclone and OCS API
user-invocable: true
arguments: <action> [path] [options]
</skill>

## Actions

- `upload <local-file> <remote-folder>` - Upload a file to Nextcloud
- `download <remote-path> [local-dir]` - Download a file from Nextcloud
- `list [path]` - List files in a Nextcloud directory
- `share <path>` - Create a public share link
- `delete <path>` - Delete a file from Nextcloud

## Examples

```bash
/nextcloud upload ~/Downloads/clip.mp3 Podcasts
/nextcloud download Podcasts/clip.mp3
/nextcloud list Podcasts
/nextcloud share /Podcasts/clip.mp3
/nextcloud delete Podcasts/old-clip.mp3
```

## Instructions

When the user invokes this skill:

1. **For `upload`**: Use `appmesh_nextcloud_upload` with local_path and remote_folder
2. **For `download`**: Use `appmesh_nextcloud_download` with remote_path and optional local_dir
3. **For `list`**: Use `appmesh_nextcloud_list` with optional path (omit for root)
4. **For `share`**: Use `appmesh_nextcloud_share` with path (include leading slash)
5. **For `delete`**: Use `appmesh_nextcloud_delete` with path

## Common Folders

| Folder | Purpose |
|--------|---------|
| `Podcasts` | Audio clips and podcast extracts |
| `Documents` | General documents |
| `Photos` | Images and screenshots |
| `Music` | Music files |
| `Videos` | Video files |
| `Public` | Publicly shared files |

## Configuration

Credentials are set in `server/.env`:
- `NEXTCLOUD_HOST` - Hostname (default: cloud.goldcoast.org)
- `NEXTCLOUD_USER` - Username (default: markc)
- `NEXTCLOUD_PASS` - App password (required for share links)

rclone remote `nextcloud:` must be configured (`~/.config/rclone/rclone.conf`).
