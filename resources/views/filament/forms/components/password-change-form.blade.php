@php
    use App\Rules\PasswordHistoryRule;
    $user = auth()->user();
@endphp

<div x-data="passwordChangeForm({
        currentPassword: '',
        newPassword: '',
        newPasswordConfirmation: '',
        errors: {},
        strength: '',
        isSubmitting: false
    })" x-init="init()" class="space-y-4">
    
    <!-- Current Password -->
    <div>
        <x-filament::forms::field
            label="Current Password"
            :required="true"
        >
            <x-filament::forms::input
                type="password"
                name="current_password"
                x-model="currentPassword"
                required
                autocomplete="current-password"
                class="w-full"
            />
        </x-filament::forms::field>
    </div>

    <!-- New Password -->
    <div>
        <x-filament::forms::field
            label="New Password"
            :required="true"
        >
            <x-filament::forms::input
                type="password"
                name="new_password"
                x-model="newPassword"
                @input="validatePasswordStrength()"
                @blur="validatePasswordHistory()"
                required
                autocomplete="new-password"
                class="w-full"
            />
            <div class="mt-2">
                <div class="flex items-center space-x-2">
                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                        <div 
                            class="h-2 rounded-full transition-all duration-300"
                            :class="getStrengthClass()"
                            :style="`width: ${getStrengthPercentage()}%`"
                        ></div>
                    </div>
                    <span class="text-sm font-medium" :class="getStrengthTextColor()">
                        {{ strength }}
                    </span>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-600">
                <ul class="space-y-1">
                    <li :class="hasMinLength ? 'text-green-600' : 'text-gray-400'">
                        ✓ At least 8 characters
                    </li>
                    <li :class="hasUppercase ? 'text-green-600' : 'text-gray-400'">
                        ✓ At least one uppercase letter (A-Z)
                    </li>
                    <li :class="hasLowercase ? 'text-green-600' : 'text-gray-400'">
                        ✓ At least one lowercase letter (a-z)
                    </li>
                    <li :class="hasNumber ? 'text-green-600' : 'text-gray-400'">
                        ✓ At least one number (0-9)
                    </li>
                </ul>
            </div>
        </x-filament::forms::field>
    </div>

    <!-- Confirm Password -->
    <div>
        <x-filament::forms::field
            label="Confirm New Password"
            :required="true"
        >
            <x-filament::forms::input
                type="password"
                name="new_password_confirmation"
                x-model="newPasswordConfirmation"
                @blur="validatePasswordMatch()"
                required
                autocomplete="new-password"
                class="w-full"
            />
        </x-filament::forms::field>
    </div>

    <!-- Error Messages -->
    <div x-show="Object.keys(errors).length > 0" class="rounded-md bg-red-50 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000-16zM8.707 7.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4a1 1 0 001.414-1.414l-1.293 1.293a1 1 0 00-1.414 0z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Password Requirements</h3>
                <div class="mt-2 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li x-show="errors.minLength">Password must be at least 8 characters long.</li>
                        <li x-show="errors.uppercase">Password must contain at least one uppercase letter (A-Z).</li>
                        <li x-show="errors.lowercase">Password must contain at least one lowercase letter (a-z).</li>
                        <li x-show="errors.number">Password must contain at least one number (0-9).</li>
                        <li x-show="errors.history">You cannot reuse your last 3 passwords.</li>
                        <li x-show="errors.match">Passwords do not match.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end">
        <button
            type="button"
            @click="submitPasswordChange()"
            :disabled="isSubmitting || !isFormValid"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span x-show="!isSubmitting">Change Password</span>
            <span x-show="isSubmitting">Changing...</span>
        </button>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('passwordChangeForm', (initialData) => ({
        ...initialData,
        
        // Computed properties
        hasMinLength: false,
        hasUppercase: false,
        hasLowercase: false,
        hasNumber: false,
        passwordsMatch: false,
        
        get isFormValid() {
            return this.currentPassword && 
                   this.newPassword && 
                   this.newPasswordConfirmation &&
                   this.hasMinLength &&
                   this.hasUppercase &&
                   this.hasLowercase &&
                   this.hasNumber &&
                   this.passwordsMatch &&
                   Object.keys(this.errors).length === 0;
        },
        
        getStrengthClass() {
            const classes = {
                'weak': 'bg-red-500',
                'medium': 'bg-yellow-500',
                'strong': 'bg-green-500',
                'very-strong': 'bg-green-600'
            };
            return classes[this.strength] || 'bg-gray-300';
        },
        
        getStrengthPercentage() {
            const percentages = {
                'weak': '25',
                'medium': '50',
                'strong': '75',
                'very-strong': '100'
            };
            return percentages[this.strength] || '0';
        },
        
        getStrengthTextColor() {
            const colors = {
                'weak': 'text-red-600',
                'medium': 'text-yellow-600',
                'strong': 'text-green-600',
                'very-strong': 'text-green-700'
            };
            return colors[this.strength] || 'text-gray-600';
        },
        
        // Methods
        init() {
            this.$watch('newPassword', () => {
                this.validatePasswordStrength();
                this.validatePasswordMatch();
            });
            
            this.$watch('newPasswordConfirmation', () => {
                this.validatePasswordMatch();
            });
        },
        
        validatePasswordStrength() {
            const password = this.newPassword;
            this.errors = {};
            
            // Reset validation flags
            this.hasMinLength = false;
            this.hasUppercase = false;
            this.hasLowercase = false;
            this.hasNumber = false;
            
            // Minimum 8 characters
            if (password.length < 8) {
                this.errors.minLength = true;
            } else {
                this.hasMinLength = true;
            }
            
            // At least one uppercase letter
            if (!/[A-Z]/.test(password)) {
                this.errors.uppercase = true;
            } else {
                this.hasUppercase = true;
            }
            
            // At least one lowercase letter
            if (!/[a-z]/.test(password)) {
                this.errors.lowercase = true;
            } else {
                this.hasLowercase = true;
            }
            
            // At least one number
            if (!/[0-9]/.test(password)) {
                this.errors.number = true;
            } else {
                this.hasNumber = true;
            }
            
            // Calculate strength
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            
            const strengthLevels = ['weak', 'medium', 'strong', 'very-strong'];
            this.strength = strengthLevels[Math.min(strength - 1, 3)] || 'weak';
        },
        
        validatePasswordHistory() {
            // This would be checked on the server side
            // For now, just clear any history errors
            delete this.errors.history;
        },
        
        validatePasswordMatch() {
            if (this.newPassword && this.newPasswordConfirmation) {
                if (this.newPassword !== this.newPasswordConfirmation) {
                    this.errors.match = true;
                    this.passwordsMatch = false;
                } else {
                    delete this.errors.match;
                    this.passwordsMatch = true;
                }
            } else {
                this.passwordsMatch = false;
            }
        },
        
        async submitPasswordChange() {
            if (!this.isFormValid) return;
            
            this.isSubmitting = true;
            
            try {
                const response = await fetch('/user/change-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        current_password: this.currentPassword,
                        new_password: this.newPassword,
                        new_password_confirmation: this.newPasswordConfirmation
                    })
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    // Success
                    this.currentPassword = '';
                    this.newPassword = '';
                    this.newPasswordConfirmation = '';
                    this.errors = {};
                    this.strength = '';
                    
                    // Show success message
                    alert('Password changed successfully!');
                } else {
                    // Server validation errors
                    this.errors = result.errors || {};
                }
            } catch (error) {
                console.error('Error changing password:', error);
                alert('An error occurred while changing your password.');
            } finally {
                this.isSubmitting = false;
            }
        }
    }));
});
</script>
