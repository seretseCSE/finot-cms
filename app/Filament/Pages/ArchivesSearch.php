<?php

namespace App\Filament\Pages;

use App\Models\Announcement;
use Filament\Schemas\Schema;
use App\Models\BlogPost;
use App\Models\Contribution;
use App\Models\MediaItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Auth;

class ArchivesSearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.archives-search';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationLabel(): string
    {
        return 'Archives Search';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin', 'finance_head', 'av_head']);
    }

    public ?array $filters = [];

    public bool $hasSearched = false;

    public array $results = [];

    public function mount(): void
    {
        $this->form->fill([
            'query' => '',
            'archive_type' => 'all',
            'date_from' => null,
            'date_to' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
                TextInput::make('query')
                    ->label('Search')
                    ->placeholder('Search archived records...')
                    ->columnSpan(2),

                Select::make('archive_type')
                    ->label('Archive Type')
                    ->options([
                        'all' => 'All Archives',
                        'contributions' => 'Contributions',
                        'blog_posts' => 'Blog Posts',
                        'announcements' => 'Announcements',
                        'media_items' => 'Media Items',
                    ])
                    ->default('all'),

                DatePicker::make('date_from')
                    ->label('From Date')
                    ->native(false),

                DatePicker::make('date_to')
                    ->label('To Date')
                    ->native(false),
            ])
            ->columns(4);
    }

    public function searchArchives(): void
    {
        $this->filters = $this->form->getState();
        $this->hasSearched = true;
        $this->results = [];

        $query = $this->filters['query'] ?? '';
        $type = $this->filters['archive_type'] ?? 'all';
        $dateFrom = $this->filters['date_from'] ?? null;
        $dateTo = $this->filters['date_to'] ?? null;

        if ($type === 'all' || $type === 'contributions') {
            $this->results['contributions'] = $this->searchContributions($query, $dateFrom, $dateTo);
        }

        if ($type === 'all' || $type === 'blog_posts') {
            $this->results['blog_posts'] = $this->searchBlogPosts($query, $dateFrom, $dateTo);
        }

        if ($type === 'all' || $type === 'announcements') {
            $this->results['announcements'] = $this->searchAnnouncements($query, $dateFrom, $dateTo);
        }

        if ($type === 'all' || $type === 'media_items') {
            $this->results['media_items'] = $this->searchMediaItems($query, $dateFrom, $dateTo);
        }
    }

    private function searchContributions(string $query, ?string $dateFrom, ?string $dateTo): array
    {
        $results = Contribution::query()
            ->where('is_archived', true)
            ->when(filled($query), function ($q) use ($query) {
                $q->whereHas('member', function ($sq) use ($query) {
                    $sq->where('first_name', 'like', "%{$query}%")
                        ->orWhere('father_name', 'like', "%{$query}%")
                        ->orWhere('member_code', 'like', "%{$query}%");
                })->orWhere('month_name', 'like', "%{$query}%");
            })
            ->when(filled($dateFrom), fn ($q) => $q->where('archived_at', '>=', $dateFrom))
            ->when(filled($dateTo), fn ($q) => $q->where('archived_at', '<=', $dateTo))
            ->with('member')
            ->orderByDesc('archived_at')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => "Contribution: {$c->month_name}",
                'subtitle' => $c->member?->full_name ?? 'Unknown Member',
                'amount' => $c->amount,
                'date' => $c->archived_at?->toDateString(),
                'url' => null,
            ])
            ->toArray();

        return $results;
    }

    private function searchBlogPosts(string $query, ?string $dateFrom, ?string $dateTo): array
    {
        $results = BlogPost::query()
            ->where('status', 'Archived')
            ->when(filled($query), function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('title_am', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->when(filled($dateFrom), fn ($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when(filled($dateTo), fn ($q) => $q->where('updated_at', '<=', $dateTo))
            ->with('author')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => 'By '.($p->author?->name ?? 'Unknown'),
                'date' => $p->updated_at?->toDateString(),
                'url' => null,
            ])
            ->toArray();

        return $results;
    }

    private function searchAnnouncements(string $query, ?string $dateFrom, ?string $dateTo): array
    {
        $results = Announcement::query()
            ->where('status', 'Archived')
            ->when(filled($query), function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('title_am', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->when(filled($dateFrom), fn ($q) => $q->where('updated_at', '>=', $dateFrom))
            ->when(filled($dateTo), fn ($q) => $q->where('updated_at', '<=', $dateTo))
            ->with('createdBy')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'subtitle' => 'By '.($a->createdBy?->name ?? 'Unknown'),
                'date' => $a->updated_at?->toDateString(),
                'url' => null,
            ])
            ->toArray();

        return $results;
    }

    private function searchMediaItems(string $query, ?string $dateFrom, ?string $dateTo): array
    {
        $results = MediaItem::query()
            ->onlyTrashed()
            ->when(filled($query), function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('tags', 'like', "%{$query}%");
            })
            ->when(filled($dateFrom), fn ($q) => $q->where('deleted_at', '>=', $dateFrom))
            ->when(filled($dateTo), fn ($q) => $q->where('deleted_at', '<=', $dateTo))
            ->with('uploadedBy')
            ->orderByDesc('deleted_at')
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'subtitle' => $m->type.' — By '.($m->uploadedBy?->name ?? 'Unknown'),
                'date' => $m->deleted_at?->toDateString(),
                'url' => null,
            ])
            ->toArray();

        return $results;
    }

    public function getTotalResultsCount(): int
    {
        return collect($this->results)->sum(fn ($group) => count($group));
    }
}
