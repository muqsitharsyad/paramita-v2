@if($items->hasPages() || $items->total() > $items->perPage())
<footer class="admin-pager">
    <span>Menampilkan {{ $items->firstItem() ?? 0 }}&ndash;{{ $items->lastItem() ?? 0 }} dari {{ $items->total() }} data</span>
    {{ $items->links('pagination::bootstrap-5') }}
</footer>
@endif
