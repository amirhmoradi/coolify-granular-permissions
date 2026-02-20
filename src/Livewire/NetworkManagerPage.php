<?php

namespace AmirhMoradi\CoolifyEnhanced\Livewire;

use App\Models\Server;
use Livewire\Component;

/**
 * Full-page Livewire component for the Server > Networks page.
 *
 * Renders the server navbar and sidebar alongside the NetworkManager.
 * Used as the target for the server.networks route.
 */
class NetworkManagerPage extends Component
{
    public Server $server;

    public function mount()
    {
        if (! config('coolify-enhanced.enabled', false) || ! config('coolify-enhanced.network_management.enabled', false)) {
            abort(404);
        }

        try {
            $this->server = Server::ownedByCurrentTeam()
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
