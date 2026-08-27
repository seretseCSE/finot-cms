@include('errors.500', [
    'title' => 'Too many requests',
    'heading' => 'Please slow down',
    'message' => 'Too many requests were sent. Wait a moment and try again.',
])
