<?php

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Exceptions\TourCapacityExceededException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExceptionHandlerService
{
    /**
     * Handle service exceptions with proper logging and user notifications
     */
    public static function handleServiceException(Throwable $e, string $context = ''): void
    {
        $exceptionClass = get_class($e);
        $message = $e->getMessage();

        // Log the exception
        Log::error("Service Exception in {$context}", [
            'exception_type' => $exceptionClass,
            'message' => $message,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Send user notification for critical exceptions
        if ($e instanceof InsufficientFundsException || $e instanceof TourCapacityExceededException) {
            self::sendCriticalUserNotification($message, $context);
        } else {
            self::sendWarningUserNotification($message, $context);
        }
    }

    /**
     * Send critical error notification to user
     */
    protected static function sendCriticalUserNotification(string $message, string $context): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Critical Error')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Send warning notification to user
     */
    protected static function sendWarningUserNotification(string $message, string $context): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Warning')
            ->body($message)
            ->warning()
            ->persistent()
            ->send();
    }
}
