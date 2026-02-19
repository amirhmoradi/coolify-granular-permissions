# Network Management System — Architecture Plan

## Executive Summary

This plan adds a **Docker network management system** to coolify-enhanced that provides per-environment network isolation, a dedicated proxy network, server-level network management UI, and per-resource network assignment — all integrated with the existing granular permissions system.

The design is **phased** to minimize overlay complexity and maximize maintainability. Phase 1 delivers core isolation using Docker-level network manipulation (post-deployment hooks + reconciliation), with **zero new overlay files**. Later phases add proxy isolation and compose-level integration only when demand justifies the maintenance cost.

---

## Research Summary

### Current Coolify Networking Problems

1. **Flat network architecture**: All resources on a server share a single `coolify` bridge network (or `coolify-overlay` for Swarm). Any container can communicate with any other container — no isolation between projects, environments, or teams.

2. **Security risk**: A compromised container can access databases, caches, and internal APIs of all other resources. No network segmentation (frontend/backend/data tiers).

3. **Limited "Connect to Predefined Network" toggle**: The only network control is a boolean that adds compose-based apps to the destination network. It's all-or-nothing.

4. **No network management UI**: Users cannot create, inspect, or delete Docker networks. The only UI is a read-only "Destination" page showing network name and server IP.

5. **Proxy connects to everything**: Traefik/Caddy auto-connects to ALL Coolify-managed networks, meaning the proxy has network-level access to every container.

### Industry Comparison

| Platform | Default Isolation | Custom Networks | Network UI | RBAC for Networks |
|----------|------------------|-----------------|------------|-------------------|
| **Coolify** | None (flat) | No | Minimal | No |
| **Portainer EE** | None (Docker default) | Full CRUD | Comprehensive | Yes (per-network ACL) |
| **Dokploy** | None (flat `dokploy-network`) | No UI, manual only | No | No |
| **CapRover** | None (flat `captain-overlay-network`) | Manual override only | No | No |

### Key Technical Findings

- Docker bridge networks support up to ~200 per host before iptables performance degrades
- `docker network connect/disconnect` works on running containers without restart
- Overlay networks (Swarm) can only be created from manager nodes
- Traefik requires `traefik.docker.network` label when containers are on multiple networks
- Docker's `default-address-pools` (Coolify default: `10.0.0.0/8` with `/24` subnets) provides ~65K network capacity

---

## Architecture Decisions

### Decision 1: Post-Deployment Hook Approach (Not Overlay-Based)

**Problem**: Overlaying `ApplicationDeploymentJob.php` (4130 lines, 16+ network references), `parsers.php` (2484 lines), and 8 Database Start actions would add ~9,665 lines of overlay code. This creates an unmaintainable fork that silently breaks on every Coolify update.

**Decision**: Use Docker-level network manipulation via post-deployment hooks and periodic reconciliation instead of modifying compose generation.

**How it works**:
1. Coolify deploys resources normally using its own network logic
2. After deployment completes, our event listener connects the container to the managed environment network
3. Optionally disconnects the container from the default `coolify` network (for isolation)
4. A periodic reconciler fixes any drift every minute

**Trade-offs**:
- (+) Zero new overlay files for core feature
- (+) Survives Coolify updates without breakage
- (+) Simple, debuggable — `docker network connect/disconnect` is well-understood
- (-) Brief window between deployment and network reassignment (~1-3 seconds)
- (-) Build-time containers still use the destination network (not the environment network)
- (-) Swarm services need compose-level integration (Phase 3)

### Decision 2: Per-Environment Default Isolation

**Problem**: Per-project isolation is simpler but allows staging to accidentally interact with production. Per-environment provides the right security boundary.

**Decision**: Each environment gets its own Docker network. Resources within an environment can communicate by container name. Cross-environment communication requires explicit shared networks.

**Network naming**: `ce-env-{environment_uuid}` prefix to avoid collision with Coolify's UUID-based names.

### Decision 3: Dedicated Proxy Network (Opt-In)

**Problem**: Connecting the proxy to all networks means every container is reachable from the proxy, even internal-only services.

**Decision**: Create a `ce-proxy-{server_uuid}` network. Only resources with FQDNs join it. The proxy connects to this network only.

**Important**: This is **opt-in**, not default. Existing installations keep current behavior until explicitly migrated. This prevents breaking existing setups where internal services depend on proxy reachability.

### Decision 4: Shared Networks for Cross-Environment Communication

**Problem**: Per-environment isolation blocks legitimate cross-environment communication (e.g., shared database in `infra` environment accessed by `app` environment).

**Decision**: Support "shared networks" as a first-class concept. Users create named shared networks that resources from any environment can explicitly join.

### Decision 5: Standalone Docker First, Swarm Deferred

**Problem**: Swarm overlay networks require compose-level integration (you can't `docker network connect` a Swarm task — networking is controlled by the service spec). This means overlaying deployment code, which we want to avoid.

**Decision**: Phase 1 targets standalone Docker (bridge networks) only. Swarm support is Phase 3, built only when user demand justifies the overlay cost.

---

## Phased Implementation Plan

### Phase 1: Core Network Isolation (No Overlays)

**Scope**: Environment networks, shared networks, server-level management UI, resource-level network assignment, permission integration.

#### 1.1 Data Model

**New `managed_networks` table:**
```sql
CREATE TABLE managed_networks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,                     -- Human-readable name
    docker_network_name VARCHAR(255) NOT NULL,      -- Actual Docker network name (ce-env-xxx)
    server_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NOT NULL,
    driver VARCHAR(50) DEFAULT 'bridge',            -- bridge, overlay, macvlan
    scope ENUM('environment', 'project', 'shared', 'proxy', 'system') NOT NULL,
    project_id BIGINT UNSIGNED NULL,                -- Set for project-scoped networks
    environment_id BIGINT UNSIGNED NULL,            -- Set for environment-scoped networks
    subnet VARCHAR(50) NULL,                        -- e.g., '172.20.0.0/24'
    gateway VARCHAR(50) NULL,
    is_internal BOOLEAN DEFAULT FALSE,              -- Docker --internal flag
    is_attachable BOOLEAN DEFAULT TRUE,             -- Docker --attachable flag
    is_proxy_network BOOLEAN DEFAULT FALSE,         -- Proxy should connect to this
    options JSON NULL,                              -- Driver-specific options
    labels JSON NULL,                               -- Docker labels
    docker_id VARCHAR(255) NULL,                    -- Docker network ID (sha256)
    status ENUM('active', 'pending', 'error', 'orphaned') DEFAULT 'pending',
    error_message TEXT NULL,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY unique_docker_name_server (docker_network_name, server_id),
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    INDEX idx_environment (environment_id),
    INDEX idx_project (project_id),
    INDEX idx_scope (scope)
);
```

**New `resource_networks` pivot table:**
```sql
CREATE TABLE resource_networks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_type VARCHAR(255) NOT NULL,            -- App\Models\Application, Service, etc.
    resource_id BIGINT UNSIGNED NOT NULL,
    managed_network_id BIGINT UNSIGNED NOT NULL,
    aliases JSON NULL,                              -- DNS aliases for this resource on this network
    ipv4_address VARCHAR(50) NULL,                  -- Static IP assignment (optional)
    is_auto_attached BOOLEAN DEFAULT FALSE,         -- Auto-attached vs manually assigned
    is_connected BOOLEAN DEFAULT FALSE,             -- Actual Docker connection state
    connected_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY unique_resource_network (resource_type, resource_id, managed_network_id),
    FOREIGN KEY (managed_network_id) REFERENCES managed_networks(id) ON DELETE CASCADE,
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_network (managed_network_id)
);
```

#### 1.2 Models

**`ManagedNetwork` model** (`src/Models/ManagedNetwork.php`):
- Relationships: `belongsTo(Server)`, `belongsTo(Team)`, `belongsTo(Project)`, `belongsTo(Environment)`
- Polymorphic: `resources()` via `resource_networks` pivot
- Scopes: `forServer()`, `forEnvironment()`, `forProject()`, `shared()`, `proxy()`, `active()`
- Accessors: `connectedContainers()`, `isDockerSynced()`

**`ResourceNetwork` pivot model** (`src/Models/ResourceNetwork.php`):
- Polymorphic: `resource()` morphTo
- Relationship: `belongsTo(ManagedNetwork)`

#### 1.3 Network Service

**`NetworkService.php`** (`src/Services/NetworkService.php`):

```php
class NetworkService
{
    // Docker operations (via instant_remote_process)
    public static function createDockerNetwork(Server $server, ManagedNetwork $network): bool;
    public static function deleteDockerNetwork(Server $server, ManagedNetwork $network): bool;
    public static function connectContainer(Server $server, string $networkName, string $containerName, ?array $aliases = null): bool;
    public static function disconnectContainer(Server $server, string $networkName, string $containerName, bool $force = false): bool;
    public static function inspectNetwork(Server $server, string $networkName): ?array;
    public static function listDockerNetworks(Server $server): Collection;

    // High-level operations
    public static function ensureEnvironmentNetwork(Environment $environment, Server $server): ManagedNetwork;
    public static function ensureProxyNetwork(Server $server): ManagedNetwork;
    public static function ensureSharedNetwork(string $name, Server $server, Team $team): ManagedNetwork;
    public static function getResourceNetworks($resource): Collection;
    public static function getAvailableNetworks($resource, User $user): Collection;

    // Reconciliation
    public static function reconcileResource($resource): void;  // Ensure Docker state matches DB
    public static function reconcileServer(Server $server): void;  // Full server sync
    public static function syncFromDocker(Server $server): void;  // Discover Docker networks → DB

    // Auto-provisioning
    public static function autoAttachResource($resource): void;  // Attach env network + proxy if FQDN
    public static function autoDetachResource($resource): void;  // Remove on resource deletion
}
```

**Docker command patterns:**
```bash
# Create network (idempotent)
docker network create --driver bridge --attachable ce-env-{uuid} 2>/dev/null || true

# Connect container (idempotent)
docker network connect --alias {container-name} ce-env-{uuid} {container-name} 2>/dev/null || true

# Disconnect container
docker network disconnect ce-env-{uuid} {container-name} 2>/dev/null || true

# Inspect network (JSON output)
docker network inspect ce-env-{uuid} --format '{{json .}}'

# List all networks
docker network ls --format '{{json .}}'
```

#### 1.4 Event-Driven Network Assignment

**Listen for Coolify's deployment completion events:**

```php
// In CoolifyEnhancedServiceProvider::boot()
$this->app->booted(function () {
    // ... existing registrations ...

    if (config('coolify-enhanced.network_management.enabled')) {
        $this->registerNetworkManagement();
    }
});

protected function registerNetworkManagement()
{
    // Listen for application status changes (deployment complete)
    Event::listen('App\Events\ApplicationStatusChanged', function ($event) {
        NetworkReconcileJob::dispatch($event->resource)->delay(now()->addSeconds(3));
    });

    Event::listen('App\Events\ServiceStatusChanged', function ($event) {
        NetworkReconcileJob::dispatch($event->resource)->delay(now()->addSeconds(3));
    });

    // Register periodic reconciliation (every minute)
    $this->app['events']->listen('Illuminate\Console\Events\ScheduledTaskStarting', ...);
}
```

**`NetworkReconcileJob`** (`src/Jobs/NetworkReconcileJob.php`):
1. Get the resource's server
2. Ensure the environment network exists on the server
3. Get all containers for the resource
4. For each container:
   a. Connect to the environment network (with container name as alias)
   b. If resource has FQDN and proxy isolation is enabled: connect to proxy network
   c. If isolation mode is "strict": disconnect from the default `coolify` network
5. Update `resource_networks.is_connected` status
6. Log all operations

**Periodic reconciler** (registered in scheduler):
- Runs every minute
- For each server with network management enabled:
  - Compare `resource_networks` desired state with actual Docker state
  - Fix any drift (reconnect disconnected containers, disconnect unwanted connections)
  - Update `managed_networks.last_synced_at`

#### 1.5 Auto-Provisioning

When a resource is deployed:
1. **Environment network**: Auto-create `ce-env-{env_uuid}` on the resource's server if not exists. Auto-attach the resource.
2. **Proxy network**: If the resource has an FQDN and proxy isolation is enabled, auto-create `ce-proxy-{server_uuid}` and auto-attach.
3. **Shared networks**: If the resource is explicitly assigned to shared networks, connect to those too.

When an environment is deleted:
1. Disconnect all containers from the environment network
2. Remove the Docker network
3. Delete the `managed_networks` record

#### 1.6 UI Components

**Server-level: "Networks" page** — new sidebar item on server pages

`src/Livewire/NetworkManager.php` + `resources/views/livewire/network-manager.blade.php`:
- Tab 1: **Managed Networks** — list of all managed networks on the server
  - Name, type (environment/shared/proxy), scope, driver, subnet, connected resources count
  - Create shared network button (name, internal flag)
  - Delete network action (with confirmation)
  - Click to expand: show connected containers with IPs, aliases
- Tab 2: **Docker Networks** — raw list of all Docker networks (synced from Docker)
  - Shows all networks including unmanaged ones
  - "Import" action to bring unmanaged network into management
  - Sync button to refresh from Docker

**Resource-level: "Networks" section** — on resource configuration pages

`src/Livewire/ResourceNetworks.php` + `resources/views/livewire/resource-networks.blade.php`:
- Shows current network memberships:
  - Environment network (auto, cannot remove)
  - Proxy network (auto if FQDN, can toggle)
  - Shared networks (manually assigned, can add/remove)
- Multi-select dropdown: "Add to shared network"
- Live connect/disconnect for running containers
- Shows connection status (connected/disconnected) and IP addresses

**Settings-level: "Network Policies"** — on Settings page

`src/Livewire/NetworkSettings.php` + `resources/views/livewire/network-settings.blade.php`:
- Enable/disable network management
- Isolation mode: `none` (current behavior) / `environment` (default) / `strict` (also disconnects from coolify network)
- Proxy isolation: toggle (opt-in)
- Network limit per server (default: 200)
- Auto-provisioning toggle

#### 1.7 View Overlays

Minimal overlays for UI integration (same pattern as existing overlays):

1. **Server sidebar** (`components/server/sidebar.blade.php`) — already overlaid for resource backups. Add "Networks" sidebar item.
2. **Resource configuration pages** (application/database/service `configuration.blade.php`) — already overlaid. Add "Networks" sidebar item + `@elseif` content branch.
3. **Settings navbar** (`components/settings/navbar.blade.php`) — already overlaid. Add "Networks" tab.

These are view-only overlays (Blade templates), NOT code overlays. They carry minimal upstream sync risk.

#### 1.8 Permission Integration

Network operations respect the existing permission system:

| Action | Required Permission |
|--------|-------------------|
| View network list | `view` on project/environment |
| View network details | `view` on project/environment |
| Connect resource to shared network | `manage` on resource's environment |
| Disconnect resource from shared network | `manage` on resource's environment |
| Create shared network | Admin/Owner only |
| Delete shared network | Admin/Owner only |
| Server-level network page | Admin/Owner only |
| Network policies settings | Admin/Owner only |

**`NetworkPolicy.php`** registered via `$this->app->booted()`:
```php
Gate::policy(ManagedNetwork::class, NetworkPolicy::class);
```

#### 1.9 API Endpoints

```
GET    /api/v1/servers/{uuid}/networks           — List managed networks
POST   /api/v1/servers/{uuid}/networks           — Create shared network
DELETE /api/v1/servers/{uuid}/networks/{uuid}     — Delete network
GET    /api/v1/servers/{uuid}/networks/{uuid}     — Network details + connected resources
POST   /api/v1/servers/{uuid}/networks/sync       — Sync from Docker

GET    /api/v1/resources/{type}/{uuid}/networks   — List resource's networks
POST   /api/v1/resources/{type}/{uuid}/networks   — Attach to network
DELETE /api/v1/resources/{type}/{uuid}/networks/{network_uuid} — Detach from network
```

#### 1.10 Configuration

```php
// config/coolify-enhanced.php
'network_management' => [
    'enabled' => env('COOLIFY_NETWORK_MANAGEMENT', false),

    // Isolation mode: 'none', 'environment', 'strict'
    // - none: no auto-provisioning, manual only
    // - environment: auto-create environment networks, resources auto-join
    // - strict: same as environment + disconnect from default coolify network
    'isolation_mode' => env('COOLIFY_NETWORK_ISOLATION', 'environment'),

    // Whether to use a dedicated proxy network
    'proxy_isolation' => env('COOLIFY_PROXY_ISOLATION', false),

    // Maximum managed networks per server
    'max_networks_per_server' => env('COOLIFY_MAX_NETWORKS', 200),

    // Network name prefix (avoid collisions with Coolify's naming)
    'prefix' => 'ce',

    // Reconciliation interval in seconds
    'reconcile_interval' => 60,

    // Delay before post-deployment network assignment (seconds)
    'post_deploy_delay' => 3,
],
```

#### 1.11 Migration Strategy for Existing Installations

When network management is first enabled:
1. Create `managed_networks` records for all existing `standalone_dockers` entries (scope=`system`)
2. Create environment network records for all environments that have resources on each server
3. Do NOT auto-connect or disconnect anything — just create the tracking records
4. User can then enable isolation mode to start enforcing network boundaries
5. A "Migrate" button on the Network Settings page runs the full reconciliation

---

### Phase 2: Proxy Network Isolation (One Small Overlay)

**Prerequisite**: Phase 1 complete and stable.

**Scope**: Override proxy network behavior so proxy only connects to designated proxy networks.

**Single overlay file**: `proxy.php` (475 lines — small and stable, changes infrequently upstream).

Changes to `proxy.php`:
- `connectProxyToNetworks()` → only connect to networks where `is_proxy_network=true`
- `collectDockerNetworksByServer()` → include managed networks in the collection
- `generateDefaultProxyConfiguration()` → include proxy networks in the compose config
- `ensureProxyNetworksExist()` → include managed proxy networks

**Traefik label injection**: Add `traefik.docker.network=ce-proxy-{server_uuid}` to all FQDN-bearing resources. This is injected via the `NetworkReconcileJob` by setting Docker labels on running containers:
```bash
docker container update --label-add traefik.docker.network=ce-proxy-{server_uuid} {container}
```
Note: `docker container update` does not support label changes. Instead, the label must be injected at deployment time. Since we can't modify the deployment code without overlays, we use an alternative approach:
- Add the label to the resource's `custom_labels` field in the database
- Coolify reads `custom_labels` during deployment and applies them
- This leverages Coolify's existing label injection mechanism without any overlay

**Migration**: Existing installations keep Traefik connected to all networks. After enabling proxy isolation, a migration command:
1. Creates the proxy network
2. Connects Traefik to it
3. Adds `traefik.docker.network` label to all FQDN resources
4. On next redeploy of each resource, it joins the proxy network
5. After all resources redeployed, optionally disconnect Traefik from old networks

---

### Phase 3: Swarm Support (Overlay of proxy.php Only)

**Prerequisite**: Phase 2 complete, user demand confirmed.

**Scope**: Overlay network support for Swarm mode.

**Key differences from standalone**:
- Network creation: `--driver overlay --attachable` (must execute on manager node)
- Container assignment: For Swarm services, cannot use `docker network connect` post-deployment. Must modify service spec or use Coolify's compose generation
- Encrypted overlay: Optional `--opt encrypted` for inter-node encryption

**Approach**: For Swarm, the reconciliation approach won't work (can't `docker network connect` to Swarm tasks). Two options:
1. **Modify Coolify's compose files via the DB** — update `docker_compose` column to include managed networks before deployment
2. **Overlay `ApplicationDeploymentJob.php`** — direct compose generation modification

Option 1 is preferred as it doesn't require overlays, but needs careful testing with Coolify's compose parsers. Deferred until there is concrete Swarm user demand.

---

### Phase 4: Native Compose Integration (Optional, Demand-Driven)

**Prerequisite**: Strong user demand for build-time network isolation.

**Scope**: Inject managed networks directly into compose files during generation (eliminates the post-deployment window).

**Overlays required**:
- `ApplicationDeploymentJob.php` (4130 lines)
- Database Start actions (8 files, ~2500 lines total)
- `parsers.php` (2484 lines)

**This phase should only be pursued if**:
1. The post-deployment hook approach proves insufficient for a significant number of users
2. Build-time network isolation is a concrete requirement (not hypothetical)
3. Coolify's deployment code has stabilized (fewer upstream changes)

---

## File Structure

```
src/
├── Models/
│   ├── ManagedNetwork.php              # Network model
│   └── ResourceNetwork.php             # Pivot model
├── Services/
│   └── NetworkService.php              # Docker network operations
├── Jobs/
│   └── NetworkReconcileJob.php         # Post-deploy + periodic reconciliation
├── Policies/
│   └── NetworkPolicy.php               # Permission policy
├── Livewire/
│   ├── NetworkManager.php              # Server-level network management
│   ├── ResourceNetworks.php            # Per-resource network assignment
│   └── NetworkSettings.php             # Settings page component
├── Http/
│   └── Controllers/Api/
│       └── NetworkController.php       # REST API
├── Overrides/
│   └── Views/
│       ├── components/server/
│       │   └── sidebar.blade.php       # Add Networks sidebar item (already overlaid)
│       ├── components/settings/
│       │   └── navbar.blade.php        # Add Networks tab (already overlaid)
│       └── livewire/project/
│           ├── application/
│           │   └── configuration.blade.php  # Add Networks sidebar (already overlaid)
│           ├── database/
│           │   └── configuration.blade.php  # Add Networks sidebar (already overlaid)
│           └── service/
│               └── configuration.blade.php  # Add Networks sidebar (already overlaid)
├── resources/views/livewire/
│   ├── network-manager.blade.php       # Server network list + management
│   ├── resource-networks.blade.php     # Resource network assignment
│   └── network-settings.blade.php      # Settings page
├── database/migrations/
│   ├── xxxx_create_managed_networks_table.php
│   └── xxxx_create_resource_networks_table.php
└── routes/
    ├── api.php                         # API routes (extended)
    └── web.php                         # Web routes (extended)
```

## Key Technical Details

### Race Condition Handling

**Docker-level**: Use idempotent commands (`docker network create ... 2>/dev/null || true`). Docker's network creation is atomic — no half-created state.

**Database-level**: Unique constraint on `(docker_network_name, server_id)` in `managed_networks`. Use `firstOrCreate()` with exception handling for concurrent inserts.

**Reconciliation**: The periodic reconciler is the safety net. Even if a race condition causes a transient inconsistency, it's fixed within 60 seconds.

### Network Name Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Environment | `ce-env-{env_uuid}` | `ce-env-abc123def` |
| Proxy | `ce-proxy-{server_uuid}` | `ce-proxy-xyz789ghi` |
| Shared | `ce-shared-{network_uuid}` | `ce-shared-mno456pqr` |
| System | (unchanged) | `coolify` (existing) |

### Proxy Label Strategy (Phase 2)

For containers on multiple networks, Traefik needs `traefik.docker.network` to know which network IP to use. Strategy:

1. Add the label to the resource's `custom_labels` via DB (Coolify reads these during deployment)
2. No deployment code overlay needed — Coolify's existing label injection handles it
3. The label persists across redeployments because it's stored in the resource model

### Docker Address Pool

Coolify's default: `10.0.0.0/8` with `/24` subnets = ~65,000 possible networks. At 200 networks per server limit, this is more than sufficient. No daemon.json changes needed.

### Cleanup & Lifecycle

- **Resource deleted**: `autoDetachResource()` disconnects from all managed networks, removes `resource_networks` records
- **Environment deleted**: Disconnect all containers, remove Docker network, delete `managed_networks` record
- **Server removed**: Cascade delete all managed networks for that server
- **Feature disabled**: All network behavior reverts to stock Coolify. Managed networks remain as Docker networks but are no longer actively managed. No data loss.

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Post-deploy window (1-3s without isolation) | High | Low | Acceptable for Phase 1; compose-level integration in Phase 4 |
| Coolify upstream changes to views we overlay | Medium | Medium | Views already overlaid; no new view overlays needed |
| Docker iptables performance with many networks | Low | Medium | 200 network limit; document constraint |
| Race conditions in concurrent deploys | Medium | Low | Idempotent Docker commands + DB constraints + reconciler |
| Swarm incompatibility in Phase 1 | Medium | Medium | Explicitly scoped to standalone; Swarm is Phase 3 |
| `docker network connect` failure on running container | Low | Low | Retry logic + reconciler catches drift |

---

## Success Metrics

1. Resources in different environments on the same server cannot communicate (ping test)
2. Resources in the same environment can communicate by container name (DNS test)
3. Proxy can only reach containers on the proxy network (connection test)
4. Network management UI is responsive and accurate (< 5s sync)
5. Zero overlay files added in Phase 1 (only view overlays)
6. Backward compatible: disabling the feature returns to stock Coolify behavior
