# Coolify Enhanced

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Build and Publish Docker Image](https://github.com/amirhmoradi/coolify-enhanced/actions/workflows/docker-publish.yml/badge.svg)](https://github.com/amirhmoradi/coolify-enhanced/actions/workflows/docker-publish.yml)
[![Docker Image](https://img.shields.io/badge/ghcr.io-coolify--enhanced-blue)](https://ghcr.io/amirhmoradi/coolify-enhanced)

**Granular permissions and encrypted S3 backups for Coolify v4**

> **Note**: This addon is designed for Coolify v4. Coolify v5 is expected to include similar functionality natively. When v5 is released, we will provide a migration guide.

## Features

### Granular Permissions
- **Project-level permissions**: Grant users access to specific projects only
- **Permission levels**: View Only, Deploy, Full Access
- **Environment overrides**: Fine-tune access per environment within each project
- **Access Matrix UI**: Unified table to manage all users, projects, and environments at a glance
- **API support**: Full REST API for permission management

### Encrypted S3 Backups
- **Encryption at rest**: All database backups encrypted using rclone's crypt backend (NaCl SecretBox: XSalsa20 + Poly1305)
- **Per-storage configuration**: Enable encryption independently on each S3 storage destination
- **Full rclone crypt options**: Main password, salt (password2), filename encryption (off/standard/obfuscate), directory name encryption
- **Transparent operation**: Encrypted on upload, decrypted on download — no manual steps
- **Backward compatible**: Existing unencrypted backups continue to work alongside encrypted ones
- **Database coverage**: PostgreSQL, MySQL, MariaDB, MongoDB backup/restore/cleanup

### Common
- **Install/Uninstall scripts**: Automated setup with Coolify detection
- **Backward compatible**: Feature flag for safe rollout
- **Docker deployed**: Custom image extending official Coolify

## TL;DR - Show me:
<img width="2378" height="1964" alt="coolify-enhanced-screenshot" src="https://github.com/user-attachments/assets/0fd6b8ee-d9a8-4154-b09e-a6dbe2d4e46a" />

## Requirements

- Coolify v4.x
- Docker & Docker Compose
- Root access to the Coolify server

## Installation

### Quick Install (Recommended)

```bash
git clone https://github.com/amirhmoradi/coolify-enhanced.git
cd coolify-enhanced
sudo bash install.sh
```

Running without arguments opens an interactive menu where you can:
1. **Install Coolify** from the official repository (for fresh servers)
2. **Install the Enhanced Addon** (requires Coolify)
3. **Uninstall** the addon
4. **Check Status** of your installation
5. **Full Setup** (Coolify + addon in one step)

For automation or CI, use CLI arguments:

```bash
# Fresh server: install Coolify + addon non-interactively
sudo bash install.sh --install-coolify --install-addon --unattended

# Just install the addon (Coolify already running)
sudo bash install.sh --install-addon

# Check what's installed
sudo bash install.sh --status
```

Run `sudo bash install.sh --help` for all options.

### Manual Install

Create `/data/coolify/source/docker-compose.custom.yml`:

```yaml
services:
  coolify:
    image: ghcr.io/amirhmoradi/coolify-enhanced:latest
    environment:
      - COOLIFY_ENHANCED=true
```

Restart Coolify:

```bash
cd /data/coolify/source
bash upgrade.sh
```

> **Note:** Coolify natively supports `docker-compose.custom.yml` — it is automatically merged with the main compose file and survives upgrades.

### Build Locally

```bash
git clone https://github.com/amirhmoradi/coolify-enhanced.git
cd coolify-enhanced
sudo bash install.sh --local
```

Or build manually:

```bash
docker build \
  --build-arg COOLIFY_VERSION=latest \
  -t coolify-enhanced:latest \
  -f docker/Dockerfile .
```

**Available Image Tags:**
- `latest` - Latest stable release (built against latest Coolify)
- `vX.Y.Z` - Specific release version (e.g., `v1.0.0`)
- `coolify-X.Y.Z` - Built against specific Coolify version (e.g., `coolify-4.0.0-beta.365`)
- `sha-XXXXXX` - Specific commit SHA for traceability

## Uninstallation

```bash
sudo bash uninstall.sh
```

The uninstaller will:
- Optionally clean up database tables (prompted)
- Remove `docker-compose.custom.yml` (with backup)
- Remove environment variables
- Restart Coolify with the original image

For manual uninstall, see [Installation Guide](docs/installation.md#reverting-to-original-coolify).

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `COOLIFY_ENHANCED` | `false` | Enable/disable the enhanced features |

> For backward compatibility, `COOLIFY_GRANULAR_PERMISSIONS=true` is also supported.

### Configuration File

Publish the config file for customization:

```bash
php artisan vendor:publish --tag=coolify-enhanced-config
```

This creates `config/coolify-enhanced.php` with options for:

- Permission levels and their capabilities
- Roles that bypass permission checks
- Permission cascade behavior
- Auto-grant settings for new projects
- Rclone Docker image for backup encryption

## Usage

### Granular Permissions

#### Access Matrix UI

The access management interface is injected into Coolify's existing **Team > Admin** page. Navigate to `/team/admin` as an admin or owner to see the **Granular Access Management** section.

The Access Matrix provides:
- A unified table showing all users, projects, and environments
- Per-cell permission dropdowns (None, View Only, Deploy, Full Access)
- Environment-level inheritance from project permissions with optional overrides
- **All/None** buttons per-row (set all resources for a user) and per-column (set all users for a resource)
- Search/filter for users
- Visual indicators for role bypass (owner/admin), inheritance, and permission levels

#### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/projects/{uuid}/access` | List users with project access |
| POST | `/api/v1/projects/{uuid}/access` | Grant project access |
| PATCH | `/api/v1/projects/{uuid}/access/{user_id}` | Update permissions |
| DELETE | `/api/v1/projects/{uuid}/access/{user_id}` | Revoke access |
| GET | `/api/v1/projects/{uuid}/access/{user_id}/check` | Check permission |

### Encrypted S3 Backups

#### Storage Encryption Settings

The encryption form is injected into each **S3 Storage Detail** page (`/storages/{uuid}`). Configure per-storage:

1. **Enable encryption** — Toggle on/off
2. **Encryption password** — Main encryption key (required)
3. **Salt (password2)** — Optional secondary key for extra security
4. **Filename encryption** — `off` (default), `standard`, or `obfuscate`
5. **Directory name encryption** — Encrypt directory names (requires filename encryption)

> **Warning**: If you lose the encryption password, your backups cannot be recovered.

#### How It Works

- When encryption is **enabled** on an S3 storage, all new database backups to that storage are encrypted using rclone's crypt backend before upload
- When encryption is **disabled** or for existing unencrypted backups, the original MinIO client (`mc`) behavior is preserved
- Each backup execution tracks whether it was encrypted via `is_encrypted` field
- Restore operations detect encryption status and use rclone to decrypt as needed

## Permission Levels

| Level | View | Deploy | Manage | Delete |
|-------|------|--------|--------|--------|
| `view_only` | Yes | No | No | No |
| `deploy` | Yes | Yes | No | No |
| `full_access` | Yes | Yes | Yes | Yes |

## Role Hierarchy

| Role | Access Level |
|------|--------------|
| Owner | Full access to all team resources (bypasses permission checks) |
| Admin | Full access to all team resources (bypasses permission checks) |
| Member | Requires explicit project access |
| Viewer | Requires explicit project access (read-only by default) |

## How It Works

### Permission Check Flow

```
User Action -> Policy Check -> Is Feature Enabled?
                                    |
                    +---------------+---------------+
                    | No                            | Yes
                    v                               v
              Allow (v4 default)           Check User Role
                                                    |
                                    +---------------+---------------+
                                    | Owner/Admin                   | Member/Viewer
                                    v                               v
                              Allow (bypass)              Check Project Access
                                                                    |
                                                    +---------------+---------------+
                                                    | Has Access                    | No Access
                                                    v                               v
                                              Check Permission                   Deny
                                                    |
                                            +-------+-------+
                                            | Has Perm      | No Perm
                                            v               v
                                          Allow           Deny
```

### Backup Encryption Flow

```
Backup Job → S3 Storage has encryption?
                    |
        +-----------+-----------+
        | No                    | Yes
        v                       v
  mc upload (original)    rclone crypt upload
                                |
                          Build env file with:
                          - S3 remote config
                          - Crypt remote config
                          - Obscured passwords
                                |
                          Docker run rclone container
                          with --env-file
                                |
                          Upload encrypted backup
                                |
                          Mark is_encrypted=true
                                |
                          Cleanup env file + container
```

### Database Schema

```
+---------------+       +---------------+       +---------------+
|    users      |       | project_user  |       |   projects    |
+---------------+       +---------------+       +---------------+
| id            |--+    | id            |    +--| id            |
| name          |  |    | user_id       |----+  | name          |
| email         |  +----| project_id    |-------| team_id       |
+---------------+       | permissions   |       +---------------+
                        +---------------+

+-------------------+       +-------------------+
| environment_user  |       |   s3_storages     |
+-------------------+       +-------------------+
| id                |       | ...existing...    |
| user_id           |       | encryption_enabled|
| environment_id    |       | encryption_password|
| permissions       |       | encryption_salt   |
+-------------------+       | filename_encryption|
                            | directory_name_   |
                            |   encryption      |
                            +-------------------+
```

## Upgrading Coolify

When upgrading Coolify to a new version:

1. Update the `COOLIFY_VERSION` build argument
2. Rebuild your custom image
3. Test in a staging environment
4. Deploy to production

```bash
docker build \
  --build-arg COOLIFY_VERSION=v4.0.1 \
  -t coolify-enhanced:v4.0.1 \
  -f docker/Dockerfile \
  .
```

## Reverting to Original Coolify

```bash
sudo bash uninstall.sh
```

Or manually:

```bash
cd /data/coolify/source
rm docker-compose.custom.yml
sed -i '/COOLIFY_ENHANCED/d' .env
bash upgrade.sh
```

**Reverting is non-destructive** - your projects, users, and data remain intact. All team members will have full access to all projects (standard Coolify v4 behavior).

For detailed instructions including database cleanup options, see the [Installation Guide](docs/installation.md#reverting-to-original-coolify).

## Migrating to Coolify v5

When Coolify v5 releases with built-in granular permissions:

1. We will analyze v5's permission model
2. Provide a migration script to transfer your permission data
3. Update this README with migration instructions

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Support

- [GitHub Issues](https://github.com/amirhmoradi/coolify-enhanced/issues)
- [Coolify Discord](https://discord.gg/coolify)

## Acknowledgments

- [Coolify](https://coolify.io) - The amazing self-hostable platform this addon extends
- [rclone](https://rclone.org) - Powerful cloud storage tool providing the encryption backend
- [Dokploy](https://dokploy.com) - Inspiration for the permission model
