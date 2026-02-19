<?php

namespace AmirhMoradi\CoolifyEnhanced\Livewire;

use AmirhMoradi\CoolifyEnhanced\Models\ManagedNetwork;
use AmirhMoradi\CoolifyEnhanced\Models\ResourceNetwork;
use AmirhMoradi\CoolifyEnhanced\Services\NetworkService;
use Livewire\Component;

/**
 * Per-resource network assignment component.
 *
 * Rendered on resource configuration pages (Application, Service, Database)
 * to manage which Docker networks a resource is connected to. Allows adding
 * shared networks, removing non-auto-attached networks, and reconnecting
 * disconnected containers.
 *
 * Similar to ResourceBackupManager but for network management instead of backups.
 * Uses the same resource identification pattern (resourceId + resourceType).
 */
class ResourceNetworks extends Component
{
    public $resourceId;

    public $resourceType;

    public string $resourceName = '';

    // Selected network to add
    public string $selectedNetworkId = '';

    public function mount($resourceId, $resourceType, $resourceName = ''): void
    {
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;
        $this->resourceName = $resourceName;
    }

    public function addToNetwork(): void
    {
        if (empty($this->selectedNetworkId)) {
            return;
        }

        try {
            $resource = $this->resourceType::findOrFail($this->resourceId);
            $network = ManagedNetwork::findOrFail($this->selectedNetworkId);
            $server = NetworkService::getServerForResource($resource);

            if (! $server || $network->server_id !== $server->id) {
                $this->dispatch('error', 'Network is not on the same server.');

                return;
            }

            // Connect containers
            $containerNames = NetworkService::getContainerNames($resource);
            foreach ($containerNames as $containerName) {
                NetworkService::connectContainer($server, $network->docker_network_name, $containerName, [$containerName]);
            }

            ResourceNetwork::updateOrCreate(
                [
                    'resource_type' => $this->resourceType,
                    'resource_id' => $this->resourceId,
                    'managed_network_id' => $network->id,
                ],
                [
                    'is_auto_attached' => false,
                    'is_connected' => true,
                    'connected_at' => now(),
                    'aliases' => $containerNames,
                ]
            );

            $this->selectedNetworkId = '';
            $this->dispatch('success', 'Connected to network.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to connect to network: '.$e->getMessage());
        }
    }

    public function removeFromNetwork(int $pivotId): void
    {
        try {
            $pivot = ResourceNetwork::findOrFail($pivotId);

            // Don't allow removing auto-attached environment networks
            if ($pivot->is_auto_attached && $pivot->managedNetwork->scope === 'environment') {
                $this->dispatch('error', 'Cannot remove auto-attached environment network.');

                return;
            }

            $resource = $this->resourceType::findOrFail($this->resourceId);
            $server = NetworkService::getServerForResource($resource);
            $network = $pivot->managedNetwork;

            // Disconnect containers
            $containerNames = NetworkService::getContainerNames($resource);
            foreach ($containerNames as $containerName) {
                NetworkService::disconnectContainer($server, $network->docker_network_name, $containerName);
            }

            $pivot->delete();
            $this->dispatch('success', 'Disconnected from network.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to disconnect from network: '.$e->getMessage());
        }
    }

    public function reconnect(int $pivotId): void
    {
        try {
            $pivot = ResourceNetwork::findOrFail($pivotId);
            $resource = $this->resourceType::findOrFail($this->resourceId);
            $server = NetworkService::getServerForResource($resource);
            $network = $pivot->managedNetwork;

            $containerNames = NetworkService::getContainerNames($resource);
            foreach ($containerNames as $containerName) {
                NetworkService::connectContainer($server, $network->docker_network_name, $containerName, [$containerName]);
            }

            $pivot->update(['is_connected' => true, 'connected_at' => now()]);
            $this->dispatch('success', 'Reconnected to network.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to reconnect: '.$e->getMessage());
        }
    }

    public function render()
    {
        $currentNetworks = ResourceNetwork::where('resource_type', $this->resourceType)
            ->where('resource_id', $this->resourceId)
            ->with('managedNetwork')
            ->get();

        $resource = $this->resourceType::find($this->resourceId);
        $server = $resource ? NetworkService::getServerForResource($resource) : null;

        $availableNetworks = $server
            ? NetworkService::getAvailableNetworks($resource, $server)
            : collect();

        return view('coolify-enhanced::livewire.resource-networks', [
            'currentNetworks' => $currentNetworks,
            'availableNetworks' => $availableNetworks,
        ]);
    }
}
