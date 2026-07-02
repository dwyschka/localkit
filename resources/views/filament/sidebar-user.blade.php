@php $user = filament()->auth()->user(); @endphp
<div style="display:flex;align-items:center;gap:11px;padding:14px 16px;border-top:1px solid rgba(255,255,255,.08)">
    <span style="width:34px;height:34px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;
                 background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);font-weight:700;font-size:13px;color:#fafafa">
        {{ strtoupper(mb_substr($user?->name ?? 'U', 0, 2)) }}
    </span>
    <div style="min-width:0">
        <div style="font-size:13px;font-weight:600;color:#fafafa;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $user?->name ?? 'User' }}</div>
        <div style="font-size:12px;color:#71717a">Administrator</div>
    </div>
</div>
