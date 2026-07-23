@extends('layouts.app')

@section('title', 'New customer')

@section('content')
    <x-page-header title="New customer"
                   subtitle="The customer ID is generated automatically once you save."
                   :back="route('customers.index')" />

    <form method="POST" action="{{ route('customers.store') }}" novalidate>
        @csrf

        @include('customers._form', ['customer' => null])
    </form>
@endsection
