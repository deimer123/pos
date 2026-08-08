@extends('layouts.pos')

@section('header-left')
    <a href="{{ route('servicio-tecnico') }}"
       style="background:rgba(255,255,255,.2); border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer; color:white; text-decoration:none; display:flex; align-items:center; gap:5px;">
        ← Órdenes servicio técnico
    </a>
@endsection

@section('content')
<div style="width:100%; height:100%;">
    @livewire('servicio-tecnico-orden-pos', ['ordenId' => $ordenId], key('servicio-tecnico-orden-'.$ordenId))
</div>
@endsection
