<div>
    <h2 class="pb-4">Network Management</h2>
    <div class="subtitle pb-4">Configure Docker network isolation and management policies.</div>

    <div class="flex flex-col gap-4">
        {{-- Current status --}}
        <div class="p-4 bg-coolgray-100 rounded">
            <h3 class="pb-2">Current Configuration</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-xs text-neutral-400">Network Management</div>
                    <div class="font-bold {{ $networkManagementEnabled ? 'text-success' : 'text-warning' }}">
                        {{ $networkManagementEnabled ? 'Enabled' : 'Disabled' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-neutral-400">Isolation Mode</div>
                    <div class="font-bold">{{ ucfirst($isolationMode) }}</div>
                </div>
                <div>
                    <div class="text-xs text-neutral-400">Proxy Isolation</div>
                    <div class="font-bold {{ $proxyIsolation ? 'text-success' : 'text-neutral-400' }}">
                        {{ $proxyIsolation ? 'Enabled' : 'Disabled' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-neutral-400">Max Networks per Server</div>
                    <div class="font-bold">{{ $maxNetworksPerServer }}</div>
                </div>
            </div>
        </div>

        {{-- Environment variable configuration guide --}}
        <div class="p-4 bg-coolgray-100 rounded">
            <h3 class="pb-2">Configuration</h3>
            <div class="text-sm text-neutral-300">
                Network management is configured via environment variables in your <code class="text-warning">.env</code> file:
            </div>
            <div class="mt-2 p-3 bg-coolgray-200 rounded font-mono text-xs">
                <div># Enable network management</div>
                <div>COOLIFY_NETWORK_MANAGEMENT=true</div>
                <div class="mt-2"># Isolation mode: none, environment, strict</div>
                <div>COOLIFY_NETWORK_ISOLATION=environment</div>
                <div class="mt-2"># Enable dedicated proxy network (opt-in)</div>
                <div>COOLIFY_PROXY_ISOLATION=false</div>
                <div class="mt-2"># Maximum networks per server</div>
                <div>COOLIFY_MAX_NETWORKS=200</div>
            </div>
        </div>

        {{-- Mode descriptions --}}
        <div class="p-4 bg-coolgray-100 rounded">
            <h3 class="pb-2">Isolation Modes</h3>
            <div class="flex flex-col gap-3 text-sm">
                <div>
                    <span class="font-bold text-warning">none</span> — No auto-provisioning. Networks can only be created and managed manually. Resources stay on Coolify's default network.
                </div>
                <div>
                    <span class="font-bold text-blue-400">environment</span> — Each environment gets its own Docker network. Resources auto-join their environment network after deployment. Cross-environment communication requires shared networks.
                </div>
                <div>
                    <span class="font-bold text-error">strict</span> — Same as <code>environment</code>, but also disconnects resources from the default <code>coolify</code> network. Maximum isolation, but may break services that rely on the default network.
                </div>
            </div>
        </div>
    </div>
</div>
