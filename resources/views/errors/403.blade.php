@extends('errors.layout')
@section('title', 'Access denied')
@section('message')
{{ $exception->getMessage() ?: 'You do not have permission to open this page. If you believe this is a mistake, contact the secretariat.' }}
@endsection
