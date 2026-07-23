@extends('layouts.app')

@section('title', 'New staff account')

@section('content')
    <x-page-header title="New staff account"
                   subtitle="The new user receives a verification email once the account is created."
                   :back="route('users.index')" />

    <form method="POST" action="{{ route('users.store') }}" novalidate>
        @csrf

        @include('users._form', ['user' => null])
    </form>
@endsection
