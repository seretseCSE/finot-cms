@php
// Recursive comment item partial
// Variables expected: $comment, $depth (0 = top-level)
@endphp

<div style="display:flex;gap:14px;@if($depth > 0) margin-left: {{ min($depth, 2) * 20 }}px; @endif">
    <div style="width:{{ $depth > 0 ? '32px' : '40px' }};height:{{ $depth > 0 ? '32px' : '40px' }};border-radius:50%;background:{{ $depth > 0 ? 'var(--bg-800)' : 'linear-gradient(135deg,var(--blue-primary),var(--gold))' }};border:1px solid var(--border-subtle);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="{{ $depth > 0 ? '14' : '16' }}" height="{{ $depth > 0 ? '14' : '16' }}" fill="none" stroke="{{ $depth > 0 ? 'var(--blue-400)' : '#fff' }}" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
    </div>
    <div style="flex:1;min-width:0;">
        <p style="color:var(--text-60);font-size:{{ $depth > 0 ? '.85rem' : '.9rem' }};line-height:1.7;white-space:pre-wrap;margin-bottom:8px;">{{ $comment->content }}</p>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
            <span style="font-size:.72rem;color:var(--text-40);">{{ $comment->created_at->diffForHumans() }}</span>
            @if($depth < 2)
                <button type="button" onclick="toggleReplyForm({{ $comment->id }})" style="background:none;border:none;color:var(--blue-400);font-size:.78rem;font-weight:600;cursor:pointer;padding:0;transition:color .2s;" onmouseover="this.style.color='var(--blue-500)'" onmouseout="this.style.color='var(--blue-400)'">
                    {{ __('Reply') }}
                </button>
            @endif
        </div>

        {{-- Inline Reply Form --}}
        @if($depth < 2)
            <div id="reply-form-{{ $comment->id }}" style="display:none;margin-bottom:16px;">
                <form method="POST" action="{{ route('blog.comment.store', $post->slug) }}" style="background:var(--glass);border:1px solid var(--border-subtle);border-radius:8px;padding:16px;">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                    <textarea name="content" rows="3" required style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border-subtle);border-radius:8px;padding:10px 14px;color:var(--text-main);outline:none;font-size:.85rem;resize:vertical;margin-bottom:10px;" placeholder="{{ __('Write a reply...') }}"></textarea>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" onclick="toggleReplyForm({{ $comment->id }})" style="background:transparent;border:1px solid var(--border-subtle);color:var(--text-60);padding:6px 14px;border-radius:6px;font-size:.78rem;cursor:pointer;transition:background .2s;" onmouseover="this.style.background='var(--glass-hover)'" onmouseout="this.style.background='transparent'">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" style="padding:6px 16px;font-size:.78rem;">{{ __('Post Reply') }}</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Recursive children --}}
        @if(!empty($comment->children))
            <div style="display:flex;flex-direction:column;gap:16px;margin-top:8px;">
                @foreach($comment->children as $child)
                    @include('public.blog._comment', ['comment' => $child, 'depth' => $depth + 1, 'post' => $post])
                @endforeach
            </div>
        @endif
    </div>
</div>
