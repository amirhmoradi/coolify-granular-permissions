<?php

namespace AmirhMoradi\CoolifyPermissions\Http\Middleware;

use AmirhMoradi\CoolifyPermissions\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\Response;

class InjectPermissionsUI
{
    /**
     * Inject the granular permissions access matrix into Coolify's team admin page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false) {
            return $response;
        }

        $injection = $this->renderComponent();
        if (empty($injection)) {
            return $response;
        }

        // Wrap in a container with positioning script
        $wrapped = $this->wrapWithInjector($injection);

        // Inject before </body>
        $content = str_replace('</body>', $wrapped.'</body>', $content);
        $response->setContent($content);

        return $response;
    }

    /**
     * Determine if we should inject into this response.
     */
    protected function shouldInject(Request $request, Response $response): bool
    {
        // Only inject on team admin page
        if (! $request->is('team') && ! $request->is('team/*')) {
            return false;
        }

        // Must be an HTML response
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return false;
        }

        // Must be successful
        if (! $response->isSuccessful()) {
            return false;
        }

        // Must be authenticated
        if (! auth()->check()) {
            return false;
        }

        // User must have admin/owner role to see the matrix
        if (! PermissionService::hasRoleBypass(auth()->user())) {
            return false;
        }

        return true;
    }

    /**
     * Render the Livewire access matrix component.
     */
    protected function renderComponent(): string
    {
        try {
            return Blade::render('@livewire(\'permissions::access-matrix\')');
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Wrap the rendered component with a container and positioning script.
     */
    protected function wrapWithInjector(string $componentHtml): string
    {
        return <<<HTML

<!-- Coolify Granular Permissions - Injected Access Matrix -->
<div id="granular-permissions-inject" style="display:none;">
    {$componentHtml}
</div>
<script>
(function() {
    function positionPermissionsUI() {
        var wrapper = document.getElementById('granular-permissions-inject');
        if (!wrapper) return;

        // Try to find the main content area on the team/admin page
        var target = null;
        var selectors = [
            'main [x-data] > div:last-child',
            'main .container > div:last-child',
            'main > div > div > div:last-child',
            'main > div > div:last-child',
            'main > div:last-child',
            'main'
        ];

        for (var i = 0; i < selectors.length; i++) {
            target = document.querySelector(selectors[i]);
            if (target && target !== wrapper && !target.contains(wrapper)) break;
            target = null;
        }

        if (target) {
            target.appendChild(wrapper);
        }

        wrapper.style.display = 'block';
    }

    // Position on initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', positionPermissionsUI);
    } else {
        positionPermissionsUI();
    }

    // Re-position after Livewire navigation (wire:navigate)
    document.addEventListener('livewire:navigated', function() {
        setTimeout(positionPermissionsUI, 100);
    });
})();
</script>
<!-- End Coolify Granular Permissions -->

HTML;
    }
}
