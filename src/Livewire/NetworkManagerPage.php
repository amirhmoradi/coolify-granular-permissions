<?php

namespace AmirhMoradi\CoolifyEnhanced\Livewire;

use Livewire\Component;

/**
 * Full-page Livewire component for the Server > Networks page.
 *
 * Renders the server navbar and sidebar alongside the NetworkManager.
 * Used as the target for the server.networks route.
 */
class NetworkManagerPage extends Component
{
    public $server;

    public function mount()
    {
        try {
            $this->server = \App\Models\Server::ownedByCurrentTeam()
                ->where('uuid', request()->route('server_uuid'))
                ->firstOrFail();
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function render()
    {
        return view('coolify-enhanced::livewire.network-manager-page');
    }
}
