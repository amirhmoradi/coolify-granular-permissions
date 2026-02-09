<?php

namespace AmirhMoradi\CoolifyEnhanced\Http\Middleware;

use AmirhMoradi\CoolifyEnhanced\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InjectPermissionsUI
{
    /**
     * Inject UI components into Coolify pages:
     * - Access matrix on team admin page
     * - Encryption settings on storage detail pages
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Must be HTML, successful, and authenticated
        if (! $this->isInjectableResponse($response) || ! auth()->check()) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || empty($content)) {
            return $response;
        }

        $injections = '';

        // Inject access matrix on team admin page
        if ($this->isTeamAdminPage($request) && PermissionService::hasRoleBypass(auth()->user())) {
            $component = $this->renderAccessMatrix();
            if (! empty($component)) {
                $injections .= $this->wrapWithInjector($component);
            }
        }

        // Inject encryption form on storage detail page
        if ($this->isStorageDetailPage($request)) {
            $storageUuid = $this->extractStorageUuid($request);
            if ($storageUuid) {
                $encryptionForm = $this->renderEncryptionForm($storageUuid);
                if (! empty($encryptionForm)) {
                    $injections .= $this->wrapWithStorageInjector($encryptionForm);
                }
            }
        }

        if (! empty($injections)) {
            $content = str_replace('</body>', $injections.'</body>', $content);
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Check if the response is injectable (HTML, successful).
     */
    protected function isInjectableResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html') && $response->isSuccessful();
    }

    /**
     * Check if this is the team admin page.
     */
    protected function isTeamAdminPage(Request $request): bool
    {
        // Try named route first, fall back to URL pattern
        if ($request->routeIs('team.admin-view')) {
            return true;
        }

        return $request->is('team/admin') || $request->is('team');
    }

    /**
     * Check if this is a storage detail page.
     * Uses both named route and URL pattern matching for reliability
     * with Livewire SPA navigation.
     */
    protected function isStorageDetailPage(Request $request): bool
    {
        // Try named route first
        if ($request->routeIs('storage.show')) {
            return true;
        }

        // Fallback: match URL pattern /storages/{uuid}
        $path = trim($request->path(), '/');

        return (bool) preg_match('#^storages/[a-zA-Z0-9-]+$#', $path);
    }

    /**
     * Extract the storage UUID from the request.
     */
    protected function extractStorageUuid(Request $request): ?string
    {
        // Try route parameter first
        $uuid = $request->route('storage_uuid');
        if ($uuid) {
            return $uuid;
        }

        // Fallback: extract from URL path
        $path = trim($request->path(), '/');
        if (preg_match('#^storages/([a-zA-Z0-9-]+)$#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Render the Livewire access matrix component.
     */
    protected function renderAccessMatrix(): string
    {
        try {
            return Blade::render('@livewire(\'enhanced::access-matrix\')');
        } catch (\Throwable $e) {
            Log::error('Coolify Enhanced: Failed to render access matrix', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Render the encryption form for a specific S3 storage.
     */
    protected function renderEncryptionForm(?string $storageUuid): string
    {
        if (empty($storageUuid)) {
            return '';
        }

        try {
            $storage = \App\Models\S3Storage::where('uuid', $storageUuid)->first();
            if (! $storage) {
                Log::debug('Coolify Enhanced: S3Storage not found for UUID: '.$storageUuid);

                return '';
            }

            return Blade::render(
                '@livewire(\'enhanced::storage-encryption-form\', [\'storageId\' => '.$storage->id.'])'
            );
        } catch (\Throwable $e) {
            Log::error('Coolify Enhanced: Failed to render encryption form', [
                'uuid' => $storageUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return '';
        }
    }

    /**
     * Wrap the rendered component with a container and positioning script.
     */
    protected function wrapWithInjector(string $componentHtml): string
    {
        return <<<HTML

<!-- Coolify Enhanced - Injected Access Matrix -->
<div id="coolify-enhanced-inject" style="display:none;">
    {$componentHtml}
</div>
<script data-navigate-once>
(function() {
    function isAdminPage() {
        return window.location.pathname === '/team/admin';
    }

    function positionPermissionsUI() {
        var wrapper = document.getElementById('coolify-enhanced-inject');
        if (!wrapper) return;

        // Only show on team admin page — hide on all other pages
        if (!isAdminPage()) {
            wrapper.style.display = 'none';
            wrapper.dataset.positioned = '';
            return;
        }

        if (wrapper.dataset.positioned === 'true') return;

        // Target: the Livewire admin-view component root div inside main content
        // Coolify structure: main.lg\\:pl-56 > div.p-4 > div (livewire root)
        var target = document.querySelector('main > div > div > div:first-child');

        // Fallback: find the div containing "Admin View" heading
        if (!target) {
            var headings = document.querySelectorAll('h2');
            for (var i = 0; i < headings.length; i++) {
                if (headings[i].textContent.trim() === 'Admin View') {
                    target = headings[i].closest('div');
                    break;
                }
            }
        }

        // Final fallback: main content padding div
        if (!target) {
            target = document.querySelector('main > div');
        }

        if (target && target !== wrapper) {
            target.appendChild(wrapper);
            wrapper.dataset.positioned = 'true';
        }

        wrapper.style.display = 'block';
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', positionPermissionsUI);
    } else {
        positionPermissionsUI();
    }

    // Re-run after Livewire SPA navigation (wire:navigate)
    document.addEventListener('livewire:navigated', function() {
        var wrapper = document.getElementById('coolify-enhanced-inject');
        if (wrapper) wrapper.dataset.positioned = '';
        setTimeout(positionPermissionsUI, 50);
    });
})();
</script>
<!-- End Coolify Enhanced - Access Matrix -->

HTML;
    }

    /**
     * Wrap the encryption form with a container and positioning script.
     *
     * Coolify's storage form page renders <livewire:storage.form> which produces
     * a form with wire:submit. We inject our encryption section after it.
     */
    protected function wrapWithStorageInjector(string $componentHtml): string
    {
        return <<<'SCRIPT_START'

<!-- Coolify Enhanced - Encryption Settings -->
<div id="enhanced-encryption-inject" style="display:none;">
SCRIPT_START
            .$componentHtml.<<<'HTML'
</div>
<script data-navigate-once>
(function() {
    function isStoragePage() {
        return /^\/storages\/[a-zA-Z0-9-]+\/?$/.test(window.location.pathname);
    }

    function positionEncryptionUI() {
        var wrapper = document.getElementById('enhanced-encryption-inject');
        if (!wrapper) return;

        if (!isStoragePage()) {
            wrapper.style.display = 'none';
            wrapper.dataset.positioned = '';
            return;
        }

        if (wrapper.dataset.positioned === 'true') return;

        // Strategy 1: Find the storage form by wire:submit attribute
        var storageForm = document.querySelector('form[wire\\:submit="submit"]')
            || document.querySelector('form[wire\\:submit\\.prevent="submit"]');
        var target = storageForm ? storageForm.parentElement : null;

        // Strategy 2: Find "Storage Details" heading
        if (!target) {
            var allHeadings = document.querySelectorAll('h1, h2, h3');
            for (var i = 0; i < allHeadings.length; i++) {
                var text = allHeadings[i].textContent.trim();
                if (text === 'Storage Details' || text.indexOf('Storage') !== -1) {
                    target = allHeadings[i].closest('div');
                    break;
                }
            }
        }

        // Strategy 3: Find the main Livewire component wrapper
        if (!target) {
            var mainContent = document.querySelector('main');
            if (mainContent) {
                // Find the deepest content div
                target = mainContent.querySelector('div > div') || mainContent;
            }
        }

        if (target && target !== wrapper && !target.contains(wrapper)) {
            target.appendChild(wrapper);
            wrapper.dataset.positioned = 'true';
        }

        wrapper.style.display = 'block';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', positionEncryptionUI);
    } else {
        positionEncryptionUI();
    }

    document.addEventListener('livewire:navigated', function() {
        var wrapper = document.getElementById('enhanced-encryption-inject');
        if (wrapper) wrapper.dataset.positioned = '';
        setTimeout(positionEncryptionUI, 100);
    });
})();
</script>
<!-- End Coolify Enhanced - Encryption Settings -->

HTML;
    }
}
