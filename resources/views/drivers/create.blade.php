@extends('layouts.app')

@section('title', 'New driver')

@section('content')
    <x-page-header title="New driver"
                   subtitle="The driver code is generated automatically once you save."
                   :back="route('drivers.index')" />

    <form method="POST" action="{{ route('drivers.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        @include('drivers._form', ['driver' => null])
    </form>
@endsection
