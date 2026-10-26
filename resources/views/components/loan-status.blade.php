@props(['loan'])

<div class="flex flex-wrap gap-1">
    <flux:badge size="sm" :color="$loan->status->color()">{{ $loan->status->label() }}</flux:badge>
    @if ($loan->isOverdue())
        <flux:badge size="sm" color="red" icon="exclamation-triangle">überfällig</flux:badge>
    @endif
</div>
