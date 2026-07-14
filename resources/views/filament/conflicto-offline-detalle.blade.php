<div class="space-y-3">
    <div>
        <div class="text-xs font-semibold text-gray-500">Error</div>
        <div class="text-sm">{{ $record->error }}</div>
    </div>

    <div>
        <div class="text-xs font-semibold text-gray-500 mb-1">Datos enviados por el dispositivo</div>
        <pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-3 overflow-x-auto">{{ json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</div>
