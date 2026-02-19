<?php

namespace AmirhMoradi\CoolifyEnhanced\Livewire;

use Livewire\Component;

/**
 * Settings page for network management configuration.
 *
 * Displays the current network management configuration values
 * loaded from the coolify-enhanced config. These settings are
 * environment-variable based, so the UI serves as a status display
 * and documentation page.
 *
 * In the future, these could be stored in the database
 * (InstanceSettings model) to allow runtime changes.
 */
class NetworkSettings extends Component
{
    public bool $networkManagementEnabled;

    public string $isolationMode;

    public bool $proxyIsolation;

    public int $maxNetworksPerServer;

    public function mount(): void
    {
        $this->networkManagementEnabled = config('coolify-enhanced.network_management.enabled', false);
        $this->isolationMode = config('coolify-enhanced.network_management.isolation_mode', 'environment');
        $this->proxyIsolation = config('coolify-enhanced.network_management.proxy_isolation', false);
        $this->maxNetworksPerServer = config('coolify-enhanced.network_management.max_networks_per_server', 200);
    }

    // Note: These settings are env-based, so we show current values but can't persist changes
    // to .env from the UI. They serve as a status display + documentation page.
    // In the future, these could be stored in the database (InstanceSettings model).

    public function render()
    {
        return view('coolify-enhanced::livewire.network-settings');
    }
}
