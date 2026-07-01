@extends('layouts.pos')

@section('header-left')
    <a href="{{ route('taller') }}"
       style="background:rgba(255,255,255,.2); border:none; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer; color:white; text-decoration:none; display:flex; align-items:center; gap:5px;">
        ← Órdenes taller
    </a>
@endsection

@section('content')
<div style="width:100%; height:100%;">
    @livewire('taller-orden-pos', ['ordenId' => $ordenId], key('taller-orden-'.$ordenId))
</div>
@endsection
