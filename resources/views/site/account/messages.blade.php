@extends('site.account.layout')

@section('title', 'Messages — Inthes')
@section('account_key', 'messages')

@section('account')
@include('site.messages.inbox', ['side' => 'candidate'])
@endsection
