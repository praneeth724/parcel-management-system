@extends('layouts.app')

@section('title', 'New branch')

@section('content')
    <x-page-header title="New branch"
                   subtitle="Branches own the staff, drivers and parcels that belong to a location."
                   :back="route('branches.index')" />

    <form method="POST" action="{{ route('branches.store') }}" novalidate>
        @csrf

        @include('branches._form', ['branch' => null])
    </form>
@endsection
