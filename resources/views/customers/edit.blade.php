@extends('layouts.app')

@section('title', 'Edit '.$customer->full_name)

@section('content')
    <x-page-header :title="'Edit '.$customer->full_name"
                   :subtitle="$customer->customer_code"
                   :back="route('customers.show', $customer)" />

    <form method="POST" action="{{ route('customers.update', $customer) }}" novalidate>
        @csrf
        @method('PUT')

        @include('customers._form')
    </form>
@endsection
