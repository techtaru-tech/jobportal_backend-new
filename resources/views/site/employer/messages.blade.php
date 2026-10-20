@extends('site.employer.layout')

@section('title', 'Messages — Inthes for employers')
@section('employer_key', 'messages')

@section('employer')
@include('site.messages.inbox', ['side' => 'recruiter'])
@endsection
