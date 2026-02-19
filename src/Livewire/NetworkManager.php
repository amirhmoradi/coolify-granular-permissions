<?php

namespace AmirhMoradi\CoolifyEnhanced\Livewire;

use AmirhMoradi\CoolifyEnhanced\Models\ManagedNetwork;
use AmirhMoradi\CoolifyEnhanced\Services\NetworkService;
use App\Models\Server;
use Livewire\Component;

/**
 * Server-level network management component.
 *
 * Provides a tabbed interface for managing Docker networks on a server:
 * - "Managed" tab: Shows networks tracked by Coolify Enhanced with create/delete/sync
 * - "Docker" tab: Raw list of Docker networks from the server for reference
 *
 * Rendered on the Server > Networks page via view overlay, similar to
 * ResourceBackupPage for server-level backup management.
 */
class NetworkManager extends Component
{
    public Server $server;

    public string $activeTab = 'managed'; // 'managed' or 'docker'

    // Create shared network form
    public string $newNetworkName = '';

    public bool $newNetworkInternal = false;

    // Docker networks (raw list from Docker)
    public array $dockerNetworks = [];

    public function mount(Server $server): void
    {
        $this->server = $server;
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        if ($tab === 'docker') {
            $this->refreshDockerNetworks();
        }
    }

    public function createSharedNetwork(): void
    {
        $this->validate([
            'newNetworkName' => 'required|string|max:255',
        ]);

        try {
            $team = auth()->user()->currentTeam();
            $network = NetworkService::ensureSharedNetwork($this->newNetworkName, $this->server, $team);

            if ($this->newNetworkInternal) {
                $network->update(['is_internal' => true]);
            }

            NetworkService::createDockerNetwork($this->server, $network->fresh());

            $this->newNetworkName = '';
            $this->newNetworkInternal = false;
            $this->dispatch('success', 'Shared network created.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to create network: '.$e->getMessage());
        }
    }

    public function deleteNetwork(int $networkId): void
    {
        try {
            $network = ManagedNetwork::findOrFail($networkId);

            // Don't allow deleting env/system networks
            if (in_array($network->scope, ['environment', 'system'])) {
                $this->dispatch('error', 'Cannot delete environment or system networks.');

                return;
            }

            NetworkService::deleteDockerNetwork($this->server, $network);
            $network->resourceNetworks()->delete();
            $network->delete();
            $this->dispatch('success', 'Network deleted.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to delete network: '.$e->getMessage());
        }
    }

    public function syncNetworks(): void
    {
        try {
            NetworkService::syncFromDocker($this->server);
            NetworkService::reconcileServer($this->server);
            $this->dispatch('success', 'Network sync complete.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to sync networks: '.$e->getMessage());
        }
    }

    public function refreshDockerNetworks(): void
    {
        try {
            $this->dockerNetworks = NetworkService::listDockerNetworks($this->server)->toArray();
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Failed to list Docker networks: '.$e->getMessage());
            $this->dockerNetworks = [];
        }
    }

    public function render()
    {
        $managedNetworks = ManagedNetwork::forServer($this->server)
            ->with('resourceNetworks')
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        return view('coolify-enhanced::livewire.network-manager', [
            'managedNetworks' => $managedNetworks,
        ]);
    }
}
