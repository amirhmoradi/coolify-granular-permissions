<div class="pt-4">
    <div class="flex items-center gap-2 pb-2">
        <h3 class="text-lg font-semibold dark:text-white">Backup Encryption</h3>
        @if($encryptionEnabled)
            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                Enabled
            </span>
        @else
            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300">
                Disabled
            </span>
        @endif
    </div>
    <p class="text-sm text-neutral-500 dark:text-neutral-400 pb-4">
        Encrypt backups at rest using rclone's crypt backend (NaCl SecretBox: XSalsa20 + Poly1305).
        When enabled, all database backups uploaded to this S3 destination will be encrypted before upload
        and automatically decrypted on restore.
    </p>

    <div class="flex flex-col gap-3">
        {{-- Enable toggle --}}
        <div class="flex items-center gap-3">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox"
                       wire:model.live="encryptionEnabled"
                       class="sr-only peer">
                <div class="w-11 h-6 bg-neutral-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-neutral-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-neutral-500 peer-checked:bg-blue-600"></div>
            </label>
            <span class="text-sm font-medium dark:text-white">Enable backup encryption</span>
        </div>

        @if($encryptionEnabled)
            <div class="flex flex-col gap-3 p-4 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-base">
                {{-- Encryption Password --}}
                <div>
                    <label for="encryptionPassword" class="block text-sm font-medium dark:text-neutral-200 mb-1">
                        Encryption Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password"
                           id="encryptionPassword"
                           wire:model="encryptionPassword"
                           class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                           placeholder="Main encryption password"
                           required>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Used to encrypt/decrypt backup content. Store this securely - without it, encrypted backups cannot be restored.
                    </p>
                </div>

                {{-- Salt Password --}}
                <div>
                    <label for="encryptionSalt" class="block text-sm font-medium dark:text-neutral-200 mb-1">
                        Salt Password (password2)
                    </label>
                    <input type="password"
                           id="encryptionSalt"
                           wire:model="encryptionSalt"
                           class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                           placeholder="Optional salt for additional security">
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Optional. Adds an extra layer of security to filename encryption. Recommended if using filename encryption.
                    </p>
                </div>

                {{-- Filename Encryption --}}
                <div>
                    <label for="filenameEncryption" class="block text-sm font-medium dark:text-neutral-200 mb-1">
                        Filename Encryption
                    </label>
                    <select id="filenameEncryption"
                            wire:model="filenameEncryption"
                            class="w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="off">Off (recommended) - Filenames remain readable on S3</option>
                        <option value="standard">Standard - Filenames are encrypted on S3</option>
                        <option value="obfuscate">Obfuscate - Filenames are lightly obscured</option>
                    </select>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        "Off" is recommended for best compatibility with backup management. Content is always encrypted regardless of this setting.
                    </p>
                </div>

                {{-- Directory Name Encryption --}}
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox"
                               wire:model="directoryNameEncryption"
                               class="sr-only peer"
                               @if($filenameEncryption === 'off') disabled @endif>
                        <div class="w-11 h-6 bg-neutral-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-neutral-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-neutral-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-neutral-500 peer-checked:bg-blue-600 @if($filenameEncryption === 'off') opacity-50 @endif"></div>
                    </label>
                    <span class="text-sm font-medium dark:text-white @if($filenameEncryption === 'off') opacity-50 @endif">
                        Encrypt directory names
                    </span>
                </div>
                @if($filenameEncryption === 'off')
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 -mt-1">
                        Directory name encryption requires filename encryption to be enabled.
                    </p>
                @endif

                {{-- Warning box --}}
                <div class="p-3 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <strong>Important:</strong> Store your encryption password(s) securely. If you lose the password,
                            encrypted backups <strong>cannot be recovered</strong>. Existing unencrypted backups are not affected
                            and will continue to work normally.
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Save button --}}
        <div class="flex items-center gap-3 pt-2">
            <button wire:click="save"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-900 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <span wire:loading.remove wire:target="save">Save Encryption Settings</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>

            @if($saveMessage)
                <span class="text-sm {{ $saveStatus === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $saveMessage }}
                </span>
            @endif
        </div>
    </div>
</div>
