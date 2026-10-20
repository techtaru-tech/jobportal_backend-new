@extends('site.account.layout')

@section('title', 'My plan — Inthes')
@section('account_key', 'plan')

@section('account')
@include('site.plans.plans', ['audience' => 'jobSeeker'])
@endsection
