@extends('layouts.!master')

@section('title', 'Home - Ticketio')

@section('content')
  <h1>{{ $title }}</h1>
  <p>{{ $message }}</p>
@endsection

@dummy <script> @enddummy

@section('documentReady')
  console.log('Home page ready');
@endsection

@section('windowLoad')
  console.log('Home page loaded');
@endsection

@section('click')
  console.log('Clicked:', event.target);
@endsection
