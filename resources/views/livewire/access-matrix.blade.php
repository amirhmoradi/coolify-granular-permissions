<div>
    {{-- Granular Permissions - Access Matrix --}}
    <div class="mt-8 border-t border-neutral-700 pt-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-white">Granular Access Management</h3>
                <p class="text-sm text-neutral-400 mt-1">
                    Manage per-user access to projects and environments. Owners and Admins bypass all checks.
                </p>
            </div>
            @if(! config('coolify-permissions.enabled', false))
                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                    Feature Disabled
                </span>
            @else
                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-500/20 text-green-400 border border-green-500/30">
                    Active
                </span>
            @endif
        </div>

        @if(! config('coolify-permissions.enabled', false))
            <div class="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4 mb-4">
                <p class="text-sm text-yellow-300">
                    Granular permissions are currently disabled. Set <code class="bg-neutral-800 px-1 rounded text-xs">COOLIFY_GRANULAR_PERMISSIONS=true</code> in your environment to enable.
                    The matrix below is read-only while disabled.
                </p>
            </div>
        @endif

        {{-- Search and Bulk Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="flex-1">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search users by name, email, or role..."
                    class="w-full rounded-lg border border-neutral-600 bg-neutral-800 px-3 py-2 text-sm text-white placeholder-neutral-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                />
            </div>
            <div class="flex items-center gap-2">
                <select wire:model="bulkLevel" class="rounded-lg border border-neutral-600 bg-neutral-800 px-3 py-2 text-sm text-white focus:border-blue-500">
                    <option value="full_access">Full Access</option>
                    <option value="deploy">Deploy</option>
                    <option value="view_only">View Only</option>
                    <option value="none">None</option>
                </select>
            </div>
        </div>

        @if(count($projects) === 0)
            <div class="rounded-lg border border-neutral-700 bg-neutral-800/50 p-8 text-center">
                <p class="text-neutral-400">No projects found in this team.</p>
            </div>
        @elseif(count($filteredUsers) === 0)
            <div class="rounded-lg border border-neutral-700 bg-neutral-800/50 p-8 text-center">
                <p class="text-neutral-400">No users match your search.</p>
            </div>
        @else
            {{-- Matrix Table --}}
            <div class="rounded-lg border border-neutral-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        {{-- Header Row 1: Project groups --}}
                        <thead>
                            <tr class="bg-neutral-800 border-b border-neutral-700">
                                <th class="sticky left-0 z-20 bg-neutral-800 px-3 py-2 text-left text-xs font-medium text-neutral-400 uppercase tracking-wider min-w-[200px] border-r border-neutral-700" rowspan="2">
                                    User
                                </th>
                                <th class="sticky left-[200px] z-20 bg-neutral-800 px-3 py-2 text-center text-xs font-medium text-neutral-400 uppercase tracking-wider min-w-[100px] border-r border-neutral-700" rowspan="2">
                                    Role
                                </th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-neutral-400 uppercase tracking-wider min-w-[80px] border-r border-neutral-700" rowspan="2">
                                    Actions
                                </th>
                                @foreach($projects as $project)
                                    <th
                                        class="px-2 py-2 text-center text-xs font-medium text-neutral-300 border-r border-neutral-600 bg-neutral-750"
                                        colspan="{{ 1 + count($project['environments']) }}"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="truncate max-w-[120px]" title="{{ $project['name'] }}">{{ $project['name'] }}</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                            {{-- Header Row 2: Project + Environment sub-columns --}}
                            <tr class="bg-neutral-800/80 border-b border-neutral-700">
                                @foreach($projects as $project)
                                    {{-- Project column --}}
                                    <th class="px-2 py-1.5 text-center text-xs font-medium text-blue-400 border-r border-neutral-700 min-w-[110px]">
                                        <div class="flex flex-col items-center gap-0.5">
                                            <span>Project</span>
                                            <div class="flex gap-0.5 mt-0.5">
                                                <button
                                                    wire:click="setAllForProject({{ $project['id'] }}, bulkLevel)"
                                                    class="px-1 py-0.5 text-[10px] rounded bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 transition"
                                                    title="Set all users to selected level"
                                                >All</button>
                                                <button
                                                    wire:click="setAllForProject({{ $project['id'] }}, 'none')"
                                                    class="px-1 py-0.5 text-[10px] rounded bg-neutral-600/50 text-neutral-400 hover:bg-neutral-600 transition"
                                                    title="Revoke all users"
                                                >None</button>
                                            </div>
                                        </div>
                                    </th>
                                    {{-- Environment columns --}}
                                    @foreach($project['environments'] as $env)
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-purple-400 border-r border-neutral-700 min-w-[110px]">
                                            <div class="flex flex-col items-center gap-0.5">
                                                <span class="truncate max-w-[90px]" title="{{ $env['name'] }}">{{ $env['name'] }}</span>
                                                <div class="flex gap-0.5 mt-0.5">
                                                    <button
                                                        wire:click="setAllForEnvironment({{ $env['id'] }}, bulkLevel)"
                                                        class="px-1 py-0.5 text-[10px] rounded bg-purple-500/20 text-purple-400 hover:bg-purple-500/30 transition"
                                                        title="Set all users to selected level"
                                                    >All</button>
                                                    <button
                                                        wire:click="setAllForEnvironment({{ $env['id'] }}, 'inherited')"
                                                        class="px-1 py-0.5 text-[10px] rounded bg-neutral-600/50 text-neutral-400 hover:bg-neutral-600 transition"
                                                        title="Reset all to inherited"
                                                    >None</button>
                                                </div>
                                            </div>
                                        </th>
                                    @endforeach
                                @endforeach
                            </tr>
                        </thead>

                        {{-- Data Rows --}}
                        <tbody class="divide-y divide-neutral-700/50">
                            @foreach($filteredUsers as $user)
                                <tr class="hover:bg-neutral-800/50 transition {{ $user['bypass'] ? 'opacity-60' : '' }}">
                                    {{-- User cell (sticky) --}}
                                    <td class="sticky left-0 z-10 bg-neutral-900 px-3 py-2 border-r border-neutral-700">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-white text-sm truncate max-w-[180px]" title="{{ $user['name'] }}">
                                                {{ $user['name'] }}
                                            </span>
                                            <span class="text-xs text-neutral-500 truncate max-w-[180px]" title="{{ $user['email'] }}">
                                                {{ $user['email'] }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Role cell (sticky) --}}
                                    <td class="sticky left-[200px] z-10 bg-neutral-900 px-3 py-2 text-center border-r border-neutral-700">
                                        @php
                                            $roleColors = [
                                                'owner' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
                                                'admin' => 'bg-red-500/20 text-red-400 border-red-500/30',
                                                'member' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                                'viewer' => 'bg-neutral-500/20 text-neutral-400 border-neutral-500/30',
                                            ];
                                            $roleColor = $roleColors[$user['role']] ?? $roleColors['member'];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full border {{ $roleColor }}">
                                            {{ ucfirst($user['role']) }}
                                        </span>
                                        @if($user['bypass'])
                                            <span class="block text-[10px] text-neutral-500 mt-0.5">bypass</span>
                                        @endif
                                    </td>

                                    {{-- Row-level actions --}}
                                    <td class="px-2 py-2 text-center border-r border-neutral-700">
                                        @if(! $user['bypass'])
                                            <div class="flex flex-col gap-0.5">
                                                <button
                                                    wire:click="setAllForUser({{ $user['id'] }}, bulkLevel)"
                                                    class="px-1.5 py-0.5 text-[10px] rounded bg-green-500/20 text-green-400 hover:bg-green-500/30 transition"
                                                    title="Grant selected level to all projects"
                                                >All</button>
                                                <button
                                                    wire:click="setAllForUser({{ $user['id'] }}, 'none')"
                                                    class="px-1.5 py-0.5 text-[10px] rounded bg-neutral-600/50 text-neutral-400 hover:bg-neutral-600 transition"
                                                    title="Revoke all project access"
                                                >None</button>
                                            </div>
                                        @else
                                            <span class="text-[10px] text-neutral-600">-</span>
                                        @endif
                                    </td>

                                    {{-- Permission cells --}}
                                    @foreach($projects as $project)
                                        {{-- Project cell --}}
                                        <td class="px-1 py-1 text-center border-r border-neutral-700">
                                            @if($user['bypass'])
                                                <span class="inline-flex items-center px-2 py-1 text-[10px] rounded bg-neutral-700/50 text-neutral-500">
                                                    bypass
                                                </span>
                                            @else
                                                <select
                                                    wire:change="updateProjectPermission({{ $user['id'] }}, {{ $project['id'] }}, $event.target.value)"
                                                    class="w-full rounded border text-xs py-1 px-1.5 focus:ring-1 focus:ring-blue-500
                                                        @php
                                                            $level = $permissions[$user['id']]['p_' . $project['id']] ?? 'none';
                                                        @endphp
                                                        {{ match($level) {
                                                            'full_access' => 'border-green-600/50 bg-green-500/10 text-green-400',
                                                            'deploy' => 'border-amber-600/50 bg-amber-500/10 text-amber-400',
                                                            'view_only' => 'border-blue-600/50 bg-blue-500/10 text-blue-400',
                                                            default => 'border-neutral-600 bg-neutral-800 text-neutral-400',
                                                        } }}"
                                                >
                                                    <option value="none" {{ $level === 'none' ? 'selected' : '' }}>None</option>
                                                    <option value="view_only" {{ $level === 'view_only' ? 'selected' : '' }}>View Only</option>
                                                    <option value="deploy" {{ $level === 'deploy' ? 'selected' : '' }}>Deploy</option>
                                                    <option value="full_access" {{ $level === 'full_access' ? 'selected' : '' }}>Full Access</option>
                                                </select>
                                            @endif
                                        </td>

                                        {{-- Environment cells --}}
                                        @foreach($project['environments'] as $env)
                                            <td class="px-1 py-1 text-center border-r border-neutral-700">
                                                @if($user['bypass'])
                                                    <span class="inline-flex items-center px-2 py-1 text-[10px] rounded bg-neutral-700/50 text-neutral-500">
                                                        bypass
                                                    </span>
                                                @else
                                                    @php
                                                        $envLevel = $permissions[$user['id']]['e_' . $env['id']] ?? 'inherited';
                                                        $effectiveLevel = $envLevel !== 'inherited'
                                                            ? $envLevel
                                                            : ($permissions[$user['id']]['p_' . $project['id']] ?? 'none');
                                                    @endphp
                                                    <select
                                                        wire:change="updateEnvironmentPermission({{ $user['id'] }}, {{ $env['id'] }}, $event.target.value)"
                                                        class="w-full rounded border text-xs py-1 px-1.5 focus:ring-1 focus:ring-purple-500
                                                            {{ match($envLevel) {
                                                                'full_access' => 'border-green-600/50 bg-green-500/10 text-green-400',
                                                                'deploy' => 'border-amber-600/50 bg-amber-500/10 text-amber-400',
                                                                'view_only' => 'border-blue-600/50 bg-blue-500/10 text-blue-400',
                                                                'inherited' => 'border-purple-600/30 bg-purple-500/5 text-purple-400',
                                                                default => 'border-neutral-600 bg-neutral-800 text-neutral-400',
                                                            } }}"
                                                        title="{{ $envLevel === 'inherited' ? 'Inherited from project: ' . $effectiveLevel : '' }}"
                                                    >
                                                        <option value="inherited" {{ $envLevel === 'inherited' ? 'selected' : '' }}>
                                                            Inherited ({{ ucwords(str_replace('_', ' ', $effectiveLevel)) }})
                                                        </option>
                                                        <option value="view_only" {{ $envLevel === 'view_only' ? 'selected' : '' }}>View Only</option>
                                                        <option value="deploy" {{ $envLevel === 'deploy' ? 'selected' : '' }}>Deploy</option>
                                                        <option value="full_access" {{ $envLevel === 'full_access' ? 'selected' : '' }}>Full Access</option>
                                                    </select>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Legend --}}
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-neutral-400">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-green-500/20 border border-green-600/50"></span>
                    Full Access
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-amber-500/20 border border-amber-600/50"></span>
                    Deploy
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-blue-500/20 border border-blue-600/50"></span>
                    View Only
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-purple-500/10 border border-purple-600/30"></span>
                    Inherited
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-neutral-700/50 border border-neutral-600"></span>
                    None / Bypass
                </div>
            </div>
        @endif
    </div>
</div>
