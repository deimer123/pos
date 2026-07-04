@extends('layouts.pos')

@section('content')

<div style="width:100%; height:100%;">
    @livewire('hotel-panel', [], key('hotel-panel-main'))
</div>

@endsection
