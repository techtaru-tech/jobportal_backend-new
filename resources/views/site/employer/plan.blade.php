@extends('site.employer.layout')

@section('title', 'Plan & billing — Inthes for employers')
@section('employer_key', 'plan')

@section('employer')
@include('site.plans.plans', ['audience' => 'recruiter'])
@endsection
