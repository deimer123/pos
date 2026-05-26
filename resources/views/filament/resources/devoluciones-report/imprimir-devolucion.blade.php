<div style="height:72vh;min-height:520px;overflow:hidden;border:1px solid #dbeafe;border-radius:10px;background:#fff;">
    <iframe
        src="{{ route('devolucion.imprimir', $devolucion->id) }}"
        style="width:100%;height:100%;border:0;background:#fff;"
        title="{{ $devolucion->numero_visual }}">
    </iframe>
</div>