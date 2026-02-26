<?php

namespace App\Filament\Widgets;

use App\Models\BlogPost;
use App\Models\Announcement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class ScheduledContentWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Only show to AV Head, Admin, Superadmin
        if (!Auth::user()?->hasRole(['av_head', 'admin', 'superadmin'])) {
            return $table->query(BlogPost::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                BlogPost::where('status', 'Scheduled')
                    ->orderBy('publish_date', 'asc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('publish_date')
                    ->label('Publish Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),
            ])
            ->paginated(false)
            ->heading('Scheduled Content')
            ->description('Upcoming scheduled blog posts');
    }
}

