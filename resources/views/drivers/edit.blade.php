@extends('layouts.app')

@section('title', 'Edit '.$driver->full_name)

@section('content')
    <x-page-header :title="'Edit '.$driver->full_name"
                   :subtitle="$driver->driver_code"
                   :back="route('drivers.show', $driver)" />

    <form method="POST" action="{{ route('drivers.update', $driver) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        @include('drivers._form')
    </form>
@endsection
