<?php

namespace App\Filament\Widgets;

use App\Models\BlogPost;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentPublicationsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Only show to AV Head, Admin, Superadmin
        if (!Auth::user()?->hasRole(['av_head', 'admin', 'superadmin'])) {
            return $table->query(BlogPost::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                BlogPost::where('status', 'Published')
                    ->orderBy('published_at', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated(false)
            ->heading('Recent Publications')
            ->description('Last 5 published blog posts');
    }
}

