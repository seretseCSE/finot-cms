<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e(config('app.name')); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-gray-50">
    <?php if (isset($component)) { $__componentOriginalf75d29720390c8e1fa3307604849a543 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf75d29720390c8e1fa3307604849a543 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.navigation','data' => ['currentPage' => 'home']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['currentPage' => 'home']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf75d29720390c8e1fa3307604849a543)): ?>
<?php $attributes = $__attributesOriginalf75d29720390c8e1fa3307604849a543; ?>
<?php unset($__attributesOriginalf75d29720390c8e1fa3307604849a543); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf75d29720390c8e1fa3307604849a543)): ?>
<?php $component = $__componentOriginalf75d29720390c8e1fa3307604849a543; ?>
<?php unset($__componentOriginalf75d29720390c8e1fa3307604849a543); ?>
<?php endif; ?>

    <main>
        <section class="bg-gradient-to-r from-blue-600 to-purple-700 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                <div class="max-w-3xl">
                    <h1 class="text-4xl sm:text-5xl font-bold tracking-tight">
                        <?php echo e(__('Welcome to')); ?> <?php echo e(config('app.name')); ?>

                    </h1>
                    <p class="mt-5 text-lg text-white/90">
                        <?php echo e(__('Faith, service, and fellowship — building a stronger community together.')); ?>

                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-3">
                        <a href="<?php echo e(route('about')); ?>" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-blue-700 hover:bg-white/90">
                            <?php echo e(__('About Us')); ?>

                        </a>
                        <a href="<?php echo e(route('tours.index')); ?>" class="inline-flex items-center justify-center rounded-md border border-white/80 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">
                            <?php echo e(__('Tours')); ?>

                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('Announcements')); ?></h2>
                            <a href="<?php echo e(route('blog.index')); ?>" class="text-sm text-blue-600 hover:text-blue-700"><?php echo e(__('View all')); ?></a>
                        </div>
                        <div class="space-y-4 text-sm">
                            <div>
                                <div class="font-medium text-gray-900"><?php echo e(__('Sunday service schedule updated')); ?></div>
                                <div class="text-gray-600"><?php echo e(__('Please arrive 15 minutes early this week.')); ?></div>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900"><?php echo e(__('Youth program registration open')); ?></div>
                                <div class="text-gray-600"><?php echo e(__('Register for the next season of activities.')); ?></div>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900"><?php echo e(__('Volunteer sign-up')); ?></div>
                                <div class="text-gray-600"><?php echo e(__('Join the community service team this month.')); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('Upcoming Events')); ?></h2>
                            <a href="<?php echo e(route('events')); ?>" class="text-sm text-blue-600 hover:text-blue-700"><?php echo e(__('Calendar')); ?></a>
                        </div>
                        <div class="space-y-4 text-sm">
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-blue-50 flex flex-col items-center justify-center">
                                    <div class="text-xs text-blue-700 font-medium"><?php echo e(date('M')); ?></div>
                                    <div class="text-base font-bold text-blue-700">5</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900"><?php echo e(__('Worship Service')); ?></div>
                                    <div class="text-gray-600">10:00 AM</div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-green-50 flex flex-col items-center justify-center">
                                    <div class="text-xs text-green-700 font-medium"><?php echo e(date('M')); ?></div>
                                    <div class="text-base font-bold text-green-700">12</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900"><?php echo e(__('Youth Meeting')); ?></div>
                                    <div class="text-gray-600">6:00 PM</div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-12 h-12 rounded-lg bg-purple-50 flex flex-col items-center justify-center">
                                    <div class="text-xs text-purple-700 font-medium"><?php echo e(date('M')); ?></div>
                                    <div class="text-base font-bold text-purple-700">15</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900"><?php echo e(__('Community Outreach')); ?></div>
                                    <div class="text-gray-600">9:00 AM</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4"><?php echo e(__('Quick Links')); ?></h2>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <a href="<?php echo e(route('programs')); ?>" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"><?php echo e(__('Programs')); ?></a>
                            <a href="<?php echo e(route('songs.index')); ?>" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"><?php echo e(__('Songs')); ?></a>
                            <a href="<?php echo e(route('media')); ?>" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"><?php echo e(__('Media')); ?></a>
                            <a href="<?php echo e(route('library')); ?>" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"><?php echo e(__('Library')); ?></a>
                            <a href="<?php echo e(route('fundraising')); ?>" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"><?php echo e(__('Fundraising')); ?></a>
                            <a href="<?php echo e(route('contact')); ?>" class="rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"><?php echo e(__('Contact')); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-12 bg-white border-y border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-8 items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo e(__('Fundraising Campaigns')); ?></h2>
                        <p class="mt-2 text-gray-600"><?php echo e(__('Support active initiatives and help us expand our impact.')); ?></p>

                        <div class="mt-6 space-y-4">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900"><?php echo e(__('Youth Center Renovation')); ?></div>
                                    <div class="text-sm text-gray-600">60%</div>
                                </div>
                                <div class="mt-2 h-2 bg-gray-100 rounded-full">
                                    <div class="h-2 bg-blue-600 rounded-full" style="width: 60%"></div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900"><?php echo e(__('Community Food Bank')); ?></div>
                                    <div class="text-sm text-gray-600">57%</div>
                                </div>
                                <div class="mt-2 h-2 bg-gray-100 rounded-full">
                                    <div class="h-2 bg-green-600 rounded-full" style="width: 57%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <a href="<?php echo e(route('fundraising')); ?>" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                                <?php echo e(__('See fundraising')); ?>

                            </a>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo e(__('FAQs')); ?></h2>
                        <div class="mt-6 space-y-3">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900"><?php echo e(__('Where are you located?')); ?></div>
                                <div class="mt-1 text-sm text-gray-600"><?php echo e(__('See the address on the Contact page.')); ?></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900"><?php echo e(__('How can I volunteer?')); ?></div>
                                <div class="mt-1 text-sm text-gray-600"><?php echo e(__('Send us a message via Contact and we will respond.')); ?></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="font-medium text-gray-900"><?php echo e(__('How do I switch language?')); ?></div>
                                <div class="mt-1 text-sm text-gray-600"><?php echo e(__('Use the language switcher in the header.')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl bg-gray-900 px-6 py-10 sm:px-10 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <h2 class="text-2xl font-bold"><?php echo e(__('Stay connected')); ?></h2>
                        <p class="mt-1 text-white/80"><?php echo e(__('Get updates about events, programs, and announcements.')); ?></p>
                    </div>
                    <a href="<?php echo e(route('contact')); ?>" class="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-white/90">
                        <?php echo e(__('Contact us')); ?>

                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p>&copy; <?php echo e(date('Y')); ?> <?php echo e(config('app.name')); ?>. <?php echo e(__('All rights reserved.')); ?></p>
        </div>
    </footer>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\Laravel\Github_repo\finot-cms\resources\views/public/home.blade.php ENDPATH**/ ?>