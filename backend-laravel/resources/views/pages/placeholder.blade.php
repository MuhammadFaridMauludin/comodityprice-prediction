@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" />
    <section class="card">
        <p>Halaman {{ $title }} belum dibuat.</p>
    </section>
@endsection
