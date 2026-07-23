@extends('layouts.app')

@section('title', 'Edit '.$user->name)

@section('content')
    <x-page-header :title="'Edit '.$user->name"
                   :subtitle="$user->email"
                   :back="route('users.show', $user)" />

    <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
        @csrf
        @method('PUT')

        @include('users._form')
    </form>
@endsection
