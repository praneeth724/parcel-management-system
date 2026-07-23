@extends('layouts.app')

@section('title', 'Edit '.$branch->name)

@section('content')
    <x-page-header :title="'Edit '.$branch->name"
                   :subtitle="$branch->code"
                   :back="route('branches.show', $branch)" />

    <form method="POST" action="{{ route('branches.update', $branch) }}" novalidate>
        @csrf
        @method('PUT')

        @include('branches._form')
    </form>
@endsection
