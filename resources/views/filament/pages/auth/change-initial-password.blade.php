<x-filament-panels::page>
    <form wire:submit="changePassword" class="space-y-6">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="space-y-6">
                    <div class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                            <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">
                            {{ $this->getHeading() }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $this->getSubheading() }}
                        </p>
                    </div>
                    
                    {{ $this->form }}
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button
                    type="submit"
                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Change Password
                </button>
                
                <button
                    type="button"
                    wire:click="$wire.dispatch('logout')"
                    class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Sign Out
                </button>
            </div>
        </div>
    </form>
    
    <script>
        // Add password strength indicator
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.querySelector('input[name="data[new_password]"]');
            const confirmInput = document.querySelector('input[name="data[new_password_confirmation]"]');
            
            if (newPasswordInput) {
                // Create strength indicator container
                const strengthContainer = document.createElement('div');
                strengthContainer.className = 'mt-2';
                strengthContainer.innerHTML = `
                    <div class="flex items-center space-x-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-2">
                            <div id="strength-bar" class="h-2 rounded-full transition-all duration-300 bg-gray-300" style="width: 0%"></div>
                        </div>
                        <span id="strength-text" class="text-sm font-medium text-gray-600">Weak</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-600">
                        <ul class="space-y-1">
                            <li id="req-length" class="text-gray-400">✓ At least 8 characters</li>
                            <li id="req-uppercase" class="text-gray-400">✓ At least one uppercase letter (A-Z)</li>
                            <li id="req-lowercase" class="text-gray-400">✓ At least one lowercase letter (a-z)</li>
                            <li id="req-number" class="text-gray-400">✓ At least one number (0-9)</li>
                        </ul>
                    </div>
                `;
                
                newPasswordInput.parentNode.insertBefore(strengthContainer, newPasswordInput.nextSibling);
                
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strengthBar = document.getElementById('strength-bar');
                    const strengthText = document.getElementById('strength-text');
                    
                    // Check requirements
                    const hasLength = password.length >= 8;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasLowercase = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    
                    // Update requirement indicators
                    document.getElementById('req-length').className = hasLength ? 'text-green-600' : 'text-gray-400';
                    document.getElementById('req-uppercase').className = hasUppercase ? 'text-green-600' : 'text-gray-400';
                    document.getElementById('req-lowercase').className = hasLowercase ? 'text-green-600' : 'text-gray-400';
                    document.getElementById('req-number').className = hasNumber ? 'text-green-600' : 'text-gray-400';
                    
                    // Calculate strength
                    let strength = 0;
                    if (hasLength) strength++;
                    if (hasUppercase) strength++;
                    if (hasLowercase) strength++;
                    if (hasNumber) strength++;
                    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
                    
                    // Update strength indicator
                    const percentage = (strength / 5) * 100;
                    const colors = ['bg-red-500', 'bg-yellow-500', 'bg-green-500', 'bg-green-600', 'bg-green-700'];
                    const labels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
                    const colorClasses = ['text-red-600', 'text-yellow-600', 'text-green-600', 'text-green-700', 'text-green-800'];
                    
                    strengthBar.style.width = percentage + '%';
                    strengthBar.className = 'h-2 rounded-full transition-all duration-300 ' + colors[Math.min(strength - 1, 4)];
                    strengthText.textContent = labels[Math.min(strength - 1, 4)] || 'Weak';
                    strengthText.className = 'text-sm font-medium ' + colorClasses[Math.min(strength - 1, 4)];
                });
            }
        });
    </script>
</x-filament-panels::page>
