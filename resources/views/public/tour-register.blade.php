@extends('layouts.app')

@section('title', 'Register for Tour - ' . $tour->place)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Tour Header -->
        <div class="bg-gradient-to-r from-blue-400 to-purple-500 p-6 text-white">
            <h1 class="text-2xl font-bold mb-2">{{ $tour->place }}</h1>
            <p class="text-blue-100">{{ $tour->ethiopian_date }} at {{ $tour->start_time }}</p>
            <p class="text-blue-100">{{ $tour->formatted_cost }}</p>
        </div>

        <div class="p-6">
            <!-- Tour Description -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Tour Details</h2>
                <p class="text-gray-600">{{ $tour->description }}</p>
            </div>

            <!-- Registration Form -->
            <form action="{{ route('tour.register.submit', $tour->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <!-- Success/Error Messages -->
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                        <div class="flex">
                            <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <p class="text-red-800 font-medium">Please fix the following errors:</p>
                                <ul class="mt-2 text-sm text-red-700">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="space-y-6">
                    <!-- Full Name -->
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                            ሙሉ ስም / Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               value="{{ old('full_name') }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your full name">
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            ስልክ ቁጥር / Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               value="{{ old('phone') }}"
                               required
                               pattern="\+251[0-9]{9}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="+251912345678">
                        <p class="mt-1 text-sm text-gray-500">Format: +251912345678</p>
                    </div>

                    <!-- Number of Passengers -->
                    <div>
                        <label for="passenger_count" class="block text-sm font-medium text-gray-700 mb-2">
                            የተሳፋዮች ብዛት / Number of Passengers <span class="text-red-500">*</span>
                        </label>
                        <input type="number" 
                               id="passenger_count" 
                               name="passenger_count" 
                               value="{{ old('passenger_count', 1) }}"
                               required
                               min="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="1">
                    </div>

                    <!-- Receipt Upload -->
                    <div>
                        <label for="receipt_image" class="block text-sm font-medium text-gray-700 mb-2">
                            ደረቀም / Receipt Upload (Optional)
                        </label>
                        <div class="flex items-center space-x-4">
                            <input type="file" 
                                   id="receipt_image" 
                                   name="receipt_image" 
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <p class="mt-1 text-sm text-gray-500">PDF, JPG, PNG files only (Max 5MB)</p>
                    </div>

                    <!-- Honeypot Field (Bot Prevention) -->
                    <div style="display: none;">
                        <label for="honeypot">Leave this field empty</label>
                        <input type="text" id="honeypot" name="honeypot" value="{{ old('honeypot') }}">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-between">
                        <a href="{{ route('tours.index') }}" 
                           class="text-gray-600 hover:text-gray-900 font-medium">
                            ← Back to Tours
                        </a>
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Submit Registration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Important Information -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-3">Important Information</h3>
        <ul class="space-y-2 text-blue-800">
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>Your registration will be reviewed and confirmed by the tour coordinator.</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>You will receive a confirmation reference number after submission.</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>Phone numbers can only be registered once per tour.</span>
            </li>
            @if($tour->registration_deadline)
                <li class="flex items-start">
                    <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Registration deadline: {{ $tour->registration_deadline->format('M d, Y') }}</span>
                </li>
            @endif
            @if($tour->max_capacity)
                <li class="flex items-start">
                    <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Limited capacity: {{ $tour->confirmedPassengers->sum('passenger_count') }}/{{ $tour->max_capacity }} spots filled</span>
                </li>
            @endif
        </ul>
    </div>
</div>
@endsection
