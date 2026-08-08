@extends('layouts.pos')

@section('content')

@php
    $empresaId = auth()->user()->getEmpresaActualId();
@endphp

<div style="width:100%; height:100%;">
    @livewire('servicio-tecnico-panel', [], key('servicio-tecnico-panel-main'))
</div>

@endsection
